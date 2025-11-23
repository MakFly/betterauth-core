<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\GuestSession;
use BetterAuth\Core\Interfaces\GuestSessionRepositoryInterface;
use PDO;

/**
 * PDO implementation of GuestSessionRepositoryInterface.
 */
class PdoGuestSessionRepository implements GuestSessionRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'guest_sessions',
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

    public function create(array $data): GuestSession
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

        $session = $this->findById($id);
        if ($session === null) {
            throw new \RuntimeException('Failed to create guest session');
        }

        return $session;
    }

    public function findById(string $id): ?GuestSession
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? GuestSession::fromArray($data) : null;
    }

    public function findByToken(string $token): ?GuestSession
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE token = :token");
        $stmt->execute(['token' => $token]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? GuestSession::fromArray($data) : null;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at < :now");
        $stmt->execute(['now' => date('Y-m-d H:i:s')]);

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
                token VARCHAR(255) NOT NULL UNIQUE,
                data JSON,
                expires_at DATETIME NOT NULL,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_token (token),
                INDEX idx_expires_at (expires_at)
            )
        ") !== false;
    }
}
