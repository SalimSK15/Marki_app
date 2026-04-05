<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Repository des patients
|--------------------------------------------------------------------------
| Ce repository gère :
| - la recherche de patients existants
| - la création de patients
| - la normalisation des noms
| - la détection d'un téléphone déjà utilisé
|--------------------------------------------------------------------------
|
| Objectif métier V1 :
| - nom complet obligatoire
| - téléphone optionnel
| - date de naissance optionnelle
|
| Règle pratique :
| - si téléphone présent, il devient la meilleure clé de rapprochement
| - sinon, on utilise nom + date de naissance si possible
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../db.php';

class PatientRepository
{
    /*
    |--------------------------------------------------------------------------
    | Connexion PDO
    |--------------------------------------------------------------------------
    */
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /*
    |--------------------------------------------------------------------------
    | Trouver un patient existant
    |--------------------------------------------------------------------------
    | Stratégie :
    | 1. si téléphone présent -> chercher par téléphone
    | 2. sinon si nom + date naissance -> chercher par nom + date naissance
    | 3. sinon -> aucun rapprochement automatique
    |--------------------------------------------------------------------------
    |
    | IMPORTANT :
    | Cette méthode ne décide pas toute seule si on doit forcer ou bloquer.
    | Elle sert à retrouver un candidat probable.
    |--------------------------------------------------------------------------
    */
    public function findExisting(
        int $clinicId,
        ?string $phone,
        string $fullName,
        ?string $birthDate
    ): ?array {
        $phone = $phone !== null ? trim($phone) : null;
        $fullName = $this->normalizePersonName($fullName);
        $birthDate = $birthDate !== null ? trim($birthDate) : null;

        /*
        |--------------------------------------------------------------
        | 1) Recherche prioritaire par téléphone
        |--------------------------------------------------------------
        */
        if ($phone !== null && $phone !== '') {
            $patient = $this->findByPhone($clinicId, $phone);

            if ($patient) {
                return $patient;
            }
        }

        /*
        |--------------------------------------------------------------
        | 2) Recherche par nom + date de naissance
        |--------------------------------------------------------------
        | On ne fait cette recherche que si on a les 2 infos.
        */
        if ($fullName !== '' && $birthDate !== null && $birthDate !== '') {
            $sql = "
                SELECT
                    id,
                    clinic_id,
                    full_name,
                    birth_date,
                    phone,
                    email,
                    address,
                    notes_non_medical,
                    created_at,
                    updated_at
                FROM patients
                WHERE clinic_id = :clinic_id
                  AND full_name = :full_name
                  AND birth_date = :birth_date
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':clinic_id' => $clinicId,
                ':full_name' => $fullName,
                ':birth_date' => $birthDate,
            ]);

            $patient = $stmt->fetch();

            if ($patient) {
                return $patient;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Trouver un patient par téléphone
    |--------------------------------------------------------------------------
    | Pourquoi cette méthode ?
    | - pour séparer clairement la logique téléphone
    | - réutilisable dans queue_add_patient.php
    |--------------------------------------------------------------------------
    */
    public function findByPhone(int $clinicId, string $phone): ?array
    {
        $phone = trim($phone);

        if ($phone === '') {
            return null;
        }

        $sql = "
            SELECT
                id,
                clinic_id,
                full_name,
                birth_date,
                phone,
                email,
                address,
                notes_non_medical,
                created_at,
                updated_at
            FROM patients
            WHERE clinic_id = :clinic_id
              AND phone = :phone
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':phone' => $phone,
        ]);

        $patient = $stmt->fetch();

        return $patient ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | Créer un patient
    |--------------------------------------------------------------------------
    */
    public function create(
        int $clinicId,
        string $fullName,
        ?string $phone,
        ?string $birthDate
    ): array {
        $fullName = $this->normalizePersonName($fullName);
        $phone = $phone !== null ? trim($phone) : null;
        $birthDate = $birthDate !== null ? trim($birthDate) : null;

        if ($fullName === '') {
            throw new InvalidArgumentException('Le nom complet est obligatoire.');
        }

        if ($phone === '') {
            $phone = null;
        }

        if ($birthDate === '') {
            $birthDate = null;
        }

        $sql = "
            INSERT INTO patients (
                clinic_id,
                full_name,
                birth_date,
                phone,
                created_at,
                updated_at
            ) VALUES (
                :clinic_id,
                :full_name,
                :birth_date,
                :phone,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':full_name' => $fullName,
            ':birth_date' => $birthDate,
            ':phone' => $phone,
        ]);

        $patientId = (int) $this->pdo->lastInsertId();

        return $this->findById($patientId, $clinicId);
    }

    /*
    |--------------------------------------------------------------------------
    | Trouver un patient par id
    |--------------------------------------------------------------------------
    */
    public function findById(int $patientId, int $clinicId): array
    {
        $sql = "
            SELECT
                id,
                clinic_id,
                full_name,
                birth_date,
                phone,
                email,
                address,
                notes_non_medical,
                created_at,
                updated_at
            FROM patients
            WHERE id = :id
              AND clinic_id = :clinic_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $patientId,
            ':clinic_id' => $clinicId,
        ]);

        $patient = $stmt->fetch();

        if (!$patient) {
            throw new RuntimeException('Patient introuvable après création.');
        }

        return $patient;
    }

    /*
    |--------------------------------------------------------------------------
    | Comparer deux noms normalisés
    |--------------------------------------------------------------------------
    | Pourquoi ?
    | - éviter les différences de casse
    | - éviter les doubles espaces
    |--------------------------------------------------------------------------
    */
    public function sameNormalizedName(string $left, string $right): bool
    {
        return $this->normalizePersonName($left) === $this->normalizePersonName($right);
    }

    /*
    |--------------------------------------------------------------------------
    | Normaliser un nom de personne
    |--------------------------------------------------------------------------
    | Règles :
    | - trim
    | - supprimer les doubles espaces
    | - mettre une casse propre
    |--------------------------------------------------------------------------
    */
    private function normalizePersonName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
    /*
    |--------------------------------------------------------------------------
    | Mettre à jour l'identité d'un patient
    |--------------------------------------------------------------------------
    | Utilisé quand on corrige réellement la fiche du patient.
    |--------------------------------------------------------------------------
    */
    public function updateIdentity(
        int $patientId,
        int $clinicId,
        string $fullName,
        ?string $phone,
        ?string $birthDate
    ): array {
        $fullName = $this->normalizePersonName($fullName);
        $phone = $phone !== null ? trim($phone) : null;
        $birthDate = $birthDate !== null ? trim($birthDate) : null;

        if ($fullName === '') {
            throw new InvalidArgumentException('Le nom complet est obligatoire.');
        }

        if ($phone === '') {
            $phone = null;
        }

        if ($birthDate === '') {
            $birthDate = null;
        }

        $sql = "
            UPDATE patients
            SET
                full_name = :full_name,
                phone = :phone,
                birth_date = :birth_date,
                updated_at = NOW()
            WHERE id = :id
            AND clinic_id = :clinic_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $fullName,
            ':phone' => $phone,
            ':birth_date' => $birthDate,
            ':id' => $patientId,
            ':clinic_id' => $clinicId,
        ]);

        return $this->findById($patientId, $clinicId);
    }
}