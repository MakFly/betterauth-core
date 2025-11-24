<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\Invitation;
use BetterAuth\Core\Interfaces\InvitationRepositoryInterface;
use PDO;

/**
 * PDO implementation of InvitationRepositoryInterface.
 */
class PdoInvitationRepository implements InvitationRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'invitations',
    ) {
    }

    public function findById(string $id): ?Invitation
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Invitation::fromArray($data) : null;
    }

    public function findByEmailAndOrganization(string $email, string $organizationId): ?Invitation
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE email = ? AND organization_id = ? AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email, $organizationId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Invitation::fromArray($data) : null;
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

        return array_map(fn ($data) => Invitation::fromArray($data), $results);
    }

    public function findPendingByEmail(string $email): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE email = ? AND status = 'pending' AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC
        ");
        $stmt->execute([$email]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($data) => Invitation::fromArray($data), $results);
    }

    public function create(array $data): Invitation
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (id, organization_id, email, role, status, invited_by, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['id'],
            $data['organizationId'] ?? $data['organization_id'],
            $data['email'],
            $data['role'] ?? 'member',
            $data['status'] ?? 'pending',
            $data['invitedBy'] ?? $data['invited_by'] ?? null,
            $data['expiresAt'] ?? $data['expires_at'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return $this->findById($data['id']) ?? Invitation::fromArray($data);
    }

    public function updateStatus(string $id, string $status): Invitation
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([$status, $id]);

        return $this->findById($id) ?? Invitation::fromArray(['id' => $id, 'status' => $status]);
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

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName}
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");

        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Create the invitations table.
     */
    public function createTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(255) PRIMARY KEY,
                organization_id VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                role VARCHAR(255) NOT NULL DEFAULT 'member',
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                invited_by VARCHAR(255),
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_organization (organization_id),
                INDEX idx_email (email),
                INDEX idx_status (status),
                INDEX idx_expires (expires_at),
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
    }
}
