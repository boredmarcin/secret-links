<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

function autoDetectUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['REQUEST_URI']);
    $path = rtrim($path, '/');

    return $protocol . $host . $path;
}

$configExists = file_exists(__DIR__ . '/config.php');
$urlMismatch = false;

if ($configExists) {
    require_once __DIR__ . '/config.php';
    $currentUrl = autoDetectUrl();
    $configuredUrl = defined('APP_URL') ? APP_URL : '';

    $urlMismatch = (rtrim($configuredUrl, '/') !== rtrim($currentUrl, '/'));
}

if ($configExists && !$urlMismatch && !isset($_GET['reinstall'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Secret Links - Już zainstalowane</title>
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { font-family: 'Outfit', sans-serif; background: #F5F2EB; color: #111; }
            .display { font-family: 'Bebas Neue', sans-serif; text-transform: uppercase; letter-spacing: 1px; }
            .mono { font-family: 'Space Mono', monospace; }
        </style>
    </head>
    <body class="min-h-screen p-8 flex items-center justify-center">
        <div class="max-w-md w-full">
            <div class="bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-8 text-center">
                <div class="display text-6xl mb-4" style="color: #FF3B30;">!</div>
                <h2 class="display text-2xl mb-2">Aplikacja jest już zainstalowana</h2>
                <p class="text-sm mb-6" style="color: #999;">Ze względów bezpieczeństwa instalator został zablokowany.</p>
                <div class="border-2 rounded-sm p-4 mb-6" style="border-color: #FF3B30; background: rgba(255,59,48,0.03);">
                    <p class="mono text-xs uppercase tracking-wider font-bold" style="color: #FF3B30; letter-spacing: 2px;">Usuń plik install.php z serwera!</p>
                </div>
                <div class="flex gap-4">
                    <a href="index.php" class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 bg-[#111] text-white border-2 border-[#111] rounded-sm font-['Space_Mono'] text-xs uppercase tracking-wider transition-all hover:bg-[#2D5BFF] hover:border-[#2D5BFF]">
                        Przejdź do aplikacji
                    </a>
                    <a href="?reinstall=1" class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 bg-transparent text-[#111] border-2 border-[rgba(17,17,17,0.2)] rounded-sm font-['Space_Mono'] text-xs uppercase tracking-wider transition-all hover:border-[#111]">
                        Przeinstaluj
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$step = $_GET['step'] ?? 'check';
$config = [];

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $config = [
        'app_name' => defined('APP_NAME') ? APP_NAME : 'Secret Links',
        'app_url' => defined('APP_URL') ? APP_URL : '',
        'app_env' => defined('APP_ENV') ? APP_ENV : 'production',
        'message_expiry' => defined('MESSAGE_DEFAULT_EXPIRY') ? MESSAGE_DEFAULT_EXPIRY : 86400,
        'message_max_size' => defined('MESSAGE_MAX_SIZE') ? MESSAGE_MAX_SIZE : 10000,
        'burn_delay' => defined('MESSAGE_BURN_DELAY') ? MESSAGE_BURN_DELAY : 10,
        'rate_limit_requests' => defined('RATE_LIMIT_REQUESTS') ? RATE_LIMIT_REQUESTS : 10,
        'rate_limit_window' => defined('RATE_LIMIT_WINDOW') ? RATE_LIMIT_WINDOW : 60,
        'enable_test_mode' => defined('ENABLE_TEST_MODE') ? ENABLE_TEST_MODE : true
    ];
}

function checkRequirements() {
    $requirements = [
        'PHP Version >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'JSON Extension' => extension_loaded('json'),
        'MB String Extension' => extension_loaded('mbstring'),
        'Root Directory Writable' => is_writable(__DIR__),
        'Storage Directory' => checkStorageDirectory(),
        'Public Directory Writable' => is_writable(__DIR__ . '/public') || !file_exists(__DIR__ . '/public'),
        'Config File Writable' => is_writable(__DIR__) || (file_exists(__DIR__ . '/config.php') && is_writable(__DIR__ . '/config.php'))
    ];

    return $requirements;
}

function checkStorageDirectory() {
    $storageDir = __DIR__ . '/storage';
    if (!file_exists($storageDir)) {
        if (@mkdir($storageDir, 0755, true)) {
            return true;
        }
        return false;
    }
    return is_writable($storageDir);
}

function generateConfig($data) {
    $template = '<?php

define(\'APP_NAME\', \'' . addslashes($data['app_name']) . '\');
define(\'APP_URL\', \'' . addslashes($data['app_url']) . '\');
define(\'APP_ENV\', \'' . addslashes($data['app_env']) . '\');

define(\'STORAGE_PATH\', __DIR__ . \'/storage/messages/\');
define(\'ACTIVE_MESSAGES_PATH\', STORAGE_PATH . \'active/\');
define(\'EXPIRED_MESSAGES_PATH\', STORAGE_PATH . \'expired/\');

define(\'MESSAGE_DEFAULT_EXPIRY\', ' . intval($data['message_expiry']) . ');
define(\'MESSAGE_MAX_SIZE\', ' . intval($data['message_max_size']) . ');
define(\'MESSAGE_BURN_DELAY\', ' . intval($data['burn_delay']) . ');
define(\'ENABLE_TEST_MODE\', ' . ($data['enable_test_mode'] ? 'true' : 'false') . ');

define(\'ENCRYPTION_METHOD\', \'AES-256-CBC\');
define(\'RATE_LIMIT_REQUESTS\', ' . intval($data['rate_limit_requests']) . ');
define(\'RATE_LIMIT_WINDOW\', ' . intval($data['rate_limit_window']) . ');
define(\'APP_SECRET\', \'' . bin2hex(random_bytes(32)) . '\');

if (APP_ENV === \'development\') {
    error_reporting(E_ALL);
    ini_set(\'display_errors\', 1);
} else {
    error_reporting(0);
    ini_set(\'display_errors\', 0);
}

date_default_timezone_set(\'UTC\');

if (session_status() === PHP_SESSION_NONE) {
    ini_set(\'session.cookie_httponly\', 1);
    ini_set(\'session.use_only_cookies\', 1);
    ini_set(\'session.cookie_samesite\', \'Strict\');
    if (APP_ENV === \'production\') {
        ini_set(\'session.cookie_secure\', 1);
    }
}

if (!file_exists(ACTIVE_MESSAGES_PATH)) {
    mkdir(ACTIVE_MESSAGES_PATH, 0755, true);
}
if (!file_exists(EXPIRED_MESSAGES_PATH)) {
    mkdir(EXPIRED_MESSAGES_PATH, 0755, true);
}
';

    return $template;
}

if ($_POST && $step === 'configure') {
    $config = [
        'app_name' => $_POST['app_name'] ?? 'Secret Links',
        'app_url' => rtrim($_POST['app_url'] ?? '', '/'),
        'app_env' => $_POST['app_env'] ?? 'production',
        'message_expiry' => intval($_POST['message_expiry'] ?? 86400),
        'message_max_size' => intval($_POST['message_max_size'] ?? 10000),
        'burn_delay' => intval($_POST['burn_delay'] ?? 10),
        'rate_limit_requests' => intval($_POST['rate_limit_requests'] ?? 10),
        'rate_limit_window' => intval($_POST['rate_limit_window'] ?? 60),
        'enable_test_mode' => isset($_POST['enable_test_mode']) && $_POST['enable_test_mode'] === '1'
    ];

    $configContent = generateConfig($config);

    if (file_put_contents(__DIR__ . '/config.php', $configContent)) {
        if (!file_exists(__DIR__ . '/storage/messages/active')) {
            mkdir(__DIR__ . '/storage/messages/active', 0755, true);
        }
        if (!file_exists(__DIR__ . '/storage/messages/expired')) {
            mkdir(__DIR__ . '/storage/messages/expired', 0755, true);
        }

        $step = 'complete';
    } else {
        $error = 'Nie udało się zapisać pliku konfiguracyjnego. Sprawdź uprawnienia.';
    }
}

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secret Links - Instalacja</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background: #F5F2EB; color: #111; }
        .display { font-family: 'Bebas Neue', sans-serif; text-transform: uppercase; letter-spacing: 1px; }
        .mono { font-family: 'Space Mono', monospace; }
        .requirement-ok { color: #10b981; }
        .requirement-fail { color: #FF3B30; }
        .install-input {
            width: 100%;
            padding: 12px 16px;
            background: white;
            color: #111;
            border: 2px solid rgba(17,17,17,0.15);
            border-radius: 2px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }
        .install-input:focus {
            outline: none;
            border-color: #2D5BFF;
        }
        .install-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 28px;
            background: #111;
            color: white;
            border: 2px solid #111;
            border-radius: 2px;
            font-family: 'Space Mono', monospace;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .install-btn:hover { background: #2D5BFF; border-color: #2D5BFF; }
        .install-label {
            font-family: 'Space Mono', monospace;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #999;
            display: block;
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="min-h-screen p-8">
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-10">
            <h1 class="display text-5xl tracking-wide mb-2">Secret Links</h1>
            <p class="mono text-xs uppercase tracking-widest" style="color: #999; letter-spacing: 3px;">Instalacja</p>
        </div>

        <?php if ($step === 'check'): ?>
            <div class="bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-6 mb-6">
                <p class="mono text-xs uppercase tracking-widest mb-4" style="color: #999; letter-spacing: 3px;">Sprawdzanie wymagań</p>

                <?php
                $requirements = checkRequirements();
                $allOk = true;
                foreach ($requirements as $name => $ok):
                    if (!$ok) $allOk = false;
                ?>
                    <div class="flex justify-between items-center py-3 border-b" style="border-color: rgba(17,17,17,0.06);">
                        <span class="text-sm"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="mono text-xs uppercase tracking-wider <?php echo $ok ? 'requirement-ok' : 'requirement-fail'; ?>">
                            <?php echo $ok ? '&#10003; OK' : '&#10007; BŁĄD'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <?php if ($allOk): ?>
                    <div class="mt-6">
                        <a href="?step=configure" class="install-btn">
                            Przejdź do konfiguracji
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mt-6 border-2 rounded-sm p-4" style="border-color: #FF3B30; background: rgba(255,59,48,0.03);">
                        <p class="mono text-xs uppercase tracking-wider font-bold" style="color: #FF3B30;">Nie wszystkie wymagania są spełnione!</p>
                        <p class="text-sm mt-1" style="color: #999;">Napraw błędy przed kontynuowaniem instalacji.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 'configure'): ?>
            <div class="bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-6">
                <p class="mono text-xs uppercase tracking-widest mb-6" style="color: #999; letter-spacing: 3px;">Konfiguracja</p>

                <?php if (isset($error)): ?>
                    <div class="mb-4 border-2 rounded-sm p-4" style="border-color: #FF3B30; background: rgba(255,59,48,0.03);">
                        <p class="text-sm" style="color: #FF3B30;"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <div>
                        <label class="install-label">Nazwa aplikacji</label>
                        <input type="text" name="app_name" value="<?php echo htmlspecialchars($config['app_name'] ?? 'Secret Links'); ?>" class="install-input">
                    </div>

                    <div>
                        <label class="install-label">URL aplikacji</label>
                        <input type="url" name="app_url" value="<?php echo htmlspecialchars($config['app_url'] ?? autoDetectUrl()); ?>"
                               class="install-input" placeholder="https://example.com/secret-links">
                        <p class="mono text-xs mt-1" style="color: #999;">Wykryto: <?php echo htmlspecialchars(autoDetectUrl(), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div>
                        <label class="install-label">Środowisko</label>
                        <select name="app_env" class="install-input" style="cursor: pointer;">
                            <option value="production" <?php echo ($config['app_env'] ?? '') === 'production' ? 'selected' : ''; ?>>Production</option>
                            <option value="development" <?php echo ($config['app_env'] ?? '') === 'development' ? 'selected' : ''; ?>>Development</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="install-label">Wygaśnięcie (sekundy)</label>
                            <input type="number" name="message_expiry" value="<?php echo $config['message_expiry'] ?? 86400; ?>" class="install-input">
                            <p class="mono text-xs mt-1" style="color: #999;">86400 = 24h</p>
                        </div>
                        <div>
                            <label class="install-label">Burn delay (sekundy)</label>
                            <input type="number" name="burn_delay" value="<?php echo $config['burn_delay'] ?? 10; ?>" class="install-input">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="install-label">Max rozmiar (znaki)</label>
                            <input type="number" name="message_max_size" value="<?php echo $config['message_max_size'] ?? 10000; ?>" class="install-input">
                        </div>
                        <div>
                            <label class="install-label">Rate limit (req/min)</label>
                            <input type="number" name="rate_limit_requests" value="<?php echo $config['rate_limit_requests'] ?? 10; ?>" class="install-input">
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="enable_test_mode" value="1"
                                   <?php echo ($config['enable_test_mode'] ?? true) ? 'checked' : ''; ?>
                                   class="w-4 h-4 accent-[#2D5BFF]">
                            <span class="ml-3">
                                <span class="font-medium text-sm">Włącz tryb testowy</span>
                                <span class="mono text-xs block uppercase tracking-wider" style="color: #999;">Wiadomości testowe nie znikają po odczycie</span>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="install-btn">
                        Zapisz konfigurację
                    </button>
                </form>
            </div>

        <?php elseif ($step === 'complete'): ?>
            <div class="bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-6 text-center">
                <div class="display text-6xl mb-4" style="color: #C8FF00;">&#10003;</div>

                <h2 class="display text-2xl mb-2">Instalacja zakończona</h2>
                <p class="text-sm mb-6" style="color: #999;">Secret Links zostało pomyślnie zainstalowane i skonfigurowane.</p>

                <div class="border-2 rounded-sm p-6 mb-6 text-left" style="border-color: #FF3B30; background: rgba(255,59,48,0.03);">
                    <p class="mono text-xs uppercase tracking-wider font-bold mb-2" style="color: #FF3B30; letter-spacing: 2px;">Ważne - bezpieczeństwo!</p>
                    <p class="text-sm mb-3">Musisz natychmiast usunąć plik install.php z serwera!</p>
                    <code class="mono text-xs block p-3 bg-[#111] text-white rounded-sm">rm <?php echo htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8'); ?>/install.php</code>
                </div>

                <div class="space-y-4">
                    <a href="index.php" class="install-btn">
                        Przejdź do aplikacji
                    </a>

                    <div class="text-left border-2 rounded-sm p-4" style="border-color: rgba(17,17,17,0.1);">
                        <p class="mono text-xs uppercase tracking-wider mb-3" style="color: #999; letter-spacing: 2px;">Następne kroki</p>
                        <ul class="text-sm space-y-2" style="color: #666;">
                            <li class="font-bold" style="color: #FF3B30;">Usuń plik install.php z serwera</li>
                            <li>Skonfiguruj HTTPS</li>
                            <li>Automatyczne czyszczenie jest już włączone</li>
                            <li>W produkcji zmień APP_ENV na 'production'</li>
                            <li>Ustaw uprawnienia: <code class="mono text-xs px-1 py-0.5 bg-[#F5F2EB] rounded-sm">chmod 644 config.php</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-8">
            <p class="mono text-xs uppercase tracking-wider" style="color: #999; letter-spacing: 2px;">Secret Links v1.0</p>
        </div>
    </div>
</body>
</html>
