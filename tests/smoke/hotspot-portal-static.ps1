$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..\..\release\hotspot\greennet-hotspot-portal')
$required = @(
    'login.html',
    'status.html',
    'logout.html',
    'error.html',
    'redirect.html',
    'alogin.html',
    'api.json',
    'md5.js',
    'css\greennet-hotspot.css',
    'js\config.js',
    'js\portal.js',
    'img\greennet-logo.jpg',
    'README-AR.md',
    'INSTALLATION-AR.md',
    'TEST-CHECKLIST-AR.md'
)

foreach ($relative in $required) {
    if (-not (Test-Path -LiteralPath (Join-Path $root $relative))) {
        throw "Missing Hotspot artifact: $relative"
    }
}

$login = Get-Content -LiteralPath (Join-Path $root 'login.html') -Raw
$status = Get-Content -LiteralPath (Join-Path $root 'status.html') -Raw
$runtime = @(
    $login,
    $status,
    (Get-Content -LiteralPath (Join-Path $root 'logout.html') -Raw),
    (Get-Content -LiteralPath (Join-Path $root 'error.html') -Raw),
    (Get-Content -LiteralPath (Join-Path $root 'redirect.html') -Raw),
    (Get-Content -LiteralPath (Join-Path $root 'alogin.html') -Raw),
    (Get-Content -LiteralPath (Join-Path $root 'js\config.js') -Raw),
    (Get-Content -LiteralPath (Join-Path $root 'js\portal.js') -Raw),
    (Get-Content -LiteralPath (Join-Path $root 'css\greennet-hotspot.css') -Raw)
) -join "`n"

foreach ($token in @(
    '$(link-login-only)',
    '$(link-orig)',
    '$(link-orig-esc)',
    '$(error)',
    '$(chap-id)',
    '$(chap-challenge)',
    '$(if trial == ''yes'')',
    '$(mac-esc)'
)) {
    if (-not $login.Contains($token)) {
        throw "Missing login token: $token"
    }
}

foreach ($token in @(
    '$(username)',
    '$(ip)',
    '$(mac)',
    '$(uptime)',
    '$(session-time-left)',
    '$(bytes-in-nice)',
    '$(bytes-out-nice)',
    '$(link-logout)'
)) {
    if (-not $status.Contains($token)) {
        throw "Missing status token: $token"
    }
}

$portalScript = Get-Content -LiteralPath (Join-Path $root 'js\portal.js') -Raw
if ($portalScript -notmatch 'hexMD5\(chap\.id \+ form\.password\.value \+ chap\.challenge\)') {
    throw 'CHAP MD5 sequence is missing or reordered.'
}
if ($runtime -match '(?i)https?://(?:localhost|127\.0\.0\.1)|(?:src|href)=["'']https?://') {
    throw 'Runtime package contains a development or external runtime URL.'
}
if ($runtime -match '(?i)green.?net.*admin.*password|ghp_[A-Za-z0-9]+|github_pat_') {
    throw 'Runtime package contains a credential-like value.'
}

$missingLinks = @()
Get-ChildItem -LiteralPath $root -Filter '*.html' | ForEach-Object {
    $html = Get-Content -LiteralPath $_.FullName -Raw
    [regex]::Matches($html, '(?:src|href)="([^"]+)"') | ForEach-Object {
        $reference = $_.Groups[1].Value
        if ($reference -notmatch '^\$\(|^https?://|^#') {
            $target = Join-Path $root $reference
            if (-not (Test-Path -LiteralPath $target)) {
                $missingLinks += "$($_.Name): $reference"
            }
        }
    }
}
if ($missingLinks.Count -gt 0) {
    throw "Missing local links:`n$($missingLinks -join "`n")"
}

Write-Output "Hotspot portal static validation passed ($($required.Count) required artifacts)."
