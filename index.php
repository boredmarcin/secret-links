<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($requestPath === '/install.php' && file_exists(__DIR__ . '/install.php')) {
    require __DIR__ . '/install.php';
    exit;
}

if (!file_exists(__DIR__ . '/config.php')) {
    ?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalacja wymagana - Secret Links</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 flex items-center justify-center p-4">
    <div class="max-w-md mx-auto text-center">
        <div class="bg-gray-800 rounded-2xl shadow-2xl p-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-600 rounded-2xl mb-6">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-3">Instalacja wymagana</h1>
            <p class="text-gray-400 mb-6">Aplikacja nie została jeszcze skonfigurowana. Kliknij poniżej, aby przeprowadzić instalację.</p>
            <a href="./install.php" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Rozpocznij instalację
            </a>
            <p class="text-xs text-gray-500 mt-4">Instalacja zajmuje zaledwie kilka sekund</p>
        </div>
    </div>
</body>
</html><?php
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Encryption.php';
require_once __DIR__ . '/src/FileStorage.php';
require_once __DIR__ . '/src/SecretMessage.php';

use SecretLinks\SecretMessage;

session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

$parsedAppUrl = parse_url(APP_URL);
$basePath = rtrim($parsedAppUrl['path'] ?? '', '/');

if ($basePath && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
$path = rtrim($path, '/');
if (empty($path)) {
    $path = '/';
}


$secretMessage = new SecretMessage();


try {
    if ($path === '/api/create' && $requestMethod === 'POST') {
        header('Content-Type: application/json');
        
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!$secretMessage->checkRateLimit($clientIp)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded. Please wait before creating another message.']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        if (!isset($input['content']) || empty(trim($input['content']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Message content is required']);
            exit;
        }
        
        $content = trim($input['content']);
        $testMode = isset($input['test_mode']) && $input['test_mode'] === true;
        $customExpiry = isset($input['custom_expiry']) ? intval($input['custom_expiry']) : MESSAGE_DEFAULT_EXPIRY;
        
        if ($customExpiry < 3600 || $customExpiry > 604800) {
            $customExpiry = MESSAGE_DEFAULT_EXPIRY;
        }
        
        $result = $secretMessage->create($content, $testMode, $customExpiry);
        
        echo json_encode([
            'success' => true,
            'data' => $result,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
        exit;
    }
    
    if (preg_match('#^/api/message/([a-zA-Z0-9_-]+)$#', $path, $matches) && $requestMethod === 'GET') {
        header('Content-Type: application/json');
        
        $messageId = $matches[1];
        $isTestMode = isset($_GET['test']) && $_GET['test'] === '1';
        
        $message = $secretMessage->read($messageId, $isTestMode);
        
        if (!$message) {
            http_response_code(404);
            echo json_encode(['error' => 'Message not found or has expired']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $message
        ]);
        exit;
    }
    
    if (preg_match('#^/public/(.+)$#', $path, $matches)) {
        $filePath = __DIR__ . '/public/' . $matches[1];
        $realPath = realpath($filePath);
        $publicDir = realpath(__DIR__ . '/public');

        if ($realPath && $publicDir && str_starts_with($realPath, $publicDir . DIRECTORY_SEPARATOR) && is_file($realPath)) {
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon'
            ];

            $ext = pathinfo($realPath, PATHINFO_EXTENSION);
            $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

            header('Content-Type: ' . $mimeType);
            header('Cache-Control: public, max-age=3600');
            readfile($realPath);
            exit;
        }
    }
    
    if ($path === '' || $path === '/' || $path === '/index.php') {
        require __DIR__ . '/views/home.php';
        exit;
    }
    
    if (preg_match('#^/message/([a-zA-Z0-9_-]+)$#', $path, $matches)) {
        $messageId = $matches[1];
        $isTestMode = isset($_GET['test']) && $_GET['test'] === '1';
        
        if (!$secretMessage->exists($messageId)) {
            http_response_code(404);
            require __DIR__ . '/views/404.php';
            exit;
        }
        
        require __DIR__ . '/views/message.php';
        exit;
    }
    
    if ($path === '/stats' && APP_ENV === 'development') {
        header('Content-Type: application/json');
        echo json_encode($secretMessage->getStats(), JSON_PRETTY_PRINT);
        exit;
    }
    
    if ($path === '/cleanup' && APP_ENV === 'development') {
        $cleaned = $secretMessage->cleanup();
        header('Content-Type: application/json');
        echo json_encode(['cleaned' => $cleaned]);
        exit;
    }
    
    http_response_code(404);
    require __DIR__ . '/views/404.php';
    
} catch (Exception $e) {
    error_log('[SecretLinks] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (APP_ENV === 'development') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        http_response_code(500);
        require __DIR__ . '/views/error.php';
    }
}