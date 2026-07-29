param(
    [string]$BaseUrl = 'http://127.0.0.1:18080'
)

$ErrorActionPreference = 'Stop'
$compose = @('--env-file', '.env.example', '-f', 'compose.smoke.yaml')
$scratch = Join-Path ([System.IO.Path]::GetTempPath()) ('greennet-smoke-' + [guid]::NewGuid().ToString('N'))
$cookies = Join-Path $scratch 'cookies.txt'
$headers = Join-Path $scratch 'headers.txt'
$body = Join-Path $scratch 'body.txt'

function Assert-True([bool]$Condition, [string]$Message) {
    if (-not $Condition) {
        throw $Message
    }
}

function Invoke-SmokeRequest {
    param(
        [string]$Method,
        [string]$Path,
        [string[]]$Arguments = @()
    )

    Remove-Item -LiteralPath $headers, $body -Force -ErrorAction SilentlyContinue
    $curlArgs = @(
        '-sS', '-X', $Method,
        '-b', $cookies, '-c', $cookies,
        '-D', $headers, '-o', $body,
        '-w', '%{http_code}',
        ($BaseUrl + $Path)
    ) + $Arguments
    $status = & curl.exe @curlArgs
    if ($LASTEXITCODE -ne 0) {
        throw "curl failed for $Method $Path"
    }

    [pscustomobject]@{
        Status = [int]$status
        Headers = Get-Content -Raw -LiteralPath $headers
        Body = Get-Content -Raw -LiteralPath $body
    }
}

New-Item -ItemType Directory -Path $scratch | Out-Null

try {
    & docker compose @compose up --build --detach --wait
    if ($LASTEXITCODE -ne 0) {
        throw 'Smoke Compose stack failed to start.'
    }

    $login = Invoke-SmokeRequest GET '/login'
    Assert-True ($login.Status -eq 200) 'GET /login did not return 200.'
    $csrfMatch = [regex]::Match($login.Body, 'name="_csrf"\s+value="([^"]+)"')
    Assert-True $csrfMatch.Success 'Login CSRF token was not rendered.'
    $loginCsrf = $csrfMatch.Groups[1].Value

    foreach ($asset in @(
        '/css/app.css',
        '/js/pwa.js',
        '/manifest.webmanifest',
        '/service-worker.js',
        '/img/greennet-icon.svg'
    )) {
        $response = Invoke-SmokeRequest GET $asset
        Assert-True ($response.Status -eq 200) "$asset did not return 200."
    }

    $manifest = (Invoke-SmokeRequest GET '/manifest.webmanifest').Body | ConvertFrom-Json
    Assert-True ($manifest.start_url -eq '/login') 'Manifest start_url is not /login.'
    Assert-True ($manifest.icons[0].src -eq '/img/greennet-icon.svg') 'Manifest icon is unexpected.'

    $worker = (Invoke-SmokeRequest GET '/service-worker.js').Body
    Assert-True ($worker.Contains("url.pathname.startsWith('/api/')")) 'Service worker API network-only rule is missing.'
    Assert-True ($worker.Contains('navigationNoCache')) 'Service worker navigation no-cache rule is missing.'

    $protected = Invoke-SmokeRequest GET '/dashboard'
    Assert-True ($protected.Status -eq 302) 'Unauthenticated dashboard did not redirect.'
    $unauthApi = Invoke-SmokeRequest GET '/api/v1/subscriber/summary'
    Assert-True ($unauthApi.Status -eq 401) 'Unauthenticated API did not return 401.'

    $invalid = Invoke-SmokeRequest POST '/login' @(
        '--data-urlencode', "username=smoke-user-a",
        '--data-urlencode', 'password=wrong',
        '--data-urlencode', "_csrf=$loginCsrf"
    )
    Assert-True ($invalid.Status -eq 200) 'Invalid login did not remain on the login page.'
    $stillUnauthenticated = (Invoke-SmokeRequest GET '/api/v1/subscriber/session').Body | ConvertFrom-Json
    Assert-True (-not $stillUnauthenticated.data.authenticated) 'Invalid password authenticated the session.'

    $login = Invoke-SmokeRequest GET '/login'
    $loginCsrf = [regex]::Match($login.Body, 'name="_csrf"\s+value="([^"]+)"').Groups[1].Value
    $valid = Invoke-SmokeRequest POST '/login' @(
        '--data-urlencode', 'username=smoke-user-a',
        '--data-urlencode', 'password=SmokePass-A-2026!',
        '--data-urlencode', "_csrf=$loginCsrf"
    )
    Assert-True ($valid.Status -eq 302) 'Valid login did not redirect.'

    $sessionResponse = Invoke-SmokeRequest GET '/api/v1/subscriber/session'
    $session = $sessionResponse.Body | ConvertFrom-Json
    Assert-True $session.data.authenticated 'Valid login did not authenticate.'
    Assert-True ($session.data.username -eq 'smoke-user-a') 'Authenticated username is incorrect.'
    $csrf = $session.data.csrf_token

    $apiPaths = @(
        '/api/v1/subscriber/summary',
        '/api/v1/subscriber/package',
        '/api/v1/subscriber/usage',
        '/api/v1/subscriber/active-session',
        '/api/v1/subscriber/renewals',
        '/api/v1/subscriber/payments',
        '/api/v1/subscriber/notifications',
        '/api/v1/subscriber/support'
    )
    foreach ($path in $apiPaths) {
        $response = Invoke-SmokeRequest GET $path
        Assert-True ($response.Status -eq 200) "$path did not return 200."
        Assert-True ($response.Headers -match '(?im)^Content-Type:\s*application/json') "$path is not JSON."
        Assert-True ($response.Headers -match '(?im)^Cache-Control:\s*no-store,\s*private') "$path lacks no-store/private."
        Assert-True (-not $response.Body.Contains('smoke-user-b')) "$path disclosed subscriber B."
        Assert-True (-not $response.Body.Contains('Warning')) "$path exposed a PHP warning."
        Assert-True (-not $response.Body.Contains('Fatal error')) "$path exposed a PHP fatal error."
    }

    foreach ($page in @('/dashboard', '/my/package', '/my/account', '/my/notifications')) {
        Assert-True ((Invoke-SmokeRequest GET $page).Status -eq 200) "$page did not return 200."
    }

    $invalidRenewal = Invoke-SmokeRequest POST '/api/v1/subscriber/renewals' @(
        '-H', 'X-CSRF-Token: invalid',
        '--data-urlencode', 'message=Invalid smoke renewal'
    )
    Assert-True ($invalidRenewal.Status -eq 419) 'Invalid renewal CSRF was not rejected.'

    $renewal = Invoke-SmokeRequest POST '/api/v1/subscriber/renewals' @(
        '-H', "X-CSRF-Token: $csrf",
        '--data-urlencode', 'phone=0000000001',
        '--data-urlencode', 'message=Synthetic smoke renewal'
    )
    Assert-True ($renewal.Status -eq 201) 'Valid renewal was not created.'
    $duplicate = Invoke-SmokeRequest POST '/api/v1/subscriber/renewals' @(
        '-H', "X-CSRF-Token: $csrf",
        '--data-urlencode', 'message=Synthetic duplicate renewal'
    )
    Assert-True ($duplicate.Status -eq 409) 'Duplicate renewal was not rejected.'

    $countScript = '<?php $pdo = new PDO("sqlite:/var/www/database/smoke.sqlite"); echo $pdo->query("SELECT COUNT(*) FROM renewal_requests")->fetchColumn();'
    $countOutput = $countScript | & docker compose @compose exec -T php php
    $count = ([string]($countOutput | Select-Object -Last 1)).Trim()
    Assert-True ($count -eq '1') "Disposable smoke database renewal count was [$count], expected [1]."

    $logout = Invoke-SmokeRequest POST '/logout' @('--data-urlencode', "_csrf=$csrf")
    Assert-True ($logout.Status -eq 302) 'Valid logout did not redirect.'
    Assert-True ((Invoke-SmokeRequest GET '/api/v1/subscriber/summary').Status -eq 401) 'Logged-out session retained API access.'

    Write-Output 'SMOKE OK: boot, assets, login, APIs, ownership, renewal, logout, and PWA contracts passed.'
}
finally {
    & docker compose @compose down --volumes --remove-orphans
    Remove-Item -LiteralPath $scratch -Recurse -Force -ErrorAction SilentlyContinue
}
