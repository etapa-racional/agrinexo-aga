<?php
// Shared <head> for the Quasar pages; require it first inside <head>.
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8'); ?></title>

<!-- Quasar Framework CSS -->
<link href="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.prod.css" rel="stylesheet" type="text/css">

<!-- Roboto + Material Icons -->
<link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900|Material+Icons" rel="stylesheet" type="text/css">

<style>
    /*
     * Brand palette. Overrides Quasar's own :root block (loaded above), which is
     * why this <style> must stay after the Quasar <link>. Everything using
     * color="primary", .bg-primary/.text-primary or var(--q-primary) follows.
     */
    :root {
        --q-secondary: #82B446;
        --q-primary:  #414C33;
    }

    /* App header: Quasar's brand primary, so it follows --q-primary if retuned. */
    .ag-base-q-header {
        background-color: white;
        color: var(--q-primary);
    }

    /*
     * Dialog header band, 50px tall. The vertical padding from
     * .q-card__section--vert has to go, or 16px+50px+16px makes an 82px band;
     * the 16px sides stay. Centring is free: the headers carry `row
     * items-center`, and align-items:center centres children whatever the
     * height. min-height, not height, so a long wrapped title grows the band
     * instead of overflowing it.
     */
    .modal-header {
        background-color: var(--q-secondary);
        color: var(--q-primary);
        min-height: 50px;
        padding: 0 16px;
    }

    /*
     * Dialog titles bold. Quasar's .text-h6 sets font-weight:500 on the div
     * itself, so a weight on .modal-header alone would not reach it -- hence the
     * descendant selector, which also outranks .text-h6 (0,2,0 vs 0,1,0).
     * Bold also lifts the 20px title to WCAG's large-text threshold (3:1),
     * which this header's 3.71:1 clears but the 4.5:1 normal-text one does not.
     */
    .modal-header,
    .modal-header .text-h6 {
        font-weight: 500;
    }

    /*
     * Button and tab labels in sentence case. Quasar's .q-btn and .q-tab both
     * force text-transform: uppercase; same specificity, so this wins only by
     * coming after its <link>. Makes the per-element no-caps prop unnecessary.
     */
    .q-btn,
    .q-tab {
        text-transform: none;
    }

    /*
     * Uncolored buttons: an explicit neutral instead of Quasar's `color: inherit`
     * (which resolved to black). Buttons carrying color="..." are untouched --
     * Quasar gives those .text-white, which is !important and outranks this.
     */
    .q-btn {
        color: #545454;
    }

    /* On a colored surface, keep inheriting it, or the label goes grey-on-brand. */
    .q-header .q-btn,
    .modal-header .q-btn {
        color: inherit;
    }

    /*
     * Disabled buttons fade rather than change color. Quasar's own
     * `.disabled { opacity: .7 !important }` needs !important to beat, and the
     * extra class outranks it.
     */
    .q-btn.disabled {
        opacity: 0.5 !important;
    }

    /*
     * Dialog cards that pin their header and actions. Quasar scrolls the card
     * itself (.q-dialog__inner>div { overflow:auto }), which is what pushes the
     * header out of view; making the card a flex column lets the .form-scroll
     * child take the overflow instead. No height here -- Quasar already bounds
     * the card in both maximized and minimized dialogs.
     */
    .form-card {
        display: flex;
        flex-direction: column;
    }

    .form-scroll {
        overflow-y: auto;
        flex: 1;
    }
</style>