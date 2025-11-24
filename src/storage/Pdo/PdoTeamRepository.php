<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\Team;
use BetterAuth\Core\Interfaces\TeamRepositoryInterface;
use PDO;

/**
 * PDO implementation of TeamRepositoryInterface.
 */
class PdoTeamRepository implements TeamRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'teams',
    ) {
    }

    public function findById(string $id): ?Team
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Team::fromArray($data) : null;
    }

    public function findBySlug(string $slug, string $organizationId): ?Team
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE slug = ? AND organization_id = ?
        ");
        $stmt->execute([$slug, $organizationId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Team::fromArray($data) : null;
    }

    public function findByOrganization(string $organizationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE organization_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$organizationId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($data) => Team::fromArray($data), $results);
    }

    public function create(array $data): Team
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (id, organization_id, name, slug, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['id'],
            $data['organizationId'] ?? $data['organization_id'],
            $data['name'],
            $data['slug'] ?? null,
            isset($data['metadata']) ? json_encode($data['metadata']) : null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return $this->findById($data['id']) ?? Team::fromArray($data);
    }

    public function update(string $id, array $data): Team
    {
        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $values[] = $data['name'];
        }

        if (isset($data['slug'])) {
            $fields[] = 'slug = ?';
            $values[] = $data['slug'];
        }

        if (isset($data['metadata'])) {
            $fields[] = 'metadata = ?';
            $values[] = json_encode($data['metadata']);
        }

        if (empty($fields)) {
            return $this->findById($id) ?? Team::fromArray(['id' => $id]);
        }

        $values[] = $id;

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET " . implode(', ', $fields) . '
            WHERE id = ?
        ');

        $stmt->execute($values);

        return $this->findById($id) ?? Team::fromArray(['id' => $id]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName}
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByOrganization(string $organizationId): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName}
            WHERE organization_id = ?
        ");

        $stmt->execute([$organizationId]);

        return $stmt->rowCount();
    }

    /**
     * Create the teams table.
     */
    public function createTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(255) PRIMARY KEY,
                organization_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255),
                metadata TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_organization (organization_id),
                INDEX idx_slug (slug),
                UNIQUE KEY unique_org_slug (organization_id, slug),
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
            )
        ");
    }
}
