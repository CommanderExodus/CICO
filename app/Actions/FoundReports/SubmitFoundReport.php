<?php

namespace App\Actions\FoundReports;

use App\Models\FoundReport;
use App\Models\Item;
use App\Notifications\FoundReportSubmitted;
use App\Support\NotificationRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * Nimmt eine Fundmeldung von der öffentlichen Statusseite entgegen und
 * benachrichtigt die Verwalter (R14). Läuft ohne angemeldeten Nutzer.
 */
class SubmitFoundReport
{
    public function __construct(private NotificationRecipients $recipients)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Item $item, array $input): FoundReport
    {
        $validated = Validator::make($input, [
            'found_location' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'contact' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $report = DB::transaction(fn () => $item->foundReports()->create([
            'found_location' => trim($validated['found_location']),
            'message' => trim($validated['message']),
            'contact' => filled($validated['contact'] ?? null) ? trim($validated['contact']) : null,
        ]));

        Notification::send($this->recipients->foundReports(), new FoundReportSubmitted($report));

        return $report;
    }
}
