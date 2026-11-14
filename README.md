# Buddy Modules

A module host for BuddyBoss sites. Its first module is the **Profile and Group Tab Creator**: custom profile and group tabs and sub-tabs, plus rename, hide and reorder for the built-in BuddyBoss tabs. Buddy Modules 2.0 replaces the legacy "BuddyBoss Tab Creator" plugin.

Requires WordPress 6.4+, PHP 8.0+ and BuddyBoss Platform 2.8+.

## Install

Download **`buddy-modules.zip`** from the [latest release](https://github.com/rezwan2024/buddy-modules/releases/latest) and upload it in **Plugins → Add New → Upload Plugin**. Don't use the "Source code" downloads.

Updates show up on the WordPress **Plugins** screen and install with one click. The plugin checks this repository's releases through the bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (`lib/plugin-update-checker/`). The checker is off when the plugin runs from a git checkout; the `bdm_update_checker_enabled` filter overrides that.

## Branches

| Branch | Contents |
|---|---|
| `dev` | Source. All work happens here. |
| `main` | The built plugin only. Written by the release workflow; never commit to it by hand. |

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

## Changelog

See [`readme.txt`](readme.txt) → `== Changelog ==`.

## License

GPLv2 or later. Plugin Update Checker is MIT-licensed (`lib/plugin-update-checker/license.txt`).
