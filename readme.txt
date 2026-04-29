=== Recipes ===
Contributors: akirk
Tags: recipes, cookbook, food, schema, import
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A personal cookbook: store, categorize, scale and import recipes from the web.

== Description ==

Recipes is a personal cookbook plugin built on the WpApp framework. It lives at /recipes/ on your site, separate from your theme.

Features:

* Native WordPress storage — recipe custom post type, plus category, cuisine and tag taxonomies.
* Metric / imperial unit conversion at display time, with a per-user preference.
* Live portion scaling — type the number of servings you want and amounts rescale instantly.
* Import from URL or pasted text using schema.org Recipe JSON-LD, with a plain-text fallback. Photos are sideloaded into the media library.
* One-click save from any browser tab via the Friends browser extension.
* Dark mode via CSS light-dark().

== Installation ==

1. Place the plugin folder in `/wp-content/plugins/recipes`.
2. Run `composer install` inside the folder.
3. Activate the plugin and visit `/recipes/`.

== Changelog ==

= 1.0.0 =
* Initial release.
