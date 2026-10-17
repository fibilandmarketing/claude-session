# Free Gemini backup for Claude Code (Windows)

When your Claude usage limit runs out, keep coding in Claude Code with Google
Gemini's free tier. Your normal `claude` command stays exactly as it is; the
backup is a separate command you run only when you need it.

## How it works

Claude Code only speaks Anthropic's API format. Gemini speaks Google's format.
[Claude Code Router](https://github.com/musistudio/claude-code-router) (CCR) sits
on your PC and translates between them:

```
Claude Code ──(Anthropic format)──> CCR on 127.0.0.1:3456 ──(Gemini format)──> Google
```

CCR launches Claude Code with its own settings pointed at the router, so your
regular Claude Code setup is not touched.

## Before you start

- **Region.** Gemini's free tier is not offered in the EU/EEA, the UK or
  Switzerland. If you are there, this guide will fail at step 2. Groq is a good
  alternative and CCR supports it the same way (choose it in step 4).
- **Node.js 22 or newer.** Check with `node --version`. Install from
  <https://nodejs.org> if needed.
- **Expect small daily limits.** As of October 2026 Google only offers Flash and
  Flash-Lite models for free. Third-party trackers report roughly 20 requests a
  day for Flash and about 500 for Flash-Lite, and Google no longer publishes a
  fixed table. Claude Code makes one request per step (every file read, edit or
  command), so prefer **Flash-Lite**. Your real numbers are at
  <https://aistudio.google.com/> under your project's rate limits.
- **Privacy.** On the free tier Google may use your prompts and code to improve
  its products. Don't use it on code you can't share.

## Step 1: Get a Gemini API key

1. Go to <https://aistudio.google.com/app/apikey> and sign in with Google.
2. Click **Create API key** and pick or create a project.
3. Copy the key (it starts with `AIza`). Treat it like a password.

## Step 2: Test the key

Do this before installing anything else. CCR's own "Check Connection" button
can report success even with a wrong key, so this is the test to trust.

Open PowerShell in this folder and run:

```powershell
powershell -ExecutionPolicy Bypass -File .\test-gemini-key.ps1
```

Paste the key when asked (it stays hidden and is not saved). You should see:

```
Key is valid. N text models available:
  ...
SUCCESS. gemini-…-flash-lite replied:
  Hello! ...
Use this model ID in CCR: gemini-…-flash-lite
```

Note the model ID on the last line; you need it in step 4. If it fails, the
script prints a hint (wrong key, region blocked, or quota used up).

## Step 3: Install Claude Code Router

```powershell
npm install -g @musistudio/claude-code-router
ccr ui
```

`ccr ui` starts CCR in the background and opens its setup page in your browser.
The page's address contains a private token, so don't share it.

## Step 4: Run the setup wizard

| Wizard step | What to do |
|---|---|
| **Choose provider** | Pick **Google Gemini** from the preset list. |
| **Add credentials** | Choose **API key** and paste your Gemini key. |
| **Pick models** | Tick the model ID from step 2. If the list is empty, click **Custom model** and type it. |
| **Verify connection** | Optional. Ignore a green result here; step 2 is the real test. |
| **Connect agent** | Agent: **Claude Code**. Profile name: **Gemini Backup**. Effect scope: **Only opened from CCR** (keeps your normal `claude` untouched). Entry mode: **CLI only**. Default model: your Gemini model. Also set Opus, Sonnet, Haiku and Fable to that model, so that every request goes to Gemini. |
| **Let's start** | Click **Let's start**. |

## Step 5: Test the whole chain

1. In the CCR page, open **API Keys**, click **Add**, give it a name, and copy
   the key it shows (it's only shown once).
2. Run, with your model ID:

```powershell
powershell -ExecutionPolicy Bypass -File .\test-ccr-gateway.ps1 -Model "Google Gemini/gemini-…-flash-lite"
```

`SUCCESS. Reply through CCR:` means Claude Code's request format reaches Gemini
and comes back. You can delete that test key in **API Keys** afterwards.

## Step 6: Use it

Normal day: run `claude` as usual.

When you hit your limit:

```powershell
ccr start
ccr "Gemini Backup"
```

To make that one command, add this to your PowerShell profile (`notepad $PROFILE`):

```powershell
function claude-free {
    ccr start | Out-Null
    ccr "Gemini Backup" -- @args
}
```

Then open a new PowerShell window and type `claude-free`. To stop the router,
run `ccr stop`.

## What to expect

- Gemini Flash-Lite is much weaker than Claude at multi-step coding and tool
  use. It's fine for small edits, explanations and tests; big refactors are
  better left until your Claude limit resets.
- The backup runs with its own Claude Code settings folder, so your personal
  Claude Code settings and memory are not loaded there. Your project's
  `CLAUDE.md` still is.
- CCR stores its data in `%APPDATA%\claude-code-router`, including your Gemini
  key. It also adds a default Codex profile, which does nothing unless you use
  Codex.
- If Gemini's quota runs out too, you can add a second free provider (such as
  Groq) under **Providers** and switch the profile's model to it.

## Troubleshooting

| Message | Fix |
|---|---|
| `API key not valid` | Copy the key again from AI Studio. In CCR, update it under **Providers**. |
| `User location is not supported` | Region block. Use another provider such as Groq. |
| HTTP `429` / `RESOURCE_EXHAUSTED` | Free quota used up. Wait, or switch to a Flash-Lite model. |
| `Connection refused` | CCR isn't running: `ccr start`. |
| `Invalid or expired API key` (from CCR) | Wrong CCR client key; create a new one under **API Keys**. |
| `ccr` is not recognized | Reopen PowerShell; check `npm prefix -g` is on your PATH. |
| Scripts are blocked | Run them with `powershell -ExecutionPolicy Bypass -File ...` as shown. |

## Uninstall

```powershell
ccr stop
npm uninstall -g @musistudio/claude-code-router
```

Then delete `%APPDATA%\claude-code-router` if you want to remove the saved key.
