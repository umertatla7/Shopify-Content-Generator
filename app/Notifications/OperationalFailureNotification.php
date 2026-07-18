<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationalFailureNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $job,
        private readonly string $queue,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('GrowShopHigh queue job failed')
            ->line('A production queue job exhausted its retries.')
            ->line('Job: '.$this->job)
            ->line('Queue: '.$this->queue)
            ->line('Error: '.$this->message)
            ->line('Review the failed job and related store activity before retrying it.');
    }
}
