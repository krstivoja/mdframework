<?php

// Tests run outside an HTTP entry point, so the framework's `MD_BOOT`
// guard wouldn't fire. Define it before autoloading any MD\* class so
// `defined('MD_BOOT') || exit;` in lib files lets the class load.
defined('MD_BOOT') || define('MD_BOOT', true);

require __DIR__ . '/../vendor/autoload.php';
