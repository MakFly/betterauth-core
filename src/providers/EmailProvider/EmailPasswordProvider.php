<?php

declare(strict_types=1);

namespace BetterAuth\Providers\EmailProvider;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;

/**
 * Email/Password authentication provider.
 */
final class EmailPasswordProvider
{
    public function __construct(
        private readonly AuthManager $authManager,
    ) {
    }

    /**
     * Register a new user with email and password.
     *
     * @param array<string, mixed> $additionalData
     *
     * @throws \Exception
     */
    public function signUp(string $email, string $password, array $additionalData = []): User
    {
        $this->validateEmail($email);
        $this->validatePassword($password);

        return $this->authManager->signUp($email, $password, $additionalData);
    }

    /**
     * Sign in with email and password.
     *
     * @return array{user: User, session: Session}
     *
     * @throws \Exception
     */
    public function signIn(string $email, string $password, string $ipAddress, string $userAgent): array
    {
        $this->validateEmail($email);

        return $this->authManager->signIn($email, $password, $ipAddress, $userAgent);
    }

    /**
     * Validate email format.
     *
     * @throws \InvalidArgumentException
     */
    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
    }

    /**
     * Validate password strength.
     *
     * @throws \InvalidArgumentException
     */
    private function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long');
        }
    }
}
