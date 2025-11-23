<?php

declare(strict_types=1);

namespace BetterAuth\Core\Interfaces;

/**
 * Interface for passkey/WebAuthn credential storage.
 */
interface PasskeyStorageInterface
{
    /**
     * Store a passkey credential.
     *
     * @param string $userId The user ID
     * @param string $credentialId The credential ID
     * @param string $publicKey The public key
     * @param array<string, mixed> $metadata Additional metadata (counter, transports, etc.)
     * @return bool True if stored successfully, false otherwise
     */
    public function store(string $userId, string $credentialId, string $publicKey, array $metadata): bool;

    /**
     * Find a credential by its ID.
     *
     * @param string $credentialId The credential ID
     * @return array<string, mixed>|null The credential data or null if not found
     */
    public function findByCredentialId(string $credentialId): ?array;

    /**
     * Find all credentials for a user.
     *
     * @param string $userId The user ID
     * @return array<array<string, mixed>> Array of credential data
     */
    public function findByUserId(string $userId): array;

    /**
     * Update a credential (e.g., increment counter).
     *
     * @param string $credentialId The credential ID
     * @param array<string, mixed> $data Data to update
     * @return bool True if updated successfully, false otherwise
     */
    public function update(string $credentialId, array $data): bool;

    /**
     * Delete a credential.
     *
     * @param string $credentialId The credential ID
     * @return bool True if deleted, false otherwise
     */
    public function delete(string $credentialId): bool;
}
