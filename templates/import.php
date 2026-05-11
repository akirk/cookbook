<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only prefill / error code.
$error      = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
$source_url = isset( $_GET['source_url'] ) ? esc_url_raw( wp_unslash( $_GET['source_url'] ) ) : '';
$source_host = $source_url !== '' ? ( wp_parse_url( $source_url, PHP_URL_HOST ) ?: $source_url ) : '';
$autoimport = ! empty( $_GET['autoimport'] ) && $source_url !== '' && $error === '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$page_title = __( 'Import a recipe', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( '← All recipes', 'cookbook' ); ?></a>
<h1><?php esc_html_e( 'Import a recipe', 'cookbook' ); ?></h1>
<p class="subtitle"><?php esc_html_e( 'Paste a URL from a recipe site, or paste the recipe text itself.', 'cookbook' ); ?></p>

<style>
    main { max-width: 1120px; }
    .import-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(21rem, 28rem); gap: 1rem; align-items: start; }
    .import-preview { position: sticky; top: 1rem; background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 0.85rem 1rem; }
    .import-preview-title { margin: 0 0 0.35rem; font-size: 1rem; font-weight: 700; }
    .import-preview .help { margin: 0.35rem 0 0.75rem; }
    .preview-section { border-top: 1px solid var(--line); padding-top: 0.65rem; margin-top: 0.65rem; }
    .preview-section-head { width: 100%; display: grid; grid-template-columns: 1rem 1fr auto 1rem; gap: 0.5rem; align-items: center; border: 0; background: transparent; color: inherit; padding: 0; text-align: left; font: inherit; cursor: pointer; }
    .preview-checkbox { width: 0.85rem; height: 0.85rem; border: 1px solid var(--input-border); border-radius: 3px; background: var(--input-bg); position: relative; box-sizing: border-box; }
    .preview-section[data-state="ok"] .preview-checkbox { background: var(--accent); border-color: var(--accent); }
    .preview-section[data-state="ok"] .preview-checkbox::after { content: ""; position: absolute; left: 0.24rem; top: 0.08rem; width: 0.25rem; height: 0.5rem; border: solid var(--accent-fg); border-width: 0 2px 2px 0; transform: rotate(45deg); }
    .preview-section-label { font-weight: 700; }
    .preview-section-status { color: var(--muted); font-size: 0.85rem; white-space: nowrap; }
    .preview-section[data-state="ok"] .preview-section-status { color: var(--fg); }
    .preview-toggle { color: var(--muted); font-size: 1rem; line-height: 1; text-align: center; transition: transform 0.12s ease; }
    .preview-section-head[aria-expanded="true"] .preview-toggle { transform: rotate(90deg); }
    .preview-section-body { padding: 0.55rem 0 0 1.5rem; }
    .preview-title-value { margin: 0; font-weight: 600; }
    .preview-table-wrap { overflow-x: auto; }
    .preview-ingredient-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .preview-ingredient-table th, .preview-ingredient-table td { text-align: left; vertical-align: top; padding: 0.35rem 0.3rem; border-bottom: 1px solid var(--line); }
    .preview-ingredient-table th { color: var(--muted); font-weight: 600; }
    .preview-steps { margin: 0; padding-left: 1.2rem; }
    .preview-steps li { margin: 0.35rem 0; }
    .preview-muted { color: var(--muted); }
    @media (max-width: 850px) {
        .import-layout { grid-template-columns: 1fr; }
        .import-preview { position: static; }
    }
</style>

<?php if ( $error === 'parse' ) : ?>
    <div class="notice error">
        <?php if ( $source_url !== '' ) : ?>
            <?php
            printf(
                /* translators: %s: source hostname */
                esc_html__( 'Could not detect recipe metadata on %s. The URL is kept below as the source; paste the recipe text and use the preview to check what will be imported.', 'cookbook' ),
                esc_html( $source_host )
            );
            ?>
        <?php else : ?>
            <?php esc_html_e( 'Could not detect a recipe. Paste the recipe text and use the preview to check what will be imported.', 'cookbook' ); ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="import-form">
    <?php wp_nonce_field( 'cookbook_import' ); ?>
    <input type="hidden" name="action" value="cookbook_import">

    <label for="source_url"><?php esc_html_e( 'Recipe URL', 'cookbook' ); ?></label>
    <input id="source_url" type="url" name="source_url" placeholder="https://example.com/some-recipe" value="<?php echo esc_attr( $source_url ); ?>" autofocus>
    <p class="help"><?php esc_html_e( 'We look for schema.org Recipe metadata first. If that fails, keep the URL here and paste the recipe text below.', 'cookbook' ); ?></p>

    <label for="image_url"><?php esc_html_e( 'Photo URL', 'cookbook' ); ?></label>
    <input id="image_url" type="url" name="image_url" placeholder="https://example.com/recipe-photo.jpg">
    <p class="help"><?php esc_html_e( 'Optional. Paste an image URL from the source page to add it to the media library with the imported draft.', 'cookbook' ); ?></p>
    <div id="image-url-preview" hidden style="margin:0.5rem 0 1rem">
        <img src="" alt="" style="max-width:240px;border-radius:6px;border:1px solid var(--line)">
    </div>

    <div class="import-layout">
        <div>
            <label for="paste"><?php esc_html_e( '…or paste the recipe text', 'cookbook' ); ?></label>
            <textarea id="paste" name="paste" style="min-height:24rem;overflow:hidden" aria-describedby="paste-help import-preview" placeholder="<?php esc_attr_e( "Title\n\nIngredients\n2 cups flour\n1 tsp salt\n\nInstructions\nMix everything\nBake until done", 'cookbook' ); ?>"></textarea>
            <p class="help" id="paste-help"><?php esc_html_e( 'The result will land in your drafts so you can review and tidy it up.', 'cookbook' ); ?></p>
        </div>
        <aside class="import-preview" id="import-preview" aria-live="polite">
            <div class="import-preview-title"><?php esc_html_e( 'Paste checklist', 'cookbook' ); ?></div>
            <p class="help" data-preview-message><?php esc_html_e( 'Review each detected section before importing.', 'cookbook' ); ?></p>
            <div class="notice error" data-preview-error hidden></div>

            <section class="preview-section" data-preview-section="title" data-state="missing">
                <button type="button" class="preview-section-head" aria-expanded="false">
                    <span class="preview-checkbox" aria-hidden="true"></span>
                    <span class="preview-section-label"><?php esc_html_e( 'Title', 'cookbook' ); ?></span>
                    <span class="preview-section-status" data-preview-title-status><?php esc_html_e( 'Missing', 'cookbook' ); ?></span>
                    <span class="preview-toggle" aria-hidden="true">›</span>
                </button>
                <div class="preview-section-body" hidden>
                    <p class="preview-title-value" data-preview-title></p>
                </div>
            </section>

            <section class="preview-section" data-preview-section="ingredients" data-state="missing">
                <button type="button" class="preview-section-head" aria-expanded="false">
                    <span class="preview-checkbox" aria-hidden="true"></span>
                    <span class="preview-section-label"><?php esc_html_e( 'Ingredients', 'cookbook' ); ?></span>
                    <span class="preview-section-status" data-preview-ingredients-status><?php esc_html_e( 'Missing', 'cookbook' ); ?></span>
                    <span class="preview-toggle" aria-hidden="true">›</span>
                </button>
                <div class="preview-section-body" hidden>
                    <div class="preview-table-wrap">
                        <table class="preview-ingredient-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Number', 'cookbook' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'cookbook' ); ?></th>
                                    <th><?php esc_html_e( 'Ingredient', 'cookbook' ); ?></th>
                                    <th><?php esc_html_e( 'Additional', 'cookbook' ); ?></th>
                                </tr>
                            </thead>
                            <tbody data-preview-ingredients></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="preview-section" data-preview-section="instructions" data-state="missing">
                <button type="button" class="preview-section-head" aria-expanded="false">
                    <span class="preview-checkbox" aria-hidden="true"></span>
                    <span class="preview-section-label"><?php esc_html_e( 'Instructions', 'cookbook' ); ?></span>
                    <span class="preview-section-status" data-preview-instructions-status><?php esc_html_e( 'Missing', 'cookbook' ); ?></span>
                    <span class="preview-toggle" aria-hidden="true">›</span>
                </button>
                <div class="preview-section-body" hidden>
                    <ol class="preview-steps" data-preview-instructions></ol>
                </div>
            </section>
        </aside>
    </div>

    <div class="toolbar">
        <button class="btn" type="submit"><?php esc_html_e( 'Import', 'cookbook' ); ?></button>
        <a class="btn secondary" href="<?php echo esc_url( home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( 'Cancel', 'cookbook' ); ?></a>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('import-form');
    var paste = document.getElementById('paste');
    if (!form || !paste || !window.fetch || !window.URLSearchParams) return;

    var endpoint = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var nonceField = form.querySelector('input[name="_wpnonce"]');
    var nonce = nonceField ? nonceField.value : '';
    var timer = null;
    var sequence = 0;
    var message = document.querySelector('[data-preview-message]');
    var error = document.querySelector('[data-preview-error]');
    var title = {
        section: document.querySelector('[data-preview-section="title"]'),
        status: document.querySelector('[data-preview-title-status]'),
        value: document.querySelector('[data-preview-title]')
    };
    var ingredients = {
        section: document.querySelector('[data-preview-section="ingredients"]'),
        status: document.querySelector('[data-preview-ingredients-status]'),
        rows: document.querySelector('[data-preview-ingredients]')
    };
    var instructions = {
        section: document.querySelector('[data-preview-section="instructions"]'),
        status: document.querySelector('[data-preview-instructions-status]'),
        rows: document.querySelector('[data-preview-instructions]')
    };
    var strings = {
        parsing: <?php echo wp_json_encode( __( 'Checking the pasted text…', 'cookbook' ) ); ?>,
        empty: <?php echo wp_json_encode( __( 'Paste recipe text to fill the checklist.', 'cookbook' ) ); ?>,
        review: <?php echo wp_json_encode( __( 'Review each detected section before importing.', 'cookbook' ) ); ?>,
        missing: <?php echo wp_json_encode( __( 'Missing', 'cookbook' ) ); ?>,
        detected: <?php echo wp_json_encode( __( 'Detected', 'cookbook' ) ); ?>,
        noIngredients: <?php echo wp_json_encode( __( 'No ingredients detected.', 'cookbook' ) ); ?>,
        noInstructions: <?php echo wp_json_encode( __( 'No instructions detected.', 'cookbook' ) ); ?>,
        blank: <?php echo wp_json_encode( '-' ); ?>,
        error: <?php echo wp_json_encode( __( 'Could not preview that text yet.', 'cookbook' ) ); ?>
    };

    function countLabel(count, one, many) {
        return count + ' ' + (count === 1 ? one : many);
    }

    function sectionParts(section) {
        return {
            button: section.section.querySelector('.preview-section-head'),
            body: section.section.querySelector('.preview-section-body')
        };
    }

    function setSection(section, present, statusText) {
        var parts = sectionParts(section);
        section.section.dataset.state = present ? 'ok' : 'missing';
        section.status.textContent = statusText;
        parts.body.hidden = !present;
        parts.button.setAttribute('aria-expanded', present ? 'true' : 'false');
    }

    function resetSections() {
        title.value.textContent = '';
        clearChildren(ingredients.rows);
        clearChildren(instructions.rows);
        setSection(title, false, strings.missing);
        setSection(ingredients, false, strings.missing);
        setSection(instructions, false, strings.missing);
    }

    function setMessage(text) {
        message.textContent = text;
        message.hidden = false;
        error.hidden = true;
    }

    function setError(text) {
        resetSections();
        error.textContent = text;
        error.hidden = false;
        message.hidden = true;
    }

    function clearChildren(node) {
        while (node.firstChild) node.removeChild(node.firstChild);
    }

    function cell(text, className) {
        var td = document.createElement('td');
        td.textContent = text || strings.blank;
        if (!text) td.className = className || 'preview-muted';
        return td;
    }

    function appendIngredientRows(rows) {
        clearChildren(ingredients.rows);
        if (!rows.length) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 4;
            emptyCell.className = 'preview-muted';
            emptyCell.textContent = strings.noIngredients;
            emptyRow.appendChild(emptyCell);
            ingredients.rows.appendChild(emptyRow);
            return;
        }
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.appendChild(cell(row.amount || ''));
            tr.appendChild(cell(row.unit || ''));
            tr.appendChild(cell(row.name || ''));
            tr.appendChild(cell(row.notes || ''));
            ingredients.rows.appendChild(tr);
        });
    }

    function appendInstructionRows(rows) {
        clearChildren(instructions.rows);
        if (!rows.length) {
            var emptyRow = document.createElement('li');
            emptyRow.className = 'preview-muted';
            emptyRow.textContent = strings.noInstructions;
            instructions.rows.appendChild(emptyRow);
            return;
        }
        rows.forEach(function (row) {
            var item = document.createElement('li');
            item.textContent = row;
            instructions.rows.appendChild(item);
        });
    }

    function renderPreview(parsed) {
        var ingredientRows = Array.isArray(parsed.ingredients) ? parsed.ingredients : [];
        var instructionRows = Array.isArray(parsed.instructions) ? parsed.instructions : [];
        var hasTitle = !!(parsed.title || '').trim();

        title.value.textContent = parsed.title || '';
        appendIngredientRows(ingredientRows);
        appendInstructionRows(instructionRows);

        setSection(title, hasTitle, hasTitle ? strings.detected : strings.missing);
        setSection(ingredients, ingredientRows.length > 0, ingredientRows.length > 0 ? countLabel(ingredientRows.length, 'ingredient', 'ingredients') : strings.missing);
        setSection(instructions, instructionRows.length > 0, instructionRows.length > 0 ? countLabel(instructionRows.length, 'step', 'steps') : strings.missing);

        setMessage(strings.review);
        error.hidden = true;
    }

    [title, ingredients, instructions].forEach(function (section) {
        var parts = sectionParts(section);
        parts.button.addEventListener('click', function () {
            parts.body.hidden = !parts.body.hidden;
            parts.button.setAttribute('aria-expanded', parts.body.hidden ? 'false' : 'true');
        });
    });

    resetSections();

    function requestPreview() {
        var text = paste.value.trim();
        sequence++;
        if (!text) {
            resetSections();
            setMessage(strings.empty);
            return;
        }

        var requestId = sequence;
        setMessage(strings.parsing);

        var body = new URLSearchParams();
        body.set('action', 'cookbook_parse_text');
        body.set('_ajax_nonce', nonce);
        body.set('paste', paste.value);

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (requestId !== sequence) return;
            if (json && json.success) {
                renderPreview(json.data || {});
            } else {
                setError(json && json.data && json.data.message ? json.data.message : strings.error);
            }
        }).catch(function () {
            if (requestId === sequence) setError(strings.error);
        });
    }

    paste.addEventListener('input', function () {
        resizePaste();
        window.clearTimeout(timer);
        timer = window.setTimeout(requestPreview, 300);
    });

    function resizePaste() {
        paste.style.height = 'auto';
        paste.style.height = paste.scrollHeight + 'px';
    }

    var imageUrl = document.getElementById('image_url');
    var imagePreview = document.getElementById('image-url-preview');
    if (imageUrl && imagePreview) {
        var image = imagePreview.querySelector('img');
        imageUrl.addEventListener('input', function () {
            var url = imageUrl.value.trim();
            if (!url) {
                imagePreview.hidden = true;
                image.removeAttribute('src');
                return;
            }
            image.src = url;
            imagePreview.hidden = false;
        });
        image.addEventListener('error', function () {
            imagePreview.hidden = true;
        });
    }

    resizePaste();
    requestPreview();
})();
</script>

<?php if ( $autoimport ) : ?>
<div id="import-overlay" role="status" aria-live="polite">
    <div class="spinner" aria-hidden="true"></div>
    <p>
        <?php
        printf(
            /* translators: %s: hostname being imported from */
            esc_html__( 'Importing from %s…', 'cookbook' ),
            '<strong>' . esc_html( wp_parse_url( $source_url, PHP_URL_HOST ) ?: $source_url ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
        ?>
    </p>
</div>
<style>
    #import-overlay { position: fixed; inset: 0; background: var(--bg); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; z-index: 9999; }
    #import-overlay p { color: var(--muted); font-size: 1rem; margin: 0; }
    #import-overlay .spinner { width: 2.5rem; height: 2.5rem; border: 3px solid var(--line); border-top-color: var(--accent); border-radius: 50%; animation: byi-spin 0.9s linear infinite; }
    @keyframes byi-spin { to { transform: rotate(360deg); } }
</style>
<script>
(function () {
    var form = document.getElementById('import-form');
    if (form) form.submit();
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
