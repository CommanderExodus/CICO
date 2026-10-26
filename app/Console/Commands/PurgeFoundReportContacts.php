<?php

namespace App\Console\Commands;

use App\Actions\FoundReports\PurgeExpiredFoundReportContacts;
use Illuminate\Console\Command;

class PurgeFoundReportContacts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'found-reports:purge-contacts';

    /**
     * @var string
     */
    protected $description = 'Löscht Kontaktdaten aus Fundmeldungen nach Ablauf der Löschfrist (R23)';

    public function handle(PurgeExpiredFoundReportContacts $purge): int
    {
        $count = $purge->handle();

        $this->info("Kontaktdaten aus {$count} Fundmeldung(en) gelöscht (Frist: ".PurgeExpiredFoundReportContacts::retentionDays().' Tage).');

        return self::SUCCESS;
    }
}
