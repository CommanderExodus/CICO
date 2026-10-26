<?php

namespace App\Enums;

enum FoundReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'offen',
            self::Resolved => 'erledigt',
        };
    }
}
