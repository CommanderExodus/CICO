<?php

namespace App\Actions\FoundReports;

use App\Enums\FoundReportStatus;
use App\Models\FoundReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResolveFoundReport
{
    public function handle(User $actor, FoundReport $report): FoundReport
    {
        Gate::forUser($actor)->authorize('resolve', $report);

        if ($report->status !== FoundReportStatus::Open) {
            throw ValidationException::withMessages(['report' => 'Die Fundmeldung ist bereits erledigt.']);
        }

        $report->status = FoundReportStatus::Resolved;
        $report->resolved_at = now();
        $report->resolved_by = $actor->id;

        DB::transaction(fn () => $report->save());

        return $report;
    }
}
