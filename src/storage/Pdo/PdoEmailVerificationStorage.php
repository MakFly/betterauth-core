<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\EmailVerificationToken;
use BetterAuth\Core\Interfaces\EmailVerificationStorageInterface;
use PDO;

/**
 * PDO implementation of EmailVerificationStorageInterface.
 */
class PdoEmailVerificationStorage implements EmailVerificationStorageInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'email_verification_tokens',
    ) {
    }

    public function store(string $token, string $email, int $expiresIn): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (token, email, expires_at, created_at, updated_at)
            VALUES (:token, :email, :expires_at, :created_at, :updated_at)
        ");

        $stmt->execute([
            'token' => $token,
            'email' => $email,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByToken(string $token): ?EmailVerificationToken
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE token = :token AND expires_at > :now");
        $stmt->execute([
            'token' => $token,
            'now' => date('Y-m-d H:i:s'),
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? EmailVerificationToken::fromArray($data) : null;
    }

    public function markAsUsed(string $token): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET used_at = :used_at, updated_at = :updated_at
            WHERE token = :token
        ");

        return $stmt->execute([
            'token' => $token,
            'used_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $token): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE token = :token");
        $stmt->execute(['token' => $token]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByEmail(string $email): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE email = :email");
        $stmt->execute(['email' => $email]);

        return $stmt->rowCount();
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at < :now");
        $stmt->execute(['now' => date('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token VARCHAR(255) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL,
                used_at DATETIME,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_token (token),
                INDEX idx_email (email),
                INDEX idx_expires_at (expires_at)
            )
        ") !== false;
    }
}
