<#
.SYNOPSIS
  Starts Claude Code on a free OpenRouter model, for this run only.

.DESCRIPTION
  Uses OpenRouter's Anthropic-compatible endpoint, so no local proxy is needed.
  Your normal `claude` command is not changed: the OpenRouter settings exist
  only while this script runs.

  Needs OPENROUTER_API_KEY and OPENROUTER_MODEL (test-openrouter.ps1 can save
  both), or pass -Model. Any other arguments go straight to claude.

.EXAMPLE
  .\claude-free.ps1
.EXAMPLE
  .\claude-free.ps1 --continue
.EXAMPLE
  .\claude-free.ps1 -Model <model-id>
#>

$ErrorActionPreference = 'Stop'

# No param() block on purpose: PowerShell would bind claude flags such as
# --continue to $Model, and reject -p as ambiguous. Only -Model is ours.
$Model = $env:OPENROUTER_MODEL
$ClaudeArgs = @()
for ($i = 0; $i -lt $args.Count; $i++) {
    if ($args[$i] -eq '-Model' -and $i + 1 -lt $args.Count) { $Model = $args[$i + 1]; $i++ }
    else { $ClaudeArgs += $args[$i] }
}

$key = $env:OPENROUTER_API_KEY
if (-not $key) {
    Write-Host 'OPENROUTER_API_KEY is not set. Run test-openrouter.ps1 and save the key, then open a new window.' -ForegroundColor Red
    exit 1
}
if (-not $Model) {
    Write-Host 'No model chosen. Run test-openrouter.ps1 and save the model, or pass -Model <id>.' -ForegroundColor Red
    exit 1
}
if (-not (Get-Command claude -ErrorAction SilentlyContinue)) {
    Write-Host 'The claude command was not found. Install Claude Code first.' -ForegroundColor Red
    exit 1
}

# PowerShell deletes a variable when it is set to "", but OpenRouter needs
# ANTHROPIC_API_KEY to be an empty string. A --settings file can hold that,
# along with the other non-secret values. The key itself stays in memory only.
$settings = @{
    env = @{
        ANTHROPIC_BASE_URL            = 'https://openrouter.ai/api'
        ANTHROPIC_API_KEY             = ''
        ANTHROPIC_MODEL               = $Model
        ANTHROPIC_DEFAULT_FABLE_MODEL = $Model
        ANTHROPIC_DEFAULT_OPUS_MODEL  = $Model
        ANTHROPIC_DEFAULT_SONNET_MODEL = $Model
        ANTHROPIC_DEFAULT_HAIKU_MODEL = $Model
        CLAUDE_CODE_SUBAGENT_MODEL    = $Model
    }
}
$settingsFile = Join-Path ([IO.Path]::GetTempPath()) 'claude-free-settings.json'
$settings | ConvertTo-Json -Depth 4 | Set-Content -Path $settingsFile -Encoding ASCII

$previousToken = $env:ANTHROPIC_AUTH_TOKEN
$env:ANTHROPIC_AUTH_TOKEN = $key
Write-Host "Starting Claude Code on OpenRouter ($Model). Type /status inside to confirm." -ForegroundColor Cyan
try {
    & claude --settings $settingsFile @ClaudeArgs
} finally {
    if ($previousToken) { $env:ANTHROPIC_AUTH_TOKEN = $previousToken } else { Remove-Item Env:ANTHROPIC_AUTH_TOKEN -ErrorAction SilentlyContinue }
    Remove-Item $settingsFile -ErrorAction SilentlyContinue
}
