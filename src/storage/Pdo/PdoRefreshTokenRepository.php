<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\RefreshToken;
use BetterAuth\Core\Entities\SimpleRefreshToken;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use PDO;

/**
 * PDO implementation for refresh tokens (API mode).
 */
class PdoRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'refresh_tokens',
    ) {
    }

    public function findByToken(string $token): ?RefreshToken
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE token = :token");
        $stmt->execute(['token' => $token]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? SimpleRefreshToken::fromArray($data) : null;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE user_id = :user_id AND revoked = 0 AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        $tokens = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tokens[] = SimpleRefreshToken::fromArray($data);
        }

        return $tokens;
    }

    public function create(array $data): RefreshToken
    {
        $createdAt = $data['createdAt'] ?? date('Y-m-d H:i:s');
        $revoked = $data['revoked'] ?? 0;

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (token, user_id, expires_at, created_at, revoked, replaced_by)
            VALUES (:token, :user_id, :expires_at, :created_at, :revoked, :replaced_by)
        ");

        $stmt->execute([
            'token' => $data['token'],
            'user_id' => $data['userId'],
            'expires_at' => $data['expiresAt'],
            'created_at' => $createdAt,
            'revoked' => $revoked,
            'replaced_by' => $data['replacedBy'] ?? null,
        ]);

        $token = $this->findByToken($data['token']);
        if ($token === null) {
            throw new \RuntimeException('Failed to create refresh token');
        }

        return $token;
    }

    public function revoke(string $token, ?string $replacedBy = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET revoked = 1, replaced_by = :replaced_by
            WHERE token = :token
        ");

        return $stmt->execute([
            'token' => $token,
            'replaced_by' => $replacedBy,
        ]);
    }

    public function consume(string $token, ?string $replacedBy = null): ?RefreshToken
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET revoked = 1, replaced_by = :replaced_by
            WHERE token = :token AND revoked = 0 AND expires_at > NOW()
        ");

        $stmt->execute([
            'token' => $token,
            'replaced_by' => $replacedBy,
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->findByToken($token);
    }

    public function revokeAllForUser(string $userId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET revoked = 1
            WHERE user_id = :user_id AND revoked = 0
        ");

        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at < NOW()");
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Create the refresh tokens table.
     */
    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                token VARCHAR(255) PRIMARY KEY,
                user_id VARCHAR(50) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                revoked BOOLEAN DEFAULT FALSE,
                replaced_by VARCHAR(255) NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ") !== false;
    }
}
