# =========================================================
# GILIME RUN VERIFY (Operational Mode v3 + Log Save)
# =========================================================

$logPath = "logs/verify_latest.md"

# 로그 폴더 없으면 생성
if (!(Test-Path "logs")) {
    New-Item -ItemType Directory -Path "logs" | Out-Null
}

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$log = @()
$log += "# GILIME VERIFY LOG"
$log += ""
$log += "Timestamp: $timestamp"
$log += ""

Write-Host "=== GILIME VERIFY START ==="

$errors = 0

# 1. Secret scan
Write-Host "`n[1] Checking for suspicious keys..."
$secretCheck = git grep -n "sk-" -- . 2>$null | Where-Object {
    $_ -notmatch "\.example" -and
    $_ -notmatch "\.md" -and
    $_ -notmatch "run_verify.ps1"
}

if ($secretCheck) {
    Write-Host "❌ Real API key detected!"
    $log += "❌ Real API key detected"
    $errors++
} else {
    Write-Host "✔ No real API keys found"
    $log += "✔ No real API keys found"
}

# 2. Local config tracking
Write-Host "`n[2] Checking local config tracking..."
$configTracked = git ls-files | Where-Object {
    $_ -match "app/inc/config/config.local.php$"
}

if ($configTracked) {
    Write-Host "❌ config.local.php is tracked!"
    $log += "❌ config.local.php tracked"
    $errors++
} else {
    Write-Host "✔ config.local.php not tracked"
    $log += "✔ config.local.php not tracked"
}

# 3. Upload tracking
Write-Host "`n[3] Checking upload tracking..."
$uploadTracked = git ls-files | Where-Object {
    $_ -match "^public/uploads/"
}

if ($uploadTracked) {
    Write-Host "❌ Upload files tracked!"
    $log += "❌ Upload files tracked"
    $errors++
} else {
    Write-Host "✔ Upload folder safe"
    $log += "✔ Upload folder safe"
}

# Final result
$log += ""
$log += "## Result"

Write-Host "`n=== VERIFY RESULT ==="

if ($errors -eq 0) {
    Write-Host "✔ VERIFY PASSED"
    $log += "✔ VERIFY PASSED"
    $log | Out-File -Encoding UTF8 $logPath
    exit 0
} else {
    Write-Host "❌ VERIFY FAILED ($errors errors)"
    $log += "❌ VERIFY FAILED ($errors errors)"
    $log | Out-File -Encoding UTF8 $logPath
    exit 1
}