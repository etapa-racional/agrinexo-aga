<?php

// The AGA shell layout, shared by both language shells.
require_once __DIR__ . '/msk-lang-helpers.php';

$mskLang   = $GLOBALS['AGN_CFG_LANGUAGE']['code'] ?? 'en';
$mskAppDir = $GLOBALS['AGN_CFG_LANGUAGE']['app_dir'] ?? '../app';

// The six app destinations, in the order both menus render them.
$mskNav = [
  ['key' => 'shell.fields',     'page' => 'fields.php',     'icon' => 'grass',        'requires' => 'login'],
  ['key' => 'shell.animals',    'page' => 'animals.php',    'icon' => 'pets',         'requires' => 'login'],
  ['key' => 'shell.operations', 'page' => 'operations.php', 'icon' => 'local_florist', 'requires' => 'login'],
  ['key' => 'shell.report',     'page' => 'report.php',     'icon' => 'account_tree', 'requires' => 'login'],
  ['key' => 'shell.kbase',      'page' => 'kbase.php',      'icon' => 'menu_book',    'requires' => 'superuser'],
  ['key' => 'shell.help',       'page' => 'help.php',       'icon' => 'help_outline', 'requires' => null],
];

function msk_frame_onclick(string $page): string
{
  $src = ($GLOBALS['AGN_CFG_LANGUAGE']['app_dir'] ?? '../app') . '/' . $page;
  return htmlspecialchars(
    "mskNavigate('" . $src . "'); return false;",
    ENT_QUOTES,
    'UTF-8'
  );
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($mskLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
  <?php
  include "msk-head.php";
  ?>
</head>

<body>

  <!-- ===== TOP BAR ===== -->
  <div class="er-topbar-header">
    <div class="row">
      <?php
      include "msk-header.php";
      ?>
    </div>
  </div>
  <div class="er-topbar-app">
    <div class="er-appbar d-flex align-items-center er-topbar-app-row">

      <div class="topbar-avatar" id="appMenuToggle" data-bs-toggle="dropdown" aria-expanded="false" style="margin-left: 5px;" title="<?php echo th('shell.menu'); ?>"><i class="bi bi-chevron-down"></i></div>
      <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
        <?php foreach ($mskNav as $item): ?>
          <li<?php echo $item['requires'] ? ' data-requires="' . $item['requires'] . '"' : ''; ?>><a class="dropdown-item" href="#" onclick="<?php echo msk_frame_onclick($item['page']); ?>"><?php echo th($item['key']); ?></a></li>
          <?php endforeach; ?>
      </ul>
      <div class="flex-grow-1"></div>

      <!-- Spacer -->
      <h6>
        <a class="app-home" style="color: white" href="#" title="<?php echo th('shell.app_home'); ?>"
          onclick="<?php echo msk_frame_onclick('databases.php'); ?>"><?php echo th('shell.app_title'); ?><span id="selectedDbLabel"></span></a>
      </h6>
      <div class="flex-grow-1"></div>
      <div class="topbar-avatar" id="assistantToggle" data-requires="database" title="<?php echo th('shell.assistant'); ?>" aria-controls="assistantPane" aria-expanded="false" style="margin-right: 5px;"><span class="material-icons" style="font-size: 20px;">auto_awesome</span></div>
      <div id="userMenuLoggedIn">
        <div class="topbar-avatar" data-bs-toggle="dropdown" aria-expanded="false" title="<?php echo th('shell.account'); ?>" style="margin-right: 5px;"><i class="bi bi-person-fill"></i></div>
        <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
          <li><a class="dropdown-item" href="#" onclick="<?php echo msk_frame_onclick('profile.php'); ?>"><?php echo th('shell.profile'); ?></a></li>
          <li><a class="dropdown-item" href="#" onclick="<?php echo msk_frame_onclick('change-password.php'); ?>"><?php echo th('shell.change_password'); ?></a></li>
          <li>
            <hr class="dropdown-divider">
          </li>
          <li><a class="dropdown-item" href="#" onclick="localStorage.removeItem('auth_token'); window.krd = window.dbName = undefined; refreshMenuAuthState(); <?php echo msk_frame_onclick('login.php'); ?>"><?php echo th('shell.sign_out'); ?></a></li>
        </ul>
      </div>
      <div id="userMenuAnon" class="topbar-avatar" title="<?php echo th('shell.sign_in'); ?>" style="display:none; margin-right: 5px;" onclick="<?php echo msk_frame_onclick('login.php'); ?>"><i class="bi bi-box-arrow-in-right"></i></div>
      <!-- Spacer -->
    </div>
  </div>

  <!-- ===== SIDEBAR - Wide screens only (≥1200px); toggled by the hamburger button ===== -->
  <aside class="er-sidebar sidebar-expanded" id="sidebar">
    <?php foreach ($mskNav as $item): ?>
      <a class="sidebar-item" <?php echo $item['requires'] ? ' data-requires="' . $item['requires'] . '"' : ''; ?> href="#" title="<?php echo th($item['key']); ?>" onclick="<?php echo msk_frame_onclick($item['page']); ?>"><span class="material-icons sidebar-item-icon"><?php echo $item['icon']; ?></span><span class="sidebar-item-text"><?php echo th($item['key']); ?></span></a>
    <?php endforeach; ?>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <div class="er-content-wrapper sidebar-expanded" id="contentWrapper">
    <main class="er-content">
      <div class="er-content-pane">
        <iframe id="contentFrame" src="<?php echo htmlspecialchars($mskAppDir, ENT_QUOTES, 'UTF-8'); ?>/databases.php" style="width: 100%; height: calc(100vh - var(--toolbar-height) - 10px); max-height: 100%;"></iframe>
      </div>
      <div class="er-assistant-pane assistant-collapsed" id="assistantPane">
        <iframe id="assistantFrame" style="width: 100%; height: calc(100vh - var(--toolbar-height) - 10px); max-height: 100%;"></iframe>
      </div>
    </main>
  </div>

  <!-- Scrim for the <1200px assistant overlay; msk-agt-footer.php shows it only
       at that width, where the pane is off-canvas rather than inline. -->
  <div class="er-mobile-overlay" id="shellScrim"></div>

  <?php
  include "msk-agt-footer.php";
  ?>
</body>

</html>