param(
    [string]$BaseUrl = "http://localhost/Marki_app/Partie_medecin/public",
    [string]$ClinicCode = "clinique-el-amal",
    [string]$ShortcutName = "MARKI - Clinique"
)

$ErrorActionPreference = "Stop"

$base = $BaseUrl.TrimEnd('/')
$clinic = $ClinicCode.Trim()

if ([string]::IsNullOrWhiteSpace($clinic)) {
    throw "Le code de la structure est obligatoire."
}

$desktop = [Environment]::GetFolderPath("Desktop")
$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$icon = Join-Path $projectRoot "public\assets\icons\marki-app.ico"
$shortcut = Join-Path $desktop ($ShortcutName + ".url")
$url = $base + "/login.php?clinic=" + [Uri]::EscapeDataString($clinic)

$content = @(
    "[InternetShortcut]"
    "URL=$url"
    "IconFile=$icon"
    "IconIndex=0"
)

Set-Content -Path $shortcut -Value $content -Encoding ASCII
Write-Host "Raccourci cree : $shortcut"
Write-Host "Adresse : $url"
