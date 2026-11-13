# Buddy Modules — Upgrade Plan

> Planned with Opus 5.5 on 2026-11-13. Coding sessions use Sonnet 4.6.
> This is an **in-place upgrade** of the legacy `buddyboss-tab-creator` plugin (v1.0.0), not a new build.
> Read `CLAUDE.md` first, then this file, then `PROGRESS.md`.

---

## 1. Goal

Turn the legacy **BuddyBoss Tab Creator** plugin into **Buddy Modules**: one core plugin that hosts many modules, with an admin UI that looks and behaves exactly like the **BuddyBoss Platform 3.0 ("Settings 2.0") React admin**:

- No page reloads.
- Card grid of modules with on/off toggles and a **Settings** button.
- Side navigation inside each module.
- Toast feedback.

The first and only module in this upgrade is **Profile and Group Tab Creator**. It is the existing Tab Creator with the same features, the same data and the same frontend behaviour, rebuilt on the new UI and architecture. Later modules (Buddy SMTP, Buddy Mega Menu, Buddy Events, …) plug into the same framework. In the BuddyBoss App they appear as web fallback.

## 2. Decisions (agreed with Rezwan, 2026-11-13)

| # | Topic | Decision |
|---|---|---|
| D1 | Shape | One core plugin with modules inside (`modules/<id>/`). A module that is a separate plugin later (e.g. SMTP) still gets a card on the dashboard, and its toggle activates or deactivates that plugin. |
| D2 | Names | Plugin and menu: **Buddy Modules**. First module: **Profile and Group Tab Creator**. |
| D3 | Folder / slug | Rename `buddyboss-tab-creator/` → `buddy-modules/`, main file `buddy-modules.php`. WordPress sees a new plugin: activate it once, and the data migration brings everything across. |
| D4 | Approach | **In place**: the git history starts from the legacy code. Legacy DB, frontend nav and visibility logic is moved and renamed, not rewritten. Only the PHP-rendered admin UI (list tables, forms, jQuery) is replaced by React. |
| D5 | Dependency | BuddyBoss Platform must be active. If it is missing, show an admin notice and load nothing. Each module also declares the BP components it needs (Tab Creator: `members` and/or `groups`). |
| D6 | Menu | Top-level **Buddy Modules** menu placed **directly after the BuddyBoss menu** (BuddyBoss = position `3`). Use a `menu_order` filter, the same technique BuddyBoss Membership uses (`BbmsAppCtrl::admin_menu_order`). The legacy `BuddyBoss > Tab Creator` submenu is removed. |
| D7 | UI / UX | Copy the Platform 3.0 React admin's design and behaviour, re-prefixed. Its settings components are not exposed as a library, so they are copied, not imported. |
| D8 | Server API | **WP REST API** (`buddy-modules/v1`) with `@wordpress/api-fetch`. Platform itself uses admin-ajax. REST was chosen for built-in nonce handling, per-route permission and schema checks, and future use from the app. The UX is identical. |
| D9 | Prefixes | PHP namespace `BuddyModules\`, functions and options `bdm_`, constants `BDM_`, DB tables `{$wpdb->prefix}bdm_tc_*`, REST `buddy-modules/v1`, JS global `bdmAdminData`, CSS `bdm-admin-*`, text domain `buddy-modules`, hooks `bdm_*`. Never use `bb_`, `bp_`, `bbm_` or `bbtc_` for new code. |
| D10 | Data | New tables `bdm_tc_*`. A one-time copy from the `bbtc_*` tables keeps the same IDs. **The old tables are never modified or dropped** and stay as a backup. |
| D11 | Saving | Module toggles and simple settings **autosave** (1s debounce, single-flight queue, toast), as Platform does. The **Tab editor uses an explicit Save button**, because it has 20+ linked fields and sub-tabs and must not publish half-edited tabs. |
| D12 | Repo | Public GitHub repo **`rezwan2024/buddy-modules`**. Branch `dev` holds source. Branch `main` holds **only the built, installable plugin**. |
| D13 | Build | `@wordpress/scripts` + SCSS. `build/` and `node_modules/` are git-ignored on `dev`. A GitHub Action builds on a version tag and pushes to `main` + a GitHub Release zip. |
| D14 | Updates | **Plugin Update Checker** (YahnisElsts) bundled in the plugin. It reads GitHub Releases, so the standard WP "update available / update now" flow works. Done in **P9**, after the upgrade works. |
| D15 | Install stats | GitHub release `download_count` through `npm run stats` (P9). Active installs through an optional Cloudflare Worker (P10, later). Plugin Update Checker does **not** count installs. |
| D16 | Models | Planning and md files: **Opus 5.5**. All coding: **Sonnet 4.6** (`claude-sonnet-4-6`). |

## 3. What the legacy plugin has (inventory)

**Files** (9,332 lines total):

| Legacy file | Lines | Fate |
|---|---|---|
| `buddyboss-tab-creator.php` | 57 | Replaced by `buddy-modules.php` bootstrap |
| `includes/class-bbtc-plugin.php` | 64 | Replaced by `includes/Plugin.php` (core) |
| `includes/class-bbtc-activator.php` | 95 | **Moved**: `modules/tab-creator/includes/Installer.php` (table SQL renamed to `bdm_tc_*`) + migration |
| `includes/class-bbtc-deactivator.php` | 12 | Moved to the core |
| `includes/class-bbtc-db.php` | 388 | **Moved** almost unchanged: `modules/tab-creator/includes/DB.php` |
| `includes/profile/class-bbtc-profile-nav.php` | 558 | **Moved**: `modules/tab-creator/includes/Frontend/Profile_Nav.php` |
| `includes/profile/class-bbtc-profile-visibility.php` | 123 | **Moved**: `.../Frontend/Profile_Visibility.php` |
| `includes/group/class-bbtc-group-nav.php` | 561 | **Moved**: `.../Frontend/Group_Nav.php` |
| `includes/group/class-bbtc-group-visibility.php` | 161 | **Moved**: `.../Frontend/Group_Visibility.php` |
| `includes/admin/class-bbtc-admin-profile-tabs.php` | 2,337 | **Split**. Save/sanitize/rule logic (`process_save*`, `get_builtin_nav_labels`, `get_known_profile_nav_slugs`, ajax reorder) goes to `Tab_Service` + REST controllers. `render_*` HTML is replaced by React. |
| `includes/admin/class-bbtc-admin-group-tabs.php` | 1,908 | Same split (plus `ajax_search_groups` → lookup endpoint) |
| `includes/admin/class-bbtc-admin.php` | 217 | Replaced by core `Admin\Menu` / `Admin\Page` / REST |
| `includes/admin/class-bbtc-list-table-*.php` | 552 | Replaced by React list screens |
| `assets/js/admin.js` / `assets/css/admin.css` | 2,287 | Replaced by `src/` React + SCSS |
| `uninstall.php` | 12 | Rewritten: drops **only `bdm_*`** data, never `bbtc_*` |

**Features that must survive** (feature parity checklist, used in P8 QA):

- **Profile tabs**
  - List with drag reorder, bulk activate/deactivate/delete, status, type, availability, visibility, default, sub-tab count, date.
  - Add/Edit fields: label, slug (auto from label), enabled, default component, modifying predefined tab, site admins only, availability (All Members / None / per WP role), visibility (Do Not Modify / Anyone / None / Logged In / Profile Owner / Friends / Site Admins / per role), tab link, default subnav slug, item CSS ID, add to admin bar, administer item ID, content source (custom content editor or external URL + open in new tab), associated users (user search), sub-tabs (accordion add/edit/remove), status (Active/Draft).
- **Existing (built-in) profile tabs**: Timeline, Profile, Groups, Forums, Photos, Videos, Documents, … Rename (label override), hide, set default, reorder (`bbtc_existing_profile_tab_order`), availability and visibility overrides, sub-tabs under existing tabs (`parent_existing_slug`).
- **Group tabs**: same as profile, plus availability by group status (`enabled_for`), associated groups and excluded groups (group search), and visibility (Anyone / Logged In / Members / Mods / Admins / Site Admins).
- **Existing group tabs**: overrides + reorder (`bbtc_existing_group_tab_order`), injection of sub-tabs under existing group tabs.
- **Rule storage**: `rule_type` ∈ {availability, visibility}. `rule_key` ∈ {scope, role, user_id, audience, group_status, group_id, excluded_group_id}.
- **Options and transients**: `bbtc_db_version`, `bbtc_existing_profile_tab_order`, `bbtc_existing_group_tab_order`, transients `bbtc_builtin_profile_nav_labels`, `bbtc_builtin_group_nav_labels`, `bbtc_notice_*`, `bbtc_activation_redirect`, user meta `bbtc_experimental_notice_shown`.
- Frontend: hooks on `bp_setup_nav` (15/20/30/50), `bp_groups_setup_nav`, `groups_setup_nav`, `bp_actions`, filter `bp_after_nouveau_appearance_settings_parse_args` (group nav order).

**Known legacy bugs to fix during the port** (only these, no extra scope):

1. The custom tabs list Label column collapses to one character per line ("A b o u t"). React table layout fixes it.

## 4. Target architecture

### 4.1 Folder structure (branch `dev`)

```
buddy-modules/
├── buddy-modules.php              # header, constants (BDM_VERSION, BDM_FILE, BDM_DIR, BDM_URL), autoloader, boot
├── uninstall.php                  # drops bdm_* only
├── includes/                      # CORE (namespace BuddyModules\)
│   ├── Plugin.php                 # singleton; dependency check (BuddyBoss Platform); boots registry, admin, REST
│   ├── Autoloader.php             # PSR-4-ish: BuddyModules\Foo\Bar → includes/Foo/Bar.php ; module namespaces → modules/<id>/includes/
│   ├── Modules/
│   │   ├── Module.php             # abstract: id, label, description, icon, category, requires (bp components), settings_route, order, boot(), activate(), deactivate()
│   │   ├── Registry.php           # register/get/all; active state in option bdm_active_modules; activate/deactivate with dependency check
│   │   └── External_Module.php    # card for a separate plugin (toggle = activate_plugin/deactivate_plugin)
│   ├── Settings/
│   │   └── Fields.php             # bdm_register_side_panel / section / field (Platform-style schema), sanitize by type
│   ├── Admin/
│   │   ├── Menu.php               # add_menu_page('Buddy Modules'), menu_order filter → right after 'buddyboss-platform'
│   │   ├── Page.php               # render <div class="wrap bdm-admin-wrap"><hr class="wp-header-end"><div id="bdm-admin-root"></div>
│   │   └── Assets.php             # enqueue build/admin.js via admin.asset.php, wp-components css, bb-icons-rl, bdmAdminData, wp_set_script_translations
│   ├── REST/
│   │   ├── Controller.php         # abstract base: namespace buddy-modules/v1, permission (manage_options), error helpers
│   │   ├── Modules_Controller.php # GET /modules, POST /modules/{id} {active}
│   │   └── Settings_Controller.php# GET|POST /settings/{module}
│   └── Migration/
│       └── Runner.php             # versioned migrations, runs on activation + admin_init version check
├── modules/
│   └── tab-creator/
│       ├── module.php             # registers BuddyModules\TabCreator\Module with the Registry
│       └── includes/              # namespace BuddyModules\TabCreator\
│           ├── Module.php         # extends core Module; boots frontend + REST when active
│           ├── Installer.php      # bdm_tc_* tables (dbDelta) — from legacy activator
│           ├── Migration.php      # copy bbtc_* → bdm_tc_* once (same IDs), copy order options
│           ├── DB.php             # from legacy class-bbtc-db.php
│           ├── Tab_Service.php    # save/validate/rules logic pulled out of legacy process_save* methods
│           ├── Builtin_Tabs.php   # get_builtin_*_nav_labels, get_known_*_nav_slugs (from legacy admin classes)
│           ├── REST/
│           │   ├── Tabs_Controller.php
│           │   ├── Existing_Tabs_Controller.php
│           │   └── Lookup_Controller.php
│           └── Frontend/
│               ├── Profile_Nav.php  Profile_Visibility.php
│               └── Group_Nav.php    Group_Visibility.php
├── src/                           # React source
│   ├── admin/
│   │   ├── index.js               # createRoot('#bdm-admin-root'), ErrorBoundary
│   │   ├── App.js  Router.js      # query-param routing + pushState/popstate (Platform pattern)
│   │   ├── screens/
│   │   │   ├── Dashboard.js       # module card grid, All/Active/Inactive filter, toggles
│   │   │   └── ModuleScreen.js    # SideNavigation + generic SettingsForm OR custom panel screen
│   │   └── modules.js             # CUSTOM_PANEL_SCREENS map, lazy() per module
│   ├── common/
│   │   ├── components/            # Header, ModuleCard, SideNavigation, SettingsForm, Toast, ConfirmModal, DeleteConfirmModal,
│   │   │                          # ListToolbar, ListPagination, SortableList, SearchSelect (users/groups), EmptyState, ErrorBoundary
│   │   ├── hooks/                 # useAutosave (debounce + single-flight), useToast, useRoute
│   │   └── utils/api.js           # apiFetch wrapper (nonce middleware, error → toast)
│   ├── modules/tab-creator/
│   │   ├── screens/  TabsList.js  ExistingTabsList.js  TabEditor.js  ExistingTabEditor.js
│   │   └── components/ AvailabilityField.js VisibilityField.js SubtabsEditor.js ContentField.js
│   └── styles/                    # _variables.scss (Platform tokens), admin.scss, per-component partials
├── assets/images/                 # logo.svg, module icons
├── languages/
├── lib/plugin-update-checker/     # P9
├── .github/workflows/release.yml  # P9
├── package.json  package-lock.json  webpack.config.js
├── phpcs.xml.dist  .gitignore  .distignore
└── CLAUDE.md  PLAN.md  PROGRESS.md   # dev branch only (excluded from build via .distignore)
```

### 4.2 Admin routing (no reload)

One WP page, `admin.php?page=buddy-modules`. React reads and writes query params with `pushState`, listens to `popstate`, and fixes the WP menu highlight itself (Platform's `fixAdminMenuHighlight` pattern).

| URL | Screen |
|---|---|
| `?page=buddy-modules` | Dashboard (module cards) |
| `&module=tab-creator` | Module screen, default panel |
| `&module=tab-creator&panel=profile-tabs` | Profile tabs list (custom + existing) |
| `&module=tab-creator&panel=profile-tabs&view=new` | New profile tab |
| `&module=tab-creator&panel=profile-tabs&view=edit&id=12` | Edit profile tab |
| `&module=tab-creator&panel=profile-tabs&view=existing&slug=activity` | Edit built-in profile tab override |
| `&module=tab-creator&panel=group-tabs…` | Same set for groups |

The side navigation for Tab Creator has panels **Profile Tabs** and **Group Tabs**. Each panel is hidden if the matching BP component (`members` / `groups`) is inactive.

### 4.3 REST API (`buddy-modules/v1`, permission `manage_options`, nonce through api-fetch)

| Method | Route | Purpose |
|---|---|---|
| GET | `/modules` | `[{id,label,description,icon,category,active,available,requires,settings_route,order,external}]` |
| POST | `/modules/{id}` | `{active:bool}`. Returns the updated module, or an error with the reason (missing BP component, plugin not installed). |
| GET/POST | `/settings/{module}` | Generic registered fields (for future modules; Tab Creator mainly uses custom panels) |
| GET | `/tab-creator/tabs?type=profile\|group` | Custom tabs incl. rules and sub-tab counts |
| POST | `/tab-creator/tabs` | Create (body = full tab incl. rules + sub-tabs) |
| GET/PUT/DELETE | `/tab-creator/tabs/{id}` | Read / update / delete (delete removes rules and sub-tabs, as legacy does) |
| POST | `/tab-creator/tabs/bulk` | `{action: activate\|deactivate\|delete, ids:[]}` |
| POST | `/tab-creator/tabs/reorder` | `{type, ids:[]}` |
| GET | `/tab-creator/existing?type=` | Built-in tabs merged with overrides |
| GET/PUT/DELETE | `/tab-creator/existing/{type}/{slug}` | Read / save / reset override |
| POST | `/tab-creator/existing/reorder` | `{type, slugs:[]}` |
| GET | `/tab-creator/lookup/users?search=` | Associated users picker |
| GET | `/tab-creator/lookup/groups?search=` | Associated / excluded groups picker |
| GET | `/tab-creator/lookup/roles` | WP roles for availability/visibility checklists |

Every route has `args` with `type`, `sanitize_callback` and `validate_callback`. The sanitization rules come from the legacy `process_save*` methods, so behaviour does not change.

### 4.4 UI spec (copy of Platform 3.0)

Reference screens and source (re-read when building UI):

- **Live**: `http://prod.local/wp-admin/admin.php?page=bb-settings` (dashboard) and `…&tab=registration` (inner page).
- **Screenshots**: `/Users/shiblu/Local Sites/prod/.playwright-mcp/bm-study/` (`01-bb-settings-home.png`, `02-bb-settings-inner.png`, `03-tc-profile-list.png`, `04-tc-profile-add.png`, `05-tc-group-list.png`).
- **Source** (GitHub `buddyboss/buddyboss-platform`, branch `release`, public). Read with:
  `gh api "repos/buddyboss/buddyboss-platform/contents/<path>?ref=release" --jq .content | base64 -d`
  - `src/js/admin/webpack.config.js`
  - `src/js/admin/settings/{index,App,Router}.js`
  - `src/js/admin/settings/screens/{SettingsScreen,FeatureSettingsScreen,SideNavigation}.js`
  - `src/js/admin/settings/components/{SettingsForm,Toast}.js`
  - `src/js/admin/settings/components/common/*` (DeleteConfirmModal, ListToolbar, ListPagination, …)
  - `src/js/admin/settings/components/fields/SortableToggleList.js`
  - `src/js/admin/settings/utils/ajax.js` (for the optimistic-toggle and single-flight-save logic only)
  - `src/js/admin/common/components/BBAdminHeader.js`
  - `src/js/admin/common/styles/scss/_variables.scss`
  - `src/js/admin/settings/styles/scss/admin.scss`
- **Installed PHP side**: `plugins/buddyboss-platform/bp-core/admin/bb-admin-settings-page.php`, `bb-admin-common-assets.php`, `classes/class-bb-admin-settings-ajax.php`, `bp-core/classes/class-bb-feature-registry.php`.

Key UI elements:

- **Header**: fixed bar under the WP admin bar (`top:32px; left:160px`, with folded/mobile variants), Buddy Modules logo on the left, search box (P3+, optional), help icon.
- **Dashboard**:
  - Filter pills All (n) / Active (n) / Inactive (n), a category select, and an uppercase category heading.
  - 4-column card grid. Each card has an icon, title, description, a secondary **Settings** button (disabled when inactive) and a big toggle on the right.
  - The toggle is optimistic: update the UI, call the API, revert and show an error toast on failure.
- **Module screen**: left column with "← Back to Modules" and side-nav items with icons, active item highlighted. Right column has white rounded panels with a header (title + help icon) and field rows (label left, control right).
- **Lists**: Platform list-screen look (toolbar with search, filter and bulk action; table rows with drag handle; status pill; row actions Edit/Delete; DeleteConfirmModal). Reorder uses `react-beautiful-dnd` (Platform's choice).
- **Editor**: sections as panels. Fields are Toggle / Text / Checkbox list with "Select all" / Radio / URL / RichText (`wp.editor` or `@wordpress/components`), plus SearchSelect for users/groups. A sticky footer has **Save** and **Cancel**, with an unsaved-changes guard on navigation.
- **Tokens**: brand `#ef5d33` (hover `#d7542e`), text `#1e1e1e`/`#666`, page bg `#f6f6f6`, borders `#e5e5e5`/`#ddd`, radius 4/6/8/12/16. Override `--wp-admin-theme-color` and `--wp-components-color-accent` on the body class `toplevel_page_buddy-modules`. Icons come from the `bb-icons-rl` font (available because BuddyBoss Platform is required).
- **Toasts**: saving / success (auto-dismiss 3s) / error (sticky).

### 4.5 Build

- `package.json` deps: `@wordpress/scripts`, `@wordpress/components`, `@wordpress/element`, `@wordpress/api-fetch`, `@wordpress/i18n`, `@wordpress/icons`, `@wordpress/url`, `react-beautiful-dnd`, `sass`. Pin versions in `package-lock.json`.
- One webpack entry `src/admin/index.js` → `build/admin.js` + `admin.asset.php`. Module screens are code-split with `lazy()` into chunks. SCSS is imported from JS (wp-scripts handles it) → `build/admin.css` (+ `-rtl`).
- Scripts:
  - `npm run build`, `npm start` (watch)
  - `npm run lint:js`, `npm run lint:css`, `composer`-free `phpcs` via a global or scratchpad install
  - `npm run zip` (build + copy per `.distignore` → `buddy-modules.zip`)
  - `npm run stats` (P9)

### 4.6 Data migration (P7)

1. On activation or a version bump, `Installer` creates the `bdm_tc_tabs`, `bdm_tc_tab_rules`, `bdm_tc_existing_tab_overrides` and `bdm_tc_existing_tab_override_rules` tables (same columns as legacy).
2. `Migration` runs **once** (flag option `bdm_tc_migrated_from_legacy`). If the `bbtc_*` tables exist and the `bdm_tc_*` tables are empty, it runs `INSERT INTO bdm_tc_x SELECT * FROM bbtc_x` for each table (same IDs, so parent_id, tab_id and override_id links stay valid). It copies `bbtc_existing_*_tab_order` → `bdm_tc_existing_*_tab_order`.
3. If legacy data existed, the Tab Creator module is marked active.
4. The legacy tables and options are **untouched**.
5. **Guard**: if the legacy plugin `buddyboss-tab-creator/buddyboss-tab-creator.php` is still active, Buddy Modules shows an admin notice ("deactivate the old BuddyBoss Tab Creator") and **does not** register frontend tabs, so tabs never appear twice.
6. After migration it shows a one-time success notice with counts.

## 5. Phases

One phase = one chat session (Sonnet 4.6). If a phase runs long, stop at a clean checkpoint, update `PROGRESS.md`, and continue in a new session. Each phase ends with:

- `npm run build` green
- PHP lint (`php -l`) + phpcs clean on touched files
- A quick check on prod.local
- `PROGRESS.md` updated
- A commit on `dev`

### P0 — Repo + scaffold (in place)
1. Back up the legacy folder to `/Users/shiblu/Local Sites/prod/plugin-backups/buddyboss-tab-creator-1.0.0-legacy/` (copy, not move).
2. Rename the folder `buddyboss-tab-creator/` → `buddy-modules/`. Move the md files with it.
3. `git init`, then make the first commit **= the legacy code as is** (history starts from legacy). Create the `dev` branch.
4. Create the public GitHub repo `rezwan2024/buddy-modules` (`gh repo create` with account `rezwan2024`, after Rezwan confirms) and push `dev`. `main` is created in P9.
5. Add `package.json`, `webpack.config.js`, `.gitignore` (`node_modules/`, `build/`, `*.zip`), `.distignore`, `phpcs.xml.dist` (WPCS, prefixes `bdm`, `BuddyModules`, text domain `buddy-modules`).
6. Add a minimal `src/admin/index.js` rendering "Buddy Modules", and get `npm run build` green.
7. **Done when**: the repo is on GitHub, the build works, and the legacy plugin still works unchanged (nothing deleted yet).

### P1 — Core PHP framework
1. `buddy-modules.php` (header: Plugin Name *Buddy Modules*, Version `2.0.0`, Requires Plugins/Requires PHP 8.0, Text Domain `buddy-modules`), plus constants and the autoloader.
2. `Plugin.php`: BuddyBoss Platform dependency check (`BP_PLATFORM_VERSION`, min version kept from legacy `2.8`), notice if missing.
3. `Modules\Module`, `Modules\Registry` (option `bdm_active_modules`), `Modules\External_Module`.
4. `Admin\Menu`: top-level "Buddy Modules" + `menu_order` to sit right after `buddyboss-platform`. `Admin\Page`, `Admin\Assets` (`bdmAdminData`: restRoot, nonce, adminUrl, logoUrl, version, modules bootstrap).
5. `REST\Controller`, `REST\Modules_Controller`.
6. Register Tab Creator as a module stub (no logic yet). The legacy code keeps running from its old classes for now.
7. **Done when**: the Buddy Modules menu sits right after BuddyBoss, `GET /modules` returns Tab Creator, and `POST /modules/tab-creator` toggles it.

### P2 — React shell + dashboard
1. Port Platform tokens and SCSS (`_variables.scss`, header, cards grid, toggle, buttons, toast).
2. `Header`, `ErrorBoundary`, `Toast` + `useToast`, `utils/api.js`, `App` + `Router` (query params, pushState/popstate, menu highlight).
3. `Dashboard`: filter pills, category heading, `ModuleCard` with the optimistic toggle; Settings navigates to `&module=<id>` without a reload.
4. **Done when**: the dashboard matches screenshot 01 in look, the toggle persists across reloads, and the back/forward buttons work.

### P3 — Module screen + generic settings form
1. `ModuleScreen` with `SideNavigation` (back button, items, icons, active state).
2. PHP `Settings\Fields` (side panel → section → field registration) + `REST\Settings_Controller`.
3. `SettingsForm` with field types toggle, text, textarea, select, radio, checkbox list, number, url (only what the modules need; extend later). `useAutosave` (1s debounce, single-flight).
4. `CUSTOM_PANEL_SCREENS` map + lazy loading.
5. **Done when**: a demo field registered in PHP renders, autosaves and toasts. The Tab Creator side-nav shows Profile Tabs / Group Tabs (placeholder panels).

### P4 — Tab Creator server side (move legacy logic)
1. Move `class-bbtc-db.php` → `TabCreator\DB` (table getters → `bdm_tc_*`).
2. Move the activator SQL → `Installer`.
3. Extract `process_save`, `process_save_subtab`, `process_save_standalone_subtab`, `process_save_existing_*`, the bulk/reorder handlers and the built-in label/slug helpers from both legacy admin classes into `Tab_Service` + `Builtin_Tabs`. **Keep sanitization and rule-writing behaviour identical.**
4. Add `Tabs_Controller`, `Existing_Tabs_Controller` and `Lookup_Controller` (routes in §4.3).
5. **Done when**: every route works with `curl` and a cookie login on prod.local (CRUD, bulk, reorder, overrides, lookups), and the data written equals what the legacy form would write for the same input.

### P5 — Tab Creator React lists
1. `TabsList` (profile + group variants): toolbar, bulk actions, drag reorder, status pill, sub-tab count, row Edit/Delete, DeleteConfirmModal, empty state.
2. `ExistingTabsList`: built-in tabs with override label, hidden, default, visibility, availability, drag reorder, Edit.
3. **Done when**: the lists show the real data, reorder/bulk/delete work with no reload, and the Label column bug is gone.

### P6 — Tab Creator React editors
1. `TabEditor`: all fields from §3 inventory, slug auto-generated from the label, `AvailabilityField` / `VisibilityField` (checkbox lists + Select/Deselect all + the legacy "only applies to existing tabs" hints), `ContentField` (custom content rich text / external URL + new tab), `SubtabsEditor` (accordion add/edit/remove/reorder), associated users/groups `SearchSelect`, group-only fields (enabled_for, excluded groups), status Active/Draft.
2. `ExistingTabEditor`: override fields for built-in tabs + sub-tabs under existing tabs.
3. Explicit **Save** / Cancel, unsaved-changes guard, field-level validation errors from REST.
4. **Done when**: a tab created or edited in React behaves identically on the frontend to one made in the legacy UI.

### P7 — Frontend port + migration + legacy cleanup
1. Move the profile/group Nav + Visibility classes into `modules/tab-creator/includes/Frontend/` (namespace + `bdm_tc_*` tables, same hook priorities).
2. `Migration` + the legacy-active guard (§4.6).
3. **Delete** the legacy admin classes, list tables, `assets/js/admin.js`, `assets/css/admin.css` and the old bootstrap. Rewrite `uninstall.php` (drops `bdm_*` only).
4. **Done when**: on prod.local, deactivating the legacy plugin and activating Buddy Modules shows all existing tabs, migrated with the same IDs, on profile and group pages exactly as before.

### P8 — QA, polish, standards
1. Feature-parity checklist (§3) on prod.local, profile + group, as admin and as a member (User Switching or a cookie for another user), on desktop + mobile width.
2. The BuddyBoss App web fallback: a tab's content opens through the web view (check one tab with the app or a mobile user agent).
3. Accessibility basics (keyboard on toggles/modals, labels), RTL CSS, `wp_set_script_translations` + a `.pot` regenerated as `buddy-modules.pot`.
4. phpcs + eslint + stylelint clean. No PHP notices with `WP_DEBUG` on.
5. **Done when**: the checklist is all ✅ in `PROGRESS.md`. Version `2.0.0` is ready.

### P9 — Release system (after the plugin works)
1. Bundle **Plugin Update Checker** in `lib/plugin-update-checker/` and boot it with `PucFactory::buildUpdateChecker('https://github.com/rezwan2024/buddy-modules/', BDM_FILE, 'buddy-modules')` + `->getVcsApi()->enableReleaseAssets()`.
2. `.github/workflows/release.yml`: on tag `v*` on `dev`:
   1. `npm ci && npm run build`
   2. Assemble the dist using `.distignore`
   3. Force-push the dist to the `main` branch (built files only)
   4. Tag it
   5. Create a GitHub Release with `buddy-modules.zip` + notes
3. `npm run stats` prints `download_count` per release via `gh api`.
4. Test: release `v2.0.0`, install the zip on prod.local, publish a `v2.0.1` test release, confirm the WP "update available" notice + one-click update.
5. Add `readme.txt`/`README.md` with the changelog.

### P10 — Optional, later: active install count
A Cloudflare Worker (free tier) acts as the Plugin Update Checker metadata URL. It logs a hashed site ID + plugin/WP/PHP versions to KV/D1, then proxies the GitHub release info, and serves a small stats page. Changing the plugin to use it is one line (the metadata URL). Disclosed in the readme, with an opt-out filter `bdm_send_update_stats`.

### Later — new modules
Each new module = `modules/<id>/` + `Module` subclass + REST controller(s) + either PHP-registered fields (generic form) or custom panel screens. A separate-plugin module = an `External_Module` card. No framework changes should be needed. If one is, record it in `PLAN.md` first.

## 6. Risks and how they are handled

| Risk | Handling |
|---|---|
| Tabs show twice (legacy + new active) | Legacy-active guard (§4.6 step 5) |
| Data loss | Copy-only migration, legacy tables untouched, folder backup in P0 |
| Behaviour drift in rules/visibility | Frontend classes moved, not rewritten. Sanitization copied from legacy `process_save*`. P4 compares written data against the legacy form. |
| UI drifts from Platform | Copy tokens/SCSS from Platform source. Compare against the screenshots in `bm-study/` every UI phase. |
| `menu_order` fights with BuddyBoss Membership | Insert directly after `buddyboss-platform` at a later filter priority. Verify the menu list in P1. |
| Platform changes its admin markup | Our CSS is scoped to `bdm-admin-*` and our own body class. We only share the `bb-icons-rl` font. |
| Private-repo token leak | Repo is public. No token in the plugin. |
