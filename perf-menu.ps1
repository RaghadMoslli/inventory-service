# ============================================================
# perf-menu.ps1 - Performance demo menu (k6 + Laravel perf commands)
# Run from the project root, in the same PowerShell window where
# the k6 environment variables were set: .\perf-menu-updated.ps1
# This script does NOT seed, delete, or reset database records.
# ============================================================

$ErrorActionPreference = "Continue"

# Use UTF-8 in this PowerShell session.
try {
    [Console]::InputEncoding  = [System.Text.UTF8Encoding]::new($false)
    [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
} catch {
    # Keep the menu usable if the host does not allow console encoding changes.
}
$App = "docker compose exec laravel-app php artisan"

# ---------- All available k6 scripts ----------
$Scripts = [ordered]@{
    "products-list"       = "perf/scripts/products-list.js"
    "products-filter"     = "perf/scripts/products-filter.js"
    "product-details"     = "perf/scripts/product-details.js"
    "orders-list"         = "perf/scripts/orders-list.js"
    "cache-warm"          = "perf/scripts/cache-warm.js"
    "cache-invalidation"  = "perf/scripts/cache-invalidation.js"
    "etag-304"            = "perf/scripts/etag-304.js"
    "read-your-writes"    = "perf/scripts/read-your-writes.js"
}

# Environment variables required by particular scripts.
$ScriptRequirements = @{
    "orders-list"        = @("USER_EMAIL", "USER_PASSWORD")
    "cache-invalidation" = @("ADMIN_EMAIL", "ADMIN_PASSWORD", "MUTATION_PRODUCT_ID")
    "read-your-writes"   = @("ADMIN_EMAIL", "ADMIN_PASSWORD", "MUTATION_PRODUCT_ID")
}

# Tests relevant to each feature. Each feature is enabled ALONE on baseline.
$FeatureK6 = [ordered]@{
    "indexes"       = @("products-list", "products-filter", "orders-list")
    "eager_loading" = @("orders-list")
    "cache"         = @("products-list", "products-filter", "cache-warm", "cache-invalidation")
    "etag"          = @("etag-304")
    "replica"       = @("orders-list", "read-your-writes")
}

New-Item -ItemType Directory -Force -Path "perf/results" | Out-Null

function Say($msg, $color = "Cyan") {
    Write-Host "`n>>> $msg" -ForegroundColor $color
}

function Run($cmd) {
    Write-Host "    $cmd" -ForegroundColor DarkGray

    # Execute directly so k6 can refresh its live progress on the same line.
    $script:LastRunExitCode = 0
    Invoke-Expression $cmd
    $code = $LASTEXITCODE

    if ($null -ne $code -and $code -ne 0) {
        $script:LastRunExitCode = $code
        Write-Host "    [!] Command failed with exit code $code" -ForegroundColor Yellow
    }
}

function Test-ScriptRequirements($name) {
    if (-not $ScriptRequirements.ContainsKey($name)) { return $true }
    $missing = @()
    foreach ($varName in $ScriptRequirements[$name]) {
        $value = [Environment]::GetEnvironmentVariable($varName)
        if ([string]::IsNullOrWhiteSpace($value)) { $missing += $varName }
    }
    if ($missing.Count -gt 0) {
        Write-Host "    [!] Skipping $name. Missing environment variables: $($missing -join ', ')" -ForegroundColor Yellow
        return $false
    }
    return $true
}

function Run-K6($prefix, $names) {
    foreach ($n in $names) {
        $scriptPath = $Scripts[$n]
        if (-not (Test-Path $scriptPath)) {
            Write-Host "    [!] Script not found: $scriptPath (skipped)" -ForegroundColor Yellow
            continue
        }
        if (-not (Test-ScriptRequirements $n)) { continue }

        Say "k6: $prefix / $n"
        Run "k6 run --summary-export perf/results/$prefix-$n.json $scriptPath"
    }
}

function Pause-Menu {
    Read-Host "`nPress Enter to go back to the menu" | Out-Null
}

# ---------------- Main steps ----------------

function Step-Baseline {
    Say "Setting Baseline mode"
    Run "$App perf:mode baseline"
    Run "$App perf:mode status"
}

function Step-K6Baseline {
    Run-K6 "baseline" @("products-list", "products-filter", "product-details", "orders-list")
}

function Run-Feature($f) {
    Say "FEATURE: $f (enabled alone on top of baseline)" "Green"
    Write-Host "    k6 tests: $($FeatureK6[$f] -join ', ')"

    if ($f -in @("cache", "replica")) {
        Write-Host "    IMPORTANT: mutation tests use a dedicated test product; do not use a real product." -ForegroundColor Yellow
        if ($f -eq "cache") {
            Write-Host "    For cache-invalidation, MUTATION_PRODUCT_ID must be among the first 100 products." -ForegroundColor Yellow
        }
    }

    Run "$App perf:mode baseline"
    Run "$App perf:mode baseline --enable=$f"
    if ($script:LastRunExitCode -ne 0) { return }
    Run "$App perf:mode status"

    if ($f -eq "cache") {
        Write-Host "    Note: perf:cache-stats --reset resets counters, not cached values. cache-warm checks repeated-request behavior; it does not guarantee a cold-cache first request." -ForegroundColor Yellow
        Run "$App perf:cache-stats --reset"
    }

    Run-K6 "flag-$f" $FeatureK6[$f]

    if ($f -eq "cache") {
        Say "Cache statistics after cache tests"
        Run "$App perf:cache-stats"
    }

    if ($f -eq "indexes") {
        Say "Query plan diagnostics (not a separate functional test)"
        Run "$App perf:explain --runs=3"
    }

    if ($f -eq "eager_loading") {
        Write-Host "    Check app_query_count and app_query_time_ms in the orders-list k6 summary." -ForegroundColor Cyan
    }
}

function Step-Feature {
    $flags = @($FeatureK6.Keys)
    Write-Host ""
    for ($i = 0; $i -lt $flags.Count; $i++) {
        $f = $flags[$i]
        Write-Host ("  [{0}] {1,-14} k6: {2}" -f ($i + 1), $f, ($FeatureK6[$f] -join ", "))
    }
    Write-Host "  [A] all features, one after another"
    Write-Host "  [B] back"
    $c = Read-Host "Choose"

    if ($c -match '^[Bb]$') { return }
    if ($c -match '^[Aa]$') { $sel = $flags }
    elseif ($c -match '^\d+$' -and [int]$c -ge 1 -and [int]$c -le $flags.Count) { $sel = @($flags[[int]$c - 1]) }
    else { Write-Host "Invalid choice." -ForegroundColor Yellow; return }

    foreach ($f in $sel) {
        Run-Feature $f
        if ($sel.Count -gt 1) { Read-Host "`nPress Enter for the next feature" | Out-Null }
    }

    Say "Returning to clean Baseline"
    Run "$App perf:mode baseline"
}

function Step-Optimized {
    Say "Setting Optimized mode"
    Run "$App perf:mode optimized"
    Run "$App perf:mode status"
}

function Step-K6Optimized {
    Run-K6 "optimized" @("products-list", "products-filter", "product-details", "orders-list")
}

function Step-Helpers {
    Say "Cache stats + query plan diagnostics"
    Run "$App perf:cache-stats"
    Run "$App perf:explain"
}

function Step-DockerStats {
    Say "Opening docker stats in a new PowerShell window"
    Start-Process powershell -ArgumentList "-NoExit", "-Command", "docker stats"
    Write-Host "Keep that window open while a k6 test is running to observe live resource usage." -ForegroundColor Cyan
}

function Step-All {
    Write-Host "This flow includes cache/replica mutation tests. Use a dedicated test product only." -ForegroundColor Yellow
    $confirm = Read-Host "Continue with the full flow? (Y/N)"
    if ($confirm -notmatch '^[Yy]$') { return }

    Say "FULL FLOW: baseline > k6 > isolated features > optimized > k6" "Green"
    Step-Baseline
    Step-K6Baseline
    foreach ($f in $FeatureK6.Keys) { Run-Feature $f }
    Run "$App perf:mode baseline"
    Step-Optimized
    Step-K6Optimized
    Say "Done. Results are in perf/results/" "Green"
}

# ---------------- Interactive menu ----------------

while ($true) {
    Write-Host ""
    Write-Host "================ PERF DEMO MENU ================" -ForegroundColor Magenta
    Write-Host " 1  Baseline mode + status"
    Write-Host " 2  k6 tests - Baseline (4 tests)"
    Write-Host " 3  Isolated feature tests"
    Write-Host " 4  Optimized mode + status"
    Write-Host " 5  k6 tests - Optimized (4 tests)"
    Write-Host " 6  Helpers: cache-stats + explain"
    Write-Host " 7  docker stats (opens a new window)"
    Write-Host " 8  Run EVERYTHING in order (includes mutation tests)"
    Write-Host " 0  Exit"
    Write-Host "================================================" -ForegroundColor Magenta

    switch (Read-Host "Choose") {
        "1" { Step-Baseline;    Pause-Menu }
        "2" { Step-K6Baseline;  Pause-Menu }
        "3" { Step-Feature;     Pause-Menu }
        "4" { Step-Optimized;   Pause-Menu }
        "5" { Step-K6Optimized; Pause-Menu }
        "6" { Step-Helpers;     Pause-Menu }
        "7" { Step-DockerStats }
        "8" { Step-All;         Pause-Menu }
        "0" { return }
        default { Write-Host "Invalid choice." -ForegroundColor Yellow }
    }
}
