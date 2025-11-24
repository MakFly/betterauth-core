<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\TeamMember;
use BetterAuth\Core\Interfaces\TeamMemberRepositoryInterface;
use PDO;

/**
 * PDO implementation of TeamMemberRepositoryInterface.
 */
class PdoTeamMemberRepository implements TeamMemberRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'team_members',
    ) {
    }

    public function findById(string $id): ?TeamMember
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? TeamMember::fromArray($data) : null;
    }

    public function findByMemberAndTeam(string $memberId, string $teamId): ?TeamMember
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE member_id = ? AND team_id = ?
        ");
        $stmt->execute([$memberId, $teamId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? TeamMember::fromArray($data) : null;
    }

    public function findByTeam(string $teamId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE team_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$teamId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($data) => TeamMember::fromArray($data), $results);
    }

    public function findByMember(string $memberId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName}
            WHERE member_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$memberId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($data) => TeamMember::fromArray($data), $results);
    }

    public function create(array $data): TeamMember
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName} (id, team_id, member_id, role, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['id'],
            $data['teamId'] ?? $data['team_id'],
            $data['memberId'] ?? $data['member_id'],
            $data['role'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return $this->findById($data['id']) ?? TeamMember::fromArray($data);
    }

    public function updateRole(string $id, ?string $role): TeamMember
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET role = ?
            WHERE id = ?
        ");

        $stmt->execute([$role, $id]);

        return $this->findById($id) ?? TeamMember::fromArray(['id' => $id, 'role' => $role]);
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

    public function deleteByTeam(string $teamId): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName}
            WHERE team_id = ?
        ");

        $stmt->execute([$teamId]);

        return $stmt->rowCount();
    }

    public function deleteByMember(string $memberId): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName}
            WHERE member_id = ?
        ");

        $stmt->execute([$memberId]);

        return $stmt->rowCount();
    }

    /**
     * Create the team_members table.
     */
    public function createTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(255) PRIMARY KEY,
                team_id VARCHAR(255) NOT NULL,
                member_id VARCHAR(255) NOT NULL,
                role VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_member_team (member_id, team_id),
                INDEX idx_team (team_id),
                INDEX idx_member (member_id),
                FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
            )
        ");
    }
}
