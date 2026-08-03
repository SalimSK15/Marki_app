<?php

declare(strict_types=1);

$public = require __DIR__ . '/../app/public_bootstrap.php';
$config = $public['config'];

function shortcutAbsoluteBaseUrl(array $config): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

    if (!preg_match('/^[a-zA-Z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }

    $basePath = rtrim((string) ($config['app']['base_path'] ?? ''), '/');

    return $scheme . '://' . $host . $basePath;
}

function shortcutSafeFilename(string $value): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value) ?: 'MARKI';

    return trim($safe, '-');
}

function powershellEncodedCommand(string $script): string
{
    if (function_exists('mb_convert_encoding')) {
        $utf16 = mb_convert_encoding($script, 'UTF-16LE', 'UTF-8');
    } else {
        $utf16 = iconv('UTF-8', 'UTF-16LE', $script);
    }

    if ($utf16 === false) {
        throw new RuntimeException('Impossible de préparer le raccourci.');
    }

    return base64_encode($utf16);
}

$type = trim((string) ($_GET['type'] ?? 'clinic'));
$baseUrl = shortcutAbsoluteBaseUrl($config);
$shortcutName = 'MARKI';
$description = 'Ouvrir MARKI';

if ($type === 'platform') {
    $targetUrl = $baseUrl . '/platform-invitations.php';
    $shortcutName = 'MARKI - Administration';
    $description = 'Ouvrir l administration interne MARKI';
} else {
    require_once __DIR__ . '/../app/auth/AuthRepository.php';

    $clinicSlug = trim((string) ($_GET['clinic'] ?? ''));
    $clinic = $clinicSlug !== ''
        ? (new AuthRepository())->findClinicBySlug($clinicSlug)
        : null;

    if ($clinic === null || ($clinic['status'] ?? '') !== 'active') {
        http_response_code(404);
        echo 'Structure introuvable.';
        exit;
    }

    $targetUrl = $baseUrl
        . '/login.php?clinic='
        . rawurlencode($clinicSlug);
    $clinicName = trim((string) ($clinic['name'] ?? 'Clinique'));
    $shortcutName = 'MARKI - ' . ($clinicName !== '' ? $clinicName : 'Clinique');
    $description = 'Ouvrir MARKI pour ' . $clinicName;
}

$iconUrl = $baseUrl . '/assets/icons/marki-app.ico';
$installerFilename = 'Installer-' . shortcutSafeFilename($shortcutName) . '.cmd';

$psScript = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$targetUrl = '__TARGET_URL__'
$iconUrl = '__ICON_URL__'
$shortcutName = '__SHORTCUT_NAME__'
$description = '__DESCRIPTION__'
$localDirectory = Join-Path $env:LOCALAPPDATA 'MARKI'
$iconPath = Join-Path $localDirectory 'marki-app.ico'
$desktop = [Environment]::GetFolderPath('Desktop')
$shortcutPath = Join-Path $desktop ($shortcutName + '.lnk')

New-Item -ItemType Directory -Force -Path $localDirectory | Out-Null
Invoke-WebRequest -UseBasicParsing -Uri $iconUrl -OutFile $iconPath

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = Join-Path $env:WINDIR 'explorer.exe'
$shortcut.Arguments = $targetUrl
$shortcut.WorkingDirectory = $env:USERPROFILE
$shortcut.IconLocation = $iconPath + ',0'
$shortcut.Description = $description
$shortcut.Save()

Write-Host ''
Write-Host 'Raccourci MARKI installe sur le Bureau :' -ForegroundColor Green
Write-Host $shortcutPath
Write-Host ''
Start-Process $shortcutPath
POWERSHELL;

$replacements = [
    '__TARGET_URL__' => str_replace("'", "''", $targetUrl),
    '__ICON_URL__' => str_replace("'", "''", $iconUrl),
    '__SHORTCUT_NAME__' => str_replace("'", "''", $shortcutName),
    '__DESCRIPTION__' => str_replace("'", "''", $description),
];
$psScript = strtr($psScript, $replacements);
$encodedCommand = powershellEncodedCommand($psScript);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $installerFilename . '"');
header('Cache-Control: no-store');

 echo "@echo off\r\n";
 echo "title Installation du raccourci MARKI\r\n";
 echo "powershell.exe -NoProfile -ExecutionPolicy Bypass -EncodedCommand " . $encodedCommand . "\r\n";
 echo "if errorlevel 1 (\r\n";
 echo "  echo.\r\n";
 echo "  echo Impossible de creer le raccourci MARKI.\r\n";
 echo "  pause\r\n";
 echo ")\r\n";
