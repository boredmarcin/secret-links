<?php

namespace SecretLinks;

/**
 * Handles AES-256-CBC encryption and decryption of messages.
 * Keys are derived using PBKDF2 with 100,000 iterations.
 */
class Encryption
{
    private string $method;

    public function __construct()
    {
        $this->method = ENCRYPTION_METHOD;
    }
    
    public function encrypt(string $plainText, string $key): string
    {
        $key = $this->deriveKey($key);
        $ivLength = openssl_cipher_iv_length($this->method);
        $iv = random_bytes($ivLength);
        
        $encrypted = openssl_encrypt(
            $plainText,
            $this->method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }
        
        $result = [
            'iv' => bin2hex($iv),
            'data' => bin2hex($encrypted),
            'key_hint' => substr(bin2hex($key), 0, 8)
        ];
        
        return base64_encode(json_encode($result));
    }
    
    public function decrypt(string $encryptedText, string $key): string
    {
        $key = $this->deriveKey($key);
        $data = base64_decode($encryptedText);
        
        if ($data === false) {
            throw new \Exception('Invalid encrypted data');
        }
        
        $ivLength = openssl_cipher_iv_length($this->method);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }
        
        return $decrypted;
    }
    
    public function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function generateMessageId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function deriveKey(string $userKey): string
    {
        $salt = hex2bin(substr(hash('sha256', $userKey), 0, 32));
        return hash_pbkdf2('sha256', $userKey, $salt, 100000, 32, true);
    }
    
    public function createClientPayload(string $plainText, string $key): array
    {
        $encrypted = $this->encrypt($plainText, $key);
        $messageId = $this->generateMessageId();
        
        return [
            'id' => $messageId,
            'data' => $encrypted,
            'created' => time(),
            'algorithm' => $this->method
        ];
    }
}