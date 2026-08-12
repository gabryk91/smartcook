[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$packageScriptPath = Join-Path $PSScriptRoot 'New-SmartCookPackage.ps1'
if (-not (Test-Path -LiteralPath $packageScriptPath -PathType Leaf)) {
    throw "Script di packaging non trovato: $packageScriptPath"
}

Write-Host ''
Write-Host 'Inserisci le note della release per GitHub e Nextcloud App Store.'
$ReleaseNotes = Read-Host 'Note di rilascio (lascia vuoto per la descrizione predefinita)'

& $packageScriptPath -PublishGitHubRelease -ReleaseNotes $ReleaseNotes
if ($LASTEXITCODE -ne 0) {
    throw 'La pubblicazione di SmartCook non è stata completata.'
}
