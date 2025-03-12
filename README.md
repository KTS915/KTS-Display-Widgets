# KTS Display Widgets
Author:            Tim Kaye

Version:           0.2.0

Requires CP:       2.1

Requires at least: 6.2.3

Requires PHP:      7.4

## Description
Simply show or hide widgets on specified pages. Adds checkboxes to each widget to either show or hide it on specific posts, pages, archives, etc.

Change your sidebar content for different pages, categories, custom taxonomies, and WPML languages. Avoid creating multiple sidebars and duplicating widgets by adding check boxes to each widget in the admin which will either show or hide the widgets on every site page. Great for avoiding extra coding and keeping your sidebars clean.

By default, `Hide on checked pages` is selected with no boxes checked, so all current widgets will continue to display on all pages.

This plugin is based on Display Widgets by Stephanie Wells, but has been substantially re-written to improve performance and accessibility and to eliminate any dependency on jQuery.

### Versions
#### Version 0.2
- Significant security enhancements
- Elimination of custom walker for pages
- Nonce check added for form submission
- Outdated code replaced
- Missing text domain added where appropriate

#### Version 0.1
- Initial fork of Display Widgets plugin
- JavaScript accordions replaced with native HTML disclosure widget
- Non-semantic `div`s replaced with semantic `ul` and `li` elements
- Sanitization and escaping added
- JavaScript rewritten and enqueued properly
- CSS enqueued properly

### Translations
* Arabic
* Danish
* German
* Spanish
* Finnish
* Italian
* Japanese
* Dutch
* Polish
* Romanian
* Russian
* Albanian
* Swedish
* Chinese

## Installation
1. Upload the `kts-display-widgets` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. Go to the `Widgets` menu and show the options panel for the widget you would like to hide.
4. Select either `Show on checked pages` or `Hide on checked pages` from the drop-down and check the boxes.
