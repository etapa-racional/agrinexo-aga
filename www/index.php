<?php

// Languages on offer = the shells present; discovered, not listed.

foreach (glob(__DIR__ . '/config-*.php') as $mskConfig) {
    require_once $mskConfig;
}

$available = array_filter($GLOBALS['AGN_CFG_SHELLS'] ?? [], static function (array $s): bool {
    return is_file(__DIR__ . '/' . $s['file']);
});

if (count($available) <= 1) {
    $only = $available ? reset($available)['file'] : 'AGA-PT26-AGT-EN.php';
    header('Location: ' . $only);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGRINEXO</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #fafafa;
            color: #414C33;
        }

        img {
            max-width: 220px;
        }

        ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 12px;
        }

        a {
            display: inline-block;
            padding: 10px 22px;
            border: 1px solid #414C33;
            border-radius: 6px;
            color: #414C33;
            text-decoration: none;
            font-weight: 600;
        }

        a:hover,
        a:focus {
            background: #414C33;
            color: #fff;
        }
    </style>
</head>

<body>
    <img src="msk/logoi.png" alt="AGRINEXO">
    <ul>
        <?php foreach ($available as $code => $shell): ?>
            <li><a href="<?php echo htmlspecialchars($shell['file'], ENT_QUOTES, 'UTF-8'); ?>"
                   lang="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php
                echo htmlspecialchars($shell['label'], ENT_QUOTES, 'UTF-8'); ?></a></li>
        <?php endforeach; ?>
    </ul>
</body>

</html>
