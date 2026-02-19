# 길찾기 관련 스크립트 실행 (PHP PATH 미설정 시 사용)
# PowerShell: .\scripts\run_route_scripts.ps1 [check|sample|diagnose|import]

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path $PSScriptRoot -Parent

# PHP 경로 (XAMPP 기본)
$phpPaths = @(
  'C:\xampp\php\php.exe',
  'C:\laragon\bin\php\php-8*\php.exe',
  (Get-Command php -ErrorAction SilentlyContinue).Source
)
$phpExe = $null
foreach ($p in $phpPaths) {
  if ($p -match '\*') {
    $found = Get-Item $p -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($found) { $phpExe = $found.FullName; break }
  } elseif (Test-Path $p) {
    $phpExe = $p
    break
  }
}
if (-not $phpExe) {
  Write-Error "PHP를 찾을 수 없습니다. PATH에 추가하거나 C:\xampp\php\php.exe 설치를 확인하세요."
}

$scriptDir = Join-Path $ProjectRoot 'scripts'

function Run-Script {
  param([string]$Name, [string[]]$ScriptArgs = @())
  $scriptPath = Join-Path $scriptDir $Name
  Write-Host "`n=== $Name ===" -ForegroundColor Cyan
  & $phpExe $scriptPath @ScriptArgs
}

$cmd = $args[0]
switch ($cmd) {
  'check' {
    Run-Script 'check_route_public_data_counts.php'
  }
  'sample' {
    Run-Script 'list_route_finder_sample_stops.php'
  }
  'diagnose' {
    $from = $args[1]
    $to   = $args[2]
    if (-not $from -or -not $to) {
      Write-Host "Usage: .\scripts\run_route_scripts.ps1 diagnose `"from_stop`" `"to_stop`""
      exit 1
    }
    Run-Script 'diagnose_route_finder.php' @($from, $to)
  }
  'import' {
    Write-Host "`n=== 데이터 import (CSV 필요) ===" -ForegroundColor Yellow
    Run-Script 'import_seoul_bus_stop_master_full.php'
    Run-Script 'import_seoul_bus_route_master_full.php'
    Run-Script 'import_seoul_bus_route_stop_master_full.php'
  }
  default {
    Write-Host @"
Usage: .\scripts\run_route_scripts.ps1 <cmd> [args...]

  check   - table counts
  sample  - sample stops
  diagnose - diagnose from->to (args: from stop, to stop)
  import  - CSV import (stop, route, route_stop)

Examples:
  .\scripts\run_route_scripts.ps1 check
  .\scripts\run_route_scripts.ps1 sample
  .\scripts\run_route_scripts.ps1 diagnose "from_stop" "to_stop"
  .\scripts\run_route_scripts.ps1 import
"@
  }
}
