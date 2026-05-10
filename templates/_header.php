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
            --bg:    light-dark(#fff,    #1a1a1a);
            --fg:    light-dark(#1e1e1e, #ececec);
            --muted: light-dark(#666,    #a8a8a8);
            --line:  light-dark(#e5e5e5, #333);
            --accent: light-dark(#b8541b, #e0763a);
            --card:  light-dark(#faf7f2, #232323);
            --input-bg:     light-dark(#fff, #1a1a1a);
            --input-border: light-dark(#bbb, #444);
            --secondary-bg: light-dark(#eee, #2a2a2a);
            --secondary-fg: light-dark(#333, #ddd);
            --notice-bg:    light-dark(#fff5e0, #3a2f15);
            --notice-bd:    light-dark(#f0d8a0, #6b552a);
            --error-bg:     light-dark(#fdecea, #3d2424);
            --error-bd:     light-dark(#f5c2bd, #7a3a3a);
            --success-bg:   light-dark(#e8f5e9, #1f3621);
            --success-bd:   light-dark(#b6dab8, #3f6b42);
            --fresh:        light-dark(#18a558, #37c978);
        }
        /* Allow the WpApp masterbar's dark-mode toggle to force a scheme. */
        :root[data-theme="dark"]  { color-scheme: dark;  }
        :root[data-theme="light"] { color-scheme: light; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; line-height: 1.55; color: var(--fg); background: var(--bg); margin: 0; }
        a { color: var(--accent); }
        main { max-width: 820px; margin: 1.5rem auto; padding: 0 1rem 4rem; }
        h1 { margin: 0 0 0.25rem; font-size: 2rem; }
        h2 { margin: 1.5rem 0 0.5rem; font-size: 1.3rem; border-bottom: 1px solid var(--line); padding-bottom: 0.25rem; }
        .subtitle { color: var(--muted); margin: 0 0 1rem; }
        .toolbar { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin: 1rem 0; }
        .toolbar .spacer { flex: 1; }
        .btn, button.btn, input[type="submit"].btn { display: inline-block; background: var(--accent); color: #fff; border: 0; padding: 0.5rem 0.9rem; border-radius: 4px; text-decoration: none; font: inherit; cursor: pointer; }
        .btn.secondary { background: var(--secondary-bg); color: var(--secondary-fg); }
        .btn.danger { background: #b32d2e; color: #fff; }
        .btn.fresh { background: var(--fresh); color: #fff; }
        .meta { display: flex; gap: 1rem; color: var(--muted); font-size: 0.9rem; flex-wrap: wrap; }
        .badge { display: inline-block; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 0.1rem 0.6rem; font-size: 0.85rem; color: #555; margin-right: 0.25rem; text-decoration: none; }
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
        .ingredient-list { list-style: none; padding: 0; }
        .ingredient-list li { padding: 0.35rem 0; border-bottom: 1px dashed var(--line); display: flex; gap: 0.5rem; }
        .ingredient-list .amt { min-width: 5rem; font-weight: 600; }
        .ingredient-list .ingredient-row { display: block; }
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
        .unit-toggle button.active { background: var(--accent); color: #fff; }
        .ingredient-cloud { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 1rem 0; line-height: 1.4; }
        .ing-chip { display: inline-flex; align-items: baseline; gap: 0.35rem; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 0.2rem 0.7rem; cursor: pointer; user-select: none; text-decoration: none; color: inherit; transition: background 0.1s, border-color 0.1s; }
        .ing-chip:hover { border-color: var(--accent); }
        .ing-chip.on { background: var(--accent); color: #fff; border-color: var(--accent); }
        .ing-chip input { position: absolute; opacity: 0; pointer-events: none; }
        .ing-chip-count { color: var(--muted); font-size: 0.8em; }
        .ing-chip.on .ing-chip-count { color: rgba(255,255,255,0.8); }
        .page-head { display: flex; gap: 1rem; align-items: flex-start; justify-content: space-between; margin-bottom: 1rem; }
        .page-head h1 { margin-top: 0; }
        .page-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end; }
        .soft-panel { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 1rem; }
        .shopping-list { list-style: none; padding: 0; margin: 1rem 0; border-top: 1px solid var(--line); }
        .shopping-row { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 0.75rem; align-items: start; padding: 0.75rem 0; border-bottom: 1px solid var(--line); }
        .shopping-row.is-checked { color: var(--muted); }
        .shopping-row.is-checked .shopping-fields input[type="text"] { text-decoration: line-through; }
        .shopping-check { margin-top: 0.55rem; }
        .shopping-fields { display: grid; grid-template-columns: 5rem 5.5rem minmax(9rem, 1fr) minmax(8rem, 1fr) auto; gap: 0.45rem; align-items: center; }
        .shopping-source { margin-top: 0.35rem; color: var(--muted); font-size: 0.85rem; }
        .manual-item-row { display: grid; grid-template-columns: 5rem 5.5rem minmax(9rem, 1fr) minmax(8rem, 1fr) auto; gap: 0.45rem; align-items: center; margin-bottom: 0.5rem; }
        .shopping-fields .remove,
        .manual-item-row .remove { background: transparent; border: 0; color: #b32d2e; cursor: pointer; font-size: 1.2rem; }
        .shop-bar { position: sticky; top: 0.5rem; z-index: 5; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: 0.6rem; margin: 1rem 0; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .shop-bar strong { margin-right: auto; }
        .shop-toggle { display: inline-flex; gap: 0.35rem; align-items: center; margin: 0; font-weight: 400; color: var(--muted); }
        .shop-list { list-style: none; padding: 0; margin: 1rem 0; display: grid; gap: 0.55rem; }
        .shop-item { border: 1px solid var(--line); border-radius: 6px; background: var(--card); }
        .shop-item label { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 0.75rem; align-items: center; margin: 0; padding: 0.9rem; cursor: pointer; font-weight: 400; }
        .shop-item strong { display: block; font-size: 1.05rem; }
        .shop-item small { display: block; color: var(--muted); font-size: 0.9rem; margin-top: 0.1rem; }
        .shop-check { width: 1.35rem; height: 1.35rem; }
        .shop-item.is-checked { opacity: 0.62; }
        .shop-item.is-checked strong { text-decoration: line-through; }
        .shop-list.hide-checked .shop-item.is-checked { display: none; }
        .shop-add { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; margin-top: 1rem; }
        .planner-nav { display: flex; gap: 0.5rem; align-items: center; justify-content: space-between; margin: 1rem 0; }
        .planner-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin: 1rem 0; }
        .planner-day { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 0.85rem; }
        .planner-day h3 { margin: 0 0 0.75rem; font-size: 1rem; display: flex; justify-content: space-between; gap: 0.75rem; }
        .planner-day h3 span { color: var(--muted); font-weight: 400; }
        .planner-slot { display: grid; gap: 0.25rem; margin-bottom: 0.65rem; }
        .planner-slot:last-child { margin-bottom: 0; }
        .planner-slot label { margin: 0; font-size: 0.85rem; color: var(--muted); }
        .planner-slot-label { display: flex; gap: 0.4rem; align-items: baseline; }
        .planner-action { background: transparent; border: 0; color: var(--accent); cursor: pointer; font: inherit; font-size: 0.8rem; padding: 0; text-decoration: underline; }
        .planner-stash[hidden] { display: none; }
        .planner-stash-items { display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .planner-stash-item { display: inline-flex; gap: 0.25rem; align-items: center; border: 1px solid var(--line); border-radius: 4px; background: #fff; color: var(--muted); font: inherit; font-size: 0.85rem; padding: 0.15rem 0.25rem 0.15rem 0.55rem; }
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
            .page-head { display: block; }
            .page-actions { justify-content: flex-start; margin-top: 0.75rem; }
            .shopping-fields,
            .manual-item-row,
            .ingredient-replace-form { grid-template-columns: 1fr 1fr; }
            .ingredient-replace-form { margin-left: 0; }
            .manual-item-row .remove { justify-self: start; }
            .shop-bar { top: 0.25rem; }
            .shop-add { grid-template-columns: 1fr; }
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
<body>
    <?php wp_app_body_open(); ?>
    <main>
