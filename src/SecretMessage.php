<?php

namespace SecretLinks;

/**
 * Core business logic for creating, reading, and managing encrypted one-time messages.
 * Handles message lifecycle, rate limiting, input validation, and automatic cleanup.
 */
class SecretMessage
{
    private Encryption $encryption;
    private FileStorage $storage;
    
    public function __construct()
    {
        $this->encryption = new Encryption();
        $this->storage = new FileStorage();
    }
    
    public function create(string $content, bool $testMode = false, ?int $customExpiry = null): array
    {
        $this->smartCleanup();
        
        $content = $this->sanitizeInput($content);
        
        if (empty($content)) {
            throw new \Exception('Message content cannot be empty');
        }
        
        if (strlen($content) > MESSAGE_MAX_SIZE) {
            throw new \Exception('Message content exceeds maximum size');
        }
        
        if ($this->containsMaliciousContent($content)) {
            throw new \Exception('Content contains potentially malicious data');
        }
        
        if ($testMode && (!defined('ENABLE_TEST_MODE') || !ENABLE_TEST_MODE)) {
            throw new \Exception('Test mode is not enabled');
        }
        
        $messageId = $this->encryption->generateMessageId();
        $encryptionKey = $this->encryption->generateKey();
        
        $encryptedContent = $this->encryption->encrypt($content, $encryptionKey);
        
        $expiryTime = $customExpiry ?: MESSAGE_DEFAULT_EXPIRY;
        
        $messageData = [
            'id' => $messageId,
            'content' => $encryptedContent,
            'created' => time(),
            'expires' => time() + $expiryTime,
            'test_mode' => $testMode,
            'views' => 0,
            'max_views' => $testMode ? PHP_INT_MAX : 1,
            'ip_hash' => $this->hashIp($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent_hash' => md5($_SERVER['HTTP_USER_AGENT'] ?? '')
        ];
        
        $this->storage->save($messageId, $messageData);
        
        return [
            'id' => $messageId,
            'key' => $encryptionKey,
            'url' => $this->generateUrl($messageId, $encryptionKey),
            'test_mode' => $testMode,
            'expires_at' => date('Y-m-d H:i:s', $messageData['expires'])
        ];
    }
    
    public function read(string $messageId, bool $isTestMode = false): ?array
    {
        $this->smartCleanup();
        
        $messageData = $this->storage->load($messageId);
        
        if (!$messageData) {
            return null;
        }
        
        if ($messageData['expires'] < time()) {
            $this->storage->moveToExpired($messageId);
            return null;
        }
        
        if ($messageData['views'] >= $messageData['max_views']) {
            return null;
        }
        
        $messageData['views']++;
        $messageData['last_viewed'] = time();
        $messageData['last_viewer_ip_hash'] = $this->hashIp($_SERVER['REMOTE_ADDR'] ?? '');
        
        $shouldDelete = !$messageData['test_mode'] && !$isTestMode && $messageData['views'] >= $messageData['max_views'];
        
        if ($shouldDelete) {
            $this->storage->moveToExpired($messageId);
        } else {
            $this->storage->save($messageId, $messageData);
        }
        
        return [
            'id' => $messageId,
            'content' => $messageData['content'],
            'created' => $messageData['created'],
            'test_mode' => $messageData['test_mode'],
            'will_delete' => $shouldDelete,
            'views' => $messageData['views'],
            'max_views' => $messageData['max_views']
        ];
    }
    
    public function exists(string $messageId): bool
    {
        $messageData = $this->storage->load($messageId);
        
        if (!$messageData) {
            return false;
        }
        
        if ($messageData['expires'] < time()) {
            $this->storage->moveToExpired($messageId);
            return false;
        }
        
        if (!$messageData['test_mode'] && $messageData['views'] >= $messageData['max_views']) {
            return false;
        }
        
        return true;
    }
    
    public function getInfo(string $messageId): ?array
    {
        $messageData = $this->storage->load($messageId);
        
        if (!$messageData) {
            return null;
        }
        
        return [
            'id' => $messageId,
            'created' => date('Y-m-d H:i:s', $messageData['created']),
            'expires' => date('Y-m-d H:i:s', $messageData['expires']),
            'test_mode' => $messageData['test_mode'],
            'views' => $messageData['views'],
            'max_views' => $messageData['max_views'],
            'is_expired' => $messageData['expires'] < time(),
            'is_viewed' => $messageData['views'] >= $messageData['max_views']
        ];
    }
    
    public function delete(string $messageId): bool
    {
        return $this->storage->delete($messageId);
    }
    
    public function cleanup(): int
    {
        return $this->storage->cleanupOldMessages();
    }
    
    private function smartCleanup(): void
    {
        static $operationCount = 0;
        static $lastCleanup = null;

        $operationCount++;
        $now = time();

        $shouldCleanup = false;

        if ($operationCount % 10 === 0) {
            $shouldCleanup = true;
        }

        if ($lastCleanup === null || ($now - $lastCleanup) > 3600) {
            $shouldCleanup = true;
        }

        if ($shouldCleanup) {
            $this->cleanup();
            $lastCleanup = $now;
        }
    }
    
    private function sanitizeInput(string $content): string {
        $content = str_replace("\0", '', $content);
        
        $content = trim($content);
        
        return $content;
    }
    
    private function containsMaliciousContent(string $content): bool {
        if (preg_match('/\x00/', $content)) {
            return true;
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            return true;
        }

        return false;
    }
    
    public function getStats(): array
    {
        return $this->storage->getStats();
    }
    
    private function generateUrl(string $messageId, string $key): string
    {
        $baseUrl = rtrim(APP_URL, '/');
        return $baseUrl . '/message/' . $messageId . '#' . $key;
    }
    
    private function hashIp(string $ip): string
    {
        $secret = defined('APP_SECRET') ? APP_SECRET : 'default-fallback-secret';
        return hash_hmac('sha256', $ip . date('Y-m-d'), $secret);
    }
    
    public function checkRateLimit(string $identifier): bool
    {
        $rateLimitFile = STORAGE_PATH . 'rate_limit_' . md5($identifier) . '.json';
        
        if (file_exists($rateLimitFile)) {
            $data = json_decode(file_get_contents($rateLimitFile), true);
            $now = time();
            
            $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now) {
                return ($now - $timestamp) < RATE_LIMIT_WINDOW;
            });
            
            if (count($data['requests']) >= RATE_LIMIT_REQUESTS) {
                return false;
            }
            
            $data['requests'][] = $now;
        } else {
            $data = ['requests' => [time()]];
        }
        
        file_put_contents($rateLimitFile, json_encode($data));
        return true;
    }
}