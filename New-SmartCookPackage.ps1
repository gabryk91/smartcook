[CmdletBinding()]
param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot 'build'),
    [string]$CertificateKeyPath = (Join-Path $env:USERPROFILE '.nextcloud\certificates\smartcook.key'),
    [string]$OpenSslPath = 'C:\Program Files\OpenSSL-Win64\bin\openssl.exe',
    [string]$GitHubRepository = 'gabryk91/smartcook'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path -LiteralPath $PSScriptRoot).Path
$appName = 'smartcook'
$infoPath = Join-Path $projectRoot 'appinfo\info.xml'

if (-not (Test-Path -LiteralPath $infoPath -PathType Leaf)) {
    throw "File non trovato: $infoPath"
}

$info = [xml](Get-Content -LiteralPath $infoPath -Raw)
$version = [string]$info.info.version
if ([string]::IsNullOrWhiteSpace($version)) {
    throw 'La versione dell''app non è valorizzata in appinfo/info.xml.'
}

if (-not (Test-Path -LiteralPath $CertificateKeyPath -PathType Leaf)) {
    throw "Chiave privata per la firma non trovata: $CertificateKeyPath"
}

if (-not (Test-Path -LiteralPath $OpenSslPath -PathType Leaf)) {
    throw "OpenSSL non trovato: $OpenSslPath"
}

$tarCommand = Get-Command tar -ErrorAction SilentlyContinue
if ($null -eq $tarCommand) {
    throw 'Il comando tar non è disponibile. Installa o abilita bsdtar prima di creare il pacchetto per l''App Store.'
}

try {
    $parsedVersion = [version]$version
}
catch {
    throw "Versione non valida in appinfo/info.xml: $version"
}

$nextVersion = '{0}.{1}.{2}' -f $parsedVersion.Major, $parsedVersion.Minor, ($parsedVersion.Build + 1)

# Questa whitelist deve rimanere allineata a krankerl.toml.
$packageEntries = @(
    'appinfo',
    'css',
    'img',
    'js',
    'l10n',
    'lib',
    'templates',
    'LICENSE',
    'README.md'
)

foreach ($entry in $packageEntries) {
    $sourcePath = Join-Path $projectRoot $entry
    if (-not (Test-Path -LiteralPath $sourcePath)) {
        throw "Elemento richiesto non trovato: $entry"
    }
}

$outputPathInput = if ([IO.Path]::IsPathRooted($OutputDirectory)) {
    $OutputDirectory
} else {
    Join-Path $projectRoot $OutputDirectory
}
$outputPath = [IO.Path]::GetFullPath($outputPathInput)
New-Item -ItemType Directory -Path $outputPath -Force | Out-Null

$archiveName = '{0}-{1}-nextcloud.zip' -f $appName, $nextVersion
while (Test-Path -LiteralPath (Join-Path $outputPath $archiveName)) {
    $parsedVersion = [version]$nextVersion
    $nextVersion = '{0}.{1}.{2}' -f $parsedVersion.Major, $parsedVersion.Minor, ($parsedVersion.Build + 1)
    $archiveName = '{0}-{1}-nextcloud.zip' -f $appName, $nextVersion
}

$archivePath = Join-Path $outputPath $archiveName
$instructionsPath = Join-Path $outputPath ('{0}-{1}-nextcloud.txt' -f $appName, $nextVersion)
$storeArchiveName = '{0}-{1}.tar.gz' -f $appName, $nextVersion
$storeArchivePath = Join-Path $outputPath $storeArchiveName
$storeSignaturePath = $storeArchivePath + '.sig'
$releaseDownloadUrl = 'https://github.com/{0}/releases/download/v{1}/{2}' -f $GitHubRepository, $nextVersion, $storeArchiveName
$stagingPath = Join-Path ([IO.Path]::GetTempPath()) ("smartcook-package-{0}" -f ([guid]::NewGuid().ToString('N')))
$stagingAppPath = Join-Path $stagingPath $appName

try {
    New-Item -ItemType Directory -Path $stagingAppPath -Force | Out-Null

    foreach ($entry in $packageEntries) {
        $sourcePath = Join-Path $projectRoot $entry
        Copy-Item -LiteralPath $sourcePath -Destination (Join-Path $stagingAppPath $entry) -Recurse -Force
    }

    $stagedInfoPath = Join-Path $stagingAppPath 'appinfo\info.xml'
    $stagedInfo = Get-Content -LiteralPath $stagedInfoPath -Raw
    $stagedInfo = [regex]::Replace($stagedInfo, '(<version>)[^<]+(</version>)', ('${1}' + $nextVersion + '${2}'), 1)
    [IO.File]::WriteAllText($stagedInfoPath, $stagedInfo, [Text.UTF8Encoding]::new($false))

    Compress-Archive -LiteralPath $stagingAppPath -DestinationPath $archivePath -CompressionLevel Optimal

    & $tarCommand.Source -czf $storeArchivePath -C $stagingPath $appName
    if ($LASTEXITCODE -ne 0) {
        throw 'Impossibile creare l''archivio tar.gz per l''App Store.'
    }

    $storeSignature = (& $OpenSslPath dgst -sha512 -sign $CertificateKeyPath $storeArchivePath | & $OpenSslPath base64 -A).Trim()
    if ([string]::IsNullOrWhiteSpace($storeSignature)) {
        throw 'La firma dell''archivio per l''App Store non è stata generata.'
    }
    [IO.File]::WriteAllText($storeSignaturePath, $storeSignature + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))

    $sourceInfo = Get-Content -LiteralPath $infoPath -Raw
    $sourceInfo = [regex]::Replace($sourceInfo, '(<version>)[^<]+(</version>)', ('${1}' + $nextVersion + '${2}'), 1)
    [IO.File]::WriteAllText($infoPath, $sourceInfo, [Text.UTF8Encoding]::new($false))

    $instructions = @"
docker exec -u www-data Nextcloud php occ app:disable smartcook
docker exec -u www-data Nextcloud php occ maintenance:mode --on

unzip -o /mnt/cache/appdata/nextcloud/Install/$archiveName \
  -d /mnt/cache/appdata/nextcloud/apps/

docker exec -u root Nextcloud \
  chown -R www-data:www-data /var/www/html/custom_apps/smartcook

docker exec -u www-data Nextcloud php occ app:enable smartcook
docker exec -u www-data Nextcloud php occ maintenance:repair
docker exec -u www-data Nextcloud php occ maintenance:mode --off

--- PUBBLICAZIONE APP STORE NEXTCLOUD ---

1. Crea una GitHub Release con tag v$nextVersion nel repository $GitHubRepository.
2. Carica come asset della release il file:
   $storeArchiveName
3. Apri https://apps.nextcloud.com/developer/apps/releases/new
4. Lascia disattivata l'opzione "Notturna" e inserisci:

   Download (tar.gz):
   $releaseDownloadUrl

   Firma:
   $storeSignature

5. Seleziona "Carica" e verifica la pubblicazione su:
   https://apps.nextcloud.com/apps/$appName

Il certificato e la chiave esistenti vengono riutilizzati: non registrarne uno nuovo
per gli aggiornamenti ordinari. Non caricare o committare mai il file .key.

File generati per l'App Store:
   Archivio: $storeArchivePath
   Firma:    $storeSignaturePath
"@
    [IO.File]::WriteAllText($instructionsPath, $instructions.TrimStart(), [Text.UTF8Encoding]::new($false))
}
finally {
    if (Test-Path -LiteralPath $stagingPath) {
        Remove-Item -LiteralPath $stagingPath -Recurse -Force
    }
}

$archive = Get-Item -LiteralPath $archivePath
$storeArchive = Get-Item -LiteralPath $storeArchivePath
Write-Host ("Creato: {0} ({1:N1} KB)" -f $archive.FullName, ($archive.Length / 1KB))
Write-Host ("App Store: {0} ({1:N1} KB)" -f $storeArchive.FullName, ($storeArchive.Length / 1KB))
Write-Host ("Firma App Store: {0}" -f $storeSignaturePath)
Write-Host ("Istruzioni: {0}" -f $instructionsPath)
