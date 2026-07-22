<?php
// Secret endpoint to reset OPcache after atomic deploy
if (defined('SR_OPCACHE_SECRET') && !empty(SR_OPCACHE_SECRET) && isset($_GET['token']) && hash_equals(SR_OPCACHE_SECRET, $_GET['token'])) {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    http_response_code(200);
    die('OPcache reset successful');
}
http_response_code(403);
die('Forbidden');