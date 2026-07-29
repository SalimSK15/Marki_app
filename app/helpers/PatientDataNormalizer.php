<?php

declare(strict_types=1);

final class PatientDataNormalizer
{
    public static function normalizeName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Accepte les formats algériens locaux et internationaux, puis retourne
     * toujours le format canonique stocké en base : +213XXXXXXXXX.
     */
    public static function normalizePhone(string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', trim($value)) ?? '';

        if ($digits === '') {
            return '';
        }

        if (preg_match('/^0[567][0-9]{8}$/', $digits) === 1) {
            return '+213' . substr($digits, 1);
        }

        if (preg_match('/^213[567][0-9]{8}$/', $digits) === 1) {
            return '+' . $digits;
        }

        if (preg_match('/^00213[567][0-9]{8}$/', $digits) === 1) {
            return '+' . substr($digits, 2);
        }

        if (preg_match('/^2130[567][0-9]{8}$/', $digits) === 1) {
            return '+213' . substr($digits, 4);
        }

        if (preg_match('/^002130[567][0-9]{8}$/', $digits) === 1) {
            return '+213' . substr($digits, 6);
        }

        return $digits;
    }

    public static function isValidPhone(string $value): bool
    {
        return preg_match(
            '/^\+213[567][0-9]{8}$/',
            self::normalizePhone($value)
        ) === 1;
    }

    public static function formatPhoneForDisplay(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $normalizedPhone = self::normalizePhone($value);

        if (preg_match('/^\+213[567][0-9]{8}$/', $normalizedPhone) === 1) {
            return '0' . substr($normalizedPhone, 4);
        }

        return $value;
    }

    public static function phoneValidationMessage(): string
    {
        return 'Le numéro doit être un mobile algérien valide, par exemple 0551223344.';
    }
}
