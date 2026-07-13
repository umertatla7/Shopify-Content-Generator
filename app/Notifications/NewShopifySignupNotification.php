<?php

namespace App\Notifications;

use App\Models\Account;
use App\Models\ShopifyStore;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewShopifySignupNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly Account $account,
        private readonly ShopifyStore $store,
        private readonly string $planName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New GrowShopHigh Shopify signup')
            ->greeting('New Shopify signup completed')
            ->line('A new merchant finished installing GrowShopHigh.')
            ->line("Store: {$this->store->name}")
            ->line("Shop domain: {$this->store->shop_domain}")
            ->line("Workspace: {$this->account->name}")
            ->line("Merchant: {$this->user->name} ({$this->user->email})")
            ->line("Current package: {$this->planName}")
            ->line('You can review the account inside the admin dashboard.');
    }
}
