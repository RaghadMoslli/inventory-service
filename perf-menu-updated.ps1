# ============================================================

$ErrorActionPreference = "Continue"

$env:Path = [System.Environment]::GetEnvironmentVariable('Path','Machine') + ';' +
            [System.Environment]::GetEnvironmentVariable('Path','User')

try {
    [Console]::InputEncoding  = [System.Text.UTF8Encoding]::new($false)
    [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
} catch { }

$K6 = $null
foreach ($candidate in @(
    (Get-Command k6 -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source),
    "C:\Program Files\k6\k6.exe",
    "$env:LOCALAPPDATA\Programs\k6\k6.exe"
)) {
    if ($candidate -and (Test-Path $candidate)) { $K6 = $candidate; break }
}
if (-not $K6) {
    Write-Host "ERROR: k6.exe not found. Open a NEW PowerShell and run: k6 version" -ForegroundColor Red
    Write-Host "Or reinstall: winget install --id GrafanaLabs.k6 -e --source winget" -ForegroundColor Yellow
    return
}
Write-Host "Using k6: $K6" -ForegroundColor DarkGray

$App = "docker compose exec laravel-app php artisan"

$BaselineMode  = { param($f) "$App perf:mode baseline" }
$OptimizedMode = {
    param($f)
    if ($f -eq "all") { "$App perf:mode optimized" }
    else              { "$App perf:mode baseline --enable=$f" }
}

$Defaults = [ordered]@{
    "USER_EMAIL"          = "customer@test.com"
    "USER_PASSWORD"       = "password123"
    "ADMIN_EMAIL"         = "admin@test.com"
    "ADMIN_PASSWORD"      = "password123"
    "MUTATION_PRODUCT_ID" = "1"
}
foreach ($k in $Defaults.Keys) {
    if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($k))) {
        [Environment]::SetEnvironmentVariable($k, $Defaults[$k], "Process")
    }
}

$Scripts = [ordered]@{
    "products-list"       = "perf/scripts/products-list.js"
    "products-filter"     = "perf/scripts/products-filter.js"
    "product-details"     = "perf/scripts/product-details.js"
    "orders-list"         = "perf/scripts/orders-list.js"
    "orders-eager"        = "perf/scripts/orders-eager.js"
    "cache-warm"          = "perf/scripts/cache-warm.js"
    "cache-invalidation"  = "perf/scripts/cache-invalidation.js"
    "etag-304"            = "perf/scripts/etag-304.js"
    "read-your-writes"    = "perf/scripts/read-your-writes.js"
}

$ScriptRequirements = @{
    "orders-list"        = @("USER_EMAIL", "USER_PASSWORD")
    "orders-eager"       = @("ADMIN_EMAIL", "ADMIN_PASSWORD")
    "cache-invalidation" = @("ADMIN_EMAIL", "ADMIN_PASSWORD", "MUTATION_PRODUCT_ID")
    "read-your-writes"   = @("ADMIN_EMAIL", "ADMIN_PASSWORD", "MUTATION_PRODUCT_ID")
}

$FeatureK6 = [ordered]@{
    "indexes"       = @("products-filter", "orders-list")      # category index + orders user/created_at
    "eager_loading" = @("orders-eager")                        # N+1 vs with(items.product) on GET /orders
    "cache"         = @("cache-warm", "cache-invalidation")    # ProductCacheService
    "etag"          = @("etag-304")                            # ETagMiddleware on products GET
    "replica"       = @("read-your-writes")                    # ReadYourWritesMiddleware
}

$MaxVus = 70
$env:MAX_VUS = "$MaxVus"
Write-Host "MAX_VUS fixed at $MaxVus (edit `$MaxVus in this file to change)." -ForegroundColor DarkGray

New-Item -ItemType Directory -Force -Path "perf/results" | Out-Null

function Say($msg, $color = "Cyan") { Write-Host "`n>>> $msg" -ForegroundColor $color }

function Run($cmd) {
    Write-Host "    $cmd" -ForegroundColor DarkGray
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
        if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($varName))) { $missing += $varName }
    }
    if ($missing.Count -gt 0) {
        Write-Host "    [!] Cannot run $name. Missing environment variables: $($missing -join ', ')" -ForegroundColor Yellow
        return $false
    }
    return $true
}

function Get-Stat($json, $metric, $stat) {
    $m = $json.metrics.PSObject.Properties[$metric]
    if ($null -eq $m) { return $null }
    $p = $m.Value.PSObject.Properties[$stat]
    if ($null -eq $p) { return $null }
    return [double]$p.Value
}

function Get-FailRate($json) {
    $v = Get-Stat $json "http_req_failed" "value"
    if ($null -eq $v) { $v = Get-Stat $json "http_req_failed" "rate" }
    return $v
}

function Show-Comparison($baseFile, $optFile) {
    if (-not (Test-Path $baseFile) -or -not (Test-Path $optFile)) {
        Write-Host "    [!] A result file is missing, cannot compare." -ForegroundColor Yellow
        return
    }
    $b = Get-Content $baseFile -Raw | ConvertFrom-Json
    $o = Get-Content $optFile  -Raw | ConvertFrom-Json

    $rows = @(
        @("Response time avg",   "http_req_duration", "avg",    $false, "ms"),
        @("Response time med",   "http_req_duration", "med",    $false, "ms"),
        @("Response time p(95)", "http_req_duration", "p(95)",  $false, "ms"),
        @("Response time p(99)", "http_req_duration", "p(99)",  $false, "ms"),
        @("Response time max",   "http_req_duration", "max",    $false, "ms"),
        @("Requests / sec",      "http_reqs",         "rate",   $true,  "/s"),
        @("Total requests",      "http_reqs",         "count",  $true,  ""),
        @("DB queries / req",    "app_query_count",   "avg",    $false, ""),
        @("DB query time avg",   "app_query_time_ms", "avg",    $false, "ms")
    )

    Write-Host ""
    Write-Host ("  {0,-22} {1,14} {2,14} {3,10}" -f "Metric", "Baseline", "Optimized", "Change") -ForegroundColor Magenta
    Write-Host ("  " + ("-" * 63)) -ForegroundColor Magenta

    foreach ($r in $rows) {
        $bv = Get-Stat $b $r[1] $r[2]
        $ov = Get-Stat $o $r[1] $r[2]
        if ($null -eq $bv -or $null -eq $ov) { continue }
        $change = ""; $color = "Gray"
        if ($bv -ne 0) {
            $pct = ($ov - $bv) / $bv * 100
            $change = "{0:+0.0;-0.0;0.0}%" -f $pct
            if ([math]::Abs($pct) -ge 1) {
                $good = (($pct -gt 0) -eq $r[3])
                if ($good) { $color = "Green" } else { $color = "Red" }
            }
        }
        Write-Host ("  {0,-22} {1,14} {2,14} {3,10}" -f $r[0], (("{0:N2}" -f $bv) + $r[4]), (("{0:N2}" -f $ov) + $r[4]), $change) -ForegroundColor $color
    }

    $bf = Get-FailRate $b; $of = Get-FailRate $o
    if ($null -ne $bf -and $null -ne $of) {
        Write-Host ("  {0,-22} {1,14} {2,14}" -f "Failed requests", ("{0:P2}" -f $bf), ("{0:P2}" -f $of))
    }
}

function Run-BeforeAfter($feature, $test) {
    $scriptPath = $Scripts[$test]
    if (-not (Test-Path $scriptPath)) {
        Write-Host "    [!] Script not found: $scriptPath" -ForegroundColor Yellow
        return
    }
    if (-not (Test-ScriptRequirements $test)) { return }

    if ($test -in @("cache-invalidation", "read-your-writes")) {
        Write-Host "    IMPORTANT: this test mutates data. MUTATION_PRODUCT_ID must be a dedicated test product." -ForegroundColor Yellow
    }
    if ($test -eq "cache-warm") {
        Write-Host "    Tip: watch app_query_time_ms / cache-stats; HTTP time may stay close because stock still hits gRPC." -ForegroundColor Yellow
    }
    if ($test -eq "etag-304") {
        Write-Host "    Success means second request returns 304 (not a big p95 drop)." -ForegroundColor Yellow
    }
    if ($test -eq "orders-eager" -and $feature -eq "eager_loading") {
        Write-Host "    Success = app_query_count drops a lot (N+1 -> few queries). Ignore HTTP if close." -ForegroundColor Yellow
    }

    $baseFile = "perf/results/$feature-baseline-$test.json"
    $optFile  = "perf/results/$feature-optimized-$test.json"

    Say "[1/2] BASELINE - $feature / $test (MAX_VUS=$env:MAX_VUS)" "Green"
    Run (& $BaselineMode $feature)
    if ($script:LastRunExitCode -ne 0) { return }
    if ($feature -eq "cache" -or $test -eq "cache-warm") {
        Run "$App perf:cache-stats --reset"
    }
    Run "& `"$K6`" run --summary-export `"$baseFile`" `"$scriptPath`""

    if ($feature -eq "all") {
        Say "[2/2] OPTIMIZED - ALL flags ON / $test (MAX_VUS=$env:MAX_VUS)" "Green"
    } else {
        Say "[2/2] OPTIMIZED - ONLY '$feature' ON (others OFF) / $test (MAX_VUS=$env:MAX_VUS)" "Green"
    }
    Run (& $OptimizedMode $feature)
    if ($script:LastRunExitCode -ne 0) { return }
    if ($feature -eq "cache" -or $test -eq "cache-warm") {
        Run "$App perf:cache-stats --reset"
    }
    Run "& `"$K6`" run --summary-export `"$optFile`" `"$scriptPath`""

    Say "RESULT: $feature / $test (Baseline vs Optimized)" "Green"
    Show-Comparison $baseFile $optFile

    if ($feature -eq "indexes") {
        Say "Index plans (EXPLAIN)"
        Run "$App perf:explain --runs=3"
    }
    if ($feature -eq "cache" -or $test -eq "cache-warm") {
        Say "Cache statistics after optimized run"
        Run "$App perf:cache-stats"
    }

    Run "$App perf:mode baseline"
}

function Read-Choice($prompt, $max) {
    while ($true) {
        $c = Read-Host $prompt
        if ($c -match '^0$') { return 0 }
        if ($c -match '^\d+$' -and [int]$c -ge 1 -and [int]$c -le $max) { return [int]$c }
        Write-Host "Invalid choice." -ForegroundColor Yellow
    }
}

while ($true) {
    $flags = @($FeatureK6.Keys)

    Write-Host ""
    Write-Host "================ SELECT FEATURE ================" -ForegroundColor Magenta
    Write-Host " Single feature = that flag ONLY ON (others stay OFF)" -ForegroundColor DarkGray
    for ($i = 0; $i -lt $flags.Count; $i++) {
        Write-Host (" {0}  {1,-14}  tests: {2}" -f ($i + 1), $flags[$i], ($FeatureK6[$flags[$i]] -join ", "))
    }
    $allOption = $flags.Count + 1
    Write-Host (" {0}  ALL features together (OFF vs everything ON)" -f $allOption)
    Write-Host " 0  Exit"
    Write-Host "================================================" -ForegroundColor Magenta

    $fi = Read-Choice "Feature number" $allOption
    if ($fi -eq 0) { return }

    if ($fi -eq $allOption) {
        $feature = "all"
        $tests = @("products-filter", "orders-list", "product-details", "cache-warm", "etag-304")
        Write-Host "    NOTE: ALL mode turns EVERY flag ON in the second run." -ForegroundColor Yellow
    } else {
        $feature = $flags[$fi - 1]
        $tests = @($FeatureK6[$feature])
        Write-Host "    NOTE: second run enables ONLY '$feature' (baseline --enable=$feature)." -ForegroundColor Green
    }

    Write-Host ""
    Write-Host "---- Tests for '$feature' ----" -ForegroundColor Magenta
    for ($i = 0; $i -lt $tests.Count; $i++) { Write-Host (" {0}  {1}" -f ($i + 1), $tests[$i]) }
    Write-Host " 0  Back"

    $ti = Read-Choice "Test number" $tests.Count
    if ($ti -eq 0) { continue }

    Run-BeforeAfter $feature $tests[$ti - 1]
    Read-Host "`nPress Enter to go back to the menu" | Out-Null
}
