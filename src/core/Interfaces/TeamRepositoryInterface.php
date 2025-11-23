<?php

declare(strict_types=1);

namespace BetterAuth\Core\Interfaces;

use BetterAuth\Core\Entities\Team;

/**
 * Interface for team repository implementations.
 * Defines methods for managing teams within organizations.
 */
interface TeamRepositoryInterface
{
    /**
     * Find a team by ID.
     *
     * @param string $id
     * @return Team|null
     */
    public function findById(string $id): ?Team;

    /**
     * Find a team by slug within an organization.
     *
     * @param string $slug
     * @param string $organizationId
     * @return Team|null
     */
    public function findBySlug(string $slug, string $organizationId): ?Team;

    /**
     * Get all teams for an organization.
     *
     * @param string $organizationId
     * @return array<Team>
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * Create a new team.
     *
     * @param array<string, mixed> $data
     * @return Team
     */
    public function create(array $data): Team;

    /**
     * Update a team.
     *
     * @param string $id
     * @param array<string, mixed> $data
     * @return Team
     */
    public function update(string $id, array $data): Team;

    /**
     * Delete a team.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Delete all teams for an organization.
     *
     * @param string $organizationId
     * @return int Number of deleted teams
     */
    public function deleteByOrganization(string $organizationId): int;
}
