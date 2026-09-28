<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommerceNotification extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        private bool $mailOnly = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->mailOnly ? ['mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' · Mobile Arena')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->body)
            ->action('View update', $this->url);
    }
}
