param(
    [string]$AppRoot = 'C:\xampp\htdocs\corelynk',
    [string]$DbName = 'corelynk_db',
    [string]$MySqlDumpExe = 'C:\xampp\mysql\bin\mysqldump.exe',
    [string]$MySqlUser = 'root',
    [string]$MySqlPassword = '',
    [string]$OutputRoot = 'C:\xampp\backups\corelynk',
    [switch]$KeepStaging
)

$ErrorActionPreference = 'Stop'

function Ensure-Path {
    param([string]$Path)
    if (-not (Test-Path $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

if (-not (Test-Path $AppRoot)) { throw "Application root not found: $AppRoot" }
if (-not (Test-Path $MySqlDumpExe)) { throw "mysqldump executable not found: $MySqlDumpExe" }

Ensure-Path -Path $OutputRoot

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$backupName = "corelynk_${DbName}_$timestamp"
$backupDir = Join-Path $OutputRoot $backupName
$stageDir = Join-Path $backupDir 'stage'
$appStage = Join-Path $stageDir 'app'
$dbStage = Join-Path $stageDir 'db'

Ensure-Path -Path $backupDir
Ensure-Path -Path $stageDir
Ensure-Path -Path $appStage
Ensure-Path -Path $dbStage

$sqlDumpPath = Join-Path $dbStage "$DbName.sql"
$zipPath = Join-Path $backupDir "$backupName.zip"
$manifestPath = Join-Path $backupDir 'manifest.json'

$dumpArgs = @('--single-transaction', '--routines', '--triggers', '--events', '-u', $MySqlUser)
if ($MySqlPassword -ne '') {
    $dumpArgs = @("-p$MySqlPassword") + $dumpArgs
}
$dumpArgs += $DbName

& $MySqlDumpExe @dumpArgs | Set-Content -Path $sqlDumpPath -Encoding UTF8

$robocopyLog = Join-Path $backupDir 'robocopy.log'
$null = robocopy $AppRoot $appStage /E /R:1 /W:1 /NFL /NDL /NP /LOG:$robocopyLog /XD .git node_modules /XF *.log

$manifest = [PSCustomObject]@{
    generated_at = (Get-Date).ToString('s')
    app_root = $AppRoot
    db_name = $DbName
    sql_dump = $sqlDumpPath
    zip_path = $zipPath
    robocopy_log = $robocopyLog
}

$manifest | ConvertTo-Json -Depth 5 | Set-Content -Path $manifestPath -Encoding UTF8

if (Test-Path $zipPath) {
    Remove-Item -Path $zipPath -Force
}

Compress-Archive -Path (Join-Path $stageDir '*') -DestinationPath $zipPath -CompressionLevel Optimal

if (-not $KeepStaging) {
    Remove-Item -Path $stageDir -Recurse -Force
}

Write-Output "Backup created: $zipPath"
Write-Output "SQL dump: $sqlDumpPath"
Write-Output "Manifest: $manifestPath"