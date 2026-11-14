=== Buddy Modules ===
Contributors: rezwan2024
Tags: buddyboss, profile tabs, group tabs, community
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A module host for BuddyBoss sites. Includes the Profile and Group Tab Creator.

== Description ==

Buddy Modules adds optional modules to a BuddyBoss Platform site. Each module can be turned on or off from **Buddy Modules** in wp-admin, and has its own settings screen.

**Profile and Group Tab Creator**

* Add custom tabs and sub-tabs to member profiles and groups, with your own content (shortcodes supported).
* Choose who gets a tab: user roles or chosen members on profiles; group status, chosen groups or excluded groups on groups.
* Rename, hide and reorder the built-in BuddyBoss profile and group tabs.
* Drag to reorder, bulk activate, deactivate or delete, and keep tabs as drafts.

Buddy Modules 2.0 replaces the legacy "BuddyBoss Tab Creator" plugin. Your existing tabs, rules and built-in tab changes are copied over once; the legacy data is left untouched.

**Requirements:** BuddyBoss Platform 2.8 or newer.

== Installation ==

1. Download the latest `buddy-modules.zip`: https://github.com/rezwan2024/buddy-modules/releases/latest/download/buddy-modules.zip
2. In wp-admin, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Buddy Modules**, then open **Buddy Modules** in the admin menu.

Updates appear on the **Plugins** and **Dashboard → Updates** screens like any other plugin, and install with one click.

== Frequently Asked Questions ==

= Where do updates come from? =

From this plugin's GitHub releases. WordPress checks about twice a day; use **Check for updates** under the plugin on the Plugins screen to check now.

= What does the update check send? =

The check goes through the Buddy Modules update service (a Cloudflare Worker), which returns the latest GitHub release and counts active installs. Each check sends a one-way hash of your site address (not the address itself), the Buddy Modules, WordPress and PHP versions, and your site language. Nothing else is sent, and no cookies are set. The counts are only used to see how many sites run each version.

= Does uninstalling remove the legacy Tab Creator data? =

No. Uninstalling Buddy Modules removes only its own tables and options.

== Changelog ==

= 2.0.6 =
* Readme: website link and a clearer introduction (free and open source, more modules on the way). No functional changes.

= 2.0.5 =
* Readme: screenshots of the Buddy Modules and Tab Creator screens. No functional changes.

= 2.0.4 =
* Clearer readme: download link at the top and an easier-to-read changelog. No functional changes.

= 2.0.3 =
* Readme updates on GitHub. No functional changes.

= 2.0.2 =
* Update checks now go through the Buddy Modules update service, which counts active installs using a one-way hash of the site address.

= 2.0.1 =
* Test release to check the update from GitHub releases on the WordPress Plugins screen. No functional changes.

= 2.0.0 =
* New: Buddy Modules, the in-place upgrade of the legacy BuddyBoss Tab Creator 1.0.0, with the Profile and Group Tab Creator as its first module.
* New: React admin in the BuddyBoss Platform style: module cards with on/off toggles, side navigation, list tables, modal editors and toasts, with no page reloads.
* New: Built-in tabs list to rename, hide and reorder BuddyBoss profile and group tabs.
* New: Existing Tab Creator tabs, rules and overrides are migrated once (legacy data is kept).
* New: Updates from GitHub releases through the standard WordPress update screen.
* Fix: Saving a tab no longer resets fields that the editor does not show.
* Fix: Group tab visibility for members, moderators and admins now checks the right group and user.
* Fix: A new tab can no longer take a BuddyBoss or sibling tab slug.
* Fix: Profile sub-tab navigation now shows under a custom tab that has only one sub-tab.
* Fix: Custom group tabs show for logged-out visitors on public groups.
* Fix: Line breaks in tab content are kept on the front end.
* Improvement: RTL stylesheet, JavaScript and PHP translations (`buddy-modules.pot`), and keyboard access in menus and modals.
