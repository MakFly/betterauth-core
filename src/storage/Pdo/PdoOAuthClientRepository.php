<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\OAuthClient;
use BetterAuth\Core\Interfaces\OAuthClientRepositoryInterface;
use PDO;

/**
 * PDO implementation for OAuth clients.
 */
class PdoOAuthClientRepository implements OAuthClientRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'oauth_clients',
    ) {
    }

    public function findById(string $clientId): ?OAuthClient
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $clientId]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? OAuthClient::fromArray($data) : null;
    }

    public function create(array $data): OAuthClient
    {
        $data['created_at'] ??= date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName}
            (id, name, client_secret, redirect_uris, allowed_scopes, type, active, created_at)
            VALUES (:id, :name, :client_secret, :redirect_uris, :allowed_scopes, :type, :active, :created_at)
        ");

        $stmt->execute([
            'id' => $data['id'],
            'name' => $data['name'],
            'client_secret' => $data['client_secret'],
            'redirect_uris' => is_array($data['redirect_uris']) ? json_encode($data['redirect_uris']) : $data['redirect_uris'],
            'allowed_scopes' => is_array($data['allowed_scopes']) ? json_encode($data['allowed_scopes']) : $data['allowed_scopes'],
            'type' => $data['type'] ?? 'confidential',
            'active' => $data['active'] ?? true,
            'created_at' => $data['created_at'],
        ]);

        $client = $this->findById($data['id']);
        if ($client === null) {
            throw new \RuntimeException('Failed to create OAuth client');
        }

        return $client;
    }

    public function update(string $clientId, array $data): OAuthClient
    {
        $setParts = [];
        $params = ['id' => $clientId];

        foreach ($data as $key => $value) {
            if (in_array($key, ['redirect_uris', 'allowed_scopes'], true) && is_array($value)) {
                $value = json_encode($value);
            }
            $setParts[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        $setClause = implode(', ', $setParts);

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET {$setClause}
            WHERE id = :id
        ");

        $stmt->execute($params);

        $client = $this->findById($clientId);
        if ($client === null) {
            throw new \RuntimeException('Failed to update OAuth client');
        }

        return $client;
    }

    public function delete(string $clientId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE id = :id");

        return $stmt->execute(['id' => $clientId]);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->tableName} ORDER BY created_at DESC");
        $clients = [];

        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clients[] = OAuthClient::fromArray($data);
        }

        return $clients;
    }

    /**
     * Create the oauth_clients table.
     */
    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                client_secret VARCHAR(255) NOT NULL,
                redirect_uris TEXT NOT NULL,
                allowed_scopes TEXT NOT NULL,
                type VARCHAR(50) DEFAULT 'confidential',
                active BOOLEAN DEFAULT TRUE,
                created_at DATETIME NOT NULL,
                INDEX idx_active (active)
            )
        ") !== false;
    }
}
