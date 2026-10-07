<#
.SYNOPSIS
    Builds the installable package of mod_pixindex.

.DESCRIPTION
    Reads the version from mod_pixindex/mod_pixindex.xml, copies the module to a
    staging folder, adds minified copies of the CSS and JavaScript files
    (pixindex.min.css / pixindex.min.js, served by Joomla outside debug mode) and
    creates dist/mod_pixindex-<version>.zip.

    Minification uses esbuild through npx (Node.js required, the package is
    downloaded on first use). Without it the minified files are plain copies.
#>

$ErrorActionPreference = 'Stop'

$root     = $PSScriptRoot
$source   = Join-Path $root 'mod_pixindex'
$manifest = Join-Path $source 'mod_pixindex.xml'
$dist     = Join-Path $root 'dist'
$esbuild  = 'esbuild@0.28.2'

[xml] $xml = Get-Content -Path $manifest -Raw -Encoding UTF8
$version   = $xml.extension.version

if (-not $version) {
    throw "Version not found in $manifest"
}

$staging = Join-Path ([System.IO.Path]::GetTempPath()) ("mod_pixindex-build-" + [guid]::NewGuid().ToString('N'))

try {
    Copy-Item -Path $source -Destination $staging -Recurse

    # Minified assets next to the sources.
    $assets = @(
        @{ Source = 'media/js/pixindex.js'; Target = 'media/js/pixindex.min.js' },
        @{ Source = 'media/css/pixindex.css'; Target = 'media/css/pixindex.min.css' }
    )

    $npx = Get-Command npx -ErrorAction SilentlyContinue

    foreach ($asset in $assets) {
        $in  = Join-Path $staging $asset.Source
        $out = Join-Path $staging $asset.Target
        $ok  = $false

        if ($npx) {
            & $npx.Source --yes $esbuild $in --minify --log-level=warning "--outfile=$out"
            $ok = ($LASTEXITCODE -eq 0) -and (Test-Path $out)
        }

        if (-not $ok) {
            Write-Warning "Minification not available for $($asset.Source): copying the source file."
            Copy-Item -Path $in -Destination $out -Force
        }
    }

    New-Item -ItemType Directory -Force -Path $dist | Out-Null

    $zip = Join-Path $dist "mod_pixindex-$version.zip"

    if (Test-Path $zip) {
        Remove-Item $zip -Force
    }

    # Use .NET directly: Compress-Archive writes backslashes in entry names on Windows PowerShell 5.1,
    # which Joomla cannot extract correctly on Linux servers.
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)

    try {
        Get-ChildItem -Path $staging -Recurse -File | ForEach-Object {
            $entry = $_.FullName.Substring($staging.Length + 1).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, $entry, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    }
    finally {
        $archive.Dispose()
    }

    Write-Host "Package created: $zip"
}
finally {
    if (Test-Path $staging) {
        Remove-Item -Path $staging -Recurse -Force
    }
}
