<?php

namespace App\Notifications;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
        private readonly SupportMessage $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("GrowShopHigh support replied: {$this->ticket->subject}")
            ->greeting('Your GrowShopHigh support request has a reply')
            ->line($this->message->body)
            ->action('Open support request', url("/help?ticket={$this->ticket->id}"))
            ->line('You can reply from inside the GrowShopHigh app.');
    }
}
