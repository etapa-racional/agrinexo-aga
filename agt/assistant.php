<?php

// Entry point: a pathless require 'config.php' resolves to this instance.

$_GET['route'] = $_GET['action'] ?? 'status';

require __DIR__ . '/index.php';
