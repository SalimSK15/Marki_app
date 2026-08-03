$ErrorActionPreference = 'Stop'
$projectRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $projectRoot

Write-Host ''
Write-Host 'Creation ou reinitialisation d un administrateur MARKI' -ForegroundColor Cyan
Write-Host '--------------------------------------------------------'

$email = Read-Host 'Adresse courriel'
$name = Read-Host 'Nom complet'
$securePassword = Read-Host 'Mot de passe (12 caracteres minimum)' -AsSecureString

$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    $env:MARKI_NEW_PLATFORM_ADMIN_EMAIL = $email
    $env:MARKI_NEW_PLATFORM_ADMIN_NAME = $name
    $env:MARKI_NEW_PLATFORM_ADMIN_PASSWORD = $plainPassword

    & php 'tools\scripts\create_platform_admin.php'
    $exitCode = $LASTEXITCODE
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    Remove-Item Env:MARKI_NEW_PLATFORM_ADMIN_EMAIL -ErrorAction SilentlyContinue
    Remove-Item Env:MARKI_NEW_PLATFORM_ADMIN_NAME -ErrorAction SilentlyContinue
    Remove-Item Env:MARKI_NEW_PLATFORM_ADMIN_PASSWORD -ErrorAction SilentlyContinue
}

Write-Host ''
if ($exitCode -eq 0) {
    Write-Host 'Operation terminee.' -ForegroundColor Green
} else {
    Write-Host 'Operation echouee. Verifiez la base de donnees et la migration.' -ForegroundColor Red
}
Read-Host 'Appuyez sur Entree pour fermer'
exit $exitCode
