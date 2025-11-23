<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\Member;
use BetterAuth\Core\Interfaces\MemberRepositoryInterface;
use PDO;

/**
 * PDO implementation of MemberRepositoryInterface.
 */
class PdoMemberRepository implements MemberRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'members'
    ) {
    }

    public function findById(string $id): ?Member
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Member::fromArray($data) : null;
    }

    public function findByUserAndOrganization(string $userId, string $organizationId): ?Member
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE user_id = ? AND organization_id = ?
        ");
        $stmt->execute([$userId, $organizationId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Member::fromArray($data) : null;
    }

    public function findByOrganization(string $organizationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE organization_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$organizationId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($data) => Member::fromArray($data), $results);
    }

    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($data) => Member::fromArray($data), $results);
    }

    public function create(array $data): Member
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (id, organization_id, user_id, role, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['id'],
            $data['organizationId'] ?? $data['organization_id'],
            $data['userId'] ?? $data['user_id'],
            $data['role'] ?? 'member',
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return $this->findById($data['id']) ?? Member::fromArray($data);
    }

    public function updateRole(string $id, string $role): Member
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET role = ?
            WHERE id = ?
        ");

        $stmt->execute([$role, $id]);

        return $this->findById($id) ?? Member::fromArray(['id' => $id, 'role' => $role]);
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
     * Create the members table.
     */
    public function createTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(255) PRIMARY KEY,
                organization_id VARCHAR(255) NOT NULL,
                user_id VARCHAR(255) NOT NULL,
                role VARCHAR(255) NOT NULL DEFAULT 'member',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_org (user_id, organization_id),
                INDEX idx_organization (organization_id),
                INDEX idx_user (user_id),
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }
}
