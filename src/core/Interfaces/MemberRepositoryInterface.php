<?php

declare(strict_types=1);

namespace BetterAuth\Core\Interfaces;

use BetterAuth\Core\Entities\Member;

/**
 * Interface for member repository implementations.
 * Defines methods for managing organization members.
 */
interface MemberRepositoryInterface
{
    /**
     * Find a member by ID.
     *
     * @param string $id
     * @return Member|null
     */
    public function findById(string $id): ?Member;

    /**
     * Find a member by user ID and organization ID.
     *
     * @param string $userId
     * @param string $organizationId
     * @return Member|null
     */
    public function findByUserAndOrganization(string $userId, string $organizationId): ?Member;

    /**
     * Get all members of an organization.
     *
     * @param string $organizationId
     * @return array<Member>
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * Get all organizations a user is a member of.
     *
     * @param string $userId
     * @return array<Member>
     */
    public function findByUser(string $userId): array;

    /**
     * Create a new member.
     *
     * @param array<string, mixed> $data
     * @return Member
     */
    public function create(array $data): Member;

    /**
     * Update a member's role.
     *
     * @param string $id
     * @param string $role
     * @return Member
     */
    public function updateRole(string $id, string $role): Member;

    /**
     * Delete a member.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Delete all members of an organization.
     *
     * @param string $organizationId
     * @return int Number of deleted members
     */
    public function deleteByOrganization(string $organizationId): int;
}
