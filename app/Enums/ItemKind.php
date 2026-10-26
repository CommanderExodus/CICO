<?php

namespace App\Enums;

/**
 * Einzelgegenstände mit Seriennummer und Mengenware werden getrennt modelliert (R1).
 */
enum ItemKind: string
{
    case Unit = 'unit';
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Einzelgegenstand',
            self::Bulk => 'Mengenware',
        };
    }
}
