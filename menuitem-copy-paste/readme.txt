=== MenuItem Copy & Paste ===
Contributors: sarap422
Tags: menu, navigation, copy, paste, clone
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Copy, paste, clone, delete & collapse menu items, duplicate & export/import whole menus (JSON), and use shortcodes in menu items.

== Description ==

MenuItem Copy & Paste enhances the WordPress menu editing interface by adding five quick action buttons to each menu item, making menu management significantly more efficient.

= Key Features =

* **➕ New**: Instantly create a new custom link below the selected menu item
* **📒 Clone**: Duplicate a menu item immediately below itself
* **🔗 Copy**: Copy menu item data to clipboard with visual highlight
* **📋 Paste**: Paste copied items at the desired position
* **🗑️ Delete**: Remove menu items without opening accordions
* **▲▼ Collapse**: Fold/unfold child menu items under a parent (state saved in localStorage)

= Perfect For =

* Creating similar menu structures frequently
* Managing large numbers of menu items efficiently
* Quickly reorganizing menu layouts
* Editing menus without accordion interactions

= Features =

* Simple and intuitive UI
* Delete items without page reload (with fade-out animation)
* Support for all menu item types (pages, posts, custom links, taxonomies, etc.)
* Accurate position calculation considering hierarchical structure
* Preserves all metadata (URL, classes, attributes, etc.)
* Uses WordPress standard Dashicons

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/menuitem-copy-paste` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Appearance → Menus and start using the new buttons

== Frequently Asked Questions ==

= Which WordPress versions are supported? =

WordPress 5.0 or higher. Tested up to the latest version (7.0).

= Can deleted items be restored? =

When you click the delete button, a confirmation dialog appears. Once deleted, items cannot be restored. However, if you don't click "Save Menu" in WordPress, you can reload the page to revert changes.

= Can I paste copied items to different menus? =

Yes. After copying a menu item, you can switch to a different menu and paste it there.

= What are the default values for items created with the New button? =

Title: "New Custom Link", URL: "/". You can edit these after creation by opening the accordion.

= Can I copy child menu items? =

The current version copies individual menu items only. The Clone button duplicates only the selected item, excluding child hierarchies.

= What if the buttons don't appear? =

Clear your browser cache. If they still don't appear, there may be a conflict with other plugins. Try temporarily disabling other plugins to identify the issue.

== Screenshots ==

1. Five buttons added to the menu editing interface (New, Clone, Copy, Paste, Delete)
2. Visual highlight of copied menu items
3. Confirmation dialog when deleting

== Changelog ==

= 1.1.1 =
* Updated "Tested up to" to WordPress 7.0
* Security/coding-standards: made AJAX nonce verification explicit in each handler (menu duplicate/export/import) so it is visible to static analysis
* Documented JSON request handling for the paste and import endpoints (validated via json_decode and sanitized per-field)

= 1.1.0 =
* Added Shortcode support: shortcodes now run in menu item URL, navigation label, and description (no extra metabox required; theme-defined shortcodes are executed)
* Added Duplicate Menu: clone an entire menu (hierarchy preserved) from the editor, next to "Save Menu"; auto-named "{name}_copied"
* Added per-menu JSON Export ("Export Menu" button, top-right of the menu editor)
* Added JSON Import ("Import Menu" button): rebuilds a menu as a new menu; page/category items are re-resolved by slug on the target site, enabling cross-site migration of template menus

= 1.0.7 =
* Added Collapse feature: fold/unfold child menu items under any parent menu item
* Collapse state is persisted per-menu in localStorage and restored on page reload
* Auto-expansion before drag to safely move parents with their descendants
* Toggle button auto-shows/hides based on whether the item has children
* Adjusted button container width to accommodate the 6th button

= 1.0.6 =
* Added New feature: instantly create custom links below items
* Added Clone feature: duplicate menu items immediately below
* Added Delete feature: remove items without opening accordions
* Added visual highlight (blue background) for copied items
* Improved button tooltips with detailed descriptions
* Enhanced security: strengthened $_POST variable validation
* Improved code quality: compliance with WordPress Coding Standards

= 1.0.4 =
* Added accurate position calculation considering hierarchical structure
* Implemented recursive descendant retrieval

= 1.0.3 =
* Initial release
* Menu item copy & paste functionality

== Upgrade Notice ==

= 1.1.1 =
Maintenance release: WordPress 7.0 compatibility header and coding-standards hardening of AJAX nonce checks. No functional changes.

= 1.1.0 =
Adds shortcode support in menu items, whole-menu duplication, and per-menu JSON export/import (with slug-based re-resolution for cross-site migration).

= 1.0.7 =
Added a collapse/fold feature for parent menu items. Manage large menus more efficiently by hiding child items under their parents. State persists across reloads.

= 1.0.6 =
New creation, cloning, and deletion features added. Menu editing is now even more efficient. Security and code quality improvements included.

== Support ==

If you have any issues or feature requests, please let us know in the plugin support forum.

== Development ==

This plugin will also be available on GitHub. Contributions and issue reports are welcome.
