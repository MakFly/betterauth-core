<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\Organization;
use BetterAuth\Core\Interfaces\OrganizationRepositoryInterface;
use PDO;

/**
 * PDO implementation of OrganizationRepositoryInterface.
 */
class PdoOrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'organizations',
    ) {
    }

    public function findById(string $id): ?Organization
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Organization::fromArray($data) : null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE slug = ?
        ");
        $stmt->execute([$slug]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Organization::fromArray($data) : null;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.* FROM {$this->tableName} o
            INNER JOIN members m ON o.id = m.organization_id
            WHERE m.user_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($data) => Organization::fromArray($data), $results);
    }

    public function create(array $data): Organization
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (id, name, slug, logo, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['id'],
            $data['name'],
            $data['slug'],
            $data['logo'] ?? null,
            isset($data['metadata']) ? json_encode($data['metadata']) : null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return $this->findById($data['id']) ?? Organization::fromArray($data);
    }

    public function update(string $id, array $data): Organization
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

        if (isset($data['logo'])) {
            $fields[] = 'logo = ?';
            $values[] = $data['logo'];
        }

        if (isset($data['metadata'])) {
            $fields[] = 'metadata = ?';
            $values[] = json_encode($data['metadata']);
        }

        if (empty($fields)) {
            return $this->findById($id) ?? Organization::fromArray(['id' => $id]);
        }

        $values[] = $id;

        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET " . implode(', ', $fields) . '
            WHERE id = ?
        ');

        $stmt->execute($values);

        return $this->findById($id) ?? Organization::fromArray(['id' => $id]);
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

    public function isSlugAvailable(string $slug, ?string $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM {$this->tableName}
                WHERE slug = ?
            ");
            $stmt->execute([$slug]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM {$this->tableName}
                WHERE slug = ? AND id != ?
            ");
            $stmt->execute([$slug, $excludeId]);
        }

        return $stmt->fetchColumn() === 0;
    }

    /**
     * Create the organizations table.
     */
    public function createTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                logo TEXT,
                metadata TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_slug (slug)
            )
        ");
    }
}
