# MenuItem Copy & Paste

![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/menuitem-copy-paste?label=WordPress%20Plugin)
![WordPress Plugin: Tested WP Version](https://img.shields.io/wordpress/plugin/tested/menuitem-copy-paste)
![WordPress Plugin Required PHP Version](https://img.shields.io/wordpress/plugin/required-php/menuitem-copy-paste)
![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/menuitem-copy-paste)
![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/stars/menuitem-copy-paste)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/sarap422/wp-plugin-menuitem-copy-paste)
![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)
![GitHub last commit](https://img.shields.io/github/last-commit/sarap422/wp-plugin-menuitem-copy-paste)

Easily copy, paste, clone, and delete menu items in the WordPress menu editor.

**[📥 Download from WordPress.org](https://wordpress.org/plugins/menuitem-copy-paste/)** | **[📖 Documentation](https://github.com/sarap422/wp-plugin-menuitem-copy-paste/wiki)** | **[🐛 Report Issues](https://github.com/sarap422/wp-plugin-menuitem-copy-paste/issues)**

---

## ✨ Features

MenuItem Copy & Paste enhances the WordPress menu editing interface by adding five quick action buttons to each menu item, making menu management significantly more efficient.

- **➕ New**: Instantly create a new custom link below the selected menu item
- **📒 Clone**: Duplicate a menu item immediately below itself
- **🔗 Copy**: Copy menu item data to clipboard with visual highlight
- **📋 Paste**: Paste copied items at the desired position
- **🗑️ Delete**: Remove menu items without opening accordions

### Perfect For

- Creating similar menu structures frequently
- Managing large numbers of menu items efficiently
- Quickly reorganizing menu layouts
- Editing menus without accordion interactions

### Additional Features

- Simple and intuitive UI
- Delete items without page reload (with fade-out animation)
- Support for all menu item types (pages, posts, custom links, taxonomies, etc.)
- Accurate position calculation considering hierarchical structure
- Preserves all metadata (URL, classes, attributes, etc.)
- Uses WordPress standard Dashicons

---

## 📸 Screenshots

![Five buttons added to menu items](https://ps.w.org/menuitem-copy-paste/assets/screenshot-1.png)
*Five buttons added to the menu editing interface (New, Clone, Copy, Paste, Delete)*

![Visual highlight of copied items](https://ps.w.org/menuitem-copy-paste/assets/screenshot-2.png)
*Visual highlight of copied menu items*

![Confirmation dialog](https://ps.w.org/menuitem-copy-paste/assets/screenshot-3.png)
*Confirmation dialog when deleting*

---

## 📥 Installation

### From WordPress.org (Recommended)

1. Go to **WordPress Admin → Plugins → Add New**
2. Search for **"MenuItem Copy & Paste"**
3. Click **"Install Now"** and then **"Activate"**
4. Go to **Appearance → Menus** to start using the new buttons

### Manual Installation

1. Download the latest release from the [releases page](https://github.com/sarap422/wp-plugin-menuitem-copy-paste/releases)
2. Upload the plugin files to `/wp-content/plugins/menuitem-copy-paste/`
3. Activate the plugin through the **'Plugins'** menu in WordPress
4. Go to **Appearance → Menus** to start using the new buttons

### From GitHub

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/sarap422/wp-plugin-menuitem-copy-paste.git menuitem-copy-paste
```

Then activate the plugin through the WordPress admin panel.

---

## 🔧 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Tested up to**: WordPress 6.8

---

## ❓ Frequently Asked Questions

### Which WordPress versions are supported?

WordPress 5.0 or higher. Tested up to the latest version (6.8).

### Can deleted items be restored?

When you click the delete button, a confirmation dialog appears. Once deleted, items cannot be restored. However, if you don't click "Save Menu" in WordPress, you can reload the page to revert changes.

### Can I paste copied items to different menus?

Yes. After copying a menu item, you can switch to a different menu and paste it there.

### What are the default values for items created with the New button?

Title: "New Custom Link", URL: "/". You can edit these after creation by opening the accordion.

### Can I copy child menu items?

The current version copies individual menu items only. The Clone button duplicates only the selected item, excluding child hierarchies.

### What if the buttons don't appear?

Clear your browser cache. If they still don't appear, there may be a conflict with other plugins. Try temporarily disabling other plugins to identify the issue.

---

## 📝 Changelog

### 1.0.6 (2025-11-04)

- ➕ Added **New** feature: instantly create custom links below items
- 📒 Added **Clone** feature: duplicate menu items immediately below
- 🗑️ Added **Delete** feature: remove items without opening accordions
- 🎨 Added visual highlight (blue background) for copied items
- 📋 Improved button tooltips with detailed descriptions
- 🔒 Enhanced security: strengthened `$_POST` variable validation
- ✅ Improved code quality: compliance with WordPress Coding Standards

### 1.0.4

- Added accurate position calculation considering hierarchical structure
- Implemented recursive descendant retrieval

### 1.0.3

- Initial release
- Menu item copy & paste functionality

[View full changelog →](https://github.com/sarap422/wp-plugin-menuitem-copy-paste/releases)

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

---

## 🔗 Links

- **WordPress.org Plugin Page**: https://wordpress.org/plugins/menuitem-copy-paste/
- **Support Forum**: https://wordpress.org/support/plugin/menuitem-copy-paste/
- **GitHub Repository**: https://github.com/sarap422/wp-plugin-menuitem-copy-paste
- **Author**: [sarap422](https://github.com/sarap422)

---

## 💖 Support

If you find this plugin helpful, please:

- ⭐ Star this repository
- 🐛 [Report issues](https://github.com/sarap422/wp-plugin-menuitem-copy-paste/issues)
- 📝 [Write a review on WordPress.org](https://wordpress.org/support/plugin/menuitem-copy-paste/reviews/)
- 💬 Share with others who might find it useful

---

**Made with ❤️ by [sarap422](https://github.com/sarap422)**