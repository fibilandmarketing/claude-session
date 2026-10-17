<#
.SYNOPSIS
  Checks an OpenRouter key and finds a free model that works with Claude Code.

.DESCRIPTION
  1. Checks the key and shows its daily free limit.
  2. Lists free (":free") models that support tool calling. Claude Code needs
     tools to read files, edit code and run commands.
  3. Sends a test request with a tool to OpenRouter's Anthropic-compatible
     endpoint, the same endpoint Claude Code uses, and checks the model calls
     the tool. If a model is busy (429) it tries the next one, up to 3.
  4. Offers to save the key and model so claude-free.ps1 can use them.

  The key is read from $env:OPENROUTER_API_KEY, or prompted for (hidden input).
  Each test request counts toward your free daily limit (50 a day by default).

.EXAMPLE
  .\test-openrouter.ps1
.EXAMPLE
  .\test-openrouter.ps1 -Model <model-id>
#>
param(
    [string]$Model,
    [string]$BaseUrl = 'https://openrouter.ai/api'
)

$ErrorActionPreference = 'Stop'

function Get-ErrorText($err) {
    $msg = $err.ErrorDetails.Message
    if (-not $msg) { $msg = $err.Exception.Message }
    return $msg
}

function Get-Status($err) {
    if ($err.Exception.Response) { return [int]$err.Exception.Response.StatusCode }
    return 0
}

function Show-Hint($status, $msg) {
    if ($status -eq 401 -or $msg -match 'User not found|No auth|invalid.*key') {
        Write-Host 'Hint: the key is wrong or was deleted. Create a new one at https://openrouter.ai/keys' -ForegroundColor Yellow
    } elseif ($msg -match 'data policy|No endpoints found') {
        Write-Host 'Hint: free models need a privacy setting. Open https://openrouter.ai/settings/privacy and allow free endpoints, then retry.' -ForegroundColor Yellow
    } elseif ($status -eq 429) {
        Write-Host 'Hint: rate limited (20/min, 50/day on free). Wait a minute, or try tomorrow.' -ForegroundColor Yellow
    } elseif ($msg -match 'refused|Unable to connect|No such host|could not be resolved') {
        Write-Host 'Hint: cannot reach openrouter.ai. Check your internet connection, VPN or firewall.' -ForegroundColor Yellow
    }
}

$key = $env:OPENROUTER_API_KEY
if (-not $key) {
    $secure = Read-Host 'Paste your OpenRouter API key (input hidden)' -AsSecureString
    $key = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
}
if (-not $key) { Write-Host 'No key given.' -ForegroundColor Red; exit 1 }
$auth = @{ Authorization = "Bearer $key" }

# 1. Check the key (does not use up model requests)
Write-Host "`n[1/3] Checking the key..." -ForegroundColor Cyan
try {
    $info = (Invoke-RestMethod -Uri "$BaseUrl/v1/key" -Headers $auth -Method Get).data
} catch {
    $msg = Get-ErrorText $_; $status = Get-Status $_
    Write-Host "FAILED (HTTP $status)" -ForegroundColor Red; Write-Host $msg
    Show-Hint $status $msg; exit 1
}
Write-Host 'Key is valid.' -ForegroundColor Green
if ($info.is_free_tier) {
    Write-Host '  Free tier: 50 free-model requests a day (1,000 after a one-time $10 credit purchase).'
} else {
    Write-Host '  Account has purchased credits: 1,000 free-model requests a day.'
}

# 2. Free models that support tool calling (public list, no key needed)
Write-Host "`n[2/3] Finding free models that support tool calling..." -ForegroundColor Cyan
try {
    $all = (Invoke-RestMethod -Uri "$BaseUrl/v1/models" -Method Get).data
} catch {
    $msg = Get-ErrorText $_; Write-Host 'FAILED' -ForegroundColor Red; Write-Host $msg
    Show-Hint (Get-Status $_) $msg; exit 1
}
$free = @($all |
    Where-Object { $_.id -like '*:free' -and $_.supported_parameters -contains 'tools' } |
    Sort-Object -Property context_length -Descending)
if ($free.Count -eq 0) {
    Write-Host 'No free models with tool calling are listed right now. Check https://openrouter.ai/models?q=free' -ForegroundColor Red
    exit 1
}
Write-Host ("{0} found (largest context first):" -f $free.Count) -ForegroundColor Green
$free | ForEach-Object { Write-Host ("  {0,-55} {1,9:N0} tokens" -f $_.id, $_.context_length) }

if ($Model) { $candidates = @($Model) } else { $candidates = @($free | Select-Object -First 3 | ForEach-Object { $_.id }) }

# 3. Test tool calling through the Anthropic-compatible endpoint Claude Code uses
Write-Host "`n[3/3] Testing tool calling the way Claude Code does..." -ForegroundColor Cyan
$headers = @{ Authorization = "Bearer $key"; 'anthropic-version' = '2023-06-01' }
$working = $null
foreach ($m in $candidates) {
    Write-Host "  Trying $m ..."
    $body = @{
        model      = $m
        max_tokens = 200
        tools      = @(@{
            name         = 'list_files'
            description  = 'List the files in a folder of the project.'
            input_schema = @{ type = 'object'; properties = @{ path = @{ type = 'string' } }; required = @('path') }
        })
        messages   = @(@{ role = 'user'; content = 'Use the list_files tool to list the files in the src folder.' })
    } | ConvertTo-Json -Depth 10
    try {
        $r = Invoke-RestMethod -Uri "$BaseUrl/v1/messages" -Headers $headers -Method Post `
            -ContentType 'application/json' -Body $body
    } catch {
        $msg = Get-ErrorText $_; $status = Get-Status $_
        Write-Host "  FAILED (HTTP $status): $msg" -ForegroundColor Red
        Show-Hint $status $msg
        if ($status -eq 401 -or $msg -match 'data policy') { exit 1 }
        continue
    }
    $call = $r.content | Where-Object { $_.type -eq 'tool_use' } | Select-Object -First 1
    if ($call) {
        Write-Host ("  SUCCESS. {0} called {1} with {2}" -f $m, $call.name, ($call.input | ConvertTo-Json -Compress)) -ForegroundColor Green
        $working = $m
        break
    }
    $text = ($r.content | Where-Object { $_.type -eq 'text' } | ForEach-Object { $_.text }) -join ''
    Write-Host "  Replied but did not use the tool, so it will struggle in Claude Code. Reply: $text" -ForegroundColor Yellow
}

if (-not $working) {
    Write-Host "`nNo tested model passed. Try again later, or pick one from the list with -Model <id>." -ForegroundColor Red
    exit 1
}

Write-Host "`nUse this model: $working" -ForegroundColor Green
$answer = Read-Host 'Save this key and model for claude-free? (y/n)'
if ($answer -match '^[yY]') {
    [Environment]::SetEnvironmentVariable('OPENROUTER_API_KEY', $key, 'User')
    [Environment]::SetEnvironmentVariable('OPENROUTER_MODEL', $working, 'User')
    Write-Host 'Saved as your Windows user variables OPENROUTER_API_KEY and OPENROUTER_MODEL.'
    Write-Host 'Open a new PowerShell window before running claude-free.'
}
