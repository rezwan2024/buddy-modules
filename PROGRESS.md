# PROGRESS — Buddy Modules upgrade

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked (see Open questions)

**Current phase: P6** · Plugin version target: 2.0.0 · Last updated: 2026-11-13 (P6 session, Sonnet 4.6)

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

## P1 — Core PHP framework ✅
- [x] `buddy-modules.php` header/constants/autoloader
- [x] `Plugin.php` + BuddyBoss Platform dependency notice
- [x] `Modules\Module`, `Modules\Registry` (`bdm_active_modules`), `Modules\External_Module`
- [x] `Admin\Menu` (top level, right after BuddyBoss via `menu_order`), `Admin\Page`, `Admin\Assets`
- [x] `REST\Controller`, `REST\Modules_Controller` (GET /modules, POST /modules/{id})
- [x] Tab Creator registered as module stub
- [x] Done-when check: menu right after BuddyBoss ✅, GET /modules returns tab-creator ✅, POST toggle persists ✅, #bdm-admin-root renders ✅

## P2 — React shell + dashboard ✅
- [x] SCSS tokens + base styles ported from Platform
- [x] Header, ErrorBoundary, Toast/useToast, utils/api.js
- [x] App + Router (query params, pushState/popstate, menu highlight)
- [x] Dashboard: filter pills, category heading, ModuleCard, optimistic toggle
- [x] Done-when check: matches screenshot 01 ✅, toggle persists ✅, back/forward work ✅

## P3 — Module screen + generic settings form ✅
- [x] ModuleScreen + SideNavigation
- [x] PHP `Settings\Fields` + `REST\Settings_Controller`
- [x] SettingsForm field types + useAutosave
- [x] CUSTOM_PANEL_SCREENS + lazy loading
- [x] Done-when check: demo field autosaves; Tab Creator side-nav shows Profile/Group panels

## P4 — Tab Creator server side ✅
- [x] `TabCreator\DB` (moved, `bdm_tc_*`)
- [x] `Installer` (moved activator SQL)
- [x] `Tab_Service` + `Builtin_Tabs` (logic extracted from legacy admin classes, behaviour identical)
- [x] `Tabs_Controller`
- [x] `Existing_Tabs_Controller`
- [x] `Lookup_Controller` (users, groups, roles)
- [x] Module.php updated: boot() runs Installer::maybe_upgrade() + registers REST routes via rest_api_init
- [x] Plugin.php: deferred load_modules() to `bp_init` so bp_is_active() is reliable (BP active_components not ready at plugins_loaded:10)
- [x] Done-when check: all 10 routes respond correctly ✅; CRUD verified via authenticated browser fetch ✅

## P5 — Tab Creator React lists ✅
- [x] TabsList (profile + group)
- [x] ExistingTabsList (profile + group)
- [x] Done-when check: real data, reorder/bulk/delete without reload, Label column bug gone

## P6 — Tab Creator React editors ✅
- [x] TabEditor (all fields, Availability/Visibility/Content/Subtabs/SearchSelect)
- [x] Group-only fields (enabled_for, associated/excluded groups)
- [x] ExistingTabEditor (rename, hide/show, default, visibility/availability rules)
- [x] Save/Cancel, unsaved-changes guard, REST validation errors
- [x] Done-when check: React-made tab stores same fields/rules/slug as legacy admin

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

## Handoff note for next session
Start **P7** with Sonnet 4.6. P7 ports the frontend nav/visibility classes to the new namespace, runs the migration (copy legacy data once), adds the legacy-active guard notice, and cleans up the legacy admin classes.

P6 architecture (now complete):
- `src/tab-creator/components/TabCreatorPanel.js` — view-router: null/undefined view → TabsList; `add`/`edit` view → TabEditor
- `src/tab-creator/components/ExistingTabCreatorPanel.js` — view-router: null → ExistingTabsList; `edit` → ExistingTabEditor
- `src/tab-creator/components/TabEditor.js` — full add/edit editor; label/slug (auto-slugify), status, is_default, site_admin_only, content_source, content, external_url+new_tab, availability checkboxes (profile), enabled_for checkboxes (group), SearchSelect for assoc users / assoc groups / excluded groups, visibility checkboxes, sub-tabs section (inline add/edit/delete via POST/PATCH/DELETE /tabs)
- `src/tab-creator/components/ExistingTabEditor.js` — override editor: label_override, is_hidden, is_default, site_admin_only, availability/enabled_for checkboxes, SearchSelect for assoc/excluded groups (group), visibility checkboxes; PATCH /existing-tabs/{slug}
- `src/tab-creator/components/SearchSelect.js` — async search + multi-select: chips, debounced input, dropdown; accepts `fetchOptions(search)` and `selectedItems [{id,label}]`
- `src/tab-creator/utils/rulesHelpers.js` — `parseRules(rules, tabType)` converts flat REST rules array to form state; `serializeRules(formRules, assocUsers, assocGroups, excludedGroups)` builds save payload
- `src/styles/_tab-editor.scss` — styles for TabEditor, ExistingTabEditor, SearchSelect
- `modules.js` CUSTOM_PANEL_SCREENS: now points to TabCreatorPanel (wraps TabsList+TabEditor) and ExistingTabCreatorPanel (wraps ExistingTabsList+ExistingTabEditor)
- PHP: `Tab_Service::save_tab()` now reads `parent_id` from `$fields` (was hardcoded 0); `Tabs_Controller::extract_tab_fields()` now includes `parent_id` — enables sub-tab creation via POST /tabs
- `TabsList` Edit/Add buttons: navigate to `{module, panel, view:'edit'/'add', id}` instead of placeholder toast
- `ExistingTabsList` Edit button: navigate to `{module, panel, view:'edit', id: tab.original_slug}`
- Route.id holds the tab ID (integer) for custom tab edit, or the slug string for existing tab edit

Unsaved-changes guard: `window.confirm` on Cancel if form is dirty (compared to `initialFormRef.current` JSON snapshot).
REST validation errors: shown as `errors._server` banner below the editor header.
Sub-tabs in Add mode: not shown (parent_id requires a saved tab ID). Sub-tab section appears only in edit mode.

Note: lint:js still crashes (pre-existing TS/ESLint incompatibility — not a P6 regression); lint:css: 0 new errors from P6 files; build: 6 Dart Sass @import deprecation warnings = pre-existing.

## Found bugs (not in scope)
- (none yet)

## Session log
| Date | Phase | Model | Summary |
|---|---|---|---|
| 2026-11-13 | Planning | Opus 5.5 | Studied legacy + Platform 3.0. Decisions D1–D16. Wrote PLAN/CLAUDE/PROGRESS. |
| 2026-11-13 | P0 | Sonnet 4.6 | Backup, folder rename → buddy-modules, git init, scaffold (package.json/webpack/phpcs), src/admin/index.js, npm build green, first GPG-signed commit f445960 Verified, repo rezwan2024/buddy-modules created + dev pushed. |
| 2026-11-13 | P1 | Sonnet 4.6 | buddy-modules.php + constants, Autoloader (PSR-4-ish), Plugin singleton, Module abstract, Registry (bdm_active_modules), External_Module, Admin\Menu (menu_order after BuddyBoss) + Page + Assets, REST\Controller + Modules_Controller (GET/POST /modules). Tab Creator stub module. npm build green, php -l clean, all done-when checks passed: menu after BuddyBoss ✅, GET /modules ✅, POST toggle ✅, page renders ✅. |
| 2026-11-13 | P2 | Sonnet 4.6 | SCSS tokens (_variables, _header, _dashboard, _toast, admin.scss), ErrorBoundary, Header, Toast/useToast, api.js (manual URL build to avoid WP's middleware stripping the namespace), App (route state, navigate, popstate, menu highlight), Router, Dashboard (filter pills, category heading, ModuleCard, BigToggle, optimistic toggle). logo.svg created. npm build green (4 deprecation warnings only — Dart Sass @import, same as Platform). All done-when checks: looks like screenshot 01 ✅, toggle persists across reloads ✅, back/forward work ✅. |
| 2026-11-13 | P3 | Sonnet 4.6 | Settings\Fields (in-memory panel/section/field registry), REST\Settings_Controller (GET/POST /settings/{module}, option bdm_settings_{module}), Plugin.php (boots Settings_Controller + calls register_settings() per module), Tab Creator Module (registers profile-tabs/group-tabs panels + enable_profile_tabs toggle), useAutosave (1s debounce, stable closure via useRef), SideNavigation (back button + panel items), SettingsForm (8 field types), modules.js (CUSTOM_PANEL_SCREENS + placeholder lazy screens), ModuleScreen (fetches schema+values, sidenav+form+custom panel), Router updated. _module-screen.scss. npm build green (5 Dart Sass warnings = pre-existing), php -l clean, CSS lint clean on new file. |
| 2026-11-13 | P4 | Sonnet 4.6 | DB.php (bdm_tc_* tables, full CRUD with in-memory cache), Installer.php (activate/maybe_upgrade/create_tables via dbDelta, BDM_TC_DB_VERSION 1.0.0), Builtin_Tabs.php (profile/group slug+label helpers, transients + filter hooks), Tab_Service.php (save_tab, save_subtab, save_override, delete_tab, reorder_tabs, save_existing_tab_order; rule-writing logic identical to legacy), Tabs_Controller.php (CRUD + reorder + bulk, routes registered before (?P<id>)), Existing_Tabs_Controller.php (list/reorder/PATCH/DELETE by slug), Lookup_Controller.php (users/groups/roles), Module.php updated (boot: maybe_upgrade + rest_api_init hook). Fixed pre-existing Settings_Controller PHP 8.0 fatal (type hint). Fixed Plugin.php: deferred load_modules() to bp_init so bp_is_active() is reliable. All 10 routes verified via authenticated browser fetch. php -l clean, npm build green (5 Dart Sass warnings = pre-existing), lint:css clean; lint:js pre-existing TS/ESLint crash = not a P4 regression. |
| 2026-11-13 | P5 | Sonnet 4.6 | TabsList.js (custom tabs: drag, bulk, status toggle, single delete), ExistingTabsList.js (built-in tabs: drag, reset-override), ruleLabels.js (availability/visibility rule summaries), _tabs-list.scss + shared .bdm-admin-badge, modules.js wired to real components, Module.php: 2 new panels (profile-existing-tabs, group-existing-tabs), Tabs_Controller: subtab_count added to prepare_tab(). Label column bug gone: plain text, no row-action mixing. npm build green (6 Dart Sass warnings = pre-existing), lint:css: 0 new errors, php -l clean. Add/Edit buttons are P6 placeholders. Also added subagent debugging rule to plugin CLAUDE.md. |
| 2026-11-13 | P6 | Sonnet 4.6 | TabEditor (all fields: label+slug auto-generate, status, default, site_admin_only, content_source, content, external_url, url_new_tab, availability/enabled_for checkboxes, visibility checkboxes, SearchSelect for users/groups, sub-tabs inline add/edit/delete), ExistingTabEditor (label_override, is_hidden, is_default, site_admin_only, rules), SearchSelect (async search + chip multi-select), rulesHelpers.js (parse/serialize flat REST rules ↔ form state), TabCreatorPanel + ExistingTabCreatorPanel (view-level routers), _tab-editor.scss. PHP: Tab_Service::save_tab + Tabs_Controller::extract_tab_fields now pass parent_id for sub-tab creation. modules.js updated to panel wrappers. TabsList + ExistingTabsList Edit/Add buttons wired to navigate. npm build green (6 Dart Sass warnings = pre-existing), lint:css 0 new errors, php -l clean. |

