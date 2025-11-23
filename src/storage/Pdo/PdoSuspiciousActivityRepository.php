<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\SuspiciousActivity;
use BetterAuth\Core\Interfaces\SuspiciousActivityRepositoryInterface;
use PDO;

/**
 * PDO implementation of SuspiciousActivityRepositoryInterface.
 */
class PdoSuspiciousActivityRepository implements SuspiciousActivityRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'suspicious_activities',
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

    public function create(array $data): SuspiciousActivity
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

        $activity = $this->findById($id);
        if ($activity === null) {
            throw new \RuntimeException('Failed to create suspicious activity');
        }

        return $activity;
    }

    public function findById(string $id): ?SuspiciousActivity
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? SuspiciousActivity::fromArray($data) : null;
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue('user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $activities = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = SuspiciousActivity::fromArray($data);
        }

        return $activities;
    }

    public function findByStatus(string $status, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE status = :status ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue('status', $status, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $activities = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = SuspiciousActivity::fromArray($data);
        }

        return $activities;
    }

    public function update(string $id, array $data): SuspiciousActivity
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

        $activity = $this->findById($id);
        if ($activity === null) {
            throw new \RuntimeException('Failed to update suspicious activity');
        }

        return $activity;
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
                activity_type VARCHAR(100) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                ip_address VARCHAR(45),
                user_agent TEXT,
                risk_score INTEGER DEFAULT 0,
                reason TEXT,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_activity_type (activity_type)
            )
        ") !== false;
    }
}
