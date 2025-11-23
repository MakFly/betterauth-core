<?php

declare(strict_types=1);

namespace BetterAuth\Core\Interfaces;

use BetterAuth\Core\Entities\Organization;

/**
 * Interface for organization repository implementations.
 * Defines methods for managing organizations in the system.
 */
interface OrganizationRepositoryInterface
{
    /**
     * Find an organization by ID.
     *
     * @param string $id
     * @return Organization|null
     */
    public function findById(string $id): ?Organization;

    /**
     * Find an organization by slug.
     *
     * @param string $slug
     * @return Organization|null
     */
    public function findBySlug(string $slug): ?Organization;

    /**
     * Get all organizations for a specific user.
     *
     * @param string $userId
     * @return array<Organization>
     */
    public function findByUserId(string $userId): array;

    /**
     * Create a new organization.
     *
     * @param array<string, mixed> $data
     * @return Organization
     */
    public function create(array $data): Organization;

    /**
     * Update an existing organization.
     *
     * @param string $id
     * @param array<string, mixed> $data
     * @return Organization
     */
    public function update(string $id, array $data): Organization;

    /**
     * Delete an organization.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Check if a slug is available.
     *
     * @param string $slug
     * @param string|null $excludeId Organization ID to exclude from check
     * @return bool
     */
    public function isSlugAvailable(string $slug, ?string $excludeId = null): bool;
}
