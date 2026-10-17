<#
.SYNOPSIS
  Sends one Anthropic-format request through Claude Code Router to Gemini.

.DESCRIPTION
  This is the same kind of request Claude Code makes, so a reply here means
  the whole chain works: Anthropic format -> CCR -> Gemini -> back.

  Needs a CCR client key: in the CCR UI open API Keys -> Add -> copy it.
  Read from $env:CCR_API_KEY, or prompted for (hidden input).

.EXAMPLE
  .\test-ccr-gateway.ps1 -Model "Google Gemini/gemini-2.5-flash-lite"
#>
param(
    # "<provider name in CCR>/<model id>", exactly as shown in the CCR UI
    [Parameter(Mandatory = $true)][string]$Model,
    [string]$Gateway = 'http://127.0.0.1:3456'
)

$ErrorActionPreference = 'Stop'

$key = $env:CCR_API_KEY
if (-not $key) {
    $secure = Read-Host 'Paste your CCR client API key (input hidden)' -AsSecureString
    $key = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
}
if (-not $key) { Write-Host 'No key given.' -ForegroundColor Red; exit 1 }

$headers = @{ 'x-api-key' = $key; 'anthropic-version' = '2023-06-01' }
$body = @{
    model      = $Model
    max_tokens = 50
    messages   = @(@{ role = 'user'; content = 'Say hello in one sentence.' })
} | ConvertTo-Json -Depth 6

Write-Host "Sending test request to $Gateway via $Model ..." -ForegroundColor Cyan
try {
    $r = Invoke-RestMethod -Uri "$Gateway/v1/messages" -Headers $headers -Method Post `
        -ContentType 'application/json' -Body $body
} catch {
    $msg = $_.ErrorDetails.Message
    if (-not $msg) { $msg = $_.Exception.Message }
    Write-Host 'FAILED' -ForegroundColor Red
    Write-Host $msg
    if ($msg -match 'refused|No connection|Unable to connect') {
        Write-Host 'Hint: CCR is not running. Start it with:  ccr start' -ForegroundColor Yellow
    } elseif ($msg -match 'Invalid or expired API key|Missing auth token') {
        Write-Host 'Hint: wrong CCR client key. Create one under API Keys in the CCR UI (ccr ui).' -ForegroundColor Yellow
    } elseif ($msg -match 'API key not valid') {
        Write-Host 'Hint: CCR is fine, but the Gemini key saved in CCR is wrong. Fix it under Providers.' -ForegroundColor Yellow
    } elseif ($msg -match '429|RESOURCE_EXHAUSTED|quota') {
        Write-Host 'Hint: Gemini free quota used up. Wait, or switch to a Flash-Lite model.' -ForegroundColor Yellow
    }
    exit 1
}

$text = ($r.content | Where-Object { $_.type -eq 'text' } | ForEach-Object { $_.text }) -join ''
Write-Host 'SUCCESS. Reply through CCR:' -ForegroundColor Green
Write-Host "  $text"
