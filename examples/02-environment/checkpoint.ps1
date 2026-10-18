# SPDX-License-Identifier: GPL-2.0-or-later
param(
    [Parameter(Mandatory = $true)][ValidateSet('save', 'restore')][string]$Action,
    [Parameter(Mandatory = $true)][string]$Lab,
    [Parameter(Mandatory = $true)][string]$Archive
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.Security
$root = [IO.Path]::GetFullPath($Lab).TrimEnd('\')
$file = [IO.Path]::GetFullPath($Archive)
# Reject junctions in the destination ancestry as well as inside a saved tree.
$ancestor = $root
while ($ancestor) {
    if (Test-Path -LiteralPath $ancestor) {
        if ((Get-Item -LiteralPath $ancestor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) {
            throw 'Linked lab paths are not supported.'
        }
    }
    $ancestor = [IO.Path]::GetDirectoryName($ancestor)
}
if ($root -eq [IO.Path]::GetPathRoot($root).TrimEnd('\')) { throw 'A drive root is not a lab.' }
if ($file.StartsWith($root + '\', [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Keep the checkpoint outside the lab.'
}
# Conservative local checkpoint: no PHP or MariaDB process may be active.
# This checks process absence, not proof of a previous graceful shutdown.
if (Get-Process -Name php,mariadbd,mysqld -ErrorAction SilentlyContinue) {
    throw 'Stop PHP and MariaDB cleanly before this operation. No processes were stopped.'
}
$memory = New-Object IO.MemoryStream
$zip = $null
try {
    if ($Action -eq 'save') {
        if (Test-Path -LiteralPath $file) { throw 'Archive already exists.' }
        foreach ($required in @('joomla\configuration.php', 'database.ini', 'php\php.exe', 'database-data')) {
            if (-not (Test-Path -LiteralPath (Join-Path $root $required))) { throw 'Installed lab required.' }
        }
        $items = @(Get-ChildItem -LiteralPath $root -Recurse -Force)
        if ($items | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) {
            throw 'Linked files or directories are not supported.'
        }
        $zip = New-Object IO.Compression.ZipArchive($memory, [IO.Compression.ZipArchiveMode]::Create, $true)
        $metadata = $zip.CreateEntry('__checkpoint.json')
        $writer = New-Object IO.StreamWriter($metadata.Open())
        $writer.Write((@{ lab = $root; format = 1 } | ConvertTo-Json -Compress))
        $writer.Dispose()
        foreach ($item in $items) {
            $relative = $item.FullName.Substring($root.Length + 1).Replace('\', '/')
            if ($item.PSIsContainer) { $null = $zip.CreateEntry('lab/' + $relative + '/'); continue }
            $entry = $zip.CreateEntry('lab/' + $relative, [IO.Compression.CompressionLevel]::Optimal)
            $inputStream = [IO.File]::OpenRead($item.FullName)
            $outputStream = $entry.Open()
            try { $inputStream.CopyTo($outputStream) } finally { $inputStream.Dispose(); $outputStream.Dispose() }
        }
        $zip.Dispose(); $zip = $null
        # Plaintext ZIP stays in memory; only the DPAPI-protected bytes reach disk.
        $protected = [Security.Cryptography.ProtectedData]::Protect($memory.ToArray(), $null, 'CurrentUser')
        $outputStream = [IO.File]::Open($file, [IO.FileMode]::CreateNew)
        try { $outputStream.Write($protected, 0, $protected.Length) } finally { $outputStream.Dispose() }
        Write-Output 'PASS encrypted checkpoint saved. Recovery is not yet proved.'
    } else {
        if (Test-Path -LiteralPath $root) { throw 'Lab destination already exists. Preserve it before restoring.' }
        $plain = [Security.Cryptography.ProtectedData]::Unprotect([IO.File]::ReadAllBytes($file), $null, 'CurrentUser')
        $memory.Write($plain, 0, $plain.Length); $memory.Position = 0
        $zip = New-Object IO.Compression.ZipArchive($memory, [IO.Compression.ZipArchiveMode]::Read, $true)
        $reader = New-Object IO.StreamReader($zip.GetEntry('__checkpoint.json').Open())
        try { $metadata = $reader.ReadToEnd() | ConvertFrom-Json } finally { $reader.Dispose() }
        if ($metadata.format -ne 1 -or $metadata.lab -ne $root) {
            throw 'Restore requires the original absolute lab path and checkpoint format.'
        }
        # Validate every destination before creating any of them.
        $targets = @{}
        foreach ($entry in $zip.Entries) {
            if ($entry.FullName -eq '__checkpoint.json') { continue }
            if (-not $entry.FullName.StartsWith('lab/')) { throw 'Unexpected archive entry.' }
            $relative = $entry.FullName.Substring(4)
            if ($relative.Contains(':')) { throw 'Invalid archive path.' }
            $target = [IO.Path]::GetFullPath((Join-Path $root $relative))
            if (-not $target.StartsWith($root + '\', [StringComparison]::OrdinalIgnoreCase)) {
                throw 'Archive path escapes the lab.'
            }
            if ($targets.ContainsKey($target)) { throw 'Duplicate archive destination.' }
            $targets[$target] = $entry
        }
        foreach ($target in $targets.Keys) {
            $entry = $targets[$target]
            if ($entry.FullName.EndsWith('/')) { $null = [IO.Directory]::CreateDirectory($target); continue }
            $null = [IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($target))
            $inputStream = $entry.Open()
            $outputStream = [IO.File]::Open($target, [IO.FileMode]::CreateNew)
            try { $inputStream.CopyTo($outputStream) } finally { $inputStream.Dispose(); $outputStream.Dispose() }
        }
        Write-Output 'PASS checkpoint extracted. Start the lab and verify its saved state.'
    }
} finally {
    if ($zip) { $zip.Dispose() }
    $memory.Dispose()
}
