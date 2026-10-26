<?php

namespace App\Notifications;

use App\Models\FoundReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Benachrichtigt Verwalter über eine neue Fundmeldung (R14). Die Kontaktdaten des
 * Finders stehen bewusst nicht in der Mail, damit sie nach Ablauf der Löschfrist (R23)
 * nicht in Postfächern weiterleben.
 */
class FoundReportSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FoundReport $report)
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
        $item = $this->report->item;

        return (new MailMessage)
            ->subject("Fundmeldung: {$item->name}")
            ->greeting('Neue Fundmeldung')
            ->line("Für „{$item->name}“ ist eine Fundmeldung eingegangen.")
            ->line("Fundort: {$this->report->found_location}")
            ->line("Nachricht: {$this->report->message}")
            ->line($this->report->contact !== null
                ? 'Der Finder hat Kontaktdaten hinterlassen; sie sind im System einsehbar.'
                : 'Der Finder hat keine Kontaktdaten hinterlassen.')
            ->action('Fundmeldung ansehen', route('found-reports.index'));
    }
}
