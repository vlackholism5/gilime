# PHP PATH 설정 (현재 터미널 세션에만 적용)
# 사용: . .\scripts\phpsetup.ps1
$env:Path = "C:\xampp\php;" + $env:Path
Write-Host "PHP added to PATH for this session."
php -v
