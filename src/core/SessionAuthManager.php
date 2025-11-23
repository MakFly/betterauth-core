<?php

declare(strict_types=1);

namespace BetterAuth\Core;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Exceptions\InvalidCredentialsException;
use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Core\Exceptions\UserNotFoundException;
use BetterAuth\Core\Interfaces\RateLimiterInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;

/**
 * Session-based authentication manager for stateful applications.
 *
 * Uses SessionService to create and manage database-backed sessions.
 * Perfect for traditional web applications with cookies.
 *
 * For stateless API authentication with JWT/Paseto tokens, use TokenAuthManager instead.
 */
class SessionAuthManager
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly SessionService $sessionService,
        private readonly PasswordHasher $passwordHasher,
        private readonly ?RateLimiterInterface $rateLimiter = null,
    ) {
    }

    /**
     * Sign up a new user with email and password.
     *
     * @param string $email The user's email
     * @param string $password The user's password
     * @param array<string, mixed> $additionalData Additional user data
     * @return User The created user
     * @throws \Exception
     */
    public function signUp(string $email, string $password, array $additionalData = []): User
    {
        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser !== null) {
            throw new \InvalidArgumentException('User with this email already exists');
        }

        $passwordHash = $this->passwordHasher->hash($password);

        $userData = [
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $additionalData['name'] ?? null,
            'avatar' => $additionalData['avatar'] ?? null,
            'email_verified' => false,
            'metadata' => $additionalData['metadata'] ?? null,
        ];

        // Only set 'id' if repository generates one (UUID/ULID strategy)
        // For auto-increment (INT strategy), repository will let DB handle it
        $generatedId = $this->userRepository->generateId();
        if ($generatedId !== null) {
            $userData['id'] = $generatedId;
        }

        return $this->userRepository->create($userData);
    }

    /**
     * Sign in a user with email and password.
     *
     * @param string $email The user's email
     * @param string $password The user's password
     * @param string $ipAddress The user's IP address
     * @param string $userAgent The user's user agent
     * @return array{user: User, session: Session} The user and session
     * @throws InvalidCredentialsException
     * @throws RateLimitException
     * @throws \Exception
     */
    public function signIn(string $email, string $password, string $ipAddress, string $userAgent): array
    {
        // Rate limiting
        $rateLimitKey = "login:$email";
        if ($this->rateLimiter?->tooManyAttempts($rateLimitKey, 5, 300)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);

            throw new RateLimitException(retryAfter: $retryAfter);
        }

        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !$user->hasPassword()) {
            $this->rateLimiter?->hit($rateLimitKey, 300);

            throw new InvalidCredentialsException();
        }

        // After hasPassword() check, passwordHash is guaranteed to be non-null
        $passwordHash = $user->passwordHash;
        assert($passwordHash !== null);

        if (!$this->passwordHasher->verify($password, $passwordHash)) {
            $this->rateLimiter?->hit($rateLimitKey, 300);

            throw new InvalidCredentialsException();
        }

        // Clear rate limit on successful login
        $this->rateLimiter?->clear($rateLimitKey);

        // Check if password needs rehashing
        if ($this->passwordHasher->needsRehash($passwordHash)) {
            $newHash = $this->passwordHasher->hash($password);
            $user = $this->userRepository->update($user->id, ['password_hash' => $newHash]);
        }

        $session = $this->sessionService->create($user, $ipAddress, $userAgent);

        return [
            'user' => $user,
            'session' => $session,
        ];
    }

    /**
     * Sign out a user by deleting their session.
     *
     * @param string $sessionToken The session token
     * @return bool True if signed out, false otherwise
     */
    public function signOut(string $sessionToken): bool
    {
        return $this->sessionService->delete($sessionToken);
    }

    /**
     * Get the current user from a session token.
     *
     * @param string $sessionToken The session token
     * @return User|null The user or null if not found
     */
    public function getCurrentUser(string $sessionToken): ?User
    {
        try {
            $session = $this->sessionService->validate($sessionToken);

            return $this->userRepository->findById($session->userId);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Verify a user's email.
     *
     * @param string $userId The user ID
     * @return bool True if verified, false otherwise
     */
    public function verifyEmail(string $userId): bool
    {
        return $this->userRepository->verifyEmail($userId);
    }

    /**
     * Update user password.
     *
     * @param string $userId The user ID
     * @param string $newPassword The new password
     * @return User The updated user
     * @throws UserNotFoundException
     */
    public function updatePassword(string $userId, string $newPassword): User
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        $passwordHash = $this->passwordHasher->hash($newPassword);

        return $this->userRepository->update($userId, ['password_hash' => $passwordHash]);
    }

    /**
     * Validate a session and refresh it if needed.
     *
     * @param string $sessionToken The session token
     * @return Session The validated/refreshed session
     */
    public function validateSession(string $sessionToken): Session
    {
        return $this->sessionService->validate($sessionToken);
    }
}
