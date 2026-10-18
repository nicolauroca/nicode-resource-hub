# SPDX-License-Identifier: GPL-2.0-or-later
param(
    [Parameter(Mandatory = $true)][string]$Destination,
    [Parameter(Mandatory = $true)][string]$PackageDirectory
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# Resolve inputs before creating anything. Existing destinations are never reused.
$target = [System.IO.Path]::GetFullPath($Destination)
if (Test-Path -LiteralPath $target) {
    throw 'Destination already exists. Choose a new empty lab path; nothing was changed.'
}
$packages = Get-Content -Raw -LiteralPath (Join-Path $PSScriptRoot 'packages.json') | ConvertFrom-Json
$archives = @{}
foreach ($package in $packages) {
    $archive = Join-Path $PackageDirectory $package.file
    if (-not (Test-Path -LiteralPath $archive -PathType Leaf)) {
        throw "Missing package: $($package.file). Download the exact file in packages.json."
    }
    $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath $archive).Hash
    if ($actual -ne $package.sha256) {
        throw "SHA256 mismatch: $($package.file). Nothing has been extracted."
    }
    $archives[$package.name] = (Resolve-Path -LiteralPath $archive).Path
}

New-Item -ItemType Directory -Path $target | Out-Null
Add-Type -AssemblyName System.IO.Compression.FileSystem
foreach ($package in $packages) {
    $directory = Join-Path $target $package.name
    [System.IO.Compression.ZipFile]::ExtractToDirectory($archives[$package.name], $directory)
}
$phpDirectory = Join-Path $target 'php'
$extensionDirectory = (Join-Path $phpDirectory 'ext').Replace('\', '/')
$ini = @"
extension_dir="$extensionDirectory"
memory_limit=256M
date.timezone=UTC
display_errors=On
display_startup_errors=On
log_errors=Off
error_reporting=E_ALL
extension=gd
extension=mbstring
extension=pdo_mysql
extension=mysqli
"@
$encoding = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText((Join-Path $phpDirectory 'php.ini'), $ini, $encoding)

# A private, empty scan directory prevents additional machine-specific ini files.
$scanDirectory = Join-Path $target 'ini-scan'
New-Item -ItemType Directory -Path $scanDirectory | Out-Null
$previousScan = $env:PHP_INI_SCAN_DIR
try {
    $env:PHP_INI_SCAN_DIR = $scanDirectory
    & (Join-Path $phpDirectory 'php.exe') -c (Join-Path $phpDirectory 'php.ini') (Join-Path $PSScriptRoot 'check.php') (Join-Path $target 'joomla')
    if ($LASTEXITCODE -ne 0) {
        throw "Lab check failed (exit $LASTEXITCODE). Keep the folder to inspect the error; use a new path for the next attempt."
    }
} finally {
    $env:PHP_INI_SCAN_DIR = $previousScan
}
Write-Output 'READY: CLI prerequisite only. Joomla site and database are not installed.'
