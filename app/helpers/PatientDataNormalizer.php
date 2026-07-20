<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Normalisation des données patient
|--------------------------------------------------------------------------
| Cette classe centralise le nettoyage :
| - des noms
| - des numéros de téléphone
|
| Elle évite de répéter la même logique dans les API et repositories.
|--------------------------------------------------------------------------
*/
final class PatientDataNormalizer
{
    /*
    |--------------------------------------------------------------------------
    | Normaliser un nom
    |--------------------------------------------------------------------------
    | Exemple :
    | "  baya   KHELIFI " devient "Baya Khelifi"
    |--------------------------------------------------------------------------
    */
    public static function normalizeName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return mb_convert_case(
            $value,
            MB_CASE_TITLE,
            'UTF-8'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normaliser un numéro de téléphone
    |--------------------------------------------------------------------------
    | Exemples :
    | "0551 70 07 10"   devient "0551700710"
    | "+213 551700710"  devient "0551700710"
    | "00213 551700710" devient "0551700710"
    |--------------------------------------------------------------------------
    */
    public static function normalizePhone(string $value): string
    {
        $digits = preg_replace(
            '/\D+/',
            '',
            trim($value)
        ) ?? '';

        $hadAlgerianCountryCode = false;

        if (str_starts_with($digits, '00213')) {
            $digits = substr($digits, 5);
            $hadAlgerianCountryCode = true;
        } elseif (
            str_starts_with($digits, '213')
            && strlen($digits) >= 12
        ) {
            $digits = substr($digits, 3);
            $hadAlgerianCountryCode = true;
        }

        if (
            $hadAlgerianCountryCode
            && strlen($digits) === 9
        ) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /*
    |--------------------------------------------------------------------------
    | Valider une longueur de téléphone raisonnable
    |--------------------------------------------------------------------------
    | On accepte 8 à 15 chiffres afin de ne pas bloquer :
    | - les numéros algériens ;
    | - les numéros internationaux ;
    | - les données de test existantes.
    |--------------------------------------------------------------------------
    */
    public static function isValidPhone(string $phone): bool
    {
        $length = strlen($phone);

        return $length >= 8 && $length <= 15;
    }
}