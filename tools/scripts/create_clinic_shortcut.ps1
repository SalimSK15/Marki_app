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
$shortcutPath = Join-Path $desktop ($ShortcutName + ".lnk")
$url = $base + "/login.php?clinic=" + [Uri]::EscapeDataString($clinic)

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = Join-Path $env:WINDIR "explorer.exe"
$shortcut.Arguments = $url
$shortcut.WorkingDirectory = $env:USERPROFILE
$shortcut.IconLocation = $icon + ",0"
$shortcut.Description = "Ouvrir MARKI"
$shortcut.Save()

Write-Host "Raccourci MARKI cree avec son icone : $shortcutPath" -ForegroundColor Green
Write-Host "Adresse : $url"
