# Update the job website on this PC:
#   1. get the latest code from GitHub
#   2. rebuild/restart the Docker containers (database is kept)
#   3. fetch new jobs now, and add jobs from import files (app\imports)
#   4. show how many jobs are on the site, then open it
# Run from PowerShell in this folder:   .\update.ps1
# (first time only, if Windows blocks scripts:  Set-ExecutionPolicy -Scope CurrentUser RemoteSigned)

param([switch]$AfterPull)

# Don't stop on warnings that Docker/git print to the error stream; each step checks its own exit code.
$ErrorActionPreference = "Continue"
Set-Location -Path $PSScriptRoot

function Step($text) { Write-Host "`n=== $text ===" -ForegroundColor Cyan }
function Fail($text) { Write-Host $text -ForegroundColor Red; exit 1 }

if (-not $AfterPull) {
    Step "Checking Docker"
    docker info > $null 2>&1
    if ($LASTEXITCODE -ne 0) { Fail "Docker is not running. Open Docker Desktop, wait for 'Engine running', then run .\update.ps1 again." }

    Step "Getting latest code from GitHub"
    $changes = git status --porcelain --untracked-files=no
    if ($changes) {
        Write-Host "You have local changes that are not committed:" -ForegroundColor Yellow
        git status --short
        Fail "Commit them first (git add . ; git commit -m ""message"" ; git push), then run .\update.ps1 again."
    }
    $before = (Get-FileHash -Path $PSCommandPath).Hash
    git pull
    if ($LASTEXITCODE -ne 0) { Fail "git pull failed (see message above)." }
    git log --oneline -1

    # If this script itself was updated by the pull, run the NEW version for the remaining steps.
    if ((Get-FileHash -Path $PSCommandPath).Hash -ne $before) {
        Write-Host "update.ps1 was updated - continuing with the new version..." -ForegroundColor Yellow
        & $PSCommandPath -AfterPull
        exit $LASTEXITCODE
    }
}

Step "Restarting containers"
docker compose up -d --build --force-recreate
if ($LASTEXITCODE -ne 0) { Fail "Docker failed to start (see message above)." }
docker compose ps

Step "Waiting for the database"
for ($i = 0; $i -lt 30; $i++) {
    docker compose exec -T db pg_isready -q > $null 2>&1
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 2
}

Step "Adding jobs from import files (app\imports)"
docker compose exec -T web php bin/import.php
if ($LASTEXITCODE -ne 0) { Write-Host "Import failed (see message above)." -ForegroundColor Red }

Step "Fetching new jobs from websites"
docker compose exec -T web php bin/fetch.php

Step "Jobs on the website now"
docker compose exec -T web php bin/stats.php

$port = (Select-String -Path .env -Pattern '^APP_PORT=(\d+)' -ErrorAction SilentlyContinue).Matches.Groups[1].Value
if (-not $port) { $port = "3030" }
Step "Done - opening http://localhost:$port"
Start-Process "http://localhost:$port"
