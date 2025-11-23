<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\SecurityEvent;
use BetterAuth\Core\Interfaces\SecurityEventRepositoryInterface;
use PDO;

/**
 * PDO implementation of SecurityEventRepositoryInterface.
 */
class PdoSecurityEventRepository implements SecurityEventRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'security_events',
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

    public function create(array $data): SecurityEvent
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

        $event = $this->findById($id);
        if ($event === null) {
            throw new \RuntimeException('Failed to create security event');
        }

        return $event;
    }

    public function findById(string $id): ?SecurityEvent
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? SecurityEvent::fromArray($data) : null;
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue('user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $events = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = SecurityEvent::fromArray($data);
        }

        return $events;
    }

    public function findBySeverity(string $severity, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE severity = :severity ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue('severity', $severity, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $events = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = SecurityEvent::fromArray($data);
        }

        return $events;
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
                event_type VARCHAR(100) NOT NULL,
                severity VARCHAR(50) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_event_type (event_type),
                INDEX idx_severity (severity),
                INDEX idx_created_at (created_at)
            )
        ") !== false;
    }
}
