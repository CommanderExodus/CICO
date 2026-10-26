<?php

namespace App\Support;

/**
 * Nicht erratbare öffentliche Kennung für QR-Codes (R11): 16 Zeichen
 * Crockford-Base32 (ohne i, l, o, u) aus einem kryptografischen Zufallsgenerator,
 * also 80 Bit Entropie. Kurz genug, um sie auch vom Etikett abzutippen.
 */
final class PublicId
{
    public const ALPHABET = '0123456789abcdefghjkmnpqrstvwxyz';

    public const LENGTH = 16;

    public const PATTERN = '/^[0-9a-hjkmnp-tv-z]{16}$/';

    public static function generate(): string
    {
        $id = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $id .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $id;
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Zur besseren Lesbarkeit auf Etiketten in Vierergruppen: abcd-efgh-jkmn-pqrs.
     */
    public static function format(string $value): string
    {
        return implode('-', str_split($value, 4));
    }

    /**
     * Normalisiert eine abgetippte Kennung (Groß-/Kleinschreibung, Bindestriche, Verwechsler).
     */
    public static function normalize(string $value): string
    {
        return strtr(
            strtolower(preg_replace('/[\s-]+/', '', $value) ?? ''),
            ['o' => '0', 'i' => '1', 'l' => '1'],
        );
    }
}
