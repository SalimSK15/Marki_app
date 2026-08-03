param(
    [int]$Port = 8080
)

$ErrorActionPreference = 'Stop'

function Find-MarkIIPv4 {
    $configurations = Get-NetIPConfiguration | Where-Object {
        $_.NetAdapter.Status -eq 'Up' -and
        $_.IPv4Address -and
        $_.IPv4DefaultGateway -and
        $_.NetAdapter.InterfaceDescription -notmatch 'VirtualBox|VMware|WSL|Hyper-V|Loopback|TAP|VPN'
    }

    foreach ($configuration in $configurations) {
        foreach ($address in $configuration.IPv4Address.IPAddress) {
            if ($address -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)') {
                return [PSCustomObject]@{
                    Address = $address
                    Interface = $configuration.InterfaceAlias
                }
            }
        }
    }

    return $null
}

function Find-PhpExecutable {
    $command = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $laragonPhp = Get-ChildItem 'C:\laragon\bin\php\php-*\php.exe' -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1

    if ($laragonPhp) {
        return $laragonPhp.FullName
    }

    throw 'PHP est introuvable. Demarrez le terminal Laragon ou verifiez C:\laragon\bin\php.'
}

function Update-MarkIOrigin([string]$EnvPath, [string]$Origin) {
    if (-not (Test-Path $EnvPath)) {
        throw 'Le fichier .env est absent. Lancez d abord configure_marki_env.bat.'
    }

    $content = Get-Content -Raw -Path $EnvPath
    $line = 'MARKI_QR_PUBLIC_ORIGIN="' + $Origin + '"'

    if ($content -match '(?m)^MARKI_QR_PUBLIC_ORIGIN=.*$') {
        $content = [regex]::Replace(
            $content,
            '(?m)^MARKI_QR_PUBLIC_ORIGIN=.*$',
            $line
        )
    } else {
        $content = $content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }

    Set-Content -Path $EnvPath -Value $content -Encoding UTF8
}

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$wwwRoot = Split-Path (Split-Path $projectRoot -Parent) -Parent
$envPath = Join-Path $projectRoot '.env'
$network = Find-MarkIIPv4

if (-not $network) {
    throw 'Aucune adresse IPv4 Wi-Fi/Ethernet utilisable n a ete trouvee.'
}

$ip = $network.Address
$profile = Get-NetConnectionProfile -InterfaceAlias $network.Interface -ErrorAction SilentlyContinue
if ($profile -and $profile.NetworkCategory -ne 'Private') {
    throw "Le reseau Windows est configure en $($profile.NetworkCategory). Ouvrez Parametres Windows > Reseau et Internet > Wi-Fi > votre reseau, puis choisissez Profil reseau Prive avant de relancer ce script."
}

$origin = "http://${ip}:$Port"
$relativePath = '/Marki_app/Partie_medecin/public'
$testUrl = $origin + $relativePath + '/network-check.php'
$appUrl = $origin + $relativePath + '/'
$phpExe = Find-PhpExecutable

Update-MarkIOrigin -EnvPath $envPath -Origin $origin

$ruleName = "MARKI Mobile Test $Port"
$rule = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
if (-not $rule) {
    $firewallScript = Join-Path $PSScriptRoot 'allow_marki_private_network.ps1'
    $arguments = "-NoProfile -ExecutionPolicy Bypass -File `"$firewallScript`" -Port $Port -RuleName `"$ruleName`""
    Start-Process powershell.exe -Verb RunAs -Wait -ArgumentList $arguments
}

Write-Host ''
Write-Host '=== Test mobile MARKI ===' -ForegroundColor Cyan
Write-Host ("Carte reseau : {0}" -f $network.Interface) -ForegroundColor Yellow
Write-Host ("Adresse du PC : {0}" -f $ip) -ForegroundColor Green
Write-Host ''
Write-Host '1. Sur le telephone, desactivez temporairement les donnees mobiles.'
Write-Host '2. Gardez le telephone sur le meme Wi-Fi que le PC.'
Write-Host '3. Ouvrez d abord :' -ForegroundColor Yellow
Write-Host $testUrl -ForegroundColor Green
Write-Host '4. Puis ouvrez MARKI :' -ForegroundColor Yellow
Write-Host $appUrl -ForegroundColor Green
Write-Host ''
Write-Host 'Le serveur reste actif uniquement pendant que cette fenetre est ouverte.' -ForegroundColor Cyan
Write-Host 'Appuyez sur Ctrl+C pour arreter le test.' -ForegroundColor Cyan
Write-Host ''

& $phpExe -S "0.0.0.0:$Port" -t $wwwRoot
