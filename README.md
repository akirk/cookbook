# Cookbook

A personal cookbook for WordPress. Store, categorize, scale and convert recipes — and import them from the web with one click.

Built on the [WpApp framework](https://github.com/akirk/wp-app), so the app lives at `/cookbook/` on your site, separate from your theme, with WordPress users, capabilities, and admin bar.

## Features

- **Native WordPress storage.** Every recipe is a `cb-recipes` custom post type with `recipe_category` (hierarchical), `recipe_cuisine` (hierarchical) and `recipe_tag` (flat) taxonomies, plus structured post meta for ingredients, instructions, servings, prep/cook times, source URL and notes.
- **Metric ⇄ Imperial.** Recipes are stored in their original units; conversion happens on display. Set your preference in `/cookbook/settings`, or flip the live toggle on any recipe page. Unit aliases cover English and German (`EL`, `TL`, `Stk`, `Prise`, `Bund`, …).
- **Live portion scaling.** Type the number of servings you want and every parsed amount rescales and reconverts immediately, client-side.
- **Import from the web.** Paste a URL, the plugin extracts schema.org `Recipe` JSON-LD (handling `@graph`, `HowToSection`, `ImageObject`); paste plain text and a fallback parser splits ingredients and instructions from `Ingredients` / `Method` / `Directions` headers. Photos are sideloaded into the WordPress media library and set as the recipe's featured image.
- **One-click save from any browser tab** via the [Friends browser extension](https://github.com/akirk/browser-extension): a "Save as Recipe" action POSTs the current page's HTML to the site, where the importer turns it into a draft recipe.
- **Replace photos** through the edit form (file upload + remove checkbox).
- **Dark mode** via CSS `light-dark()`, respects the WpApp masterbar's dark-mode toggle.
- **Translatable** with the `cookbook` text domain.

## Install

```bash
cd wp-content/plugins
git clone <this-repo> cookbook
cd cookbook
composer install
```

Then activate **Cookbook** in WordPress and visit `/cookbook/`.

## Usage

| URL                              | Page                                       |
| -------------------------------- | ------------------------------------------ |
| `/cookbook/`                     | All recipes                                |
| `/cookbook/new`                  | Create a recipe                            |
| `/cookbook/import`               | Paste a URL or recipe text to import       |
| `/cookbook/recipe/{id}`          | View                                       |
| `/cookbook/recipe/{id}/edit`     | Edit                                       |
| `/cookbook/category/{slug}`      | Browse by category                         |
| `/cookbook/tag/{slug}`           | Browse by tag                              |
| `/cookbook/settings`             | Choose metric or imperial                  |

### Browser-extension import

Install the [Friends browser extension](https://github.com/akirk/browser-extension), authorise it for your site, and a **Save as Recipe** action will appear in the popup. Open any recipe page in your browser, click the action, and the importer will create a draft you can review.

The integration uses the `friends_browser_extension_actions` filter — same pattern as the [Post Collection](https://wordpress.org/plugins/post-collection/) plugin.

## Architecture

```
cookbook.php          Plugin bootstrap
src/
  App.php             BaseApp subclass: CPT, taxonomies, routes, admin-post handlers
  Importer.php        URL fetch + JSON-LD extraction + text-parse fallback
  Units.php           Mass/volume conversion, unit aliases, formatting
templates/
  index.php           Recipe list
  recipe.php          Recipe view (portion scaling + unit toggle)
  recipe-edit.php     Edit existing
  new.php             Create new
  _form.php           Shared edit form
  import.php          Paste URL or text
  settings.php        User preferences
  category.php tag.php  Taxonomy archives
```

Forms POST to `admin-post.php` with WordPress nonces. The browser-extension endpoint hooks `wp_loaded` and reads `?cookbook-collect={url}` with `body={page_html}` in the POST body.

## Standards

```bash
wp plugin check cookbook       # WordPress.org Plugin Check
composer phpcs                 # WordPress Coding Standards (via wp-app)
```

## License

GPL-2.0-or-later
