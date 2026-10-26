<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hinweis an eingetragene Admins, dass ein neues Konto auf Freischaltung wartet.
 */
class UserRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Neue Registrierung: {$this->user->name}")
            ->greeting('Neues Konto wartet auf Freischaltung')
            ->line("{$this->user->name} ({$this->user->email}) hat sich registriert.")
            ->line('Bis zur Freischaltung sieht das Konto keine Inhalte.')
            ->action('Benutzer verwalten', route('users.index', ['status' => 'pending']));
    }
}
