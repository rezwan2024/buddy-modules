# PROGRESS — Buddy Modules upgrade

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked (see Open questions)

**Current phase: P1** · Plugin version target: 2.0.0 · Last updated: 2026-11-13 (P0 session, Sonnet 4.6)

---

## P-1 Planning ✅
- [x] Study legacy Tab Creator code (9,332 lines) and UI on prod.local
- [x] Study Platform 3.0 React admin (GitHub `release` `src/js/admin/` + installed PHP) and UI on prod.local
- [x] Decisions agreed (PLAN.md §2)
- [x] PLAN.md, CLAUDE.md, PROGRESS.md written (in legacy folder, move with it in P0)

## P0 — Repo + scaffold ✅
- [x] Backup copy → `/Users/shiblu/Local Sites/prod/plugin-backups/buddyboss-tab-creator-1.0.0-legacy/`
- [x] Rename folder `buddyboss-tab-creator/` → `buddy-modules/` (plugin deactivated in WP — Rezwan to reactivate the legacy entry)
- [x] After the rename: update the memory note `~/.claude/projects/-Users-shiblu-Local-Sites-prod/memory/buddy-modules-project.md` to new path `plugins/buddy-modules/`
- [x] `git init`, first commit = legacy code + scaffold, branch `dev` (key `4130442C8C1832A2`, commit f445960, Verified ✅)
- [x] Created public repo `rezwan2024/buddy-modules` + pushed `dev`
- [x] `package.json`, `package-lock.json`, `webpack.config.js`, `.gitignore`, `.distignore`, `phpcs.xml.dist`, `scripts/zip.js`
- [x] Minimal `src/admin/index.js` + `npm run build` green
- [x] Done-when check: repo on GitHub ✅, build green ✅, legacy code committed ✅

## P1 — Core PHP framework
- [ ] `buddy-modules.php` header/constants/autoloader
- [ ] `Plugin.php` + BuddyBoss Platform dependency notice
- [ ] `Modules\Module`, `Modules\Registry` (`bdm_active_modules`), `Modules\External_Module`
- [ ] `Admin\Menu` (top level, right after BuddyBoss via `menu_order`), `Admin\Page`, `Admin\Assets`
- [ ] `REST\Controller`, `REST\Modules_Controller` (GET /modules, POST /modules/{id})
- [ ] Tab Creator registered as module stub
- [ ] Done-when check: menu order, GET/POST /modules via curl

## P2 — React shell + dashboard
- [ ] SCSS tokens + base styles ported from Platform
- [ ] Header, ErrorBoundary, Toast/useToast, utils/api.js
- [ ] App + Router (query params, pushState/popstate, menu highlight)
- [ ] Dashboard: filter pills, category heading, ModuleCard, optimistic toggle
- [ ] Done-when check: matches screenshot 01, toggle persists, back/forward work

## P3 — Module screen + generic settings form
- [ ] ModuleScreen + SideNavigation
- [ ] PHP `Settings\Fields` + `REST\Settings_Controller`
- [ ] SettingsForm field types + useAutosave
- [ ] CUSTOM_PANEL_SCREENS + lazy loading
- [ ] Done-when check: demo field autosaves; Tab Creator side-nav shows Profile/Group panels

## P4 — Tab Creator server side
- [ ] `TabCreator\DB` (moved, `bdm_tc_*`)
- [ ] `Installer` (moved activator SQL)
- [ ] `Tab_Service` + `Builtin_Tabs` (logic extracted from legacy admin classes, behaviour identical)
- [ ] `Tabs_Controller`
- [ ] `Existing_Tabs_Controller`
- [ ] `Lookup_Controller` (users, groups, roles)
- [ ] Done-when check: all routes via curl; written data = legacy form output

## P5 — Tab Creator React lists
- [ ] TabsList (profile + group)
- [ ] ExistingTabsList (profile + group)
- [ ] Done-when check: real data, reorder/bulk/delete without reload, Label column bug gone

## P6 — Tab Creator React editors
- [ ] TabEditor (all fields, Availability/Visibility/Content/Subtabs/SearchSelect)
- [ ] Group-only fields (enabled_for, associated/excluded groups)
- [ ] ExistingTabEditor (+ sub-tabs under existing tabs)
- [ ] Save/Cancel, unsaved-changes guard, REST validation errors
- [ ] Done-when check: React-made tab = legacy-made tab on frontend

## P7 — Frontend port + migration + legacy cleanup
- [ ] Frontend Nav/Visibility classes moved (namespace, `bdm_tc_*`, same priorities)
- [ ] `Migration` (copy once, same IDs, order options) + success notice
- [ ] Legacy-active guard notice
- [ ] Delete legacy admin classes/list tables/admin.js/admin.css/old bootstrap
- [ ] New `uninstall.php` (bdm_* only)
- [ ] Done-when check: on prod.local deactivate legacy → activate Buddy Modules → all tabs identical on frontend (tell Rezwan before switching plugins)

## P8 — QA, polish, standards
- [ ] Parity checklist (PLAN.md §3), all items ✅, admin + member, desktop + mobile
- [ ] BuddyBoss App web fallback check
- [ ] a11y basics, RTL, JS translations, `buddy-modules.pot`
- [ ] phpcs / eslint / stylelint clean, no notices with WP_DEBUG

## P9 — Release system
- [ ] Plugin Update Checker bundled + booted (release assets)
- [ ] `.github/workflows/release.yml` (tag on dev → build → main + Release zip)
- [ ] `npm run stats`
- [ ] Test: v2.0.0 release, then v2.0.1, and confirm the WP update notice + one-click update on prod.local
- [ ] readme + changelog

## P10 — Optional: active installs (Cloudflare Worker)
- [ ] Not started (do only when Rezwan asks)

---

## Parity checklist (filled in P8)
| Feature | Profile | Group |
|---|---|---|
| List + status/type/availability/visibility/default/sub-tab count | | |
| Drag reorder custom tabs | | |
| Bulk activate/deactivate/delete | | |
| Add/Edit all fields | | |
| Slug auto from label | | |
| Availability rules | | |
| Visibility rules | | |
| Custom content / external URL + new tab | | |
| Sub-tabs add/edit/remove | | |
| Associated users / groups + excluded groups | | |
| Existing tabs: rename/hide/default/reorder/overrides | | |
| Sub-tabs under existing tabs | | |
| Draft status | | |
| Frontend output identical to legacy | | |

## Open questions
- (none)

## Found bugs (not in scope)
- (none yet)

## Session log
| Date | Phase | Model | Summary |
|---|---|---|---|
| 2026-11-13 | Planning | Opus 5.5 | Studied legacy + Platform 3.0. Decisions D1–D16. Wrote PLAN/CLAUDE/PROGRESS. |
| 2026-11-13 | P0 | Sonnet 4.6 | Backup, folder rename → buddy-modules, git init, scaffold (package.json/webpack/phpcs), src/admin/index.js, npm build green, first GPG-signed commit f445960 Verified, repo rezwan2024/buddy-modules created + dev pushed. |

## Handoff note for next session
Start **P1** with Sonnet 4.6. Plugin is at `plugins/buddy-modules/`. The legacy Tab Creator plugin is deactivated in WP (Rezwan needs to reactivate it in WP admin so the site keeps working until P7). P1 builds the core PHP framework: buddy-modules.php, Plugin.php, Module/Registry, Admin menu, REST modules controller, Tab Creator stub. The legacy code is still untouched inside the folder and will stay until P7.
