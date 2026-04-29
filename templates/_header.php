<?php
/**
 * Shared header partial for the Recipes app.
 *
 * Templates include this near the top to set up <head>, masterbar, and the
 * outer page chrome. Pair it with templates/_footer.php.
 */
$recipes_home = home_url( '/recipes/' );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_title(); ?></title>
    <?php wp_app_head(); ?>
    <style>
        :root { --bg:#fff; --fg:#1e1e1e; --muted:#666; --line:#e5e5e5; --accent:#b8541b; --card:#faf7f2; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; line-height: 1.55; color: var(--fg); background: var(--bg); margin: 0; }
        a { color: var(--accent); }
        main { max-width: 820px; margin: 1.5rem auto; padding: 0 1rem 4rem; }
        h1 { margin: 0 0 0.25rem; font-size: 2rem; }
        h2 { margin: 1.5rem 0 0.5rem; font-size: 1.3rem; border-bottom: 1px solid var(--line); padding-bottom: 0.25rem; }
        .subtitle { color: var(--muted); margin: 0 0 1rem; }
        .toolbar { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin: 1rem 0; }
        .toolbar .spacer { flex: 1; }
        .btn, button.btn, input[type="submit"].btn { display: inline-block; background: var(--accent); color: #fff; border: 0; padding: 0.5rem 0.9rem; border-radius: 4px; text-decoration: none; font: inherit; cursor: pointer; }
        .btn.secondary { background: #eee; color: #333; }
        .btn.danger { background: #b32d2e; }
        .meta { display: flex; gap: 1rem; color: var(--muted); font-size: 0.9rem; flex-wrap: wrap; }
        .badge { display: inline-block; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 0.1rem 0.6rem; font-size: 0.85rem; color: #555; margin-right: 0.25rem; text-decoration: none; }
        .recipe-card { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 1rem 1.25rem; margin: 0.75rem 0; display: block; text-decoration: none; color: inherit; }
        .recipe-card h3 { margin: 0 0 0.25rem; }
        .recipe-card .meta { font-size: 0.85rem; }
        .grid { display: grid; gap: 0.75rem; grid-template-columns: 1fr; }
        @media (min-width: 600px) { .grid { grid-template-columns: 1fr 1fr; } }
        label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 600; }
        input[type="text"], input[type="number"], input[type="url"], textarea, select {
            width: 100%; max-width: 100%; padding: 0.5rem; border: 1px solid #bbb; border-radius: 4px; font: inherit; box-sizing: border-box; background: #fff; color: var(--fg);
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
        .notice { background: #fff5e0; border: 1px solid #f0d8a0; padding: 0.6rem 0.9rem; border-radius: 4px; margin: 0.75rem 0; }
        .notice.error { background: #fdecea; border-color: #f5c2bd; }
        .notice.success { background: #e8f5e9; border-color: #b6dab8; }
        .help { color: var(--muted); font-size: 0.85rem; margin-top: 0.25rem; }
        .portion-control { display: flex; gap: 0.5rem; align-items: center; background: var(--card); border: 1px solid var(--line); border-radius: 4px; padding: 0.4rem 0.7rem; }
        .portion-control input { width: 4.5rem; }
        .unit-toggle { background: var(--card); border: 1px solid var(--line); border-radius: 4px; padding: 0.25rem; }
        .unit-toggle button { background: transparent; border: 0; padding: 0.3rem 0.6rem; cursor: pointer; border-radius: 3px; }
        .unit-toggle button.active { background: var(--accent); color: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#1a1a1a; --fg:#eee; --muted:#aaa; --line:#333; --card:#222; }
            input, textarea, select { background:#1a1a1a; color: var(--fg); border-color:#444; }
            .btn.secondary { background: #2a2a2a; color: #ddd; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>
    <main>
