<?php

namespace App\Enums;

/**
 * Aktueller Zustand eines Gegenstands, wie ihn auch die öffentliche Statusseite zeigt (R12).
 */
enum ItemAvailability: string
{
    case Available = 'available';
    case OnLoan = 'on_loan';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'verfügbar',
            self::OnLoan => 'ausgeliehen',
            self::OutOfStock => 'nicht vorrätig',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::OnLoan => 'amber',
            self::OutOfStock => 'zinc',
        };
    }
}
