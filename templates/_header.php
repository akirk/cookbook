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
    <title><?php wp_app_title(); ?></title>
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
        .meta { display: flex; gap: 1rem; color: var(--muted); font-size: 0.9rem; flex-wrap: wrap; }
        .badge { display: inline-block; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 0.1rem 0.6rem; font-size: 0.85rem; color: #555; margin-right: 0.25rem; text-decoration: none; }
        .recipe-card { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 1rem 1.25rem; margin: 0.75rem 0; display: block; text-decoration: none; color: inherit; }
        .recipe-card h3 { margin: 0 0 0.25rem; }
        .recipe-card .meta { font-size: 0.85rem; }
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
        .instruction-list { padding-left: 1.25rem; }
        .instruction-list li { margin: 0.5rem 0; }
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
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>
    <main>
