<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketReplyNotification;
use App\Notifications\SupportTicketSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_support_ticket_before_catalog_sync(): void
    {
        Notification::fake();
        config(['services.app_review.support_email' => 'support@growshophigh.com']);

        [$user, $account, $store] = $this->makeCustomerAccount();

        $response = $this->actingAs($user)->post('/help/tickets', [
            'subject' => 'Products are not syncing',
            'body' => 'I clicked sync but I still see zero products.',
            'module' => 'store_sync',
            'priority' => 'high',
            'shopify_store_id' => $store->id,
        ]);

        $ticket = SupportTicket::query()->first();

        $response->assertRedirect("/help?ticket={$ticket->id}");
        $this->assertSame($account->id, $ticket->account_id);
        $this->assertSame($store->id, $ticket->shopify_store_id);
        $this->assertSame(SupportTicket::STATUS_WAITING_ADMIN, $ticket->status);
        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'body' => 'I clicked sync but I still see zero products.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'account_id' => $account->id,
            'action' => 'support.ticket.created',
            'entity_type' => 'support_ticket',
        ]);
        Notification::assertSentOnDemand(SupportTicketSubmittedNotification::class);
    }

    public function test_customer_cannot_view_another_accounts_ticket(): void
    {
        [$user] = $this->makeCustomerAccount('first');
        [$otherUser, $otherAccount, $otherStore] = $this->makeCustomerAccount('second');

        $ticket = SupportTicket::query()->create([
            'account_id' => $otherAccount->id,
            'shopify_store_id' => $otherStore->id,
            'opened_by' => $otherUser->id,
            'subject' => 'Private issue',
            'status' => SupportTicket::STATUS_WAITING_ADMIN,
            'priority' => 'normal',
            'module' => 'billing',
            'last_message_at' => now(),
        ]);

        $ticket->messages()->create([
            'user_id' => $otherUser->id,
            'sender_type' => 'customer',
            'body' => 'Private message',
        ]);

        $this->actingAs($user)
            ->get("/help?ticket={$ticket->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/Index')
                ->where('selectedTicket', null)
                ->has('tickets.data', 0)
            );
    }

    public function test_admin_can_reply_to_support_ticket_and_notify_customer(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['global_role' => 'super_admin']);
        [$customer, $account, $store] = $this->makeCustomerAccount();

        $ticket = SupportTicket::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'opened_by' => $customer->id,
            'subject' => 'Blog publish failed',
            'status' => SupportTicket::STATUS_WAITING_ADMIN,
            'priority' => 'normal',
            'module' => 'blogs',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post("/admin/support/{$ticket->id}/messages", [
            'body' => 'Thanks, we checked the publish log and updated the connection.',
            'status' => 'waiting_customer',
        ]);

        $response->assertRedirect("/admin/support?ticket={$ticket->id}");
        $ticket->refresh();

        $this->assertSame(SupportTicket::STATUS_WAITING_CUSTOMER, $ticket->status);
        $this->assertSame($admin->id, $ticket->assigned_to);
        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'sender_type' => 'admin',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'account_id' => $account->id,
            'action' => 'support.message.admin',
            'entity_type' => 'support_ticket',
        ]);
        Notification::assertSentOnDemand(SupportTicketReplyNotification::class);
    }

    private function makeCustomerAccount(string $slug = 'acme'): array
    {
        $user = User::factory()->create([
            'email' => "{$slug}@example.com",
        ]);

        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'plan_key' => 'starter',
            'credit_balance' => 250,
            'monthly_credit_allowance' => 250,
        ]);

        $role = Role::query()->create([
            'name' => "customer_admin_{$slug}",
            'label' => 'Customer Admin',
        ]);

        AccountUser::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
            'permissions' => ['stores.view', 'stores.sync', 'billing.manage'],
        ]);

        $user->forceFill(['current_account_id' => $account->id])->save();

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => ucfirst($slug).' Store',
            'shop_domain' => "{$slug}.myshopify.com",
            'shop_url' => "https://{$slug}.myshopify.com",
            'status' => 'connected',
        ]);

        return [$user, $account, $store];
    }
}
