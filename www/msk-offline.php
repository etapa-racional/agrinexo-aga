<?php

// The page msk-sw.js serves when a navigation fails.
require_once __DIR__ . '/config-en.php';
require_once __DIR__ . '/msk-lang-helpers.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($GLOBALS['AGN_CFG_LANGUAGE']['code'], ENT_QUOTES, 'UTF-8'); ?>">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo th('shell.offline_title'); ?> — <?php echo th('shell.app_name'); ?></title>
  <meta name="theme-color" content="#414C33">
  <style>
    :root {
      --er-darkgreen: #414C33;
      --er-bg: #f0f2f5;
      --er-gray: #65676b;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      min-height: 100dvh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: var(--er-bg);
      color: #050505;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      text-align: center;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
      padding: 32px 24px;
      max-width: 420px;
      width: 100%;
    }

    /* Same 180x40 intrinsic cap the navbar logo uses in msk-head.php. */
    .card img {
      height: auto;
      width: auto;
      max-height: 40px;
      max-width: 100%;
    }

    h1 {
      margin: 24px 0 8px;
      font-size: 1.25rem;
      color: var(--er-darkgreen);
    }

    p {
      margin: 0 0 24px;
      color: var(--er-gray);
      line-height: 1.5;
    }

    button {
      background: var(--er-darkgreen);
      color: #fff;
      border: 0;
      border-radius: 8px;
      padding: 10px 20px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
    }

    button:hover {
      opacity: 0.9;
    }
  </style>
</head>

<body>
  <div class="card">
    <img src="msk/logoi.png" alt="<?php echo th('shell.app_name'); ?>">
    <h1><?php echo th('shell.offline_title'); ?></h1>
    <p><?php echo th('shell.offline_message'); ?></p>
    <button type="button" id="retry"><?php echo th('shell.offline_retry'); ?></button>
  </div>

  <script>
    // This page renders in two places: top-level, when msk-sw.js answers a
    // cold launch with no network, and inside #contentFrame or #assistantFrame
    // when a frame's own request fails. In the second case location.reload()
    // would just fetch this page back into the same frame, leaving the shell
    // around it still broken - so recovery always targets window.top.
    //
    // Resolved against this document rather than left relative: assigning a
    // relative URL to another window's location resolves it against the
    // calling script's base, which is right here but reads like a bug.
    const SHELL = new URL(
      <?php echo json_encode(
          $GLOBALS['AGN_CFG_SHELL']['shell'],
          JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
      ); ?>,
      location.href
    ).href;

    function goShell() {
      // Both frames are same-origin, so window.top is writable; the catch is
      // only there to degrade to a same-frame navigation if that ever changes.
      try {
        (window.top || window).location.href = SHELL;
      } catch (e) {
        window.location.href = SHELL;
      }
    }

    document.getElementById('retry').addEventListener('click', goShell);

    // Come back by itself the moment connectivity returns. If both frames are
    // showing this page they will each fire, all aimed at the same URL.
    window.addEventListener('online', goShell);
  </script>
</body>

</html>
