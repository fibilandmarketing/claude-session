# Free OpenRouter backup for Claude Code (Windows)

When your Claude usage limit runs out, keep coding in Claude Code with a free
model from [OpenRouter](https://openrouter.ai). Your normal `claude` command
stays exactly as it is; `claude-free` is a separate command you run only when
you need it.

## How it works

OpenRouter offers an Anthropic-compatible endpoint, so Claude Code can talk to
it directly. Nothing extra runs on your PC:

```
claude-free ──(Anthropic format)──> https://openrouter.ai/api ──> a free model
```

`claude-free.ps1` points Claude Code at OpenRouter for that one run and puts
everything back when you exit.

## Before you start

- **Small daily limits.** Free (`:free`) models allow 20 requests a minute and
  **50 a day**. A one-time purchase of $10 in credits raises that to 1,000 a
  day for good, and free models still cost nothing. Claude Code makes one request
  per step (every file read, edit or command), so 50 a day is enough only for a
  few small tasks.
- **Busy models.** Free models are shared and sometimes return "429 rate
  limited" even when you have quota left. Wait a minute or pick another model.
- **Weaker models.** Free models are much weaker than Claude at multi-step
  coding. Use them for small fixes, explanations and tests, and save big
  changes for when your Claude limit resets. OpenRouter itself notes that
  Claude Code works best with Anthropic models.
- **Privacy.** Some free providers log or train on prompts. Don't use this on
  code you can't share.

## Step 1: Get an OpenRouter key

1. Sign up at <https://openrouter.ai> (no card needed).
2. Go to <https://openrouter.ai/keys>, click **Create Key**, and copy it (it
   starts with `sk-or-`). Treat it like a password.
3. Open <https://openrouter.ai/settings/privacy> and allow free model
   endpoints. Without this, free models return "No endpoints found matching
   your data policy".

## Step 2: Allow PowerShell scripts (one time)

Put this folder somewhere permanent, e.g. `C:\Users\<you>\claude-free`. Then in
PowerShell:

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
cd $HOME\claude-free
Get-ChildItem *.ps1 | Unblock-File
```

## Step 3: Test the key and pick a model

```powershell
.\test-openrouter.ps1
```

Paste your key when asked (hidden). The script:

1. checks the key and shows your daily limit,
2. lists today's free models that support **tool calling** (Claude Code needs
   this to read and edit files),
3. sends a test request to the same endpoint Claude Code uses, and checks the
   model really calls a tool, trying up to 3 models if one is busy.

Success looks like:

```
  SUCCESS. some/model:free called list_files with {"path":"src"}
Use this model: some/model:free
Save this key and model for claude-free? (y/n)
```

Answer `y`. The key and model are saved as your Windows user variables
`OPENROUTER_API_KEY` and `OPENROUTER_MODEL`. Then **open a new PowerShell
window**.

Each test uses up to 3 of your 50 daily requests. To test one model you picked
from the list: `.\test-openrouter.ps1 -Model <id>`.

## Step 4: Add the `claude-free` command

Open your PowerShell profile with `notepad $PROFILE` (click Yes if it asks to
create the file), add this line, and save:

```powershell
function claude-free { & "$HOME\claude-free\claude-free.ps1" @args }
```

Open a new PowerShell window.

## Step 5: Use it

Normal day: run `claude` as usual.

When you hit your limit, go to your project folder and run:

```powershell
claude-free
```

Inside, type `/status` to confirm it shows `https://openrouter.ai/api` as the
base URL. Your requests also appear at <https://openrouter.ai/activity>.

More ways to start it:

```powershell
claude-free --continue                  # pick up your last conversation
claude-free -Model <model-id>           # use another model from the step 3 list
claude-free -p "explain this error"     # one-off question, no chat
```

`--continue` sends your previous conversation to the free model. If it errors
on the old history, start a fresh `claude-free` instead.

## Troubleshooting

| Message | Fix |
|---|---|
| `User not found` / HTTP 401 | Wrong or deleted key. Create a new one, rerun step 3. |
| `No endpoints found matching your data policy` | Allow free endpoints at <https://openrouter.ai/settings/privacy>. |
| HTTP 429 / `Rate limit exceeded` | Busy model or daily limit reached. Wait, try another model, or try tomorrow. |
| `OPENROUTER_API_KEY is not set` | Rerun step 3 and answer `y`, then open a new window. |
| `claude-free` is not recognized | Check the line in `$PROFILE` and the folder path, then open a new window. |
| `running scripts is disabled` | Do step 2. |
| Model replies but never edits files | It isn't using tools well. Pick another model from the step 3 list. |

## Removing it

Delete the `claude-free` line from `$PROFILE`, then:

```powershell
[Environment]::SetEnvironmentVariable('OPENROUTER_API_KEY', $null, 'User')
[Environment]::SetEnvironmentVariable('OPENROUTER_MODEL', $null, 'User')
```
