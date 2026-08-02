$ErrorActionPreference = 'Stop'

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptDirectory '..\..')
$dataDirectory = Join-Path $projectRoot 'public\assets\data'
$destination = Join-Path $dataDirectory 'algeria-communes.json'
$licenseDestination = Join-Path $dataDirectory 'GEOALGERIA_LICENSE.txt'
$tempFile = Join-Path $env:TEMP ('marki-algeria-communes-' + [Guid]::NewGuid().ToString('N') + '.json')

$datasetUrls = @(
    'https://cdn.jsdelivr.net/npm/geoalgeria@1.1.1/data/ecommerce/communes.json',
    'https://unpkg.com/geoalgeria@1.1.1/data/ecommerce/communes.json',
    'https://raw.githubusercontent.com/yasserstudio/geoalgeria/refs/heads/main/packages/dataset/data/ecommerce/communes.json'
)

$licenseUrls = @(
    'https://cdn.jsdelivr.net/npm/geoalgeria@1.1.1/LICENSE',
    'https://unpkg.com/geoalgeria@1.1.1/LICENSE'
)

New-Item -ItemType Directory -Force -Path $dataDirectory | Out-Null

function Download-FirstAvailable {
    param(
        [string[]]$Urls,
        [string]$OutputPath
    )

    $lastError = $null

    foreach ($url in $Urls) {
        try {
            Write-Host "Téléchargement : $url"
            Invoke-WebRequest -Uri $url -OutFile $OutputPath -UseBasicParsing
            return $url
        } catch {
            $lastError = $_
            Write-Warning "Source indisponible : $url"
        }
    }

    if ($lastError) {
        throw $lastError
    }

    throw 'Aucune source de téléchargement disponible.'
}

try {
    $usedUrl = Download-FirstAvailable -Urls $datasetUrls -OutputPath $tempFile

    $raw = Get-Content -Path $tempFile -Raw -Encoding UTF8
    $data = $raw | ConvertFrom-Json
    $records = @($data)

    if ($records.Count -lt 1500) {
        throw "Le fichier téléchargé ne contient que $($records.Count) communes."
    }

    $wilayaCodes = @(
        $records |
            ForEach-Object { [int]$_.wilaya_code } |
            Sort-Object -Unique
    )

    if ($wilayaCodes.Count -lt 69) {
        throw "Le fichier ne couvre que $($wilayaCodes.Count) wilayas."
    }

    Move-Item -Path $tempFile -Destination $destination -Force

    Write-Host ''
    Write-Host "Données installées avec succès." -ForegroundColor Green
    Write-Host "Communes : $($records.Count)"
    Write-Host "Wilayas : $($wilayaCodes.Count)"
    Write-Host "Fichier : $destination"
    Write-Host "Source : $usedUrl"

    try {
        Download-FirstAvailable -Urls $licenseUrls -OutputPath $licenseDestination | Out-Null
        Write-Host "Licence : $licenseDestination"
    } catch {
        Write-Warning 'La licence n’a pas pu être téléchargée automatiquement. Consultez public/assets/data/SOURCE.md.'
    }
} finally {
    if (Test-Path $tempFile) {
        Remove-Item $tempFile -Force
    }
}
