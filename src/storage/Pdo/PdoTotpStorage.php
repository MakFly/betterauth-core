<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Interfaces\TotpStorageInterface;
use PDO;

/**
 * PDO implementation of TotpStorageInterface.
 */
class PdoTotpStorage implements TotpStorageInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'totp_secrets',
    ) {
    }

    public function store(string $userId, array $secret): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (user_id, secret, backup_codes, enabled, created_at, updated_at)
            VALUES (:user_id, :secret, :backup_codes, :enabled, :created_at, :updated_at)
            ON CONFLICT(user_id) DO UPDATE SET
                secret = :secret,
                backup_codes = :backup_codes,
                updated_at = :updated_at
        ");

        $stmt->execute([
            'user_id' => $userId,
            'secret' => $secret['secret'] ?? '',
            'backup_codes' => json_encode($secret['backup_codes'] ?? []),
            'enabled' => $secret['enabled'] ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByUserId(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return $this->hydrateTotpSecret($data);
    }

    public function isEnabled(string $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT enabled FROM {$this->tableName} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? (bool) $data['enabled'] : false;
    }

    public function enable(string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET enabled = 1, updated_at = :updated_at
            WHERE user_id = :user_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function disable(string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET enabled = 0, updated_at = :updated_at
            WHERE user_id = :user_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function useBackupCode(string $userId, string $code): bool
    {
        $secret = $this->findByUserId($userId);

        if (!$secret) {
            return false;
        }

        $backupCodes = $secret['backup_codes'] ?? [];

        if (!in_array($code, $backupCodes, true)) {
            return false;
        }

        $backupCodes = array_values(array_filter($backupCodes, fn ($c) => $c !== $code));

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET backup_codes = :backup_codes, updated_at = :updated_at
            WHERE user_id = :user_id
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'backup_codes' => json_encode($backupCodes),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function hydrateTotpSecret(array $data): array
    {
        return [
            'id' => $data['id'],
            'user_id' => $data['user_id'],
            'secret' => $data['secret'],
            'backup_codes' => $data['backup_codes'] ? json_decode($data['backup_codes'], true) : [],
            'enabled' => (bool) $data['enabled'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
        ];
    }

    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id VARCHAR(50) NOT NULL UNIQUE,
                secret VARCHAR(32) NOT NULL,
                backup_codes JSON,
                enabled BOOLEAN DEFAULT FALSE,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id)
            )
        ") !== false;
    }
}
