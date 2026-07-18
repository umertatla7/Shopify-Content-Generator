<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket->loadMissing(['account:id,name,plan_key', 'store:id,name,shop_domain', 'openedBy:id,name,email']);

        return (new MailMessage)
            ->subject("New GrowShopHigh support request: {$ticket->subject}")
            ->greeting('New merchant support request')
            ->line("Subject: {$ticket->subject}")
            ->line("Customer: {$ticket->account?->name} ({$ticket->account?->plan_key})")
            ->line("Store: {$ticket->store?->name} / {$ticket->store?->shop_domain}")
            ->line("Opened by: {$ticket->openedBy?->name} ({$ticket->openedBy?->email})")
            ->line("Module: {$ticket->module}")
            ->action('Open support inbox', url("/admin/support?ticket={$ticket->id}"));
    }
}
