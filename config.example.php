<?php


define('APP_NAME', 'Secret Links');
define('APP_URL', 'https://your-domain.com/secret-links');
define('APP_ENV', 'production');

define('STORAGE_PATH', __DIR__ . '/storage/messages/');
define('ACTIVE_MESSAGES_PATH', STORAGE_PATH . 'active/');
define('EXPIRED_MESSAGES_PATH', STORAGE_PATH . 'expired/');

define('MESSAGE_DEFAULT_EXPIRY', 86400);
define('MESSAGE_MAX_SIZE', 10000);
define('MESSAGE_BURN_DELAY', 50);

define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('RATE_LIMIT_REQUESTS', 10);
define('RATE_LIMIT_WINDOW', 60);
define('ENABLE_TEST_MODE', false);
define('APP_SECRET', 'CHANGE_THIS_TO_A_RANDOM_STRING');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set('UTC');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    if (APP_ENV === 'production') {
        ini_set('session.cookie_secure', 1);
    }
}

if (!file_exists(ACTIVE_MESSAGES_PATH)) {
    mkdir(ACTIVE_MESSAGES_PATH, 0755, true);
}
if (!file_exists(EXPIRED_MESSAGES_PATH)) {
    mkdir(EXPIRED_MESSAGES_PATH, 0755, true);
}