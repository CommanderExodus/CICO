<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Erkennt, welcher benannte Datenbank-Constraint eine Abfrage abgelehnt hat.
 *
 * PostgreSQL meldet Fehlermeldungen je nach Server-Locale übersetzt, der
 * Constraint-Name steht aber immer unverändert darin.
 */
final class ConstraintViolation
{
    public static function is(Throwable $e, string ...$constraints): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $message = $e->getMessage();

        foreach ($constraints as $constraint) {
            if (preg_match('/\b'.preg_quote($constraint, '/').'\b/', $message) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fremdschlüssel verletzt: 23503 beim Einfügen/Ändern, 23001 (restrict_violation)
     * beim Löschen eines noch referenzierten Datensatzes mit ON DELETE RESTRICT.
     */
    public static function isForeignKey(Throwable $e): bool
    {
        return $e instanceof QueryException && in_array($e->getCode(), ['23503', '23001'], true);
    }
}
