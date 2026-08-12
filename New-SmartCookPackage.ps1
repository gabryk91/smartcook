[CmdletBinding()]
param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot 'build'),
    [string]$CertificateKeyPath = (Join-Path $env:USERPROFILE '.nextcloud\certificates\smartcook.key'),
    [string]$OpenSslPath = 'C:\Program Files\OpenSSL-Win64\bin\openssl.exe',
    [string]$GitHubRepository = 'gabryk91/smartcook',
    [switch]$ConfigureGitHubReleaseSecrets,
    [securestring]$AppStoreToken,
    [string]$GitHubEnvironment = 'release'
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

if ($ConfigureGitHubReleaseSecrets) {
    $ghCommand = Get-Command gh -ErrorAction SilentlyContinue
    if ($null -eq $ghCommand) {
        throw 'GitHub CLI non trovato. Installa GitHub CLI e completa "gh auth login" prima di configurare i segreti.'
    }

    if ($null -eq $AppStoreToken) {
        $AppStoreToken = Read-Host -AsSecureString 'Incolla il token API di apps.nextcloud.com'
    }

    $tokenBstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($AppStoreToken)
    try {
        $tokenValue = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($tokenBstr)
        $keyValue = Get-Content -LiteralPath $CertificateKeyPath -Raw

        $keyValue | & $ghCommand.Source secret set APP_PRIVATE_KEY --env $GitHubEnvironment --repo $GitHubRepository
        if ($LASTEXITCODE -ne 0) {
            throw 'Impossibile salvare APP_PRIVATE_KEY nei segreti GitHub.'
        }

        $tokenValue | & $ghCommand.Source secret set APPSTORE_TOKEN --env $GitHubEnvironment --repo $GitHubRepository
        if ($LASTEXITCODE -ne 0) {
            throw 'Impossibile salvare APPSTORE_TOKEN nei segreti GitHub.'
        }
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($tokenBstr)
    }

    Write-Host "Segreti GitHub configurati nell'environment '$GitHubEnvironment' del repository $GitHubRepository."
    return
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

    $signProcess = [Diagnostics.Process]::new()
    $signProcess.StartInfo.FileName = $OpenSslPath
    $signProcess.StartInfo.Arguments = 'dgst -sha512 -sign "{0}" "{1}"' -f $CertificateKeyPath, $storeArchivePath
    $signProcess.StartInfo.UseShellExecute = $false
    $signProcess.StartInfo.RedirectStandardOutput = $true
    $signProcess.StartInfo.RedirectStandardError = $true

    [void]$signProcess.Start()
    $signatureStream = [IO.MemoryStream]::new()
    $signProcess.StandardOutput.BaseStream.CopyTo($signatureStream)
    $signError = $signProcess.StandardError.ReadToEnd()
    $signProcess.WaitForExit()
    if ($signProcess.ExitCode -ne 0) {
        throw "Impossibile firmare l'archivio per l'App Store: $signError"
    }

    $storeSignature = [Convert]::ToBase64String($signatureStream.ToArray())
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

1. Committa e invia al repository la versione $nextVersion aggiornata da questo script.
2. Crea e pubblica una GitHub Release con tag v$nextVersion nel repository $GitHubRepository.
3. Il workflow GitHub "Publish Nextcloud App Store release" allega automaticamente
   $storeArchiveName alla release e lo pubblica nell'App Store Nextcloud.
4. Verifica l'esito del workflow GitHub e la pubblicazione su:
   https://apps.nextcloud.com/apps/$appName

Configurazione iniziale (una sola volta): esegui lo script con
  .\New-SmartCookPackage.ps1 -ConfigureGitHubReleaseSecrets
e incolla il token da https://apps.nextcloud.com/account/token quando richiesto.
Lo script salva APPSTORE_TOKEN e APP_PRIVATE_KEY nell'environment GitHub "release"
senza scrivere la chiave privata nel repository.

File generati per l'App Store (fallback manuale):
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
