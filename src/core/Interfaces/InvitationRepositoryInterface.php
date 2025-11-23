<?php

declare(strict_types=1);

namespace BetterAuth\Core\Interfaces;

use BetterAuth\Core\Entities\Invitation;

/**
 * Interface for invitation repository implementations.
 * Defines methods for managing organization invitations.
 */
interface InvitationRepositoryInterface
{
    /**
     * Find an invitation by ID.
     *
     * @param string $id
     * @return Invitation|null
     */
    public function findById(string $id): ?Invitation;

    /**
     * Find an invitation by email and organization.
     *
     * @param string $email
     * @param string $organizationId
     * @return Invitation|null
     */
    public function findByEmailAndOrganization(string $email, string $organizationId): ?Invitation;

    /**
     * Get all invitations for an organization.
     *
     * @param string $organizationId
     * @return array<Invitation>
     */
    public function findByOrganization(string $organizationId): array;

    /**
     * Get all pending invitations for an email.
     *
     * @param string $email
     * @return array<Invitation>
     */
    public function findPendingByEmail(string $email): array;

    /**
     * Create a new invitation.
     *
     * @param array<string, mixed> $data
     * @return Invitation
     */
    public function create(array $data): Invitation;

    /**
     * Update invitation status.
     *
     * @param string $id
     * @param string $status
     * @return Invitation
     */
    public function updateStatus(string $id, string $status): Invitation;

    /**
     * Delete an invitation.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Delete all invitations for an organization.
     *
     * @param string $organizationId
     * @return int Number of deleted invitations
     */
    public function deleteByOrganization(string $organizationId): int;

    /**
     * Delete expired invitations.
     *
     * @return int Number of deleted invitations
     */
    public function deleteExpired(): int;
}
