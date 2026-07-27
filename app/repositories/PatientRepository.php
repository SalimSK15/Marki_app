<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Repository des patients
|--------------------------------------------------------------------------
| Règles :
| - nom obligatoire
| - téléphone obligatoire
| - même nom + même téléphone = même fiche
| - même téléphone + nom différent = téléphone familial possible
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/PatientDataNormalizer.php';

class PatientRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    /*
    |--------------------------------------------------------------------------
    | Colonnes patient communes aux recherches
    |--------------------------------------------------------------------------
    */
    private function patientColumns(): string
    {
        return "
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
        ";
    }

    /*
    |--------------------------------------------------------------------------
    | Rechercher un patient par identifiant
    |--------------------------------------------------------------------------
    */
    public function findById(
        int $patientId,
        int $clinicId
    ): array {
        $sql = "
            SELECT
                {$this->patientColumns()}
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
            throw new RuntimeException(
                'Patient introuvable.'
            );
        }

        return $patient;
    }

    /*
    |--------------------------------------------------------------------------
    | Rechercher un patient utilisant un téléphone
    |--------------------------------------------------------------------------
    | Plusieurs patients peuvent partager le même téléphone.
    | Cette méthode retourne seulement le premier pour l’avertissement.
    |--------------------------------------------------------------------------
    */
    public function findByPhone(
        int $clinicId,
        string $phone
    ): ?array {
        $phone =
            PatientDataNormalizer::normalizePhone($phone);

        if ($phone === '') {
            return null;
        }

        $sql = "
            SELECT
                {$this->patientColumns()}
            FROM patients
            WHERE clinic_id = :clinic_id
              AND phone = :phone
            ORDER BY id ASC
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
    | Rechercher la même identité exacte
    |--------------------------------------------------------------------------
    | Même nom + même téléphone = même fiche patient.
    |--------------------------------------------------------------------------
    */
    public function findByPhoneAndName(
        int $clinicId,
        string $phone,
        string $fullName,
        ?int $excludedPatientId = null
    ): ?array {
        $phone =
            PatientDataNormalizer::normalizePhone($phone);

        $fullName =
            PatientDataNormalizer::normalizeName($fullName);

        if ($phone === '' || $fullName === '') {
            return null;
        }

        $sql = "
            SELECT
                {$this->patientColumns()}
            FROM patients
            WHERE clinic_id = :clinic_id
              AND phone = :phone
              AND full_name = :full_name
        ";

        $parameters = [
            ':clinic_id' => $clinicId,
            ':phone' => $phone,
            ':full_name' => $fullName,
        ];

        if ($excludedPatientId !== null) {
            $sql .= "
                AND id <> :excluded_patient_id
            ";

            $parameters[':excluded_patient_id'] =
                $excludedPatientId;
        }

        $sql .= "
            ORDER BY id ASC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);

        $patient = $stmt->fetch();

        return $patient ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | Rechercher un autre patient utilisant le téléphone
    |--------------------------------------------------------------------------
    | Utilisé pendant une modification pour ignorer la fiche courante.
    |--------------------------------------------------------------------------
    */
    public function findByPhoneExcludingPatientId(
        int $clinicId,
        string $phone,
        int $excludedPatientId
    ): ?array {
        $phone =
            PatientDataNormalizer::normalizePhone($phone);

        if ($phone === '') {
            return null;
        }

        $sql = "
            SELECT
                {$this->patientColumns()}
            FROM patients
            WHERE clinic_id = :clinic_id
              AND phone = :phone
              AND id <> :excluded_patient_id
            ORDER BY id ASC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':clinic_id' => $clinicId,
            ':phone' => $phone,
            ':excluded_patient_id' => $excludedPatientId,
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
        $fullName =
            PatientDataNormalizer::normalizeName($fullName);

        $phone =
            PatientDataNormalizer::normalizePhone(
                $phone ?? ''
            );

        $birthDate = $birthDate !== null
            ? trim($birthDate)
            : null;

        if ($fullName === '') {
            throw new InvalidArgumentException(
                'Le nom complet est obligatoire.'
            );
        }

        if ($phone === '') {
            throw new InvalidArgumentException(
                'Le numéro de téléphone est obligatoire.'
            );
        }

        if (
            !PatientDataNormalizer::isValidPhone($phone)
        ) {
            throw new InvalidArgumentException(
                PatientDataNormalizer::phoneValidationMessage()
            );
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

        $patientId =
            (int) $this->pdo->lastInsertId();

        return $this->findById(
            $patientId,
            $clinicId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mettre à jour une fiche patient
    |--------------------------------------------------------------------------
    */
    public function updateIdentity(
        int $patientId,
        int $clinicId,
        string $fullName,
        ?string $phone,
        ?string $birthDate
    ): array {
        $fullName =
            PatientDataNormalizer::normalizeName($fullName);

        $phone =
            PatientDataNormalizer::normalizePhone(
                $phone ?? ''
            );

        $birthDate = $birthDate !== null
            ? trim($birthDate)
            : null;

        if ($fullName === '') {
            throw new InvalidArgumentException(
                'Le nom complet est obligatoire.'
            );
        }

        if ($phone === '') {
            throw new InvalidArgumentException(
                'Le numéro de téléphone est obligatoire.'
            );
        }

        if (
            !PatientDataNormalizer::isValidPhone($phone)
        ) {
            throw new InvalidArgumentException(
                PatientDataNormalizer::phoneValidationMessage()
            );
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

        return $this->findById(
            $patientId,
            $clinicId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Comparer deux noms normalisés
    |--------------------------------------------------------------------------
    */
    public function sameNormalizedName(
        string $left,
        string $right
    ): bool {
        return PatientDataNormalizer::normalizeName($left)
            === PatientDataNormalizer::normalizeName($right);
    }
}