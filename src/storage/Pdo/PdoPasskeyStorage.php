<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Interfaces\PasskeyStorageInterface;
use PDO;

/**
 * PDO implementation of PasskeyStorageInterface.
 */
class PdoPasskeyStorage implements PasskeyStorageInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'passkeys',
    ) {
    }

    public function store(array $passkey): void
    {
        $data = [
            'credential_id' => $passkey['credential_id'],
            'user_id' => $passkey['user_id'],
            'public_key' => $passkey['public_key'],
            'sign_count' => $passkey['sign_count'] ?? 0,
            'transports' => isset($passkey['transports']) ? json_encode($passkey['transports']) : null,
            'metadata' => isset($passkey['metadata']) ? json_encode($passkey['metadata']) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} ($columns)
            VALUES ($placeholders)
        ");

        $stmt->execute($data);
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE credential_id = :credential_id");
        $stmt->execute(['credential_id' => $credentialId]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return $this->hydratePasskey($data);
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        $passkeys = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $passkeys[] = $this->hydratePasskey($data);
        }

        return $passkeys;
    }

    public function update(string $credentialId, array $data): bool
    {
        $updates = [];
        foreach (['sign_count', 'metadata'] as $field) {
            if (isset($data[$field])) {
                if ($field === 'metadata') {
                    $updates[$field] = json_encode($data[$field]);
                } else {
                    $updates[$field] = $data[$field];
                }
            }
        }

        if (empty($updates)) {
            return true;
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');

        $setParts = [];
        foreach (array_keys($updates) as $key) {
            $setParts[] = "$key = :$key";
        }
        $setClause = implode(', ', $setParts);

        $updates['credential_id'] = $credentialId;

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET $setClause
            WHERE credential_id = :credential_id
        ");

        return $stmt->execute($updates);
    }

    public function delete(string $credentialId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE credential_id = :credential_id");
        $stmt->execute(['credential_id' => $credentialId]);

        return $stmt->rowCount() > 0;
    }

    private function hydratePasskey(array $data): array
    {
        return [
            'id' => $data['id'],
            'credential_id' => $data['credential_id'],
            'user_id' => $data['user_id'],
            'public_key' => $data['public_key'],
            'sign_count' => (int) $data['sign_count'],
            'transports' => $data['transports'] ? json_decode($data['transports'], true) : [],
            'metadata' => $data['metadata'] ? json_decode($data['metadata'], true) : [],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
        ];
    }

    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                credential_id VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(50) NOT NULL,
                public_key TEXT NOT NULL,
                sign_count INTEGER DEFAULT 0,
                transports JSON,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_credential_id (credential_id),
                INDEX idx_user_id (user_id)
            )
        ") !== false;
    }
}
