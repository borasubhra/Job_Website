# Update the job website on this PC:
#   1. get the latest code from GitHub
#   2. rebuild/restart the Docker containers (database is kept)
#   3. fetch new jobs now
#   4. open the website
# Run from PowerShell in this folder:   .\update.ps1
# (first time only, if Windows blocks scripts:  Set-ExecutionPolicy -Scope CurrentUser RemoteSigned)

$ErrorActionPreference = "Stop"
Set-Location -Path $PSScriptRoot

function Step($text) { Write-Host "`n=== $text ===" -ForegroundColor Cyan }

Step "Checking Docker"
docker info *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker is not running. Open Docker Desktop, wait for 'Engine running', then run .\update.ps1 again." -ForegroundColor Red
    exit 1
}

Step "Getting latest code from GitHub"
$changes = git status --porcelain --untracked-files=no
if ($changes) {
    Write-Host "You have local changes that are not committed:" -ForegroundColor Yellow
    git status --short
    Write-Host "Commit them first (git add . ; git commit -m ""message"" ; git push), then run .\update.ps1 again." -ForegroundColor Yellow
    exit 1
}
git pull
if ($LASTEXITCODE -ne 0) { Write-Host "git pull failed (see message above)." -ForegroundColor Red; exit 1 }

Step "Restarting containers"
docker compose up -d --build
if ($LASTEXITCODE -ne 0) { Write-Host "Docker failed to start (see message above)." -ForegroundColor Red; exit 1 }
docker compose ps

Step "Fetching new jobs"
Start-Sleep -Seconds 5
docker compose exec -T web php bin/fetch.php

$port = (Select-String -Path .env -Pattern '^APP_PORT=(\d+)' -ErrorAction SilentlyContinue).Matches.Groups[1].Value
if (-not $port) { $port = "3030" }
Step "Done - opening http://localhost:$port"
Start-Process "http://localhost:$port"
