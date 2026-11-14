# Buddy Modules

Optional modules for BuddyBoss Platform sites. Turn each module on or off from **Buddy Modules** in wp-admin; every module has its own settings screen.

![Buddy Modules screen in wp-admin: module cards with an on/off toggle and a Settings button](https://raw.githubusercontent.com/rezwan2024/buddy-modules/dev/docs/screenshots/dashboard.png)

## Download

**[⬇ Download the latest version (buddy-modules.zip)](https://github.com/rezwan2024/buddy-modules/releases/latest/download/buddy-modules.zip)**

This link always gives the newest release. Older versions and release notes: [all releases](https://github.com/rezwan2024/buddy-modules/releases).

Don't use GitHub's green **Code → Download ZIP** button or the "Source code" files on the releases page. Those are not installable plugins.

## Install

1. Download **buddy-modules.zip** with the link above.
2. In wp-admin, go to **Plugins → Add New → Upload Plugin**, choose the zip and click **Install Now**.
3. Click **Activate**, then open **Buddy Modules** in the admin menu.

To update an older copy by hand, upload the new zip the same way and choose **Replace current with uploaded**.

## Updates

After installing, new versions appear on the **Plugins** and **Dashboard → Updates** screens like any other plugin and install with one click. WordPress checks about twice a day; open **Dashboard → Updates** or click **Check for updates** under the plugin to check now.

## Modules

### Profile and Group Tab Creator

- Add custom tabs and sub-tabs to member profiles and groups, with your own content (shortcodes supported).
- Choose who gets a tab: user roles or chosen members on profiles; group status, chosen groups or excluded groups on groups.
- Rename, hide and reorder the built-in BuddyBoss profile and group tabs.
- Drag to reorder, bulk activate, deactivate or delete, and keep tabs as drafts.

![Profile and Group Tab Creator settings: Profile Tabs list with type, status and sub-tab count, and the Group Tabs and Built-in Tabs sections in the side menu](https://raw.githubusercontent.com/rezwan2024/buddy-modules/dev/docs/screenshots/tab-creator.png)

Buddy Modules 2.0 replaces the legacy "BuddyBoss Tab Creator" plugin. Your existing tabs, rules and built-in tab changes are copied over once; the legacy data is left untouched.

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer
- BuddyBoss Platform 2.8 or newer

## Privacy

Update checks go through the Buddy Modules update service, which returns the latest release and counts active installs. Each check sends a one-way hash of your site address (not the address itself), the Buddy Modules, WordPress and PHP versions, and your site language. Nothing else is sent, and no cookies are set.

## Changelog

### Version 2.0.5

- **Readme:** screenshots of the Buddy Modules and Tab Creator screens. No functional changes.

### Version 2.0.4

- Clearer readme: download link at the top and an easier-to-read changelog. No functional changes.

### Version 2.0.3

- Readme updates on GitHub. No functional changes.

### Version 2.0.2

- Update checks now go through the Buddy Modules update service, which counts active installs using a one-way hash of the site address.

### Version 2.0.1

- Test release to check the update from GitHub releases on the WordPress Plugins screen. No functional changes.

### Version 2.0.0

- **New:** Buddy Modules, the in-place upgrade of the legacy BuddyBoss Tab Creator 1.0.0, with the Profile and Group Tab Creator as its first module.
- **New:** React admin in the BuddyBoss Platform style: module cards with on/off toggles, side navigation, list tables, modal editors and toasts, with no page reloads.
- **New:** Built-in tabs list to rename, hide and reorder BuddyBoss profile and group tabs.
- **New:** Existing Tab Creator tabs, rules and overrides are migrated once (legacy data is kept).
- **New:** Updates from GitHub releases through the standard WordPress update screen.
- **Fix:** Saving a tab no longer resets fields that the editor does not show.
- **Fix:** Group tab visibility for members, moderators and admins now checks the right group and user.
- **Fix:** A new tab can no longer take a BuddyBoss or sibling tab slug.
- **Fix:** Profile sub-tab navigation now shows under a custom tab that has only one sub-tab.
- **Fix:** Custom group tabs show for logged-out visitors on public groups.
- **Fix:** Line breaks in tab content are kept on the front end.
- **Improvement:** RTL stylesheet, JavaScript and PHP translations (`buddy-modules.pot`), and keyboard access in menus and modals.

## License

GPLv2 or later. The bundled Plugin Update Checker is MIT-licensed.
