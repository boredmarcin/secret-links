<?php

namespace SecretLinks;

/**
 * File-based persistence layer for encrypted messages.
 * Stores messages as JSON files with LOCK_EX for atomicity and 0600 permissions.
 * Manages active/expired message lifecycle and rate limit file cleanup.
 */
class FileStorage
{
    private string $storagePath;
    
    public function __construct()
    {
        $this->storagePath = ACTIVE_MESSAGES_PATH;
    }
    
    public function save(string $messageId, array $data): bool
    {
        $filePath = $this->getFilePath($messageId);
        $jsonData = json_encode($data);
        
        if ($jsonData === false) {
            throw new \Exception('Failed to encode message data');
        }
        
        $result = file_put_contents($filePath, $jsonData, LOCK_EX);
        
        if ($result === false) {
            throw new \Exception('Failed to save message to file');
        }
        
        chmod($filePath, 0600);
        
        return true;
    }
    
    public function load(string $messageId): ?array
    {
        $filePath = $this->getFilePath($messageId);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $jsonData = file_get_contents($filePath);
        
        if ($jsonData === false) {
            return null;
        }
        
        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $data;
    }
    
    public function delete(string $messageId): bool
    {
        $filePath = $this->getFilePath($messageId);
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        return unlink($filePath);
    }
    
    public function moveToExpired(string $messageId): bool
    {
        $currentPath = $this->getFilePath($messageId);
        $expiredPath = EXPIRED_MESSAGES_PATH . $messageId . '.json';
        
        if (!file_exists($currentPath)) {
            return false;
        }
        
        return rename($currentPath, $expiredPath);
    }
    
    public function exists(string $messageId): bool
    {
        return file_exists($this->getFilePath($messageId));
    }
    
    public function cleanupOldMessages(int $maxAge = MESSAGE_DEFAULT_EXPIRY): int
    {
        $count = 0;
        $now = time();
        
        $files = glob($this->storagePath . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && isset($data['created'])) {
                $age = $now - $data['created'];
                
                if ($age > $maxAge) {
                    $messageId = basename($file, '.json');
                    if ($this->moveToExpired($messageId)) {
                        $count++;
                    }
                }
            }
        }
        
        $expiredFiles = glob(EXPIRED_MESSAGES_PATH . '*.json');
        $maxExpiredAge = 7 * 24 * 60 * 60;

        foreach ($expiredFiles as $file) {
            if (filemtime($file) < ($now - $maxExpiredAge)) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        $rateLimitFiles = glob(STORAGE_PATH . 'rate_limit_*.json');
        foreach ($rateLimitFiles as $file) {
            if (filemtime($file) < ($now - RATE_LIMIT_WINDOW * 2)) {
                unlink($file);
            }
        }

        return $count;
    }
    
    public function getStats(): array
    {
        $activeCount = count(glob($this->storagePath . '*.json'));
        $expiredCount = count(glob(EXPIRED_MESSAGES_PATH . '*.json'));
        
        $totalSize = 0;
        
        foreach (glob($this->storagePath . '*.json') as $file) {
            $totalSize += filesize($file);
        }
        
        foreach (glob(EXPIRED_MESSAGES_PATH . '*.json') as $file) {
            $totalSize += filesize($file);
        }
        
        return [
            'active_messages' => $activeCount,
            'expired_messages' => $expiredCount,
            'total_messages' => $activeCount + $expiredCount,
            'storage_size' => $this->formatBytes($totalSize)
        ];
    }
    
    private function getFilePath(string $messageId): string
    {
        $messageId = preg_replace('/[^a-zA-Z0-9_-]/', '', $messageId);
        return $this->storagePath . $messageId . '.json';
    }
    
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}