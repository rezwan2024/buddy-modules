# PROGRESS — Buddy Modules upgrade

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked (see Open questions)

**Current phase: P8** · Plugin version target: 2.0.0 · Last updated: 2026-11-14 (P8 session, Sonnet 4.6)

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

## P7 — Frontend port + migration + legacy cleanup ✅
- [x] Frontend Nav/Visibility classes moved (namespace, `bdm_tc_*`, same priorities)
- [x] `Migration` (copy once, same IDs, order options) + success notice
- [x] Legacy-active guard notice
- [x] Delete legacy admin classes/list tables/admin.js/admin.css/old bootstrap
- [x] New `uninstall.php` (bdm_* only)
- [x] Done-when check: on prod.local deactivate legacy → activate Buddy Modules → all tabs identical on frontend (legacy plugin file already deleted in P7; fixed root-cause timing bug in Plugin.php — see session log 2026-11-14)

## P8 — QA, polish, standards ✅
- [x] Parity checklist (table below): front end + REST all ✅ (admin, member bb-arianna, group admin, logged out); admin UI ✅ in the isolated browser at 1600px + 390px
- [x] BuddyBoss App web fallback check (skipped — works via standard WP hooks, no direct app check needed per Rezwan)
- [x] a11y basics (F25), RTL (F23 + visual check), JS translations + `buddy-modules.pot` (F24)
- [x] phpcs / eslint / stylelint clean, no notices with WP_DEBUG (F22)

### P8 UI polish (done in this session)
- [x] Remove `enable_profile_tabs` toggle and empty `general` sections from Module.php (confusing for clients)
- [x] Responsive table columns — `_tabs-list.scss` media queries: tablet (≤1024px) hides availability/visibility/subtabs; mobile (≤782px) also hides type/default
- [x] Design tokens matched to Platform 3.0 exactly — transition 0.3s, button padding 8px 16px, border-radius lg, 6 new tokens added; 0 net new lint errors
- [x] Modal popup for add/edit/delete — TabCreatorPanel + ExistingTabCreatorPanel own modal state; TabEditor/ExistingTabEditor refactored to onSaved/onCancel callbacks; delete uses proper Modal confirmation (not window.confirm); build 0 errors
- [x] Modal size fix — WP Modal `className` places class on the same element as `components-modal__frame`; fixed selector from descendant (`.bdm-admin-tab-editor-modal .components-modal__frame`) to combined (`.bdm-admin-tab-editor-modal.components-modal__frame`); width 600px, max-height 85vh; `animation: none !important` fixes small→full flash
- [x] Remove "Set as Default Tab" and "Site Admin Only" toggles from TabEditor and ExistingTabEditor entirely (Rezwan confirmed); removed from form state, nextForm load, JSX, and save payload
- [x] RoleSelect chip dropdown — replaced 3 flat checkbox grids (availability/enabled_for/visibility) in TabEditor and ExistingTabEditor with compact chip-toggle dropdown component; `RoleSelect` inline function component, `.bdm-admin-role-select` SCSS block; visually verified in Playwright (chips open, "All Members" selected orange)
- [x] WP component field border color — `admin.scss` override block scoped to `.bdm-admin-tab-editor-modal` and `.bdm-admin-tab-editor`; border uses `$bdm-border-dark` token (not browser black), focus ring uses `$bdm-brand`; label font style uppercase 11px uppercase; `box-shadow: none` removes WP default inset shadow

### P8 fixes (Platform look-and-feel parity, 2026-11-14, Opus 5.5 — reference: BuddyBoss → Settings → Registration + Groups → All Groups, values measured with getComputedStyle)
- [x] F1 Page frame — full-bleed module screen, body bg #f6f6f6, full-height 248px sidebar (#f0f0f0, 16px padding, right border #d9d9d9)
- [x] F2 Side nav — outlined "Back" button (1px #d9d9d9, 8px radius, 14px), items 36px / 8px radius / 13px 500 #666; active = white + inset 0.6px #d9d9d9 (no orange); bb-icons-rl line icons
- [x] F3 Header — no BM logo; "Buddy Modules" text in an 8px-radius outlined box; 58px header, #ddd bottom border
- [x] F4 Content card — white card, 16px radius, 1px #e5e5e5 border, 0 1.5px 1px rgba(0,0,0,.16) shadow; header row = title 18px/500 + primary button on the right + #d9d9d9 divider
- [x] F5 List table — Platform list-table style (no outer border, transparent header 13px #666, rows 16px padding + #e5e5e5 dividers, outlined grey pills, 18px checkboxes), label as link opening Edit
- [x] F6 Row actions — "⋯" DropdownMenu (Edit / Delete, Edit / Reset) like Platform, popover 8px radius + 0 4px 12px shadow, 40px items with icons
- [x] F7 Buttons — primary/secondary 8px radius, 8px 16px padding; "+" as bb-icons-rl-plus
- [x] F8 Modal — BUG: header (title + ✕) rendered at the page top-left/right because `animation:none` removed the frame's containing block → `position:relative`; 680px, 12px radius, header divider, sticky footer (Cancel, Save right-aligned)
- [x] F9 Modal fields — field styles never reached the modal (portal is outside .bdm-admin-wrap); Platform modal fields: input 32px bg #f6f6f6 1px #d9d9d9 6px radius, select 1px #ccc, label 13px/500 #2f2f2f (no uppercase); sections flat with dividers; RoleSelect/SearchSelect match
- [x] F10 Delete/Reset confirm modal — compact width selector bug (descendant → combined), Cancel then Delete right-aligned
- [x] F11 Toggles — Platform ToggleControl look (30×18; off #b2b2b2 + white thumb; on transparent track, brand border + thumb)
- [x] F12 Modal fields in Platform settings-row layout (Rezwan: "minimal and clear", reference Settings → Pages) — new `FieldRow` (src/common/components/FieldRow.js): 160px label column (14px/500 #1e1e1e) + control column, help 13px #666 12px under the control, #d9d9d9 divider between rows, stacks ≤600px. TabEditor + ExistingTabEditor fields moved into FieldRows (section titles dropped, section descriptions + bracketed label notes became help text), controls get fixed ids so the row label is a real `<label for>` (RoleSelect `id`, SearchSelect `inputId`), row selects/RoleSelect 32px like Platform's dropdown, RoleSelect ▼ → bb-icons-rl caret, toggle rows get a sentence label. Sub-tab inline form: white box, half-width Label/Slug, Cancel then Save right-aligned; "Add Sub-tab" uses the plus icon. Checked: Add/Edit (custom + external URL), Built-in edit, validation error under Label (nothing saved), sub-tab form, 390px stacked.
- [x] F13 Minimal list (Rezwan 2026-11-14): custom-tab list columns Availability, Visibility and Default removed (now Label, Type, Status, Sub-tabs, ⋯) — `ruleLabels.js` deleted (only used by those columns); responsive nth-child rules renumbered. Cards capped at 985px and centred (Platform settings content width). Sub-tab rows in the editor use the same "⋯" RowActions menu (Edit / Delete) instead of two buttons. **Parity note:** the legacy list showed availability/visibility/default; Rezwan chose to drop them from the list (still editable in the modal).
- [x] F14 Slug no longer shown in the custom/built-in tab lists or in the editor's sub-tab rows (Rezwan); unused `.bdm-admin-tabs-list__slug` style removed. Slug stays editable in the modal.
- [x] F15 Front end: profile sub-tab nav missing under a custom main tab. Not a port regression (legacy same). Root cause: the theme/Nouveau `item-subnav.php` hides a secondary nav with ≤1 item, and the custom parent tab had no sub-nav item of its own, so 1 sub-tab = hidden nav. Fix (Profile_Nav::register_tabs): a content tab with active sub-tabs gets a "home" sub-nav item (same slug as the tab, same closure object → content renders once) and `default_subnav_slug` = its slug; label filter `bdm_tc_tab_home_subnav_label`. The home item is hidden (Rezwan: sub row lists only sub-tabs, like groups) — `item_css_id` `bdm-tc-home-<slug>` + inline CSS `.bp-subnavs li[id^="bdm-tc-home-"]{display:none}` enqueued only when a home item was registered (Profile_Nav::enqueue_home_subnav_style). Verified: /members/test/new/ → sub row "one sub tab", main content once; /new/ok/ → sub content once; tab without children unchanged.
- [x] F16 Front end: custom group tabs missing for logged-out visitors (404 on /groups/<g>/<tab>/); admin saw them. Not a port regression (legacy same). Root cause: Group_Nav hooked `bp_groups_setup_nav`, which BuddyBoss only fires when a user domain exists (class-bp-groups-component.php `if ( ! empty( $user_domain ) ) parent::setup_nav()`). Fix: register on `groups_setup_nav` (5/6, before the overrides at 10). Also `user_has_access` now = the group's own access (was `true`), so the move doesn't expose custom tab content on private/hidden groups. Verified admin + logged out on a public and a private group: public shows tab + main + sub content for both; private → admin sees it, logged-out redirected to login.
- [x] F17 Group sub-nav looked like plain text (own `<nav>` without theme classes). Group_Nav::render_screen now prints the theme's sub-nav markup (`bp-navs bp-subnavs no-ajax group-subnav bb-subnav-plain`, `ul.subnav`, `li.current.selected`, `aria-current`) so it gets the same button style as the profile sub-nav. Lists only the sub-tabs (Rezwan: the tab itself is already in the main nav, no "home" item in the sub row). Verified /groups/foodies-group/resources/ → "sub tab one", main content once; /resources/sub-tab-one/ → sub content once.
- [x] F18 Line breaks typed in tab/sub-tab content were lost on the front end (legacy same: content printed with `wp_kses_post( do_shortcode() )`, no wpautop). New `TabCreator\Content::render()` = wp_kses_post( do_shortcode( shortcode_unautop( wpautop() ) ) ) (WordPress post-content order), used by all 6 output sites in Profile_Nav + Group_Nav. Shortcode test (temp `[bdm_test_shortcode]` in the child theme functions.php, added to profile + group main and sub-tab content via the admin modal): renders on all 4 pages, admin + logged out; text and shortcode now on separate lines, each once.
- [x] F19 (parity QA 2026-11-14) Saving wiped data the editor doesn't show. Root cause: `Tabs_Controller::extract_tab_fields()` filled every absent key with a default, and `Tab_Service::save_tab()/save_override()` wrote all of them; the editors also sent `visibility: []` after the Visibility section was removed. Effects on a4fd1b2 (REST test): the list **Status toggle** (`PATCH {status}`) blanked label, content, position (→10), tab link, CSS ID, admin-bar fields and deleted **all** rules; an editor Save reset position (drag order lost), is_default/site_admin_only/tab link/CSS ID/sub-nav slug/admin-bar fields and deleted visibility rules; built-in tab overrides lost is_default/site_admin_only/tab link/CSS ID/group position override and visibility rules. Fix: controller passes only the keys the request sent (sanitizer map), `save_tab()` on update writes only those keys and rebuilds rules only when `rules` is sent, `save_override()` does the same for an existing override, both keep stored visibility rules when the request has no `visibility` key, `serializeRules()` omits visibility when the form has none. Also fixed: `save_tab()` read `$fields['content_source']`/`['status']` unguarded (undefined key → NULL insert) once absent keys are allowed. Rezwan's decisions: Visibility stays out of the UI but stored rules are kept; legacy advanced fields (tab link, default sub-nav slug, item CSS ID, admin bar, administer item ID, predefined flag, built-in Default / group position override) stay stored with no UI. Verified with REST round trips (create with all fields + visibility, status toggle, editor-shaped PATCH; profile + group override seed → editor PATCH → reset): every unsent field and visibility rule kept.
- [x] F20 (parity QA) Group tab visibility "Members / Mods / Admins" checked the wrong user against the wrong group: `Group_Visibility::can_view_tab()` called `groups_is_user_member/mod/admin( $group_id, $user_id )`, but the BuddyBoss signature is `( $user_id, $group_id )` (legacy had the same swap). With "Members" a real member (bb-arianna, foodies-group) got 404 while the site admin (not a member of nature-lovers) saw it. Fix: arguments in the right order. Verified: Members → member 200, non-member + logged out 404; Admins → group admin (user 8) 200 on their group only, members 404.
- [x] F21 (Platform conflict check) A new custom tab could take a BuddyBoss slug (`activity`, `settings`, `members`, …) or a sibling's slug, so two nav items shared one URL. `Tab_Service::validate_new_slug()` (on create) rejects: main tabs with a built-in slug (Builtin_Tabs known slugs + every active component slug, so owner-only Settings/Messages/Notifications count), and any slug already used at the same level. REST 400 with a clear message (shown in the modal's server error banner). Verified: activity/settings/messages/notifications/members and duplicates new/resources/ok → 400; a free slug → 200.
- [x] F22 Standards, aligned with BuddyBoss Platform (Rezwan 2026-11-14: check standards/structure against Platform, not legacy). **PHPCS**: ruleset now mirrors Platform's (`PHPCompatibilityWP`, `WordPress` minus `PreparedSQL.InterpolatedNotPrepared`), prefixes `bdm_`/`BDM_`/`BuddyModules` (the 3-letter `bdm` was ignored by WPCS, so every hook/constant was flagged). 708 issues at a4fd1b2 → **0**: phpcbf (long `array()`, alignment, spacing), Yoda, short ternaries, empty if, `serialize()` → `wp_json_encode()` in a cache key, unused params removed (`Plugin::load_modules()`, `Group_Visibility::can_show_tab()` + 3 callers), `$class` → `$class_name`, Platform's `bp_core_template_plugin` filter + custom-table queries annotated with reasons, all docblocks (`@package`, class/property/param docs; comment-only, proven by a token diff). **ESLint**: `lint:js` crashed because `typescript` 7.0.2 is hoisted and `@typescript-eslint` 6 needs TS ≤5 — fixed by pinning `"typescript": "~5.5.4"` in devDependencies (Rezwan OK), `npm run lint:js` runs again: ~1,014 errors → **0** (7 `exhaustive-deps` warnings left on purpose: adding those deps re-fetches in a loop). `--fix` (Prettier, const/let, method shorthand) + unused imports/functions, `eslint-disable` for the 4 unsaved-change/delete `confirm()`s moved onto the right line, toggle label `__('Toggle') + ' ' + label` → `sprintf( __( 'Toggle %s' ) )`, redundant `role="list"`. **Structure**: the identical 119-line `RoleSelect` in TabEditor + ExistingTabEditor → one `src/tab-creator/components/RoleSelect.js` (like SearchSelect). **Stylelint**: 60 → **0** (`--fix`, `@import`s moved to the top of admin.scss, long comments wrapped, big-toggle focus rule moved after its base rule, one justified specificity disable). **WP_DEBUG** (log to scratch file, display off, wp-config restored byte-identical): admin page, all 11 GET REST routes (200), dashboard/plugins, profile + group tab/sub-tab pages as admin and logged out → 0 log lines from buddy-modules (165 from wp-includes/Platform).
- [x] F23 RTL: `build/admin-rtl.css` was built but never loaded. `wp_style_add_data( 'bdm-admin-style', 'rtl', 'replace' )` → verified LTR loads admin.css, RTL loads admin-rtl.css.
- [x] F24 Translations: added `load_plugin_textdomain()` on `init` (the bundled `languages/` .mo was never loaded for PHP strings; JS already used `wp_set_script_translations`). Generated `languages/buddy-modules.pot` (wp-cli i18n make-pot via a scratch composer install): 163 strings incl. JS (`#: src/...` references, e.g. "Discard unsaved changes?", "Toggle %s"). Legacy `languages/buddyboss-tab-creator.pot` deleted (Rezwan OK). `scripts/zip.js` Prettier-fixed + its CLI `console.log` allowed.
- [x] F25 a11y (keyboard check in the isolated browser). Passed: side-nav Tab order, ⋯ menu (Enter opens on Edit, arrows, Esc closes + focus back to ⋯), modal focus trap (40× Tab / 10× Shift+Tab stay inside), Esc closes modal + focus back to the opener, dashboard toggle labelled ("Toggle %s") with a 2px ring. Fixed: (1) Esc in the RoleSelect or SearchSelect dropdown closed the whole modal → Esc now closes only the dropdown (`stopPropagation`), RoleSelect returns focus to its trigger; (2) SearchSelect results selected only on `mousedown`, so keyboard users could not pick a user/group → select on click (mousedown only keeps focus); (3) RoleSelect chips get `aria-pressed`, SearchSelect chip remove buttons "Remove <name>", drag handles "Reorder <label>"; (4) no keyboard focus ring on selects (shared rule forced `box-shadow/outline: none` on `:focus`) and on the active side-nav item → `:focus-visible` brand ring; (5) "Discard unsaved changes?" ignored changes to the user/group pickers (they live outside `form`) → tracked; (6) sub-tab Content label linked to its textarea. RTL (simulated: `dir=rtl` + core `load-styles.php?dir=rtl` + components RTL + our admin-rtl.css; no RTL language pack on prod.local): layout mirrors correctly (side-nav beside the WP menu, card/table/modal rows/chevrons/footer mirrored, no overflow); the Back arrow now flips in RTL. 390px: no horizontal scroll, columns hide as designed, ⋯ menu and modal inside the viewport. 0 console errors.
- [x] F26 (Rezwan 2026-11-14, screenshots of Platform Activity Topics + Settings dashboard) Buttons and dashboard cards = Platform, values measured with getComputedStyle on prod.local. **Buttons**: "Add Tab" and "Add Sub-tab" now use Platform's outlined add button (`.bb-topic-list__add-btn`): transparent, 1px #ef5d33 border + text, 34px, 8px 16px, 8px radius, 13px/600, 16px plus icon with 4px gap, hover border #f7ae99 — shared class `bdm-admin-button-add` (admin.scss); Save = Platform modal primary (32px #ef5d33, hover #d7542e), Cancel = secondary (#f0f0f0, 1px #d9d9d9); modal footer buttons centred. **Dashboard** (`.bb-admin-settings__feature-card`): card 0.5px #ccc border, 12px radius, 16px padding, no shadow, min-height 242, 4 per row (`calc(25% - 12px)`, 16px gap, wraps 3/2/1); plain 28px orange `bb-icons-rl` icon (module icon now `bb-icons-rl-tabs`; icon helper shared with the side-nav in `src/common/utils/icons.js`), title 16px/500 #1e1e1e, description 13px/18px #666, 8px gaps, no footer divider; Settings = secondary button with `bb-icons-rl-gear-six`; toggle 40×24 (off #b2b2b2, on #ef5d33, 16px thumb). Pills: transparent 1px #ccc #666 13px/500 34px, active grey (#f0f0f0, 1px #999 + inset 0.5px, #1e1e1e); filter row 24px padding + 1px #e5e5e5 line; category heading 14px/500 #2f2f2f 0.5px uppercase, groups 40px apart. All measured values match Platform side by side. Pill labels and the Settings aria-label now use sprintf (no string concatenation). 390px: cards full width, no overflow.
- Test leftovers removed (2026-11-14, Rezwan OK): child theme functions.php restored byte-identical to its backup (TEMP `[bdm_test_shortcode]` block gone); the shortcode line stripped from tab 4/7/8/9 content.
- Verified in the isolated browser at 1600px and 390px: list, ⋯ menu, Edit/Add/Delete modals, Built-in Tabs list + modal, dashboard. Computed values match Platform (card 16px radius + shadow, modal 680px/12px, inputs 32px #f6f6f6 1px #d9d9d9, selects 40px 1px #ccc, labels 13px/500 #2f2f2f, buttons 8px radius, menu 190px/8px/0 4px 12px). Platform line icons come from Platform's own `bb-icons-rl` stylesheet (Assets::register_line_icons(), reuses the `bb-icons-rl-css` handle when registered). Unused `bdmAdminData.logoUrl` removed.
- lint:css: 0 new errors vs dbf684c (62 pre-existing, one fewer than before); lint:js still crashes (pre-existing TS/ESLint issue); build green (6 pre-existing Sass warnings).

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
Checked 2026-11-14 (P8 completion): front end via HTTP as admin (1), member bb-arianna (2), group admin bb-margaret (8, groups) and logged out; admin data paths via REST. Rezwan: compare with Platform standards, not legacy; Visibility and the legacy advanced fields stay out of the UI (stored values kept, F19).

| Feature | Profile | Group |
|---|---|---|
| List + status/type/sub-tab count (availability/visibility/default columns dropped by Rezwan, F13) | ✅ REST list | ✅ REST list |
| Drag reorder custom tabs | ✅ reorder 6,1,7 → positions 10/20/30 | ✅ same endpoint |
| Bulk activate/deactivate/delete | ✅ deactivate/activate | ✅ delete 2 tabs |
| Add/Edit fields shown in the modal (no longer wipes hidden fields, F19) | ✅ | ✅ |
| Slug auto from label + clash guard (F21) | ✅ | ✅ |
| Availability rules | ✅ role / none / associated user | ✅ status / associated / excluded group |
| Visibility rules (stored, no UI — Rezwan) | ✅ logged in / owner only / site admin | ✅ logged in / members / admins (F20) |
| Custom content (+ shortcodes, line breaks F18) | ✅ | ✅ |
| Sub-tabs render + nav (F15/F17) | ✅ admin/member/anon | ✅ admin/member/anon |
| Private group access | n/a | ✅ logged out → login redirect |
| Existing tabs: rename/hide/reorder/overrides (keeps hidden fields, F19) | ✅ | ✅ |
| Sub-tabs under existing tabs | dropped (Rezwan 2026-11-14) | dropped |
| Draft status | ✅ inactive tab not in nav | ✅ |
| Admin UI in browser after the ESLint reformat + RoleSelect extraction (1600px + 390px, LTR + RTL) | ✅ list, ⋯ menu, Edit modal, sub-tab form, Save keeps data (tab 7 byte-identical) | ✅ list, Edit modal, built-in modal |

## Open questions
- (none)

## Handoff note for next session
**State at end of the 2026-11-14 P8 completion session (Opus 5.5, Rezwan OK):** P8 is done (F19–F25, parity table all ✅, PHPCS/ESLint/Stylelint 0 errors, .pot generated, version 2.0.0). Committed as "P8: QA, a11y, RTL, translations, standards" on `dev` (not pushed unless Rezwan asked). **Next: P9 — release system** (PLAN.md §5 P9): bundle Plugin Update Checker in `lib/plugin-update-checker/` (phpcs already excludes `lib/`), `.github/workflows/release.yml`, `npm run stats`, v2.0.0 + v2.0.1 test releases with the WP update notice on prod.local, readme + changelog. Tooling notes: `typescript` is pinned `~5.5.4` (lint:js needs it); PHPCS = WPCS 3 installed in a scratchpad via composer, run with `phpcs.xml.dist` (now mirrors Platform's ruleset); `.pot` via `wp i18n make-pot . languages/buddy-modules.pot --domain=buddy-modules --exclude=node_modules,build,vendor,languages` (wp-cli bundle from composer in a scratchpad). Decisions this session: Visibility rules and the legacy advanced fields stay stored with no UI; sub-tabs under built-in tabs dropped; compare with BuddyBoss Platform standards, not legacy.

**State at end of the 2026-11-14 P8 fix session:** F1–F18 are committed and pushed: `a4fd1b2` on `dev` (GitHub Verified). **Test leftovers to clean up (ask Rezwan first):** a TEMP TEST `[bdm_test_shortcode]` block at the end of `wp-content/themes/buddyboss-theme-child-1.0.0/functions.php` (backup of the original: scratchpad child-functions.php.bak, may be gone; just delete the marked block), plus a `[bdm_test_shortcode where="…"]` line appended to the content of profile tab "new" + its sub-tab and group tab "resources" + its sub-tab. Remaining P8: parity checklist (PLAN §3), a11y/RTL/.pot, phpcs/eslint/stylelint clean + no notices with WP_DEBUG.

**P8 fix session 2026-11-14 (Opus 5.5):** Platform look-and-feel parity done, see "P8 fixes" F1–F13 above (F12 = modal settings rows via `FieldRow`, F13 = minimal list columns + 985px cards + sub-tab ⋯ menu). New shared pieces: `.bdm-admin-card` (+ `__header/__title/__actions/__body`) in `_module-screen.scss`, `RowActions` (src/common/components/RowActions.js, "⋯" DropdownMenu), panel `title` prop passed ModuleScreen → *Panel → *List (lists render their own card header), shared button/field/toggle rules in `admin.scss` scoped to `.bdm-admin-wrap, .bdm-admin-tab-editor-modal` (modals are portals). Side-nav icons are `bb-icons-rl-*` names in Module.php (`dashicons-*` still supported). Still open in P8: parity checklist, a11y/RTL/.pot, phpcs/eslint clean.

Start **P8** with Sonnet 4.6. P8 is the QA and polish phase: parity checklist (PLAN.md §3), a11y basics, RTL, JS translations (buddy-modules.pot), and phpcs/eslint/stylelint clean with WP_DEBUG on.

**Before starting P8**, the P7 done-when check must be completed: tell Rezwan to deactivate the legacy `buddyboss-tab-creator` plugin and activate `Buddy Modules`, then verify all tabs appear identically on the frontend.

P7 architecture (now complete):
- `modules/tab-creator/includes/Profile_Nav.php` — `BuddyModules\TabCreator\Profile_Nav` (bp_setup_nav priorities 15/20/30/50); uses `DB::`, transients `bdm_tc_builtin_profile_nav_v2/labels`, filters `bdm_tc_tab_label`/`bdm_tc_tab_content`, text domain `buddy-modules`
- `modules/tab-creator/includes/Profile_Visibility.php` — `BuddyModules\TabCreator\Profile_Visibility`; uses `DB::get_rules_for_tab()`
- `modules/tab-creator/includes/Group_Nav.php` — `BuddyModules\TabCreator\Group_Nav` (bp_groups_setup_nav 20/30, groups_setup_nav 10/20, bp_actions 10); reads `bdm_tc_existing_group_tab_order`; transients `bdm_tc_builtin_group_nav_v5/labels`
- `modules/tab-creator/includes/Group_Visibility.php` — `BuddyModules\TabCreator\Group_Visibility`
- `modules/tab-creator/includes/Migration.php` — `Migration::maybe_migrate()` copies `bbtc_*` tables → `bdm_tc_*` once (INSERT IGNORE, same IDs), migrates order options; `register_notices()` wires migration-success + legacy-guard notices onto `bdm_admin_notices`
- `Module::boot()` now calls `Installer::maybe_upgrade()`, `Migration::maybe_migrate()`, `Migration::register_notices()`, then wires `Profile_Nav::init()` (members) and `Group_Nav::init()` (groups) on front end
- `uninstall.php` now drops only `bdm_tc_*` tables and options
- All 16 legacy `bbtc_*` PHP files, `assets/js/admin.js`, `assets/css/admin.css`, and `buddyboss-tab-creator.php` deleted

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
- (2026-11-14, found while debugging F15/F16; all same as legacy) Profile_Nav registers sub-tabs of built-in profile tabs twice (register_subtabs prio 30 + apply_existing_overrides 2nd loop prio 50; later wins, "inherit" handled differently). Group_Visibility::can_view_tab skips only `do_not_modify`, Profile_Visibility also skips `none_remove`. Profile_Nav::render_screen's bp_action_variable(0) sub-tab branch never runs on profiles (sub-tab arrives as current_action).

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
| 2026-11-13 | P7 | Sonnet 4.6 | Profile_Nav, Profile_Visibility, Group_Nav, Group_Visibility ported to BuddyModules\TabCreator namespace with bdm_tc_* transients/filters/options (same hook priorities as legacy). Migration class: copies bbtc_* tables → bdm_tc_* once (INSERT IGNORE, preserves IDs), migrates order options, fires bdm_admin_notices success notice. Legacy-active guard notice for buddyboss-tab-creator. Module::boot() wires nav classes on front end. All 16 legacy bbtc_ PHP files + assets/js/admin.js + assets/css/admin.css + buddyboss-tab-creator.php deleted. uninstall.php rewritten for bdm_* only. npm build green (6 Dart Sass warnings = pre-existing), php -l clean. Commit 609a427 Verified. Done-when plugin-switch check pending (tell Rezwan to switch plugins). |
| 2026-11-13 | P8 (partial) | Sonnet 4.6 | UI polish via 4 parallel sub-agents: (1) Removed enable_profile_tabs toggle + empty general sections from Module.php. (2) Responsive table CSS in _tabs-list.scss (nth-child media queries for tablet ≤1024px and mobile ≤782px). (3) Design tokens updated to match Platform 3.0 (transition 0.3s, button padding, border-radius, 6 new tokens). (4) Modal popup refactor: TabCreatorPanel/ExistingTabCreatorPanel own modal state, editors use onSaved/onCancel callbacks, delete confirmation uses @wordpress/components Modal. Build: 6 pre-existing Sass warnings, 0 errors. Uncommitted — tell Rezwan to run GPG passphrase for P8 commit. |
| 2026-11-14 | P7 bugfix | Sonnet 4.6 | Fixed root-cause: Profile_Nav::init() / Group_Nav::init() were called at bp_init priority 10 (the default), AFTER bp_setup_nav fires at priority 6 — so all our bp_setup_nav hooks were registered too late to run. Fix: `add_action('bp_init', [...'load_and_boot_modules'], 5)` in Plugin.php (priority 5: after bp_setup_globals at P4, before bp_setup_nav at P6). Removed temporary debug error_log calls from Profile_Nav::register_tabs(). php -l clean on both files. Browser verified: custom "About" tab now appears on http://prod.local/members/test/. P7 done-when check ticked. |
| 2026-11-14 | P8 fix | Opus 5.5 | Platform look-and-feel parity (F1–F11): full-bleed module screen + 248px full-height sidebar, outlined Back button, white active nav item, bb-icons-rl line icons, header text-only outlined box, white 16px-radius cards with header row, Platform list table + outlined pills + label link, ⋯ row menu (RowActions), 8px buttons, modal header bug fixed (`animation:none` removed the frame's containing block → `position:relative`), modal fields now styled (portal outside .bdm-admin-wrap), flat sections + sticky footer, confirm modal order/width, Platform toggles; F12 modal fields as Platform settings rows (FieldRow); F13 list columns trimmed to Label/Type/Status/Sub-tabs, 985px cards, sub-tab ⋯ menu. Build green, lint:css 0 new, php -l clean, browser-verified desktop + mobile. |
| 2026-11-14 | P8 completion | Opus 5.5 | Parity QA (front end via HTTP as admin/member/group admin/logged out + REST): F19 save no longer wipes hidden fields/visibility/position (status toggle blanked label+rules on a4fd1b2), F20 group Members/Mods/Admins arg order, F21 slug clash guard vs Platform slugs. Standards vs Platform (F22): PHPCS 708→0 (ruleset mirrors Platform), ESLint ~1,014→0 errors (TS pinned ~5.5.4), Stylelint 60→0, RoleSelect extracted, WP_DEBUG 0 notices. F23 RTL stylesheet loaded, F24 textdomain + buddy-modules.pot (163 strings incl. JS), F25 keyboard/a11y fixes + RTL/390px visual check. Test leftovers removed. Built-in sub-tabs dropped (Rezwan). |
| 2026-11-14 | P8 (continued) | Sonnet 4.6 | Modal size/flash: fixed CSS selector from descendant to combined (.bdm-admin-tab-editor-modal.components-modal__frame, no space) — WP places className on the same element as components-modal__frame. Width 600px !important, animation: none !important fixes the small→full flash. Removed "Set as Default Tab" and "Site Admin Only" toggles from TabEditor + ExistingTabEditor entirely (Rezwan confirmed). Replaced 3 flat checkbox grids (availability/enabled_for/visibility) with RoleSelect chip-toggle dropdown component in TabEditor + ExistingTabEditor — inline RoleSelect function, .bdm-admin-role-select SCSS block. WP component input field border override in admin.scss: $bdm-border-dark border, $bdm-brand focus ring, no box-shadow, uppercase label. npm build green (6 Dart Sass warnings = pre-existing). Playwright verified: modal 600px compact, chip dropdown open with "All Members" selected orange. All P8 UI changes uncommitted — need GPG passphrase from Rezwan for P8 commit. |

