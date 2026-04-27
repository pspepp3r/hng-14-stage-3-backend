<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;
use Exception;

final class AuthService
{
    private PDO $db;
    private string $jwtSecret;
    private int $accessExpiry;
    private int $refreshExpiry;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->jwtSecret = getenv('JWT_SECRET') ?: 'default_secret';
        $this->accessExpiry = (int)(getenv('JWT_ACCESS_EXPIRY') ?: 180);
        $this->refreshExpiry = (int)(getenv('JWT_REFRESH_EXPIRY') ?: 300);
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    public function generateTokens(array $user): array
    {
        $now = time();
        
        $accessPayload = [
            'iat' => $now,
            'exp' => $now + $this->accessExpiry,
            'sub' => $user['id'],
            'role' => $user['role']
        ];

        $refreshPayload = [
            'iat' => $now,
            'exp' => $now + $this->refreshExpiry,
            'sub' => $user['id'],
            'type' => 'refresh'
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $this->jwtSecret, 'HS256'),
            'refresh_token' => JWT::encode($refreshPayload, $this->jwtSecret, 'HS256')
        ];
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (array)$decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function findOrCreateUser(array $githubUser): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE github_id = ?");
        $stmt->execute([$githubUser['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $id = $this->generateUuidV7();
            $stmt = $this->db->prepare(
                "INSERT INTO users (id, github_id, username, email, avatar_url, role) VALUES (UNHEX(REPLACE(?, '-', '')), ?, ?, ?, ?, ?)"
            );
            $role = 'analyst'; // Default role
            $stmt->execute([
                $id,
                $githubUser['id'],
                $githubUser['login'],
                $githubUser['email'] ?? null,
                $githubUser['avatar_url'],
                $role
            ]);
            
            return $this->findUserById($id);
        }

        // Update last login
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);

        $user['id'] = $this->binaryToUuid($user['id']);
        return $user;
    }

    public function findUserById(string $hexId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = UNHEX(REPLACE(?, '-', ''))");
        $stmt->execute([$hexId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user['id'] = $this->binaryToUuid($user['id']);
            return $user;
        }
        return null;
    }

    private function binaryToUuid(string $binary): string
    {
        $hex = \bin2hex($binary);
        return \substr($hex, 0, 8) . '-' .
            \substr($hex, 8, 4) . '-' .
            \substr($hex, 12, 4) . '-' .
            \substr($hex, 16, 4) . '-' .
            \substr($hex, 20, 12);
    }

    private function generateUuidV7(): string
    {
        // Simple UUID v7 generation logic (timestamp + randomness)
        $timestamp = (int)(microtime(true) * 1000);
        $hex = sprintf('%012x', $timestamp);
        $hex .= bin2hex(random_bytes(10));
        
        // Format: 8-4-4-4-12
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            '7' . substr($hex, 13, 3), // Version 7
            sprintf('%x', (hexdec(substr($hex, 16, 1)) & 0x3 | 0x8) ) . substr($hex, 17, 3), // Variant 1
            substr($hex, 20, 12)
        );
    }
}
