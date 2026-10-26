<?php

namespace App\Actions\FoundReports;

use App\Models\FoundReport;
use Carbon\CarbonInterface;

/**
 * Löscht Kontaktdaten aus Fundmeldungen nach Ablauf der festen Frist (R23).
 * Der Audit-Trail schwärzt die gelöschten Werte, sodass sie auch dort nicht überleben.
 */
class PurgeExpiredFoundReportContacts
{
    /**
     * @return int Anzahl der bereinigten Fundmeldungen
     */
    public function handle(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $cutoff = $now->subDays(self::retentionDays());

        return FoundReport::query()
            ->whereNotNull('contact')
            ->where('created_at', '<', $cutoff)
            ->update([
                'contact' => null,
                'contact_purged_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public static function retentionDays(): int
    {
        return (int) config('cico.found_reports.contact_retention_days');
    }
}
