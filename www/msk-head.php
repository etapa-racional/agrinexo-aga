<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AGRINEXO .PT</title>
<meta name="robots" content="index, follow" />

<!-- ===== INSTALLABLE APP =====
     One manifest per language (different name, start_url and id), so EN and PT
     install as two apps rather than overwriting each other. iOS ignores the
     manifest's name and icons for Add to Home Screen, hence the apple-* tags.
     Installability needs a secure context: HTTPS, or localhost. -->
<link rel="manifest" href="<?php echo htmlspecialchars($GLOBALS['AGN_CFG_SHELL']['manifest'] ?? 'msk-manifest.php', ENT_QUOTES, 'UTF-8'); ?>">
<meta name="theme-color" content="#414C33">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo th('shell.app_name'); ?>">
<link rel="apple-touch-icon" href="msk/apple-touch-icon.png">
<link rel="icon" href="msk/icon-192.png" sizes="192x192">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Sidebar nav icons, so the shell shows each page its own toolbar icon. -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,700&subset=latin,latin-ext' rel='stylesheet' type='text/css'>
<link href="https://fonts.googleapis.com/css?family=Roboto&display=swap" rel="stylesheet">
<script src="ger/jui/external/jquery/jquery.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mulish:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Patrick+Hand&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&family=Jost:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

<!-- Bootstrap core CSS -->
<link href="ger/bst/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Custom styles for this template -->
<link href="msk/GeralV3.css" rel="stylesheet">
<style>
    :root {
        /* msk/logoi.png is 180x40 intrinsic - never scale it up past that. */
        --brand-logo-h: 40px;
        --brand-logo-h-sm: 36px;
        --brand-logo-max-w-sm: 42vw;

        /* The msk-header.php navbar is this tall at every width, so .er-topbar-header
       keeps a constant height across the AGA pages. */
        --brand-row-h: 70px;
    }

    /* min-height rather than height: below lg the Bootstrap collapse expands
     inside .navbarx, and a fixed height would clip the open mobile menu. */
    .navbarx {
        min-height: var(--brand-row-h);
    }

    /* Auto width AND height with two maxima is what preserves the 180x40 ratio.
     Pairing an explicit height with max-width would squash the image instead.
     Do not add `display: block` - it changes the navbar's measured height. */
    .navbar-brand img {
        height: auto;
        width: auto;
        max-height: var(--brand-logo-h);
        max-width: 100%;
    }

    /* The vw cap stops the logo crowding out the toggler on a 320px phone. */
    @media (max-width: 767.98px) {

        .navbar-brand img {
            max-height: var(--brand-logo-h-sm);
            max-width: var(--brand-logo-max-w-sm);
        }
    }

</style>
<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
<!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

<style>
    :root {
        --er-darkgreen: #414C33;
        --er-blue: #1877f2;
        --er-bg: #f0f2f5;
        --er-gray: #65676b;
        --er-light-gray: #e4e6eb;
        --sidebar-width: 220px;
        --sidebar-collapsed-width: 56px;

        /* Assistant pane. Inline beside the content at >=1200px, an off-canvas
         overlay below that (see .er-assistant-pane). --content-gap is the
         breathing space between the two panes, subtracted from the content
         pane so the pair still totals 100%. */
        --assistant-width: 30%;
        --content-gap: 10px;
        /* Height of the .er-topbar-app row. Raise this when the app title
         needs a third line - see .er-appbar h6 below. */
        --app-row-h: 50px;

        /* .er-topbar-header's brand row plus the .er-topbar-app row below it.
         The sidebar, content wrapper and mobile overlay all offset against this
         total, so it must stay equal to the two bars combined - hence the two
         tokens rather than a repeated literal, which drifts the moment one of
         the two bars is resized on its own. */
        --toolbar-height: calc(var(--brand-row-h) + var(--app-row-h));
    }

    body {
        background-color: var(--er-bg);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }

    /* ===== TOP BAR =====
       Two stacked fixed bars: the brand row on top, the app controls row
       below it. Together they still fill --toolbar-height, which the
       sidebar, content wrapper and mobile overlay all offset against. */
    .er-topbar-header,
    .er-topbar-app {
        background-color: var(--er-darkgreen);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
    }

    .er-topbar-header {
        height: var(--brand-row-h);
        z-index: 1000;
    }

    .er-topbar-app {
        top: var(--brand-row-h);
        height: var(--app-row-h);
        z-index: 999;
    }

    /* Horizontal edge padding for the app controls row only — msk-header.php's
       navbar brings its own via .container-fluid, so the header bar needs none. */
    .er-topbar-app-row {
        padding: 5px 5px 5px 5px;
    }

    /* The app bar is a centred flex row, so any item that grows re-centres
       every sibling: a title wrapping to a second line was shifting the menu
       toggle and both avatars. Pinning the height to .er-topbar-app's own
       takes the title out of the row's height entirely - it still wraps, but
       the wrapped block is centred inside a box that no longer moves. */
    .er-appbar {
        height: 100%;
    }

    /* Deliberately NOT clipped. Two lines at this line-height are ~38px and
       clear the default 50px row; a third line overflows visibly, which is the
       cue to either shorten shell.app_title in the msk-lang-*.php dictionary or
       raise --app-row-h (~58px fits three lines). */
    .er-appbar h6 {
        margin: 0;
        min-width: 0;
        line-height: 1.2;
        text-align: center;
    }

    .er-appbar .app-home {
        display: block;
        color: white; 
    }

    /* The avatar itself is already flex-shrink: 0; this stops its wrapper
       collapsing and squashing it before the title has given up any width. */
    .er-appbar #userMenuLoggedIn {
        flex-shrink: 0;
    }

    .er-topbar-app .topbar-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: #fff;
        color: var(--er-darkgreen);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        flex-shrink: 0;
    }

    /* ===== SIDEBAR - Wide screens (≥1200px) ===== */
    .er-sidebar {
        position: fixed;
        top: var(--toolbar-height);
        left: 0;
        bottom: 0;
        width: var(--sidebar-width);
        background: #fff;
        overflow-y: auto;
        border-right: 1px solid var(--er-light-gray);
        transition: width 0.2s ease;
        z-index: 900;
        display: none;
    }

    /* ===== OFF-CANVAS (<1200px) =====
       Below 1200px the sidebar is not used at all: syncAppMenuMode() in
       the shell footer turns #appMenuToggle into a Bootstrap dropdown and the
       app bar's <ul> carries the navigation, which is why $mskNav renders into
       both. The assistant pane is the only off-canvas panel here. */

    /* Scrim for the assistant overlay. Covers the content only, so both bars
       stay live. It sits just under the panel (900) so a tap lands here rather
       than inside the assistant iframe - the only way to dismiss an overlay
       that swallows its own pointer events - and well under the two bars, so
       their dropdowns stay clickable while the overlay is open. */
    .er-mobile-overlay {
        display: none;
        position: fixed;
        top: var(--toolbar-height);
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 899;
    }

    .er-mobile-overlay.active {
        display: block;
    }

    @media (max-width: 1199px) {
        /* Off-canvas from the right. Fixed takes it out of flow, so
           .er-content-pane goes full width on its own and the transform - not
           a width - is what shows and hides it. */
        .er-assistant-pane {
            position: fixed;
            top: var(--toolbar-height);
            right: 0;
            bottom: 0;
            left: auto;
            width: min(420px, 100%);
            margin-left: 0;
            background: #fff;
            z-index: 900;
            transform: translateX(100%);
            transition: transform 0.25s ease;
            box-shadow: -2px 0 8px rgba(0, 0, 0, 0.2);
        }

        .er-assistant-pane:not(.assistant-collapsed) {
            transform: translateX(0);
        }
    }

    .er-sidebar .sidebar-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        color: #050505;
        transition: background-color 0.15s;
        white-space: nowrap;
        overflow: hidden;
        margin: 0 8px 2px;
        font-size: 0.95rem;
        font-weight: 500;
    }

    .er-sidebar .sidebar-item:hover {
        background-color: var(--er-bg);
    }

    .er-sidebar .sidebar-item-icon {
        flex-shrink: 0;
        width: 20px;
        text-align: center;
        font-size: 1.05rem;
        color: var(--er-gray);
    }

    /* Material glyphs run larger than the Bootstrap ones at the same size. */
    .er-sidebar .material-icons.sidebar-item-icon {
        font-size: 1.25rem;
        line-height: 1;
    }

    .er-sidebar .sidebar-item-text {
        overflow: hidden;
        text-overflow: ellipsis;
        color: #414C33;
    }

    /* ===== MAIN CONTENT ===== */
    .er-content-wrapper {
        margin-top: var(--toolbar-height);
        transition: margin-left 0.2s ease;
    }

    /* The two panes side by side. flex rather than the inline-block the panes
       used to carry inline: a collapsed pane is zero-width, and the whitespace
       text node between two inline-blocks is enough to push it onto a second
       line - which, with the iframe's viewport-tall height, would leave an
       empty screenful below the content. */
    .er-content {
        display: flex;
        align-items: stretch;
        /*max-width: 680px;*/
        /*margin: 16px auto;*/
        /*padding: 0 12px;*/
    }

    /* Takes whatever the assistant leaves, so it needs no state class of its
       own: collapsing the pane (or lifting it out of flow into the overlay)
       hands the width over automatically. */
    .er-content-pane {
        flex: 1 1 auto;
        min-width: 0;
    }

    /* Inline pane at >=1200px. Collapsed is flex-basis: 0 rather than
       display: none - display cannot transition, and keeping the iframe in the
       DOM means reopening does not re-request the pane. */
    .er-assistant-pane {
        flex: 0 0 var(--assistant-width);
        min-width: 0;
        overflow: hidden;
        margin-left: 1px;
        transition: flex-basis 0.2s ease, margin-left 0.2s ease;
    }

    .er-assistant-pane.assistant-collapsed {
        flex-basis: 0;
        margin-left: 0;
    }

    /* ===== WIDE SCREENS (≥1200px) ===== */
    @media (min-width: 1200px) {
        .er-sidebar {
            display: block;
        }

        .er-content-wrapper.sidebar-expanded {
            margin-left: var(--sidebar-width);
        }

        .er-content-wrapper.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

        .er-sidebar.sidebar-collapsed .sidebar-item {
            justify-content: center;
            padding: 10px 0;
            margin: 0 4px 2px;
        }

        .er-sidebar.sidebar-collapsed .sidebar-item-text {
            display: none;
        }

        .er-sidebar.sidebar-collapsed {
            width: var(--sidebar-collapsed-width);
        }
    }
</style>