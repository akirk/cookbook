# Cookbook

A personal cookbook for WordPress. Store, import, categorize, cook, scale, convert, plan, and shop from your own recipes.

Built on the [WpApp framework](https://github.com/akirk/wp-app), so the app lives at `/cookbook/` on your site, separate from your theme, with WordPress users, capabilities, and admin bar.

## Features

- **Native WordPress storage.** Every recipe is a `cb-recipes` custom post type with `recipe_category` (hierarchical), `recipe_cuisine` (hierarchical), `recipe_tag` (flat), and `recipe_ingredient` taxonomies, plus structured post meta for ingredients, instructions, servings, prep/cook times, source URL, and notes. Shopping lists and week plans are separate user-authored CPTs.
- **Import from the web.** Paste a URL, the plugin extracts schema.org `Recipe` JSON-LD (handling `@graph`, `HowToSection`, and `ImageObject`) or HTML microdata. Plain-text paste import includes a live checklist preview for detected title, ingredients, and instructions. Photos are sideloaded into the WordPress media library and set as the recipe's featured image.
- **Browser-extension import.** The [Friends browser extension](https://github.com/akirk/browser-extension) can POST the current page HTML to the site through a "Save as Recipe" action, where the importer turns it into a draft recipe.
- **Recipe refetch.** Imported recipes keep their source URL and can be re-parsed later without replacing notes, tags, or publication status.
- **Metric ⇄ Imperial.** Recipes are stored in their original units; conversion happens on display. Set your preference in `/cookbook/settings`, or flip the live toggle on any recipe page. Unit aliases cover English and German (`EL`, `TL`, `Stk`, `Prise`, `Bund`, ...).
- **Live portion scaling.** Type the number of servings you want and every parsed amount rescales and reconverts immediately, client-side. The same scaled quantities flow into shopping-list adds and cooking mode.
- **Cooking mode.** Recipe pages can open a focused cooking view with a large active step, step navigation, ingredient and step checkoffs, progress state saved in the browser, and screen wake lock where supported.
- **Ingredient tools.** Browse recipes by ingredients you have, allow a configurable number of missing ingredients, browse individual ingredient pages, replace an ingredient from the recipe view, and merge/group/rename ingredient terms.
- **Shopping list.** Add a recipe's scaled ingredients to your personal shopping list, combine compatible duplicate items, edit the list at home, and use a focused shop mode with large tap targets, hide-checked, undo, and quick add.
- **Week planner.** Plan breakfast, lunch, and dinner for a week, prefill a recipe into the planner from its recipe page, then add the planned recipes' ingredients to your shopping list.
- **Recipe variations.** Link recipes as parent/child variations, browse the variation family from parent or child recipe pages, and use **Edit as variation** to create a prefilled child recipe from an existing one.
- **Recipe editing.** Create recipes manually, edit structured ingredients and instructions, categorize by category/cuisine/tags, add notes, replace photos through file upload or image URL, and remove photos.
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
| `/cookbook/new?variation_of={id}` | Create a prefilled recipe variation        |
| `/cookbook/import`               | Paste a URL or recipe text to import       |
| `/cookbook/shopping-list`        | Personal shopping list                     |
| `/cookbook/planner`              | Weekly meal planner                        |
| `/cookbook/by-ingredients`       | Find recipes by ingredients on hand        |
| `/cookbook/manage-ingredients`   | Merge, group, and rename ingredients       |
| `/cookbook/recipe/{id}`          | View                                       |
| `/cookbook/recipe/{id}/edit`     | Edit                                       |
| `/cookbook/category/{slug}`      | Browse by category                         |
| `/cookbook/tag/{slug}`           | Browse by tag                              |
| `/cookbook/ingredient/{slug}`    | Browse by ingredient                       |
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
  recipe.php          Recipe view (portion scaling, unit toggle, cooking mode)
  recipe-edit.php     Edit existing
  new.php             Create new
  _form.php           Shared edit form
  import.php          Paste URL or text
  shopping-list.php   User-authored shopping list CPT view
  planner.php         User-authored week plan CPT view
  by-ingredients.php  Ingredient-on-hand search
  manage-ingredients.php  Ingredient term maintenance
  settings.php        User preferences
  category.php tag.php ingredient.php  Taxonomy archives
```

Forms POST to `admin-post.php` with WordPress nonces. The browser-extension endpoint hooks `wp_loaded` and reads `?cookbook-collect={url}` with `body={page_html}` in the POST body.

## Standards

```bash
wp plugin check cookbook       # WordPress.org Plugin Check
composer phpcs                 # WordPress Coding Standards (via wp-app)
```

## License

GPL-2.0-or-later
