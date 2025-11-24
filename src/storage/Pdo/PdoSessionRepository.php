<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Interfaces\SessionRepositoryInterface;
use PDO;

/**
 * PDO implementation of SessionRepositoryInterface.
 */
class PdoSessionRepository implements SessionRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'sessions',
    ) {
    }

    public function findByToken(string $token): ?Session
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE token = :token");
        $stmt->execute(['token' => $token]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Session::fromArray($data) : null;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE user_id = :user_id AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        $sessions = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sessions[] = Session::fromArray($data);
        }

        return $sessions;
    }

    public function create(array $data): Session
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

        $session = $this->findByToken($data['token']);
        if ($session === null) {
            throw new \RuntimeException('Failed to create session');
        }

        return $session;
    }

    public function update(string $token, array $data): Session
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

        $data['token'] = $token;

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET $setClause
            WHERE token = :token
        ");

        $stmt->execute($data);

        $session = $this->findByToken($token);
        if ($session === null) {
            throw new \RuntimeException('Failed to update session');
        }

        return $session;
    }

    public function delete(string $token): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE token = :token");
        $stmt->execute(['token' => $token]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByUserId(string $userId): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at < NOW()");
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Create the sessions table.
     */
    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                token VARCHAR(255) PRIMARY KEY,
                user_id VARCHAR(50) NOT NULL,
                expires_at DATETIME NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(500) NOT NULL,
                metadata JSON,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ") !== false;
    }
}
