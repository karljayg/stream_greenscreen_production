# Backup server data/*.json then SCP deploy files for stream settings layer.
$ErrorActionPreference = "Stop"
$PEM = "$env:USERPROFILE\Downloads\REVIEW\2024\Other\karl ptfs aws.pem"
$SshTarget = "ec2-user@psistorm.com"
$Remote = "/var/www/html/psistorm/stream_production"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$Log = Join-Path $Root "scripts\deploy_backup_$Stamp.log"

"" | Set-Content $Log
function Log($m) { Add-Content $Log $m; Write-Output $m }

Log "Backup on server: data/backups/$Stamp"
& ssh -i $PEM $SshTarget "mkdir -p $Remote/data/backups/$Stamp && cp -a $Remote/data/*.json $Remote/data/backups/$Stamp/ 2>/dev/null || true"
if ($LASTEXITCODE -ne 0) { Log "SSH backup warning exit $LASTEXITCODE" }

$files = @(
    @{ Local = "index.php"; Remote = "$Remote/" },
    @{ Local = "asset_version.php"; Remote = "$Remote/" },
    @{ Local = "stream_broadcast.php"; Remote = "$Remote/" },
    @{ Local = "stream_user_preferences.php"; Remote = "$Remote/" },
    @{ Local = "js\stream_settings.js"; Remote = "$Remote/js/" }
)
foreach ($f in $files) {
    $src = Join-Path $Root $f.Local
    Log "SCP $src -> $($f.Remote)"
    & scp -i $PEM $src "${SshTarget}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { Log "FAILED $($f.Local)"; exit 1 }
}
Log "Done. Log: $Log"
