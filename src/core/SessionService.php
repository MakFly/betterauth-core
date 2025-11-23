<?php

declare(strict_types=1);

namespace BetterAuth\Core;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Exceptions\SessionExpiredException;
use BetterAuth\Core\Interfaces\SessionRepositoryInterface;
use BetterAuth\Core\Utils\Crypto;
use DateTimeImmutable;

/**
 * Service for managing user sessions.
 */
class SessionService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly int $sessionLifetime = 86400 * 7, // 7 days default
    ) {
    }

    /**
     * Create a new session for a user.
     *
     * @param User $user The user to create a session for
     * @param string $ipAddress The user's IP address
     * @param string $userAgent The user's user agent
     * @param array<string, mixed> $metadata Additional session metadata
     * @return Session The created session
     * @throws \Exception
     */
    public function create(
        User $user,
        string $ipAddress,
        string $userAgent,
        array $metadata = []
    ): Session {
        $token = Crypto::randomToken(32);
        $expiresAt = new DateTimeImmutable("+{$this->sessionLifetime} seconds");

        return $this->sessionRepository->create([
            'token' => $token,
            'user_id' => $user->id,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Validate and retrieve a session by token.
     *
     * @param string $token The session token
     * @return Session The valid session
     * @throws SessionExpiredException
     */
    public function validate(string $token): Session
    {
        $session = $this->sessionRepository->findByToken($token);

        if ($session === null) {
            throw new SessionExpiredException('Session not found');
        }

        if ($session->isExpired()) {
            $this->sessionRepository->delete($token);

            throw new SessionExpiredException();
        }

        return $session;
    }

    /**
     * Refresh a session's expiration time.
     *
     * @param string $token The session token
     * @return Session The refreshed session
     * @throws SessionExpiredException
     */
    public function refresh(string $token): Session
    {
        $session = $this->validate($token);

        $expiresAt = new DateTimeImmutable("+{$this->sessionLifetime} seconds");

        return $this->sessionRepository->update($token, [
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete a session.
     *
     * @param string $token The session token
     * @return bool True if deleted, false otherwise
     */
    public function delete(string $token): bool
    {
        return $this->sessionRepository->delete($token);
    }

    /**
     * Delete all sessions for a user.
     *
     * @param string $userId The user ID
     * @return int Number of sessions deleted
     */
    public function deleteAllForUser(string $userId): int
    {
        return $this->sessionRepository->deleteByUserId($userId);
    }

    /**
     * Get all active sessions for a user.
     *
     * @param string $userId The user ID
     * @return Session[] Array of active sessions
     */
    public function getAllForUser(string $userId): array
    {
        return $this->sessionRepository->findByUserId($userId);
    }

    /**
     * Clean up expired sessions.
     *
     * @return int Number of sessions deleted
     */
    public function cleanupExpired(): int
    {
        return $this->sessionRepository->deleteExpired();
    }
}
