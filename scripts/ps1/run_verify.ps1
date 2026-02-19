# Aiven MySQL verification runner (local dev).
# Runs sql/verify/*.sql in order and writes results to logs/verify_latest.md.
# Requires: AIVEN_MYSQL_HOST, AIVEN_MYSQL_PORT, AIVEN_MYSQL_DB, AIVEN_MYSQL_USER, AIVEN_MYSQL_PASSWORD
# Optional: AIVEN_MYSQL_SSL_CA (path to CA cert for --ssl-ca)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent

# Load .env from project root if present (do not overwrite existing env vars)
$envPath = Join-Path $ProjectRoot '.env'
if (Test-Path $envPath) {
  Get-Content $envPath -Encoding UTF8 | ForEach-Object {
    $line = $_.Trim()
    if ($line -and $line -notmatch '^\s*#') {
      if ($line -match '^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$') {
        $key = $matches[1].Trim()
        $val = $matches[2].Trim()
        if ($val -match '^["''](.*)["'']$') { $val = $matches[1] }
        if (-not [System.Environment]::GetEnvironmentVariable($key, 'Process')) {
          [System.Environment]::SetEnvironmentVariable($key, $val, 'Process')
        }
      }
    }
  }
}

$host_   = $env:AIVEN_MYSQL_HOST
$port_   = $env:AIVEN_MYSQL_PORT
$db_     = $env:AIVEN_MYSQL_DB
$user_   = $env:AIVEN_MYSQL_USER
$pass_   = $env:AIVEN_MYSQL_PASSWORD
$sslCa   = $env:AIVEN_MYSQL_SSL_CA

if (-not $host_ -or -not $db_ -or -not $user_) {
  $msg = "Missing required env (or .env): AIVEN_MYSQL_HOST, AIVEN_MYSQL_DB, AIVEN_MYSQL_USER. Optional: AIVEN_MYSQL_PORT, AIVEN_MYSQL_PASSWORD, AIVEN_MYSQL_SSL_CA"
  $logDir = Join-Path $ProjectRoot 'logs'
  if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
  $logFile = Join-Path $logDir 'verify_latest.md'
  "# Verify failed (config)\n\n$msg\n\nTime: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" | Set-Content -Path $logFile -Encoding UTF8
  Write-Error $msg
}

$port_ = if ($port_) { $port_ } else { '3306' }

$sqlDir = Join-Path $ProjectRoot 'sql\verify'
$logDir = Join-Path $ProjectRoot 'logs'
$logFile = Join-Path $logDir 'verify_latest.md'

if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }

$files = @()
if (Test-Path $sqlDir) {
  $files = Get-ChildItem -Path $sqlDir -Filter '*.sql' | Sort-Object Name
}

$sb = New-Object System.Text.StringBuilder
[void]$sb.AppendLine("# Verify result")
[void]$sb.AppendLine("")
[void]$sb.AppendLine("Time: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
[void]$sb.AppendLine("")

$anyFailure = $false

if ($files.Count -eq 0) {
  [void]$sb.AppendLine("No `sql/verify/*.sql` files found. Add .sql files to run.")
} else {
  foreach ($f in $files) {
    $pathForSource = $f.FullName -replace '\\', '/'
    $mysqlArgs = @(
      '-h', $host_,
      '-P', $port_,
      '-u', $user_,
      '--default-character-set=utf8mb4',
      $db_
    )
    if ($pass_ -ne $null -and $pass_ -ne '') {
      $mysqlArgs += @('-p' + $pass_)
    }
    if ($sslCa -and (Test-Path $sslCa)) {
      $mysqlArgs += @('--ssl-mode=VERIFY_CA', "--ssl-ca=$sslCa")
    }
    $mysqlArgs += @('-e', "source $pathForSource")

    [void]$sb.AppendLine("## $($f.Name)")
    [void]$sb.AppendLine("")

    try {
      $out = & mysql @mysqlArgs 2>&1
      $code = $LASTEXITCODE
      if ($code -ne 0) {
        $anyFailure = $true
        [void]$sb.AppendLine("**ERROR** (exit $code)")
        [void]$sb.AppendLine("")
        [void]$sb.AppendLine("```")
        [void]$sb.AppendLine(($out | Out-String).Trim())
        [void]$sb.AppendLine("```")
      } else {
        [void]$sb.AppendLine("```")
        [void]$sb.AppendLine(($out | Out-String).Trim())
        [void]$sb.AppendLine("```")
      }
    } catch {
      $anyFailure = $true
      [void]$sb.AppendLine("**ERROR** (exception)")
      [void]$sb.AppendLine("")
      [void]$sb.AppendLine("```")
      [void]$sb.AppendLine($_.Exception.Message)
      [void]$sb.AppendLine("```")
    }
    [void]$sb.AppendLine("")
  }
}

$content = $sb.ToString()
$content | Set-Content -Path $logFile -Encoding UTF8 -NoNewline
if ($content.Length -gt 0 -and -not $content.EndsWith("`n")) {
  Add-Content -Path $logFile -Value "" -Encoding UTF8
}

if ($anyFailure) {
  Write-Error "One or more sql/verify/*.sql runs failed. See logs/verify_latest.md"
}

Write-Host "Verify output written to logs/verify_latest.md"
