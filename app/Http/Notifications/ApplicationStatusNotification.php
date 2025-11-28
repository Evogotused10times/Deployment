<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicationStatusNotification extends Notification {
    use Queueable;

    public function __construct(public string $status) {}

    public function via($notifiable) {
        return ['mail'];
    }

    public function toMail($notifiable) {
        return (new MailMessage)
            ->subject('Memorabeth Application Status')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your burial application has been {$this->status}.")
            ->line('Thank you for choosing Memorabeth.');
    }
}
