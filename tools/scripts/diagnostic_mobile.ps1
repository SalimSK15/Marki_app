param(
    [int]$Port = 80
)

$ErrorActionPreference = 'SilentlyContinue'

Write-Host ''
Write-Host '=== Diagnostic reseau MARKI ===' -ForegroundColor Cyan
Write-Host ''

Write-Host '1. Profils reseau actifs' -ForegroundColor Yellow
Get-NetConnectionProfile |
    Where-Object { $_.IPv4Connectivity -ne 'Disconnected' } |
    Select-Object Name, InterfaceAlias, NetworkCategory, IPv4Connectivity |
    Format-Table -AutoSize

Write-Host '2. Adresses IPv4 utilisables' -ForegroundColor Yellow
$configurations = Get-NetIPConfiguration | Where-Object {
    $_.NetAdapter.Status -eq 'Up' -and
    $_.IPv4Address -and
    $_.NetAdapter.InterfaceDescription -notmatch 'VirtualBox|VMware|WSL|Hyper-V|Loopback|TAP|VPN'
}

if (-not $configurations) {
    Write-Host 'Aucune adresse IPv4 physique active trouvee.' -ForegroundColor Red
} else {
    foreach ($configuration in $configurations) {
        foreach ($address in $configuration.IPv4Address.IPAddress) {
            Write-Host ("- Carte : {0}" -f $configuration.InterfaceAlias)
            Write-Host ("  IPv4  : {0}" -f $address) -ForegroundColor Green
            Write-Host ("  Test  : http://{0}:{1}/Marki_app/Partie_medecin/public/network-check.php" -f $address, $Port)
            Write-Host ''
        }
    }
}

Write-Host '3. Ecoute du serveur web' -ForegroundColor Yellow
$listeners = Get-NetTCPConnection -State Listen -LocalPort $Port
if ($listeners) {
    $listeners | Select-Object LocalAddress, LocalPort, OwningProcess | Format-Table -AutoSize
    if ($listeners.LocalAddress -contains '127.0.0.1') {
        Write-Host 'Attention : Apache semble ecouter seulement sur localhost.' -ForegroundColor Red
    }
    if ($listeners.LocalAddress -contains '0.0.0.0' -or $listeners.LocalAddress -contains '::') {
        Write-Host 'Le serveur ecoute sur le reseau local.' -ForegroundColor Green
    }
} else {
    Write-Host ("Aucun serveur n'ecoute sur le port {0}. Demarrez Apache/Laragon." -f $Port) -ForegroundColor Red
}

Write-Host '4. Regle pare-feu MARKI' -ForegroundColor Yellow
$rule = Get-NetFirewallRule -DisplayName 'MARKI Laragon HTTP' -ErrorAction SilentlyContinue
if ($rule) {
    $rule | Select-Object DisplayName, Enabled, Direction, Action, Profile | Format-Table -AutoSize
} else {
    Write-Host 'Regle absente. Lancez allow_marki_private_network.bat en administrateur.' -ForegroundColor DarkYellow
}

Write-Host ''
Write-Host 'Ne choisissez jamais 192.168.56.1 : c est habituellement la carte VirtualBox.' -ForegroundColor Magenta
Write-Host 'Si le telephone fournit le point d acces, testez de preference avec un deuxieme telephone connecte a ce point d acces.' -ForegroundColor Magenta
Write-Host ''
Read-Host 'Appuyez sur Entree pour fermer'
