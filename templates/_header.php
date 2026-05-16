<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Shared header partial for the Cookbook app.
 *
 * Templates include this near the top to set up <head>, masterbar, and the
 * outer page chrome. Pair it with templates/_footer.php.
 */
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( isset( $page_title ) ? $page_title : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_app_title escapes. ?></title>
    <?php wp_app_head(); ?>
    <style>
        :root {
            color-scheme: light dark;
            --bg:    light-dark(var(--wp-app-color-background, #f6f7f7), #1a1a1a);
            --fg:    light-dark(var(--wp-app-color-text, #1d2327), #ececec);
            --muted: light-dark(var(--wp-app-color-muted, #646970), #a8a8a8);
            --line:  light-dark(var(--wp-app-color-border, #dcdcde), #333);
            --accent: var(--wp-app-color-primary, #3858e9);
            --accent-hover: var(--wp-app-color-primary-hover, var(--wp-app-color-accent, var(--accent)));
            --accent-highlight: var(--wp-app-admin-color-accent, var(--accent-hover));
            --accent-fg: #fff;
            --card:  light-dark(var(--wp-app-color-surface, #fff), #232323);
            --input-bg:     light-dark(var(--wp-app-color-surface, #fff), #1a1a1a);
            --input-border: light-dark(var(--wp-app-color-border, #dcdcde), #444);
            --secondary-bg: light-dark(var(--wp-app-color-secondary, #f0f0f1), #2a2a2a);
            --secondary-fg: light-dark(var(--wp-app-color-secondary-text, #1d2327), #ddd);
            --notice-bg:    light-dark(var(--wp-app-color-secondary, #f0f0f1), #2a2a2a);
            --notice-bd:    light-dark(var(--wp-app-color-border, #dcdcde), #444);
            --error-bg:     light-dark(#fdecea, #3d2424);
            --error-bd:     light-dark(#f5c2bd, #7a3a3a);
            --success-bg:   light-dark(#e8f5e9, #1f3621);
            --success-bd:   light-dark(#b6dab8, #3f6b42);
            --fresh:        var(--accent);
            --fresh-fg:     var(--accent-fg);
            --household:    var(--accent-highlight);
            --household-fg: var(--accent-fg);
            --cookbook-masterbar-height: var(--wp-admin--admin-bar--height, 32px);
            --cookbook-sticky-gap: 0.5rem;
        }
        /* Allow the WpApp masterbar's dark-mode toggle to force a scheme. */
        :root[data-theme="dark"]  { color-scheme: dark;  }
        :root[data-theme="light"] { color-scheme: light; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; line-height: 1.55; color: var(--fg); background: var(--bg); margin: 0; }
        a { color: var(--accent); }
        a:hover, a:focus { color: var(--accent-hover); }
        main { max-width: 820px; margin: 1.5rem auto; padding: 0 1rem 4rem; }
        h1 { margin: 0 0 0.25rem; font-size: 2rem; }
        h2 { margin: 1.5rem 0 0.5rem; font-size: 1.3rem; border-bottom: 1px solid var(--line); padding-bottom: 0.25rem; }
        .subtitle { color: var(--muted); margin: 0 0 1rem; }
        .toolbar { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin: 1rem 0; }
        .toolbar .spacer { flex: 1; }
        .btn, button.btn, input[type="submit"].btn { display: inline-block; background: var(--accent); color: var(--accent-fg); border: 0; padding: 0.5rem 0.9rem; border-radius: 4px; text-decoration: none; font: inherit; cursor: pointer; }
        .btn:hover, .btn:focus, button.btn:hover, button.btn:focus, input[type="submit"].btn:hover, input[type="submit"].btn:focus { background: var(--accent-hover); color: var(--accent-fg); }
        .btn.secondary { background: var(--secondary-bg); color: var(--secondary-fg); }
        .btn.danger { background: #b32d2e; color: #fff; }
        .btn.fresh { background: var(--fresh); color: var(--fresh-fg); }
        .btn.household { background: var(--household); color: var(--household-fg); }
        .meta { display: flex; gap: 1rem; color: var(--muted); font-size: 0.9rem; flex-wrap: wrap; }
        .badge { display: inline-block; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 0.1rem 0.6rem; font-size: 0.85rem; color: var(--muted); margin-right: 0.25rem; text-decoration: none; }
        .recipe-card { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 1rem 1.25rem; margin: 0.75rem 0; display: block; text-decoration: none; color: inherit; }
        .recipe-card h3 { margin: 0 0 0.25rem; }
        .recipe-card .meta { font-size: 0.85rem; }
        .variation-panel { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 0.8rem 0.95rem; margin: 1rem 0; }
        .variation-panel-title { display: flex; gap: 0.5rem; align-items: baseline; justify-content: space-between; margin-bottom: 0.45rem; }
        .variation-panel-title strong { font-size: 1rem; }
        .variation-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.25rem; }
        .variation-list li { display: flex; gap: 0.45rem; align-items: baseline; flex-wrap: wrap; }
        .variation-list a,
        .variation-list strong { min-width: 0; overflow-wrap: anywhere; }
        .grid { display: grid; gap: 0.75rem; grid-template-columns: 1fr; }
        @media (min-width: 600px) { .grid { grid-template-columns: 1fr 1fr; } }
        label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 600; }
        input[type="text"], input[type="number"], input[type="url"], input[type="file"], textarea, select {
            width: 100%; max-width: 100%; padding: 0.5rem; border: 1px solid var(--input-border); border-radius: 4px; font: inherit; box-sizing: border-box; background: var(--input-bg); color: var(--fg);
        }
        textarea { min-height: 8rem; }
        .row { display: grid; grid-template-columns: 5rem 6rem 1fr 1fr auto; gap: 0.4rem; align-items: center; margin-bottom: 0.4rem; }
        .row input { width: 100%; }
        .row .remove { background: transparent; border: 0; color: #b32d2e; cursor: pointer; font-size: 1.2rem; }
        .recipe-form-sections { display: grid; gap: 0.85rem; margin-bottom: 0.75rem; }
        .recipe-form-section { border-left: 3px solid var(--line); padding-left: 0.85rem; }
        .recipe-form-section-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; }
        .recipe-form-section-rows { display: grid; gap: 0.4rem; }
        .recipe-form-section .add-ingredient-row,
        .recipe-form-section .add-instruction-row { margin-top: 0.15rem; }
        .recipe-row-inserter { position: relative; display: flex; align-items: center; justify-content: center; min-height: 0.9rem; margin: -0.25rem 0; opacity: 0; transition: opacity 0.12s ease; }
        .recipe-row-inserter:hover,
        .recipe-row-inserter:focus-within { opacity: 1; }
        .recipe-row-inserter-line { position: absolute; left: 0; right: 0; height: 1px; background: var(--line); }
        .recipe-row-inserter-actions { position: relative; display: inline-flex; gap: 0.25rem; background: var(--bg); padding: 0 0.35rem; }
        .recipe-row-inserter button { border: 1px solid var(--line); border-radius: 999px; background: var(--input-bg); color: var(--muted); cursor: pointer; font: inherit; font-size: 0.78rem; line-height: 1; padding: 0.18rem 0.45rem; }
        .recipe-row-inserter button:hover,
        .recipe-row-inserter button:focus { border-color: var(--accent); color: var(--accent); outline: none; }
        .ingredient-list { list-style: none; padding: 0; }
        .ingredient-list li { padding: 0.35rem 0; border-bottom: 1px dashed var(--line); display: flex; gap: 0.5rem; }
        .ingredient-list .amt { min-width: 5rem; font-weight: 600; }
        .ingredient-list .ingredient-row { display: block; }
        .ingredient-sections,
        .instruction-sections { display: grid; gap: 0.85rem; }
        .recipe-part-title { margin: 0.5rem 0 0.35rem; color: var(--muted); font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0; }
        .recipe-part .ingredient-list,
        .recipe-part .instruction-list { margin-top: 0; }
        .ingredient-line { display: flex; gap: 0.5rem; align-items: baseline; }
        .ingredient-line .ingredient-name { flex: 1; min-width: 0; }
        .ingredient-actions { opacity: 0; transition: opacity 0.1s; }
        .ingredient-row:hover .ingredient-actions,
        .ingredient-row:focus-within .ingredient-actions { opacity: 1; }
        .ingredient-replace-toggle { background: transparent; border: 0; color: var(--accent); cursor: pointer; font: inherit; padding: 0; }
        .ingredient-replace-form { display: grid; grid-template-columns: 5rem 5.5rem minmax(9rem, 1fr) minmax(8rem, 1fr) auto auto; gap: 0.4rem; align-items: center; margin: 0.5rem 0 0 5.5rem; }
        .ingredient-replace-form[hidden] { display: none; }
        .instruction-list { padding-left: 1.25rem; }
        .instruction-list li { margin: 0.5rem 0; }
        .cook-mode[hidden] { display: none; }
        body.cook-mode-active { overflow: hidden; }
        .cook-mode { position: fixed; inset: 0; z-index: 9999; overflow: auto; background: var(--bg); color: var(--fg); }
        .cook-mode-shell { min-height: 100%; display: grid; grid-template-rows: auto 1fr; }
        .cook-mode-topbar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; background: var(--bg); border-bottom: 1px solid var(--line); }
        .cook-mode-title { min-width: 0; }
        .cook-mode-kicker { margin: 0 0 0.15rem; color: var(--muted); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0; }
        .cook-mode h2 { margin: 0; border: 0; padding: 0; font-size: 1.35rem; overflow-wrap: anywhere; }
        .cook-mode-layout { display: grid; grid-template-columns: minmax(16rem, 24rem) minmax(0, 1fr); gap: 1rem; align-items: start; padding: 1rem; }
        .cook-mode-panel { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 1rem; }
        .cook-mode-panel h3 { margin: 0 0 0.75rem; font-size: 1rem; }
        .cook-ingredient-list,
        .cook-step-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.55rem; }
        .cook-ingredient label { display: grid; grid-template-columns: auto minmax(4.5rem, auto) minmax(0, 1fr); gap: 0.65rem; align-items: baseline; margin: 0; padding: 0.55rem 0; border-bottom: 1px dashed var(--line); cursor: pointer; font-weight: 400; }
        .cook-ingredient:last-child label { border-bottom: 0; }
        .cook-ingredient input,
        .cook-step-check,
        .cook-active-check input { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
        .cook-ingredient-amount { font-weight: 700; }
        .cook-ingredient-name { min-width: 0; overflow-wrap: anywhere; }
        .cook-ingredient-note { color: var(--muted); }
        .cook-ingredient.is-checked,
        .cook-step-row.is-checked { opacity: 0.62; }
        .cook-ingredient.is-checked .cook-ingredient-name,
        .cook-step-row.is-checked .cook-step-list-text { text-decoration: line-through; }
        .cook-mode-main { display: grid; gap: 1rem; }
        .cook-mode-progress { display: grid; gap: 0.5rem; }
        .cook-mode-progress-row { display: flex; justify-content: space-between; gap: 1rem; color: var(--muted); font-size: 0.9rem; }
        .cook-mode-progress progress { width: 100%; height: 0.6rem; accent-color: var(--accent); }
        .cook-active-step { min-height: 12rem; display: flex; align-items: center; padding: 1.25rem; border: 1px solid var(--line); border-radius: 6px; background: var(--bg); font-size: 2rem; line-height: 1.35; overflow-wrap: anywhere; }
        .cook-active-step:focus { outline: 2px solid var(--accent); outline-offset: 2px; }
        .cook-active-check { display: inline-flex; gap: 0.5rem; align-items: center; margin: 0; font-weight: 600; cursor: pointer; }
        .cook-mode-nav { display: flex; gap: 0.5rem; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .cook-mode-nav-group { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .cook-step-row { border: 1px solid var(--line); border-radius: 6px; background: var(--bg); }
        .cook-step-row.is-active { border-color: var(--accent); box-shadow: inset 0 0 0 1px var(--accent); }
        .cook-step-list-row { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 0.65rem; align-items: start; margin: 0; padding: 0.65rem; }
        .cook-step-jump { width: 100%; border: 0; background: transparent; color: inherit; text-align: left; padding: 0; font: inherit; cursor: pointer; }
        .cook-step-list-index { display: block; color: var(--muted); font-size: 0.8rem; font-weight: 700; margin-bottom: 0.15rem; }
        .cook-step-list-text { display: block; overflow-wrap: anywhere; }
        .notice { background: var(--notice-bg); border: 1px solid var(--notice-bd); padding: 0.6rem 0.9rem; border-radius: 4px; margin: 0.75rem 0; }
        .notice.error { background: var(--error-bg); border-color: var(--error-bd); }
        .notice.success { background: var(--success-bg); border-color: var(--success-bd); }
        .help { color: var(--muted); font-size: 0.85rem; margin-top: 0.25rem; }
        .portion-control { display: flex; gap: 0.5rem; align-items: center; background: var(--card); border: 1px solid var(--line); border-radius: 4px; padding: 0.4rem 0.7rem; }
        .portion-control input { width: 4.5rem; }
        .unit-toggle { background: var(--card); border: 1px solid var(--line); border-radius: 4px; padding: 0.25rem; }
        .unit-toggle button { background: transparent; border: 0; padding: 0.3rem 0.6rem; cursor: pointer; border-radius: 3px; }
        .unit-toggle button.active { background: var(--accent); color: var(--accent-fg); }
        .ingredient-cloud { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 1rem 0; line-height: 1.4; }
        .ing-chip { display: inline-flex; align-items: baseline; gap: 0.35rem; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 0.2rem 0.7rem; cursor: pointer; user-select: none; text-decoration: none; color: inherit; transition: background 0.1s, border-color 0.1s; }
        .ing-chip:hover { border-color: var(--accent); }
        .ing-chip.on { background: var(--accent); color: var(--accent-fg); border-color: var(--accent); }
        .ing-chip input { position: absolute; opacity: 0; pointer-events: none; }
        .ing-chip-count { color: var(--muted); font-size: 0.8em; }
        .ing-chip.on .ing-chip-count { color: color-mix(in srgb, var(--accent-fg) 78%, transparent); }
        .page-head { display: flex; gap: 1rem; align-items: flex-start; justify-content: space-between; margin-bottom: 1rem; }
        .page-head h1 { margin-top: 0; }
        .page-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end; }
        .soft-panel { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 1rem; }
        .shopping-list { list-style: none; padding: 0; margin: 1rem 0; border-top: 1px solid var(--line); }
        .shopping-row { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 0.75rem; align-items: start; padding: 0.75rem 0; border-bottom: 1px solid var(--line); }
        .shopping-row.is-selected { background: var(--secondary-bg); }
        .shopping-check,
        .shopping-row-select { margin-top: 0.55rem; }
        .shopping-fields { display: grid; grid-template-columns: 5rem 5.5rem minmax(9rem, 1fr) minmax(8rem, 1fr) auto; gap: 0.45rem; align-items: center; }
        .shopping-source { margin-top: 0.35rem; color: var(--muted); font-size: 0.85rem; }
        .shopping-bulk-bar { position: fixed; left: 50%; bottom: 1rem; z-index: 30; transform: translateX(-50%); width: min(820px, calc(100vw - 2rem)); display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; background: var(--secondary-bg); border: 1px solid var(--line); border-radius: 6px; padding: 0.6rem; margin: 0; box-shadow: 0 8px 24px rgba(0,0,0,0.18); }
        .shopping-bulk-bar[hidden] { display: none; }
        .shopping-bulk-bar input { width: min(18rem, 100%); }
        #shopping-list-form.has-shopping-bulk-bar { padding-bottom: 5.5rem; }
        .manual-item-row { display: grid; grid-template-columns: 5rem 5.5rem minmax(9rem, 1fr) minmax(8rem, 1fr) auto; gap: 0.45rem; align-items: center; margin-bottom: 0.5rem; }
        .shopping-fields .remove,
        .manual-item-row .remove { background: transparent; border: 0; color: #b32d2e; cursor: pointer; font-size: 1.2rem; }
        .shop-bar { position: sticky; top: calc(var(--cookbook-masterbar-height) + var(--cookbook-sticky-gap)); z-index: 5; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: 0.6rem; margin: 1rem 0; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .shop-bar-main { margin-right: auto; display: flex; gap: 0.75rem; align-items: baseline; flex-wrap: wrap; min-width: min(100%, 18rem); }
        .shop-household-summary { color: var(--muted); font-size: 0.9rem; overflow-wrap: anywhere; }
        .shop-list { list-style: none; padding: 0; margin: 1rem 0; display: grid; gap: 0.55rem; }
        .shop-item { border: 1px solid var(--line); border-radius: 6px; background: var(--card); }
        .shop-item label { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 0.75rem; align-items: center; margin: 0; padding: 0.9rem; cursor: pointer; font-weight: 400; }
        .shop-item strong { display: block; font-size: 1.05rem; }
        .shop-item small { display: block; color: var(--muted); font-size: 0.9rem; margin-top: 0.1rem; }
        .shop-check { width: 1.35rem; height: 1.35rem; }
        .shop-item.is-checked { opacity: 0.62; }
        .shop-item.is-checked strong { text-decoration: line-through; }
        .shop-add { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; margin-top: 1rem; }
        .household-reminders { margin: 1rem 0; }
        .household-reminders h2 { margin-top: 0; }
        .household-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.45rem; }
        .household-list li { display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; border-bottom: 1px dashed var(--line); padding: 0.45rem 0; }
        .household-list li:last-child { border-bottom: 0; }
        .household-list strong,
        .household-list small { display: block; }
        .household-list small { color: var(--muted); font-size: 0.9rem; }
        .planner-nav { display: flex; gap: 0.5rem; align-items: center; justify-content: space-between; margin: 1rem 0; }
        .planner-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin: 1rem 0; }
        .planner-day { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 0.85rem; }
        .planner-day h3 { margin: 0 0 0.75rem; font-size: 1rem; display: flex; justify-content: space-between; gap: 0.75rem; }
        .planner-day h3 span { color: var(--muted); font-weight: 400; }
        .planner-slot { display: grid; gap: 0.25rem; margin-bottom: 0.65rem; position: relative; }
        .planner-slot:last-child { margin-bottom: 0; }
        .planner-slot label { margin: 0; font-size: 0.85rem; color: var(--muted); }
        .planner-slot-label { display: flex; gap: 0.4rem; align-items: baseline; }
        .planner-action { background: transparent; border: 0; color: var(--accent); cursor: pointer; font: inherit; font-size: 0.8rem; padding: 0; text-decoration: underline; }
        .planner-slot input[data-copy-highlight] { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
        .planner-autocomplete { border: 1px solid var(--line); border-radius: 6px; background: var(--card); box-shadow: 0 6px 18px rgba(0,0,0,0.12); max-height: 14rem; overflow-y: auto; padding: 0.25rem; z-index: 20; }
        .planner-autocomplete[hidden] { display: none; }
        .planner-autocomplete-option { display: block; width: 100%; border: 0; border-radius: 4px; background: transparent; color: inherit; cursor: pointer; font: inherit; padding: 0.5rem 0.6rem; text-align: left; }
        .planner-autocomplete-option:hover,
        .planner-autocomplete-option:focus,
        .planner-autocomplete-option[aria-selected="true"] { background: var(--secondary-bg); color: var(--fg); outline: none; }
        .planner-previous { display: flex; gap: 0.4rem; align-items: baseline; color: var(--muted); font-size: 0.8rem; line-height: 1.35; }
        .planner-stash[hidden] { display: none; }
        .planner-stash-items { display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .planner-stash-item { display: inline-flex; gap: 0.25rem; align-items: center; border: 1px solid var(--line); border-radius: 4px; background: var(--input-bg); color: var(--muted); font: inherit; font-size: 0.85rem; padding: 0.15rem 0.25rem 0.15rem 0.55rem; }
        .planner-stash-item.is-selected { border-color: var(--accent); color: var(--accent); }
        .planner-stash-select,
        .planner-stash-remove { background: transparent; border: 0; color: inherit; cursor: pointer; font: inherit; padding: 0; }
        .planner-stash-remove { display: inline-flex; align-items: center; justify-content: center; width: 1rem; height: 1rem; border-radius: 999px; color: var(--muted); font-size: 0.9rem; line-height: 1; }
        .planner-stash-remove:hover,
        .planner-stash-remove:focus { background: var(--error-bg); color: #b32d2e; }
        .planned-strip { display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin: 1rem 0; }
        .planned-card { display: flex; gap: 0.75rem; align-items: center; background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 0.65rem; color: inherit; text-decoration: none; }
        .planned-card img,
        .planned-card .planned-thumb { width: 68px; height: 68px; object-fit: cover; border-radius: 5px; flex-shrink: 0; background: var(--secondary-bg); }
        .planned-card .planned-thumb { display: flex; align-items: center; justify-content: center; color: var(--muted); font-weight: 700; }
        .planned-card strong { display: block; color: var(--fg); }
        .planned-card span { display: block; color: var(--muted); font-size: 0.85rem; }
        @media (min-width: 680px) {
            .planner-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .planned-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 980px) {
            .planner-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .planned-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            :root {
                --cookbook-masterbar-height: var(--wp-admin--admin-bar--height, 46px);
                --cookbook-sticky-gap: 0.25rem;
            }
            .page-head { display: block; }
            .page-actions { justify-content: flex-start; margin-top: 0.75rem; }
            .shopping-fields,
            .manual-item-row,
            .ingredient-replace-form { grid-template-columns: 1fr 1fr; }
            .recipe-form-section-header { grid-template-columns: 1fr; }
            .ingredient-replace-form { margin-left: 0; }
            .manual-item-row .remove { justify-self: start; }
            .shop-add { grid-template-columns: 1fr; }
            #shopping-list-form.has-shopping-bulk-bar { padding-bottom: 8.5rem; }
            .household-list li { align-items: flex-start; flex-direction: column; }
            .cook-mode-topbar { align-items: flex-start; flex-direction: column; }
            .cook-mode-layout { grid-template-columns: 1fr; }
            .cook-mode-main { order: 1; }
            .cook-mode-ingredients { order: 2; }
            .cook-active-step { min-height: 9rem; font-size: 1.45rem; }
            .cook-mode-nav { align-items: stretch; flex-direction: column; }
            .cook-mode-nav-group { width: 100%; }
            .cook-mode-nav-group .btn { flex: 1; text-align: center; }
        }
    </style>
</head>
<body class="wp-app-body">
    <?php wp_app_body_open(); ?>
    <main>
