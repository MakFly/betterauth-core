<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\DeviceInfo;
use BetterAuth\Core\Interfaces\DeviceInfoRepositoryInterface;
use PDO;

/**
 * PDO implementation of DeviceInfoRepositoryInterface.
 */
class PdoDeviceInfoRepository implements DeviceInfoRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'device_infos',
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

    public function create(array $data): DeviceInfo
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

        $device = $this->findById($id);
        if ($device === null) {
            throw new \RuntimeException('Failed to create device info');
        }

        return $device;
    }

    public function findById(string $id): ?DeviceInfo
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? DeviceInfo::fromArray($data) : null;
    }

    public function findByFingerprint(string $userId, string $fingerprint): ?DeviceInfo
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id AND fingerprint = :fingerprint");
        $stmt->execute(['user_id' => $userId, 'fingerprint' => $fingerprint]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? DeviceInfo::fromArray($data) : null;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);

        $devices = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $devices[] = DeviceInfo::fromArray($data);
        }

        return $devices;
    }

    public function update(string $id, array $data): DeviceInfo
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

        $device = $this->findById($id);
        if ($device === null) {
            throw new \RuntimeException('Failed to update device info');
        }

        return $device;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
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
                fingerprint VARCHAR(255) NOT NULL UNIQUE,
                user_agent TEXT,
                ip_address VARCHAR(45),
                device_name VARCHAR(255),
                os_name VARCHAR(100),
                os_version VARCHAR(100),
                browser_name VARCHAR(100),
                browser_version VARCHAR(100),
                is_trusted BOOLEAN DEFAULT FALSE,
                last_seen_at DATETIME,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_fingerprint (fingerprint)
            )
        ") !== false;
    }
}
