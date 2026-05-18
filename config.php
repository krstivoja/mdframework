<?php

defined('FRONTPRESS_BOOT') || exit;

// ── Admin login ───────────────────────────────────────────────────────────
define('MD_ADMIN_USER',      getenv('MD_ADMIN_USER')      ?: 'fpsadmin');
define('MD_ADMIN_PASS_HASH', '$2y$10$vtjwgYT9XXXiDip2QUXsNusBbKvBog/zopVEa/t9PR4.0kq2P532y');

// ── Runtime ───────────────────────────────────────────────────────────────
define('MD_APP_ENV',              getenv('MD_APP_ENV')              ?: 'dev');
define('MD_APP_DEBUG',            getenv('MD_APP_DEBUG')            ?: '0');
define('MD_SESSION_IDLE_SECONDS', getenv('MD_SESSION_IDLE_SECONDS') ?: '7200');
