<#
.SYNOPSIS
  Checks that a Google Gemini API key really works, and lists the models it can use.

.DESCRIPTION
  Talks to Google directly (no router involved). Run this first: CCR's own
  "Check Connection" button can report success even with an invalid key.

  The key is read from $env:GEMINI_API_KEY, or prompted for (hidden input).
  It is never printed or written to disk.

.EXAMPLE
  .\test-gemini-key.ps1
.EXAMPLE
  .\test-gemini-key.ps1 -Model gemini-2.5-flash-lite
#>
param(
    [string]$Model
)

$ErrorActionPreference = 'Stop'
$base = 'https://generativelanguage.googleapis.com/v1beta'

$key = $env:GEMINI_API_KEY
if (-not $key) {
    $secure = Read-Host 'Paste your Gemini API key (input hidden)' -AsSecureString
    $key = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
}
if (-not $key) { Write-Host 'No key given.' -ForegroundColor Red; exit 1 }
$headers = @{ 'x-goog-api-key' = $key }

function Show-ApiError($err) {
    $status = $null
    if ($err.Exception.Response) { $status = [int]$err.Exception.Response.StatusCode }
    $body = $err.ErrorDetails.Message
    if (-not $body) { $body = $err.Exception.Message }
    Write-Host "FAILED (HTTP $status)" -ForegroundColor Red
    Write-Host $body
    if ($body -match 'API_KEY_INVALID|API key not valid') {
        Write-Host 'Hint: the key is wrong or was deleted. Copy it again from https://aistudio.google.com/app/apikey' -ForegroundColor Yellow
    } elseif ($body -match 'location is not supported|FAILED_PRECONDITION') {
        Write-Host 'Hint: the free tier is not available in your region (EU/EEA, UK, Switzerland). Pick another provider, e.g. Groq.' -ForegroundColor Yellow
    } elseif ($status -eq 429) {
        Write-Host 'Hint: free quota used up for now. Wait, or try a Flash-Lite model (higher daily limit).' -ForegroundColor Yellow
    } elseif ($status -eq 404) {
        Write-Host 'Hint: that model name does not exist for your key. Run without -Model to see the list.' -ForegroundColor Yellow
    }
}

# 1. List models this key can call
Write-Host "`n[1/2] Listing models available to this key..." -ForegroundColor Cyan
try {
    $resp = Invoke-RestMethod -Uri "$base/models?pageSize=1000" -Headers $headers -Method Get
} catch { Show-ApiError $_; exit 1 }

$usable = @($resp.models |
    Where-Object { $_.supportedGenerationMethods -contains 'generateContent' -and $_.name -match 'gemini' } |
    ForEach-Object { $_.name -replace '^models/', '' } |
    Sort-Object -Descending)
Write-Host ("Key is valid. {0} text models available:" -f $usable.Count) -ForegroundColor Green
$usable | ForEach-Object { Write-Host "  $_" }

if (-not $Model) {
    # Newest stable Flash-Lite, then Flash: these are the ones with free quota.
    $stable = $usable | Where-Object { $_ -notmatch 'preview|exp|image|tts|live|audio|embedding|thinking|latest' }
    $Model = ($stable | Where-Object { $_ -match 'flash-lite' } | Select-Object -First 1)
    if (-not $Model) { $Model = ($stable | Where-Object { $_ -match 'flash' } | Select-Object -First 1) }
    if (-not $Model) { $Model = $usable | Select-Object -First 1 }
}

# 2. Send one real prompt
Write-Host "`n[2/2] Sending a test prompt to $Model ..." -ForegroundColor Cyan
$body = @{ contents = @(@{ parts = @(@{ text = 'Say hello in one sentence.' }) }) } | ConvertTo-Json -Depth 6
try {
    $r = Invoke-RestMethod -Uri "$base/models/${Model}:generateContent" -Headers $headers `
        -Method Post -ContentType 'application/json' -Body $body
} catch { Show-ApiError $_; exit 1 }

$text = ($r.candidates[0].content.parts | ForEach-Object { $_.text }) -join ''
Write-Host "SUCCESS. $Model replied:" -ForegroundColor Green
Write-Host "  $text"
Write-Host "`nUse this model ID in CCR: $Model"
