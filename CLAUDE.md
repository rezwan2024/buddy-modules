# CLAUDE.md — Buddy Modules

Instructions for every Claude Code session working on this plugin. Read this file in full, then `docs/development/PLAN.md` (at least §2 Decisions + the current phase), then `docs/development/PROGRESS.md`.

## What this is

**Buddy Modules** is a WordPress plugin (owner: Rezwan) that hosts modules for BuddyBoss sites. It is an **in-place upgrade of the legacy "BuddyBoss Tab Creator" plugin**. Its admin UI copies the **BuddyBoss Platform 3.0 React admin** (no reloads, module cards with toggles + Settings, side-nav, toasts). The first module is **Profile and Group Tab Creator**.

## Models

- **Coding sessions: Sonnet 4.6** (`/model claude-sonnet-4-6`). If the session is on another model, tell Rezwan before coding.
- Planning, re-planning and editing `docs/development/PLAN.md` / `CLAUDE.md`: Opus 5.5. If a coding session finds the plan wrong or missing something, **stop, write the issue in `docs/development/PROGRESS.md` → "Open questions"**, and ask Rezwan. Do not redesign silently.

## Session protocol

**One phase per chat session. Never start a second phase in the same session, even if the first finishes early. End the session after the phase commit.**

1. Read **only `docs/development/PROGRESS.md`** at session start (the handoff note tells you exactly what to do). Do NOT re-read CLAUDE.md or docs/development/PLAN.md in full — the session prompt already contains the critical rules and phase scope. Only read a specific section of docs/development/PLAN.md or CLAUDE.md if you need a detail not in the prompt or docs/development/PROGRESS.md.
2. Work **only on that phase**. No bonus features, no refactors outside the phase, no "while I'm here" fixes. A side bug goes into `docs/development/PROGRESS.md` → "Found bugs (not in scope)".
3. Tick tasks in `docs/development/PROGRESS.md` as they finish (not at the end), so a cut-off session can resume.
4. Before ending, run the phase's "Done when" checks, then update `docs/development/PROGRESS.md`:
   - task ticks
   - session log line
   - handoff note for the next session
   - open questions
5. Commit on `dev` (see Git) and push it to GitHub right away (Rezwan, 2026-11-14: always push, never leave commits only local).
6. After the commit, **always** output the next-session prompt (see template below) and stop. Do not wait for Rezwan to ask — generate it automatically every time.

## Next-session prompt template

After every phase commit, output this block verbatim, filling in the next phase number and scope from docs/development/PLAN.md. Rezwan pastes it into a new chat to start the next phase.

```
Buddy Modules plugin — P<N> session.

Plugin (dev checkout): /Users/shiblu/Local Sites/prod/buddy-modules-dev/

FIRST: Read docs/development/PROGRESS.md only (find the handoff note). Do NOT re-read CLAUDE.md or docs/development/PLAN.md in full.

--- CRITICAL RULES ---

Model: Sonnet 4.6 only. Tell Rezwan before coding if on another model.
One phase per session. After the P<N> commit, output the P<N+1> prompt and stop.

Prefixes — PHP namespace: BuddyModules\; functions/options/hooks: bdm_; constants: BDM_;
CSS: bdm-admin-* (BEM); REST: buddy-modules/v1; JS global: bdmAdminData; text domain: buddy-modules.
Never use bb_, bp_, bbm_, bbtc_ for new code.

Build commands (run from plugin root):
  npm run build          — must be green before every commit
  npm run lint:js && npm run lint:css
  php -l <file>          — every touched PHP file

Git: branch dev. Commit message: "P<N>: <what>".
GPG sign every commit — personal key 4130442C8C1832A2:
  git config --local user.name "MD. Rezwan Shiblu"
  git config --local user.email "142478080+rezwan2024@users.noreply.github.com"
  git config --local user.signingkey 4130442C8C1832A2
  git config --local commit.gpgsign true
  git config --local gpg.program /opt/homebrew/bin/gpg
Tell Rezwan before committing (he enters the GPG passphrase). Never --no-gpg-sign.
Never commit build/, node_modules/, *.min.js, *.min.css.
Push every commit to GitHub right away (gh auth switch -h github.com -u rezwan2024 → push → check Verified → switch back).

Browser testing: isolated `playwright` MCP browser only (never playwright-chrome).
Admin cookies: mint via PHP CLI (see wp-content/CLAUDE.md "Self-service login").
Foreign admin notices suppressed on BDM pages — BDM notices hook onto bdm_admin_notices.
apiRequest() builds absolute URL (restRoot + path) — never pass path alone to apiFetch.

--- P<N> SCOPE ---

<paste the phase tasks from docs/development/PLAN.md §5 P<N> here, numbered>

Done when: <paste the "Done when" line from docs/development/PLAN.md>
```

## Locations

- **Project docs**: `CLAUDE.md` stays at the repo root (Claude Code loads it from there); `docs/development/PLAN.md` and `docs/development/PROGRESS.md` hold the plan and the progress/handoff notes. All three are on `dev` only (never in the zip or on `main`).
- **Dev checkout (git, branch `dev`)**: `/Users/shiblu/Local Sites/prod/buddy-modules-dev/` (moved out of `wp-content/plugins/` in P9, 2026-11-14, so a WordPress update never replaces the working tree).
- **Installed plugin on prod.local**: `.../wp-content/plugins/buddy-modules/` = the **release zip**, kept up to date through the WP update screen (no `.git`). To test unreleased dev code there, copy the built dist over it (`npm run zip`, then `rsync -a --delete dist/buddy-modules/ <plugins>/buddy-modules/`) and reinstall the latest release afterwards (WP update screen or the release zip). Never move the checkout back into `plugins/` while the release copy is there. The update checker is off in any folder that has `.git` (filter `bdm_update_checker_enabled`).
- Legacy backup (after P0): `/Users/shiblu/Local Sites/prod/plugin-backups/buddyboss-tab-creator-1.0.0-legacy/`
- Test site: `http://prod.local` (wp-admin), BuddyBoss Platform 3.7.x active.
- GitHub: `https://github.com/rezwan2024/buddy-modules` (public). `gh` has two accounts: use **`rezwan2024`** for this repo (`gh auth switch -u rezwan2024` before repo commands, switch back to `rezwan-buddyboss` after).
- Platform 3.0 reference source: GitHub `buddyboss/buddyboss-platform`, branch `release`, folder `src/js/admin/` (file list in `docs/development/PLAN.md` §4.4). Read with:
  `gh api "repos/buddyboss/buddyboss-platform/contents/<path>?ref=release" --jq .content | base64 -d`
- UI reference screenshots: `/Users/shiblu/Local Sites/prod/.playwright-mcp/bm-study/`

## Naming and prefixes (never deviate)

| Thing | Use |
|---|---|
| PHP namespace | `BuddyModules\` (core), `BuddyModules\TabCreator\` (module) |
| Functions / options / hooks / transients | `bdm_` (module-specific: `bdm_tc_`) |
| Constants | `BDM_` |
| DB tables | `{$wpdb->prefix}bdm_tc_*` |
| REST namespace | `buddy-modules/v1` |
| JS global | `bdmAdminData` |
| CSS classes | `bdm-admin-*` (BEM: `bdm-admin-card__title--active`) |
| Text domain | `buddy-modules` |
| Script/style handles | `bdm-admin`, `bdm-admin-style` |

**Forbidden in new code**: `bb_`, `bp_`, `bbm_` and `bbtc_` prefixes (except when *calling* BuddyBoss functions like `bp_is_active()`, or *reading* legacy `bbtc_*` tables in the migration). Never write to `bbtc_*` tables or options.

## Architecture rules

- Follow `docs/development/PLAN.md` §4 exactly: folder structure, REST routes, routing, UI spec.
- **Server API = WP REST** (`register_rest_route`, `WP_REST_Controller` base) with `permission_callback` → `current_user_can( 'manage_options' )`. Every arg gets a `sanitize_callback` + `validate_callback`. The JS side uses `@wordpress/api-fetch` only (no jQuery, no admin-ajax).
- **Moving legacy code**: keep the logic, change only names/namespaces/table names. Sanitization, rule writing and hook priorities must behave the same as the legacy classes. When unsure, diff against the backup copy.
- **UI = Platform 3.0 copy**:
  - Take SCSS tokens and component structure from the Platform source.
  - Use `@wordpress/components` (ToggleControl `is-big`, Button, Modal, CheckboxControl, TextControl, SelectControl, …) styled like Platform.
  - Scope all CSS to `.bdm-admin-*` or the body class `toplevel_page_buddy-modules`.
  - Keep `<hr class="wp-header-end">` above the React root.
- Optimistic toggles revert on failure. Autosave = 1s debounce + single-flight queue. The tab editor uses an explicit Save button.
- **Foreign admin notices are suppressed on every Buddy Modules page.** `Admin\Page::register()` hooks into `admin_notices` / `all_admin_notices` at priority -1 and calls `remove_all_actions()` on those hooks, then fires `do_action('bdm_admin_notices')` so only BDM-specific notices appear. When adding a plugin notice, hook it onto `bdm_admin_notices`, not `admin_notices`. Never remove this suppression — it is intentional (Rezwan, 2026-11-13).
- All user-facing strings go through `__()` (PHP) or `@wordpress/i18n` (JS) with the `buddy-modules` domain.
- PHP 8.0+, WP 6.4+. Each function/class gets a docblock. `@since 2.0.0` for new code.
- Escape output, sanitize input, `$wpdb->prepare` every query (keep the legacy `DB` allow-list for `orderby`).
- No new runtime dependencies beyond those in `docs/development/PLAN.md` §4.5 without asking.

## Commands

```bash
cd "/Users/shiblu/Local Sites/prod/buddy-modules-dev"
npm ci                 # first time / after lockfile change
npm start              # watch while developing
npm run build          # must be green before every commit
npm run lint:js && npm run lint:css
php -l <file>          # every touched PHP file
npm run zip            # dist zip (P0+)
```

phpcs: don't `composer install` inside the plugin. Install WPCS once into the scratchpad (or use a global phpcs) and run it against `phpcs.xml.dist`.

## Testing on prod.local

- **Browser**: the isolated `playwright` MCP browser (never `playwright-chrome`, which is only for claude.ai skill edits).
- **Admin login without credentials**: mint auth cookies (`wp-content/CLAUDE.md` → "Self-service login"). Detect the Local MySQL socket, `require wp-load.php`, `wp_generate_auth_cookie( 1, … )` for `LOGGED_IN_COOKIE` / `SECURE_AUTH_COOKIE` / `AUTH_COOKIE`, then `context.addCookies()` with domain `prod.local`. The same cookies work for `curl -b` against the REST routes, plus header `X-WP-Nonce`, fetched from `bdmAdminData.nonce` on the page.
- Save screenshots under `/Users/shiblu/Local Sites/prod/.playwright-mcp/bm-dev/` (the MCP cannot write outside the prod folder).
- **Site settings**: tell Rezwan before activating/deactivating plugins or flipping site settings on prod.local, and restore them afterwards. The legacy Tab Creator is **still active on prod.local** until P7. Don't deactivate it before P7 without asking.
- Never roll a plugin back to an older version after a test.

## Git

- Branches: `dev` = source (all work). `main` = built plugin only, written **only by the release workflow** (P9). Never commit to `main` by hand.
- Commit messages: `P<n>: <what>` (e.g. `P2: dashboard module cards with optimistic toggle`), ending with the Co-Authored-By line from the session's attribution reminder.
- **Every commit is GPG-signed and must show "Verified" on GitHub (HARD RULE).** This repo uses the **personal** key, not the work key:
  ```bash
  git config --local user.name "MD. Rezwan Shiblu"
  git config --local user.email "142478080+rezwan2024@users.noreply.github.com"
  git config --local user.signingkey 4130442C8C1832A2
  git config --local commit.gpgsign true
  git config --local gpg.program /opt/homebrew/bin/gpg
  ```
  - Key `4130442C8C1832A2` (RSA 4096, expires 2029-11-12) is on GitHub account `rezwan2024`, and its email is verified there (set up 2026-11-13). Public key: `wp-content/gpg-public-4130442C8C1832A2-rezwan2024.asc`.
  - **Never** use the work key `BDC53DC5963B081D` / `rezwan@buddyboss.com` here (that email belongs to `rezwan-buddyboss`, so it would show Unverified).
  - Rezwan types the passphrase into the pinentry popup. Tell him before committing. Never `--no-gpg-sign`.
  - Before push: `git log --format='%h %G? %GK %ce' origin/dev..HEAD` shows `G 4130442C8C1832A2` on every line.
  - After push: `gh api repos/rezwan2024/buddy-modules/commits/<sha> --jq '.commit.verification | "\(.verified) \(.reason)"'` returns `true valid`.
  - `gh`: the `rezwan2024` login has scopes `repo, workflow, admin:gpg_key`. Use `gh auth switch -h github.com -u rezwan2024` before repo commands and `gh auth switch -h github.com -u rezwan-buddyboss` after (this gh version has no `-u` on `refresh`).
- Never commit `node_modules/`, `build/`, `*.zip`, or anything secret. `.distignore` keeps `src/`, md files, configs and `node_modules` out of the dist.
- Push every commit to `dev` on GitHub right away. Releases / tags when Rezwan asks or when a change must reach `main` (only the release workflow writes `main`).
- **Releasing** (P9): bump the 4 version strings (header, `BDM_VERSION`, `package.json` via `npm version x.y.z --no-git-tag-version`, readme `Stable tag`), add `= x.y.z =` to readme `== Changelog ==`, signed commit, `git push origin dev vX.Y.Z` after `git tag vX.Y.Z`. `.github/workflows/release.yml` builds, publishes `main` + moves the tag + creates the Release. Then `git fetch --tags --force`. Full steps in `README.md`.

## Debugging with subagents

**When a bug requires investigation or time to debug, spawn a subagent instead of doing it in the main session. This keeps the main context lean.**

- Spawn with `Agent({ subagent_type: "fork", ... })` for bugs where the full conversation context is useful.
- For fresh investigations (file search, grep, isolated testing), spawn `Agent({ subagent_type: "general-purpose", ... })`.
- Give the subagent: the exact symptom, relevant file paths, the REST route or function to investigate, and what to report back.
- The subagent reports findings; the main session applies the fix.
- Never debug interactively in the main session when investigation would take more than 2–3 tool calls.

## Don'ts

- Don't rebuild features from scratch that exist in the legacy code. Move them.
- Don't change Tab Creator behaviour or add fields/features. Parity first (`docs/development/PLAN.md` §3).
- Don't touch BuddyBoss Platform, theme or other plugins' files.
- Don't drop or alter legacy `bbtc_*` data.
- Don't put Platform's brand name or logo in our UI. Use the Buddy Modules logo.
