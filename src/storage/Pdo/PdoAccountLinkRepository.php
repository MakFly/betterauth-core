<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\AccountLink;
use BetterAuth\Core\Interfaces\AccountLinkRepositoryInterface;
use PDO;

/**
 * PDO implementation of AccountLinkRepositoryInterface.
 */
class PdoAccountLinkRepository implements AccountLinkRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'account_links',
        private readonly bool $useAutoIncrement = false,
    ) {
    }

    public function generateId(): ?string
    {
        if ($this->useAutoIncrement) {
            return null;
        }

        return $this->generateUuidV7();
    }

    public function create(array $data): AccountLink
    {
        $data['created_at'] ??= date('Y-m-d H:i:s');
        $data['updated_at'] ??= date('Y-m-d H:i:s');

        if (isset($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} ($columns)
            VALUES ($placeholders)
        ");

        $stmt->execute($data);

        $id = $data['id'] ?? (string) $this->pdo->lastInsertId();

        $link = $this->findById($id);
        if ($link === null) {
            throw new \RuntimeException('Failed to create account link');
        }

        return $link;
    }

    public function findById(string $id): ?AccountLink
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? AccountLink::fromArray($data) : null;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);

        $links = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $links[] = AccountLink::fromArray($data);
        }

        return $links;
    }

    public function findByProvider(string $provider): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE provider = :provider ORDER BY created_at DESC");
        $stmt->execute(['provider' => $provider]);

        $links = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $links[] = AccountLink::fromArray($data);
        }

        return $links;
    }

    public function findByUserAndProvider(string $userId, string $provider): ?AccountLink
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id AND provider = :provider");
        $stmt->execute(['user_id' => $userId, 'provider' => $provider]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? AccountLink::fromArray($data) : null;
    }

    public function getPrimaryLink(string $userId): ?AccountLink
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id AND is_primary = 1");
        $stmt->execute(['user_id' => $userId]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? AccountLink::fromArray($data) : null;
    }

    public function setPrimary(string $id): bool
    {
        $link = $this->findById($id);

        if (!$link) {
            return false;
        }

        // Remove primary from all other links for this user
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET is_primary = 0, updated_at = :updated_at
            WHERE user_id = :user_id AND id != :id
        ");

        $stmt->execute([
            'user_id' => $link->getUserId(),
            'id' => $id,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Set this link as primary
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET is_primary = 1, updated_at = :updated_at
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(string $id, array $data): AccountLink
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (isset($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = "$key = :$key";
        }
        $setClause = implode(', ', $setParts);

        $data['id'] = $id;

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET $setClause
            WHERE id = :id
        ");

        $stmt->execute($data);

        $link = $this->findById($id);
        if ($link === null) {
            throw new \RuntimeException('Failed to update account link');
        }

        return $link;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteAllForUser(string $userId): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }

    public function countForUser(string $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->tableName} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function isLinked(string $userId, string $provider): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->tableName} WHERE user_id = :user_id AND provider = :provider");
        $stmt->execute(['user_id' => $userId, 'provider' => $provider]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function generateUuidV7(): string
    {
        if (class_exists(\Ramsey\Uuid\Uuid::class) && method_exists(\Ramsey\Uuid\Uuid::class, 'uuid7')) {
            return (string) \Ramsey\Uuid\Uuid::uuid7();
        }

        $timestamp = (int) (microtime(true) * 1000);

        $data = pack('J', $timestamp << 16);
        $data = substr($data, 0, 6) . random_bytes(10);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x70);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(50) PRIMARY KEY,
                user_id VARCHAR(50) NOT NULL,
                provider VARCHAR(100) NOT NULL,
                provider_user_id VARCHAR(255) NOT NULL,
                provider_email VARCHAR(255),
                provider_metadata JSON,
                is_primary BOOLEAN DEFAULT FALSE,
                linked_at DATETIME,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY unique_user_provider (user_id, provider),
                INDEX idx_user_id (user_id),
                INDEX idx_provider (provider),
                INDEX idx_provider_user_id (provider_user_id)
            )
        ") !== false;
    }
}
