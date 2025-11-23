<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\SessionActivity;
use BetterAuth\Core\Interfaces\SessionActivityRepositoryInterface;
use PDO;

/**
 * PDO implementation of SessionActivityRepositoryInterface.
 */
class PdoSessionActivityRepository implements SessionActivityRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'session_activities',
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

    public function create(array $data): SessionActivity
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
            throw new \RuntimeException('Failed to create session activity');
        }

        return $activity;
    }

    public function findById(string $id): ?SessionActivity
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? SessionActivity::fromArray($data) : null;
    }

    public function findBySessionId(string $sessionId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE session_id = :session_id ORDER BY created_at DESC");
        $stmt->execute(['session_id' => $sessionId]);

        $activities = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = SessionActivity::fromArray($data);
        }

        return $activities;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);

        $activities = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = SessionActivity::fromArray($data);
        }

        return $activities;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteBySessionId(string $sessionId): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE session_id = :session_id");
        $stmt->execute(['session_id' => $sessionId]);

        return $stmt->rowCount();
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
                session_id VARCHAR(50) NOT NULL,
                user_id VARCHAR(50) NOT NULL,
                action VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_session_id (session_id),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            )
        ") !== false;
    }
}
