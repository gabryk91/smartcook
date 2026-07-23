[CmdletBinding()]
param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot 'build')
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
"@
    [IO.File]::WriteAllText($instructionsPath, $instructions.TrimStart(), [Text.UTF8Encoding]::new($false))
}
finally {
    if (Test-Path -LiteralPath $stagingPath) {
        Remove-Item -LiteralPath $stagingPath -Recurse -Force
    }
}

$archive = Get-Item -LiteralPath $archivePath
Write-Host ("Creato: {0} ({1:N1} KB)" -f $archive.FullName, ($archive.Length / 1KB))
Write-Host ("Istruzioni: {0}" -f $instructionsPath)
