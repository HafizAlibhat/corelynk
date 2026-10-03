param(
    [string]$Message = ""
)

$ErrorActionPreference = "Stop"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$dateHuman = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

if ([string]::IsNullOrWhiteSpace($Message)) {
    $commitMsg = "checkpoint: snapshot at $dateHuman"
} else {
    $commitMsg = "checkpoint: $Message ($dateHuman)"
}

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " CoreLynk Auto-Checkpoint Backup Utility  " -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

# Stage application files
git add app/ tests/ public/assets/ composer.json composer.lock .gitignore AGENTS.md CLAUDE.md docs/ .agents/

# Check if there is anything to commit
$status = git status --porcelain app/ tests/ public/assets/ composer.json composer.lock .gitignore AGENTS.md CLAUDE.md docs/ .agents/
if ([string]::IsNullOrWhiteSpace($status)) {
    Write-Host "[OK] Working tree clean. No new changes to commit in core application files." -ForegroundColor Green
} else {
    Write-Host "[+] Committing staged changes..." -ForegroundColor Yellow
    git commit -m "$commitMsg"
    Write-Host "[OK] Commit created successfully." -ForegroundColor Green
}

# Create a local checkpoint tag
$tagName = "checkpoint-$timestamp"
git tag -a "$tagName" -m "Automated checkpoint created at $dateHuman"
Write-Host "[OK] Tag created: $tagName" -ForegroundColor Green

# Update backup branch
git branch -f backup/auto-latest HEAD
Write-Host "[OK] Backup branch updated: backup/auto-latest" -ForegroundColor Green

Write-Host ""
Write-Host "--------------------------------------------------------" -ForegroundColor Cyan
Write-Host "SAFETY GUARANTEE:" -ForegroundColor Yellow
Write-Host "Your code is now locked into Git history."
Write-Host "To view recent checkpoints:  git tag -l 'checkpoint-*'"
Write-Host "To restore this state anytime: git checkout $tagName"
Write-Host "--------------------------------------------------------" -ForegroundColor Cyan
