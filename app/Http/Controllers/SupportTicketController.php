<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ShopifyStore;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Notifications\SupportTicketSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.support.index');
        }

        $account = $request->user()->currentAccount;
        abort_unless($account, 403);

        $tickets = SupportTicket::query()
            ->with(['store:id,name,shop_domain', 'openedBy:id,name,email'])
            ->withCount('messages')
            ->where('account_id', $account->id)
            ->latest('last_message_at')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $selectedTicket = SupportTicket::query()
            ->with(['store:id,name,shop_domain', 'openedBy:id,name,email', 'messages.user:id,name,email'])
            ->where('account_id', $account->id)
            ->when($request->integer('ticket'), fn ($query, int $ticketId) => $query->whereKey($ticketId))
            ->latest('last_message_at')
            ->latest()
            ->first();

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
            'selectedTicket' => $selectedTicket,
            'stores' => ShopifyStore::query()
                ->where('account_id', $account->id)
                ->latest()
                ->get(['id', 'name', 'shop_domain']),
            'filters' => [
                'ticket' => $selectedTicket?->id,
            ],
            'modules' => $this->modules(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $request->user()->currentAccount;
        abort_unless($account, 403);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'module' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'in:normal,high'],
            'shopify_store_id' => ['nullable', 'integer'],
        ]);

        $store = $this->storeForAccount($account->id, $validated['shopify_store_id'] ?? null);
        $now = now();

        $ticket = SupportTicket::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store?->id,
            'opened_by' => $request->user()->id,
            'subject' => $validated['subject'],
            'status' => SupportTicket::STATUS_WAITING_ADMIN,
            'priority' => $validated['priority'] ?? 'normal',
            'module' => $validated['module'] ?: 'general',
            'last_message_at' => $now,
            'last_customer_message_at' => $now,
            'metadata' => [
                'shopify_context' => $request->only(['shop', 'host', 'embedded']),
            ],
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'sender_type' => 'customer',
            'body' => $validated['body'],
        ]);

        ActivityLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store?->id,
            'user_id' => $request->user()->id,
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'action' => 'support.ticket.created',
            'status' => 'success',
            'entity_type' => 'support_ticket',
            'description' => "Customer opened support ticket: {$ticket->subject}",
            'metadata' => ['module' => $ticket->module],
            'new_values' => $ticket->only(['subject', 'status', 'priority', 'module']),
            'ip_address' => $request->ip(),
        ]);

        $this->notifyAdmin($ticket);

        return redirect()
            ->route('support.index', ['ticket' => $ticket->id])
            ->with('status', 'Support request sent. We will reply here and by email.');
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $account = $request->user()->currentAccount;
        abort_unless($account && $ticket->account_id === $account->id, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'sender_type' => 'customer',
            'body' => $validated['body'],
        ]);

        $ticket->update([
            'status' => SupportTicket::STATUS_WAITING_ADMIN,
            'last_message_at' => $message->created_at,
            'last_customer_message_at' => $message->created_at,
            'closed_at' => null,
        ]);

        ActivityLog::query()->create([
            'account_id' => $ticket->account_id,
            'shopify_store_id' => $ticket->shopify_store_id,
            'user_id' => $request->user()->id,
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'action' => 'support.message.customer',
            'status' => 'success',
            'entity_type' => 'support_ticket',
            'description' => "Customer replied to support ticket: {$ticket->subject}",
            'metadata' => ['module' => $ticket->module],
            'ip_address' => $request->ip(),
        ]);

        $this->notifyAdmin($ticket);

        return redirect()
            ->route('support.index', ['ticket' => $ticket->id])
            ->with('status', 'Reply sent to support.');
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $account = $request->user()->currentAccount;
        abort_unless($account && $ticket->account_id === $account->id, 403);

        $ticket->update([
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return redirect()
            ->route('support.index', ['ticket' => $ticket->id])
            ->with('status', 'Support request closed.');
    }

    private function notifyAdmin(SupportTicket $ticket): void
    {
        $recipient = config('services.app_review.support_email');

        if (blank($recipient)) {
            return;
        }

        Notification::route('mail', $recipient)->notify(new SupportTicketSubmittedNotification($ticket));
    }

    private function storeForAccount(int $accountId, ?int $storeId): ?ShopifyStore
    {
        $query = ShopifyStore::query()->where('account_id', $accountId);

        if ($storeId) {
            return (clone $query)->whereKey($storeId)->firstOrFail();
        }

        return $query->latest()->first();
    }

    private function modules(): array
    {
        return ['general', 'installation', 'store_sync', 'products', 'collections', 'topics', 'blogs', 'store_audit', 'ai_visibility', 'keyword_tracking', 'billing'];
    }
}
