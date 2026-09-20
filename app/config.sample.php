<?php
// Guarded defines: a per-context config loaded first wins.
if (!defined('ECO_API_URL')) { define('ECO_API_URL', 'https://agrinexo.pt/api/'); }
if (!defined('OPS_API_URL')) { define('OPS_API_URL', 'https://agrinexo.pt/api'); }
if (!defined('OPS_MEDIA_URL')) { define('OPS_MEDIA_URL', 'https://agrinexo.pt'); }
if (!defined('PLAN_PREP_DAYS'))    { define('PLAN_PREP_DAYS', 30); }
if (!defined('PLAN_CLEANUP_DAYS')) { define('PLAN_CLEANUP_DAYS', 30); }
if (!defined('ARCGIS_TOKEN')) { define('ARCGIS_TOKEN', ''); }
