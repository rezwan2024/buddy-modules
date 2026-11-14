# Buddy Modules — development

This is the **`dev`** branch: the plugin source, build tooling, release workflow and update Worker. To install the plugin, use the [latest release](https://github.com/rezwan2024/buddy-modules/releases/latest); the user-facing readme is on [`main`](https://github.com/rezwan2024/buddy-modules/tree/main).

Requires WordPress 6.4+, PHP 8.0+ and BuddyBoss Platform 2.8+ (Node 22 to build).

## Active installs

**Stats page: https://buddy-modules-updates.buddy-modules-update-worker.workers.dev/stats**

Active installs (sites that checked for updates in the last 7 days) by plugin, WordPress and PHP version. It is private: open it once as `/stats?token=<STATS_TOKEN>` (the token is not in this repo) and a cookie keeps you signed in for a year. `?format=json` gives the raw numbers. How it works: [Update Worker](#update-worker-active-installs).

## Branches

| Branch | Contents |
|---|---|
| `main` (default) | The built plugin only, with the user-facing readme (`README.dist.md` is published as its `README.md`). Written by the release workflow; never commit to it by hand. |
| `dev` | Source. All work happens here. |

## Updates in WordPress

The plugin checks for updates with the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (`lib/plugin-update-checker/`), through the update Worker below; `bdm_send_update_stats` → false checks this repository's releases directly. The checker is off when the plugin runs from a git checkout; the `bdm_update_checker_enabled` filter overrides that.

## Development

```bash
npm ci
npm start             # watch build
npm run build         # production build into build/
npm run lint:js && npm run lint:css
npm run zip           # build + dist/buddy-modules/ + dist/buddy-modules.zip (respects .distignore)
npm run stats         # download count per release (needs the GitHub CLI)
npm run changelog -- 2.0.1   # print one version's changelog from readme.txt
```

## Releasing

1. On `dev`, set the new version in all four places: the `Version:` header and `BDM_VERSION` in `buddy-modules.php`, `version` in `package.json`, and `Stable tag:` in `readme.txt`.
2. Add a `= x.y.z =` entry at the top of the `== Changelog ==` section in `readme.txt`. It becomes the release notes.
3. Commit, push `dev`, then tag and push the tag:

   ```bash
   git tag v2.0.1
   git push origin dev v2.0.1
   ```

The [release workflow](.github/workflows/release.yml) then checks the tag is on `dev` and matches every version string, runs `npm ci && npm run build`, assembles the dist with `.distignore`, publishes it to `main` as one commit, moves the tag onto that commit, and creates the GitHub Release with `buddy-modules.zip`. A version with a hyphen (`v2.1.0-beta.1`) is published as a pre-release, which the update checker ignores.

Because the workflow moves the tag onto the built commit on `main`, refresh local tags afterwards with `git fetch --tags --force`.

## Update Worker (active installs)

`worker/` is a Cloudflare Worker (free tier) that the plugin's update checker calls instead of GitHub:

- `GET /update` returns Plugin Update Checker metadata for the latest GitHub release (`download_url` = the release's `buddy-modules.zip`). It does not use the GitHub API (which rate-limits Cloudflare's shared IPs): the tag comes from the `github.com/…/releases/latest` redirect, requirements and changelog from `readme.txt` on `main`. Cached 10 minutes; if GitHub can't be reached, the last good answer (kept 30 days) is served and records the site in D1: SHA-256 site hash, plugin/WP/PHP versions, first/last seen.
- `GET /stats` ([open](https://buddy-modules-updates.buddy-modules-update-worker.workers.dev/stats)) shows active installs (sites seen in the last 7 days) by plugin, WordPress and PHP version. Open `/stats?token=<STATS_TOKEN>` once; a cookie keeps you signed in. `?format=json` gives the raw numbers.
- Opt-out: `add_filter( 'bdm_send_update_stats', '__return_false' );` makes the plugin check GitHub directly.

Deploy (from `worker/`):

```bash
npm install
npx wrangler login                       # once
npx wrangler d1 create buddy-modules-stats   # put the database_id in wrangler.toml
npm run db:init                          # create the table
npx wrangler secret put STATS_TOKEN      # any long random string
npm run deploy
```

Local run: `printf 'STATS_TOKEN=localtest\n' > .dev.vars && npm run db:init:local && npm run dev` (http://127.0.0.1:8787).

## Readmes

| File | Shown where |
|---|---|
| `README.md` (this file) | `dev` branch on GitHub |
| `README.dist.md` | `main` branch and the release zip, as `README.md` (copied by `scripts/dist.js`) |
| `readme.txt` | WordPress "View details" screen; its `== Changelog ==` becomes the release notes |

## Changelog

See [`readme.txt`](readme.txt) → `== Changelog ==`.

## License

GPLv2 or later. Plugin Update Checker is MIT-licensed (`lib/plugin-update-checker/license.txt`).
