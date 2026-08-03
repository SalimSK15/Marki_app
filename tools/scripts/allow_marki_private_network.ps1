param(
    [int]$Port = 80,
    [string]$RuleName = "MARKI Local HTTP"
)

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
$isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Error 'Ce script doit etre execute en administrateur.'
    exit 1
}

$existing = Get-NetFirewallRule -DisplayName $RuleName -ErrorAction SilentlyContinue

if ($existing) {
    Set-NetFirewallRule `
        -DisplayName $RuleName `
        -Enabled True `
        -Direction Inbound `
        -Action Allow `
        -Profile Private | Out-Null

    Set-NetFirewallAddressFilter `
        -AssociatedNetFirewallRule $existing `
        -RemoteAddress LocalSubnet | Out-Null

    Set-NetFirewallPortFilter `
        -AssociatedNetFirewallRule $existing `
        -Protocol TCP `
        -LocalPort $Port | Out-Null

    Write-Host "Regle mise a jour pour le port $Port." -ForegroundColor Green
} else {
    New-NetFirewallRule `
        -DisplayName $RuleName `
        -Direction Inbound `
        -Action Allow `
        -Protocol TCP `
        -LocalPort $Port `
        -Profile Private `
        -RemoteAddress LocalSubnet | Out-Null

    Write-Host "Regle creee pour le port $Port." -ForegroundColor Green
}

Write-Host 'Acces limite au profil Prive et aux appareils du sous-reseau local.' -ForegroundColor Cyan
Write-Host 'Aucun port du routeur Internet n a ete ouvert.' -ForegroundColor Cyan
