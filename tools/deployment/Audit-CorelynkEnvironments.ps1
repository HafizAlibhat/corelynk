param(
    [string]$ProdRoot = 'C:\xampp\htdocs\corelynk',
    [string]$DevRoot = 'C:\xampp\htdocs\corelynk_dev',
    [string]$ProdDb = 'corelynk_db',
    [string]$DevDb = 'corelynk_db_dev',
    [string]$MySqlExe = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$MySqlUser = 'root',
    [string]$MySqlPassword = '',
    [string]$OutputDir = 'C:\xampp\htdocs\corelynk\docs\generated-audits'
)

$ErrorActionPreference = 'Stop'

function Ensure-Path {
    param([string]$Path)
    if (-not (Test-Path $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Invoke-MySqlText {
    param([string]$Query)

    $args = @('-u', $MySqlUser, '-N', '-B', '-e', $Query)
    if ($MySqlPassword -ne '') {
        $args = @("-p$MySqlPassword") + $args
    }

    & $MySqlExe @args
}

function Get-ComparableFiles {
    param([string]$Root)

    $excludedPrefixes = @(
        'vendor',
        'writable',
        '.git',
        'node_modules',
        'testing',
        'tmp',
        'archives',
        'public\uploads'
    )

    Get-ChildItem -Path $Root -Recurse -File |
        Where-Object {
            $relative = $_.FullName.Substring($Root.Length).TrimStart('\')
            -not ($excludedPrefixes | Where-Object { $relative -eq $_ -or $relative -like ("$_\*") })
        } |
        ForEach-Object {
            [PSCustomObject]@{
                RelativePath = $_.FullName.Substring($Root.Length).TrimStart('\')
                Hash = (Get-FileHash -Path $_.FullName -Algorithm SHA256).Hash
            }
        }
}

if (-not (Test-Path $ProdRoot)) { throw "Production root not found: $ProdRoot" }
if (-not (Test-Path $DevRoot)) { throw "Development root not found: $DevRoot" }
if (-not (Test-Path $MySqlExe)) { throw "MySQL executable not found: $MySqlExe" }

Ensure-Path -Path $OutputDir

$timestamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$reportPath = Join-Path $OutputDir "dev-prod-audit_$timestamp.md"

$prodFiles = Get-ComparableFiles -Root $ProdRoot
$devFiles = Get-ComparableFiles -Root $DevRoot

$prodIndex = @{}
$devIndex = @{}

foreach ($file in $prodFiles) { $prodIndex[$file.RelativePath] = $file.Hash }
foreach ($file in $devFiles) { $devIndex[$file.RelativePath] = $file.Hash }

$onlyProd = @($prodIndex.Keys | Where-Object { -not $devIndex.ContainsKey($_) } | Sort-Object)
$onlyDev = @($devIndex.Keys | Where-Object { -not $prodIndex.ContainsKey($_) } | Sort-Object)
$changed = @($prodIndex.Keys | Where-Object { $devIndex.ContainsKey($_) -and $prodIndex[$_] -ne $devIndex[$_] } | Sort-Object)

$tablePresenceQuery = @"
SELECT table_name, 'only_in_prod' AS diff_type
FROM information_schema.tables
WHERE table_schema='$ProdDb'
AND table_name NOT IN (
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema='$DevDb'
)
UNION ALL
SELECT table_name, 'only_in_dev' AS diff_type
FROM information_schema.tables
WHERE table_schema='$DevDb'
AND table_name NOT IN (
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema='$ProdDb'
)
ORDER BY diff_type, table_name;
"@

$schemaSummaryQuery = @"
SELECT diff_type, COUNT(*) AS cnt
FROM (
    SELECT p.table_name, p.column_name, 'only_in_prod' AS diff_type
    FROM information_schema.columns p
    LEFT JOIN information_schema.columns d
      ON d.table_schema='$DevDb'
     AND d.table_name=p.table_name
     AND d.column_name=p.column_name
    WHERE p.table_schema='$ProdDb'
      AND d.column_name IS NULL

    UNION ALL

    SELECT d.table_name, d.column_name, 'only_in_dev' AS diff_type
    FROM information_schema.columns d
    LEFT JOIN information_schema.columns p
      ON p.table_schema='$ProdDb'
     AND p.table_name=d.table_name
     AND p.column_name=d.column_name
    WHERE d.table_schema='$DevDb'
      AND p.column_name IS NULL

    UNION ALL

    SELECT p.table_name, p.column_name, 'definition_diff' AS diff_type
    FROM information_schema.columns p
    INNER JOIN information_schema.columns d
      ON d.table_schema='$DevDb'
     AND d.table_name=p.table_name
     AND d.column_name=p.column_name
    WHERE p.table_schema='$ProdDb'
      AND CONCAT_WS('|', p.column_type, p.is_nullable, COALESCE(p.column_default, '<NULL>'), p.extra)
       <> CONCAT_WS('|', d.column_type, d.is_nullable, COALESCE(d.column_default, '<NULL>'), d.extra)
) x
GROUP BY diff_type
ORDER BY diff_type;
"@

$keyCountsQuery = @"
SELECT 'prod' AS env, 'customers' AS table_name, COUNT(*) AS cnt FROM $ProdDb.customers
UNION ALL SELECT 'dev', 'customers', COUNT(*) FROM $DevDb.customers
UNION ALL SELECT 'prod', 'quotations', COUNT(*) FROM $ProdDb.quotations
UNION ALL SELECT 'dev', 'quotations', COUNT(*) FROM $DevDb.quotations
UNION ALL SELECT 'prod', 'sales_orders', COUNT(*) FROM $ProdDb.sales_orders
UNION ALL SELECT 'dev', 'sales_orders', COUNT(*) FROM $DevDb.sales_orders
UNION ALL SELECT 'prod', 'purchase_orders', COUNT(*) FROM $ProdDb.purchase_orders
UNION ALL SELECT 'dev', 'purchase_orders', COUNT(*) FROM $DevDb.purchase_orders
UNION ALL SELECT 'prod', 'customs_invoices', COUNT(*) FROM $ProdDb.customs_invoices
UNION ALL SELECT 'dev', 'customs_invoices', COUNT(*) FROM $DevDb.customs_invoices
UNION ALL SELECT 'prod', 'customs_invoice_items', COUNT(*) FROM $ProdDb.customs_invoice_items
UNION ALL SELECT 'dev', 'customs_invoice_items', COUNT(*) FROM $DevDb.customs_invoice_items
UNION ALL SELECT 'prod', 'products', COUNT(*) FROM $ProdDb.products
UNION ALL SELECT 'dev', 'products', COUNT(*) FROM $DevDb.products
UNION ALL SELECT 'prod', 'product_variants', COUNT(*) FROM $ProdDb.product_variants
UNION ALL SELECT 'dev', 'product_variants', COUNT(*) FROM $DevDb.product_variants
UNION ALL SELECT 'prod', 'vendors', COUNT(*) FROM $ProdDb.vendors
UNION ALL SELECT 'dev', 'vendors', COUNT(*) FROM $DevDb.vendors
ORDER BY table_name, env;
"@

$tablePresence = Invoke-MySqlText -Query $tablePresenceQuery
$schemaSummary = Invoke-MySqlText -Query $schemaSummaryQuery
$keyCounts = Invoke-MySqlText -Query $keyCountsQuery

$report = @()
$report += '# Automated Dev vs Production Audit'
$report += ''
$report += "Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$report += ''
$report += '## Inputs'
$report += ''
$report += ('- Production root: `{0}`' -f $ProdRoot)
$report += ('- Development root: `{0}`' -f $DevRoot)
$report += ('- Production DB: `{0}`' -f $ProdDb)
$report += ('- Development DB: `{0}`' -f $DevDb)
$report += ''
$report += '## File Comparison'
$report += ''
$report += "- Changed files: $($changed.Count)"
$report += "- Only in production: $($onlyProd.Count)"
$report += "- Only in development: $($onlyDev.Count)"
$report += ''
$report += '### Changed Files'
$report += ''
if ($changed.Count -eq 0) {
    $report += '- None'
} else {
    $report += $changed | ForEach-Object { ('- `{0}`' -f $_) }
}
$report += ''
$report += '### Production-Only Files'
$report += ''
if ($onlyProd.Count -eq 0) {
    $report += '- None'
} else {
    $report += $onlyProd | ForEach-Object { ('- `{0}`' -f $_) }
}
$report += ''
$report += '### Development-Only Files'
$report += ''
if ($onlyDev.Count -eq 0) {
    $report += '- None'
} else {
    $report += $onlyDev | ForEach-Object { ('- `{0}`' -f $_) }
}
$report += ''
$report += '## Database Table Presence Drift'
$report += ''
if (-not $tablePresence) {
    $report += '- None'
} else {
    $report += $tablePresence | ForEach-Object {
        $parts = $_ -split "`t"
        ('- `{0}` : `{1}`' -f $parts[0], $parts[1])
    }
}
$report += ''
$report += '## Schema Summary'
$report += ''
if (-not $schemaSummary) {
    $report += '- No schema drift detected'
} else {
    $report += $schemaSummary | ForEach-Object {
        $parts = $_ -split "`t"
        ('- `{0}`: {1}' -f $parts[0], $parts[1])
    }
}
$report += ''
$report += '## Key Business Table Counts'
$report += ''
$report += '| Table | Environment | Count |'
$report += '|---|---|---:|'
$report += $keyCounts | ForEach-Object {
    $parts = $_ -split "`t"
    ('| {0} | {1} | {2} |' -f $parts[1], $parts[0], $parts[2])
}

Set-Content -Path $reportPath -Value $report -Encoding UTF8
Write-Output "Audit report written: $reportPath"