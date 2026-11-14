# Buddy Modules

Optional modules for BuddyBoss Platform sites. Turn each module on or off from **Buddy Modules** in wp-admin; every module has its own settings screen.

## Modules

### Profile and Group Tab Creator

- Add custom tabs and sub-tabs to member profiles and groups, with your own content (shortcodes supported).
- Choose who gets a tab: user roles or chosen members on profiles; group status, chosen groups or excluded groups on groups.
- Rename, hide and reorder the built-in BuddyBoss profile and group tabs.
- Drag to reorder, bulk activate, deactivate or delete, and keep tabs as drafts.

Buddy Modules 2.0 replaces the legacy "BuddyBoss Tab Creator" plugin. Your existing tabs, rules and built-in tab changes are copied over once; the legacy data is left untouched.

## Requirements

- WordPress 6.4 or newer
- PHP 8.0 or newer
- BuddyBoss Platform 2.8 or newer

## Install

1. Download **`buddy-modules.zip`** from the [latest release](https://github.com/rezwan2024/buddy-modules/releases/latest). Don't use the "Source code" downloads.
2. In wp-admin, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Buddy Modules**, then open **Buddy Modules** in the admin menu.

## Updates

New versions appear on the **Plugins** and **Dashboard → Updates** screens like any other plugin and install with one click. WordPress checks about twice a day; click **Check for updates** under the plugin on the Plugins screen to check now.

## Privacy

Update checks go through the Buddy Modules update service, which returns the latest release and counts active installs. Each check sends a one-way hash of your site address (not the address itself), the Buddy Modules, WordPress and PHP versions, and your site language. Nothing else is sent, and no cookies are set.

To check GitHub directly and send nothing, add this to a small plugin or your theme's `functions.php`:

```php
add_filter( 'bdm_send_update_stats', '__return_false' );
```

## Changelog

See [`readme.txt`](readme.txt) → `== Changelog ==`, or the [releases page](https://github.com/rezwan2024/buddy-modules/releases).

## License

GPLv2 or later. The bundled Plugin Update Checker is MIT-licensed (`lib/plugin-update-checker/license.txt`).
