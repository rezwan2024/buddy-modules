# CLAUDE.md — Buddy Modules

Instructions for every Claude Code session working on this plugin. Read this file in full, then `PLAN.md` (at least §2 Decisions + the current phase), then `PROGRESS.md`.

## What this is

**Buddy Modules** is a WordPress plugin (owner: Rezwan) that hosts modules for BuddyBoss sites. It is an **in-place upgrade of the legacy "BuddyBoss Tab Creator" plugin**. Its admin UI copies the **BuddyBoss Platform 3.0 React admin** (no reloads, module cards with toggles + Settings, side-nav, toasts). The first module is **Profile and Group Tab Creator**.

## Models

- **Coding sessions: Sonnet 4.6** (`/model claude-sonnet-4-6`). If the session is on another model, tell Rezwan before coding.
- Planning, re-planning and editing `PLAN.md` / `CLAUDE.md`: Opus 5.5. If a coding session finds the plan wrong or missing something, **stop, write the issue in `PROGRESS.md` → "Open questions"**, and ask Rezwan. Do not redesign silently.

## Session protocol

1. Read `CLAUDE.md` → `PLAN.md` → `PROGRESS.md`. Find the first phase/task not marked done.
2. Work **only on that phase**. No bonus features, no refactors outside the phase, no "while I'm here" fixes. A side bug goes into `PROGRESS.md` → "Found bugs (not in scope)".
3. Tick tasks in `PROGRESS.md` as they finish (not at the end), so a cut-off session can resume.
4. Before ending, run the phase's "Done when" checks, then update `PROGRESS.md`:
   - task ticks
   - session log line
   - handoff note for the next session
   - open questions
5. Commit on `dev` (see Git). Push only when Rezwan asks.

## Locations

- Plugin (local, Local by Flywheel site "prod"): `/Users/shiblu/Local Sites/prod/app/public/wp-content/plugins/buddy-modules/`. Before P0 it is still `.../plugins/buddyboss-tab-creator/`.
- Legacy backup (after P0): `/Users/shiblu/Local Sites/prod/plugin-backups/buddyboss-tab-creator-1.0.0-legacy/`
- Test site: `http://prod.local` (wp-admin), BuddyBoss Platform 3.7.x active.
- GitHub: `https://github.com/rezwan2024/buddy-modules` (public). `gh` has two accounts: use **`rezwan2024`** for this repo (`gh auth switch -u rezwan2024` before repo commands, switch back to `rezwan-buddyboss` after).
- Platform 3.0 reference source: GitHub `buddyboss/buddyboss-platform`, branch `release`, folder `src/js/admin/` (file list in `PLAN.md` §4.4). Read with:
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

- Follow `PLAN.md` §4 exactly: folder structure, REST routes, routing, UI spec.
- **Server API = WP REST** (`register_rest_route`, `WP_REST_Controller` base) with `permission_callback` → `current_user_can( 'manage_options' )`. Every arg gets a `sanitize_callback` + `validate_callback`. The JS side uses `@wordpress/api-fetch` only (no jQuery, no admin-ajax).
- **Moving legacy code**: keep the logic, change only names/namespaces/table names. Sanitization, rule writing and hook priorities must behave the same as the legacy classes. When unsure, diff against the backup copy.
- **UI = Platform 3.0 copy**:
  - Take SCSS tokens and component structure from the Platform source.
  - Use `@wordpress/components` (ToggleControl `is-big`, Button, Modal, CheckboxControl, TextControl, SelectControl, …) styled like Platform.
  - Scope all CSS to `.bdm-admin-*` or the body class `toplevel_page_buddy-modules`.
  - Keep `<hr class="wp-header-end">` above the React root.
- Optimistic toggles revert on failure. Autosave = 1s debounce + single-flight queue. The tab editor uses an explicit Save button.
- All user-facing strings go through `__()` (PHP) or `@wordpress/i18n` (JS) with the `buddy-modules` domain.
- PHP 8.0+, WP 6.4+. Each function/class gets a docblock. `@since 2.0.0` for new code.
- Escape output, sanitize input, `$wpdb->prepare` every query (keep the legacy `DB` allow-list for `orderby`).
- No new runtime dependencies beyond those in `PLAN.md` §4.5 without asking.

## Commands

```bash
cd "/Users/shiblu/Local Sites/prod/app/public/wp-content/plugins/buddy-modules"
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
- Push / create repo / create releases / tags only when Rezwan asks.

## Don'ts

- Don't rebuild features from scratch that exist in the legacy code. Move them.
- Don't change Tab Creator behaviour or add fields/features. Parity first (`PLAN.md` §3).
- Don't touch BuddyBoss Platform, theme or other plugins' files.
- Don't drop or alter legacy `bbtc_*` data.
- Don't put Platform's brand name or logo in our UI. Use the Buddy Modules logo.
