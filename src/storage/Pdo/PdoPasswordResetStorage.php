<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\PasswordResetToken;
use BetterAuth\Core\Interfaces\PasswordResetStorageInterface;
use DateTimeImmutable;
use PDO;

/**
 * PDO implementation of PasswordResetStorageInterface.
 */
class PdoPasswordResetStorage implements PasswordResetStorageInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'password_reset_tokens',
    ) {
    }

    public function store(string $token, string $email, int $expiresIn): PasswordResetToken
    {
        $expiresAt = new DateTimeImmutable("+$expiresIn seconds");
        $createdAt = new DateTimeImmutable();

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (token, email, expires_at, created_at, used)
            VALUES (:token, :email, :expires_at, :created_at, 0)
        ");

        $stmt->execute([
            'token' => $token,
            'email' => $email,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);

        return PasswordResetToken::fromArray([
            'token' => $token,
            'email' => $email,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'used' => false,
        ]);
    }

    public function findByToken(string $token): ?PasswordResetToken
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE token = :token");
        $stmt->execute(['token' => $token]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? PasswordResetToken::fromArray($data) : null;
    }

    public function markAsUsed(string $token): bool
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET used = 1 WHERE token = :token");

        return $stmt->execute(['token' => $token]);
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
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at < NOW()");
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Create the password reset tokens table.
     */
    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                token VARCHAR(255) PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                INDEX idx_email (email),
                INDEX idx_expires_at (expires_at)
            )
        ") !== false;
    }
}
