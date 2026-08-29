<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$sqlFile = $projectRoot . '/database/markii_db.sql';
$sqliteFile = $projectRoot . '/database/markii_db.sqlite';

if (!file_exists($sqlFile)) {
    $sqlFile = $projectRoot . '/markii_db.sql';
}

if (file_exists($sqliteFile)) {
    unlink($sqliteFile);
}

$pdo = new PDO('sqlite:' . $sqliteFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = file_get_contents($sqlFile);

preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\)\s*(?:ENGINE=InnoDB[^;]*|;)/s', $sql, $matches, PREG_SET_ORDER);

$pdo->beginTransaction();

foreach ($matches as $match) {
    $tableName = $match[1];
    $body = $match[2];

    $lines = explode("\n", $body);
    $newColumns = [];
    $hasPrimaryKey = false;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (str_starts_with($line, 'PRIMARY KEY') || str_starts_with($line, 'KEY ') || str_starts_with($line, 'UNIQUE KEY ') || str_starts_with($line, 'CONSTRAINT ')) {
            continue;
        }

        $line = rtrim($line, ',');
        $line = preg_replace("/COMMENT\s+'[^']*'/i", '', $line);

        // Si c'est la colonne id principal
        if (preg_match('/^`id`\s+(?:bigint|int|smallint)/i', $line)) {
            $line = '`id` INTEGER PRIMARY KEY AUTOINCREMENT';
            $hasPrimaryKey = true;
        } else {
            $line = preg_replace('/bigint UNSIGNED NOT NULL AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $line);
            $line = preg_replace('/bigint UNSIGNED NOT NULL/i', 'INTEGER NOT NULL', $line);
            $line = preg_replace('/bigint UNSIGNED DEFAULT NULL/i', 'INTEGER DEFAULT NULL', $line);
            $line = preg_replace('/bigint UNSIGNED/i', 'INTEGER', $line);
            $line = preg_replace('/int UNSIGNED NOT NULL AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $line);
            $line = preg_replace('/int UNSIGNED NOT NULL/i', 'INTEGER NOT NULL', $line);
            $line = preg_replace('/int UNSIGNED/i', 'INTEGER', $line);
            $line = preg_replace('/smallint UNSIGNED/i', 'INTEGER', $line);
            $line = preg_replace('/tinyint UNSIGNED/i', 'INTEGER', $line);
            $line = preg_replace('/tinyint\(1\)/i', 'INTEGER', $line);
            $line = preg_replace('/varchar\(\d+\)/i', 'TEXT', $line);
            $line = preg_replace('/time NOT NULL/i', 'TEXT NOT NULL', $line);
            $line = preg_replace('/enum\([^)]+\)/i', 'TEXT', $line);
            $line = preg_replace('/\bjson\b/i', 'TEXT', $line);
            $line = preg_replace('/COLLATE [^\s]+/i', '', $line);
            $line = preg_replace('/CHARACTER SET [^\s]+/i', '', $line);
            $line = preg_replace('/ON UPDATE CURRENT_TIMESTAMP/i', '', $line);
        }

        $line = trim($line);
        if ($line !== '') {
            $newColumns[] = $line;
        }
    }

    $createSql = "CREATE TABLE `$tableName` (\n  " . implode(",\n  ", $newColumns) . "\n);";

    try {
        $pdo->exec($createSql);
    } catch (PDOException $e) {
        echo "Erreur création table $tableName: " . $e->getMessage() . "\n";
    }
}

$pdo->commit();

// Extraction et exécution des INSERT INTO
preg_match_all('/INSERT INTO `([^`]+)`[^;]+;/s', $sql, $insertMatches);

$pdo->beginTransaction();
$insertCount = 0;

foreach ($insertMatches[0] as $insertSql) {
    try {
        $pdo->exec($insertSql);
        $insertCount++;
    } catch (PDOException $e) {
        $cleanedInsert = str_replace("\\'", "''", $insertSql);
        $cleanedInsert = str_replace('\\"', '"', $cleanedInsert);
        try {
            $pdo->exec($cleanedInsert);
            $insertCount++;
        } catch (PDOException $e2) {
            echo "Erreur INSERT table : " . $e2->getMessage() . "\n";
        }
    }
}

$pdo->commit();

echo "Importation SQLite terminée avec succès dans : $sqliteFile !\n";
echo "Nombre de blocs de données insérés : $insertCount / " . count($insertMatches[0]) . "\n";
