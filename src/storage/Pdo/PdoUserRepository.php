<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\SimpleUser;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use PDO;

/**
 * PDO implementation of UserRepositoryInterface.
 */
class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'users',
        private readonly bool $useAutoIncrement = false,
    ) {
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? SimpleUser::fromArray($data) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE email = :email");
        $stmt->execute(['email' => $email]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? SimpleUser::fromArray($data) : null;
    }

    public function findByProvider(string $provider, string $providerId): ?User
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE JSON_EXTRACT(metadata, '$.oauth_providers.{$provider}.provider_id') = :provider_id
        ");
        $stmt->execute(['provider_id' => $providerId]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? SimpleUser::fromArray($data) : null;
    }

    public function create(array $data): User
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

        // Get the ID (either provided or auto-generated)
        $id = $data['id'] ?? (string) $this->pdo->lastInsertId();

        $user = $this->findById($id);
        if ($user === null) {
            throw new \RuntimeException('Failed to create user');
        }

        return $user;
    }

    public function update(string $id, array $data): User
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

        $user = $this->findById($id);
        if ($user === null) {
            throw new \RuntimeException('Failed to update user');
        }

        return $user;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function verifyEmail(string $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET email_verified = 1,
                email_verified_at = :verified_at,
                updated_at = :updated_at
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function generateId(): ?string
    {
        // If using auto-increment, let database handle it
        if ($this->useAutoIncrement) {
            return null;
        }

        // Generate UUID v7 for string-based IDs (time-ordered, better for database performance)
        return $this->generateUuidV7();
    }

    /**
     * Generate a UUID v7 (time-ordered).
     *
     * Uses ramsey/uuid if available, otherwise falls back to manual generation.
     */
    private function generateUuidV7(): string
    {
        // Use ramsey/uuid v7 if available (ramsey/uuid 4.6+)
        if (class_exists(\Ramsey\Uuid\Uuid::class) && method_exists(\Ramsey\Uuid\Uuid::class, 'uuid7')) {
            return (string) \Ramsey\Uuid\Uuid::uuid7();
        }

        // Fallback: Manual UUID v7 generation (time-ordered)
        // UUID v7: timestamp (48 bits) + random (74 bits)
        $timestamp = (int) (microtime(true) * 1000); // milliseconds since epoch

        $data = pack('J', $timestamp << 16); // 48-bit timestamp in first 6 bytes
        $data = substr($data, 0, 6) . random_bytes(10); // Add 10 random bytes

        // Set version (7) and variant bits
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x70); // Version 7
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Variant 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Create the users table.
     */
    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(50) PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255),
                name VARCHAR(255),
                avatar VARCHAR(500),
                email_verified BOOLEAN DEFAULT FALSE,
                email_verified_at DATETIME,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_email (email)
            )
        ") !== false;
    }
}
