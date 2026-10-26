<?php

namespace App\Enums;

/**
 * Gespeicherter Status einer Ausleihe (R5). „Überfällig“ ist bewusst kein Status,
 * sondern wird aus ausgegeben + überschrittenem Rückgabedatum abgeleitet.
 */
enum LoanStatus: string
{
    case Reserved = 'reserved';
    case Issued = 'issued';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'reserviert',
            self::Issued => 'ausgegeben',
            self::Returned => 'zurück',
            self::Cancelled => 'storniert',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Reserved => 'sky',
            self::Issued => 'amber',
            self::Returned => 'zinc',
            self::Cancelled => 'zinc',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Reserved || $this === self::Issued;
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Reserved->value, self::Issued->value];
    }
}
