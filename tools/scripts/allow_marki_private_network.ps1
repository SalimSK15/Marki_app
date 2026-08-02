param(
    [int]$Port = 80
)

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
$isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Error 'Ce script doit etre execute en administrateur.'
    exit 1
}

$ruleName = 'MARKI Laragon HTTP'
$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue

if ($existing) {
    Set-NetFirewallRule -DisplayName $ruleName -Enabled True -Direction Inbound -Action Allow -Profile Private | Out-Null
    Set-NetFirewallPortFilter -AssociatedNetFirewallRule $existing -Protocol TCP -LocalPort $Port | Out-Null
    Write-Host "Regle mise a jour pour le port $Port sur les reseaux prives." -ForegroundColor Green
} else {
    New-NetFirewallRule `
        -DisplayName $ruleName `
        -Direction Inbound `
        -Action Allow `
        -Protocol TCP `
        -LocalPort $Port `
        -Profile Private | Out-Null
    Write-Host "Regle creee pour le port $Port sur les reseaux prives." -ForegroundColor Green
}

Write-Host 'Aucun acces n a ete ouvert sur les reseaux publics.' -ForegroundColor Cyan
Read-Host 'Appuyez sur Entree pour fermer'
