<?php
// Secret endpoint to reset OPcache after atomic deploy
if (defined('SR_OPCACHE_SECRET') && !empty(SR_OPCACHE_SECRET) && isset($_SERVER['HTTP_X_SR_RESET_TOKEN']) && hash_equals(SR_OPCACHE_SECRET, $_SERVER['HTTP_X_SR_RESET_TOKEN'])) {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    http_response_code(200);
    die('OPcache reset successful');
}
http_response_code(403);
die('Forbidden');