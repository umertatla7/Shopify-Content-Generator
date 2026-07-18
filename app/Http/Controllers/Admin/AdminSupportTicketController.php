<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\ShopifyStore;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Notifications\SupportTicketReplyNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $filters = $request->only(['search', 'status', 'module', 'account_id', 'shopify_store_id', 'ticket']);

        $ticketsQuery = SupportTicket::query()
            ->with(['account:id,name,plan_key,billing_email', 'store:id,name,shop_domain', 'openedBy:id,name,email'])
            ->withCount('messages')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('account', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('store', fn ($query) => $query->where('shop_domain', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
            ->when($filters['shopify_store_id'] ?? null, fn ($query, $storeId) => $query->where('shopify_store_id', $storeId));

        $tickets = (clone $ticketsQuery)
            ->latest('last_message_at')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $selectedTicket = SupportTicket::query()
            ->with(['account:id,name,plan_key,billing_email,credit_balance,monthly_credit_allowance', 'store:id,name,shop_domain,status,last_synced_at', 'openedBy:id,name,email', 'assignedTo:id,name,email', 'messages.user:id,name,email'])
            ->when($request->integer('ticket'), fn ($query, int $ticketId) => $query->whereKey($ticketId))
            ->latest('last_message_at')
            ->latest()
            ->first();

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'selectedTicket' => $selectedTicket,
            'filters' => $filters,
            'summary' => [
                'waiting_admin' => SupportTicket::query()->where('status', SupportTicket::STATUS_WAITING_ADMIN)->count(),
                'open' => SupportTicket::query()->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_WAITING_CUSTOMER, SupportTicket::STATUS_WAITING_ADMIN])->count(),
                'closed' => SupportTicket::query()->where('status', SupportTicket::STATUS_CLOSED)->count(),
            ],
            'accounts' => Account::query()->orderBy('name')->get(['id', 'name']),
            'stores' => ShopifyStore::query()->orderBy('name')->get(['id', 'name', 'shop_domain']),
            'modules' => ['general', 'installation', 'store_sync', 'products', 'collections', 'topics', 'blogs', 'store_audit', 'ai_visibility', 'keyword_tracking', 'billing'],
            'statuses' => [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_WAITING_ADMIN, SupportTicket::STATUS_WAITING_CUSTOMER, SupportTicket::STATUS_CLOSED],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'in:open,waiting_customer,closed'],
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'sender_type' => 'admin',
            'body' => $validated['body'],
        ]);

        $status = $validated['status'] ?? SupportTicket::STATUS_WAITING_CUSTOMER;
        $ticket->update([
            'status' => $status,
            'assigned_to' => $request->user()->id,
            'last_message_at' => $message->created_at,
            'last_admin_message_at' => $message->created_at,
            'closed_at' => $status === SupportTicket::STATUS_CLOSED ? now() : null,
        ]);

        ActivityLog::query()->create([
            'account_id' => $ticket->account_id,
            'shopify_store_id' => $ticket->shopify_store_id,
            'user_id' => $request->user()->id,
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'action' => 'support.message.admin',
            'status' => 'success',
            'entity_type' => 'support_ticket',
            'description' => "Admin replied to support ticket: {$ticket->subject}",
            'metadata' => ['module' => $ticket->module],
            'ip_address' => $request->ip(),
        ]);

        $this->notifyCustomer($ticket, $message);

        return redirect()
            ->route('admin.support.index', ['ticket' => $ticket->id])
            ->with('status', 'Support reply sent.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:open,waiting_admin,waiting_customer,closed'],
            'priority' => ['required', 'in:normal,high'],
            'module' => ['nullable', 'string', 'max:80'],
        ]);

        $ticket->update([
            ...$validated,
            'closed_at' => $validated['status'] === SupportTicket::STATUS_CLOSED ? now() : null,
            'assigned_to' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.support.index', ['ticket' => $ticket->id])
            ->with('status', 'Support ticket updated.');
    }

    private function notifyCustomer(SupportTicket $ticket, SupportMessage $message): void
    {
        $ticket->loadMissing(['openedBy:id,name,email', 'account:id,billing_email']);
        $recipient = $ticket->openedBy?->email ?: $ticket->account?->billing_email;

        if (blank($recipient)) {
            return;
        }

        Notification::route('mail', $recipient)->notify(new SupportTicketReplyNotification($ticket, $message));
    }
}
