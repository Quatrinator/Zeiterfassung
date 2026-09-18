$ErrorActionPreference = 'Stop'
$projectPath = Split-Path $PSScriptRoot -Parent
$stagePath = Join-Path $projectPath ('.runtime/release-' + [guid]::NewGuid().ToString('N'))
$outputPath = Join-Path $projectPath 'dist'
New-Item -ItemType Directory -Force $stagePath, $outputPath | Out-Null
$folders = @('public','src','templates','config','bin','database','docker','styles','tests')
$files = @('compose.yaml','compose.test.yaml','.env.example','.gitignore','.dockerignore','README.md','IMPLEMENTIERUNG.md','THIRD_PARTY_NOTICES.md','Anforderungsanalyse.md','Konzept.md')
foreach ($folder in $folders) {
    foreach ($sourceFile in Get-ChildItem -LiteralPath (Join-Path $projectPath $folder) -Recurse -File -Force) {
        $relativePath = [IO.Path]::GetRelativePath($projectPath, $sourceFile.FullName)
        if ($relativePath -eq 'config\config.local.php' -or $relativePath -eq 'config/config.local.php') { continue }
        $targetPath = Join-Path $stagePath $relativePath
        New-Item -ItemType Directory -Force (Split-Path $targetPath -Parent) | Out-Null
        Copy-Item -LiteralPath $sourceFile.FullName -Destination $targetPath
    }
}
foreach ($file in $files) { Copy-Item -LiteralPath (Join-Path $projectPath $file) -Destination (Join-Path $stagePath $file) }
New-Item -ItemType Directory -Force (Join-Path $stagePath 'secrets') | Out-Null
New-Item -ItemType File (Join-Path $stagePath 'secrets/.gitkeep') | Out-Null
$archivePath = Join-Path $outputPath 'zeitwerk-1.0.0.zip'
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path -LiteralPath $archivePath) { Remove-Item -LiteralPath $archivePath }
[IO.Compression.ZipFile]::CreateFromDirectory($stagePath, $archivePath, [IO.Compression.CompressionLevel]::Optimal, $false)
$archive = [IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    foreach ($entry in $archive.Entries) {
        $name=$entry.FullName.Replace('\','/')
        if ($name -match '(^|/)(\.env|config\.local\.php|db_password\.txt|db_root_password\.txt|ERSTER-LOGIN\.txt)$' -or $name -match '^(\.runtime|\.tools)/') { throw "Vertrauliche Datei im Paket: $name" }
    }
    Write-Output ("Paket geprüft: {0} Dateien, {1}" -f $archive.Entries.Count, $archivePath)
} finally { $archive.Dispose() }
Get-FileHash -LiteralPath $archivePath -Algorithm SHA256
