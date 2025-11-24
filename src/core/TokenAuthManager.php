<?php

declare(strict_types=1);

namespace BetterAuth\Core;

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Exceptions\InvalidCredentialsException;
use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\Utils\Crypto;
use DateTimeImmutable;

/**
 * Token-based authentication manager for stateless APIs and microservices.
 *
 * Uses TokenService (Paseto V4) to create and verify JWT-like access/refresh tokens.
 * Perfect for REST APIs, SPAs, mobile apps, and microservices.
 *
 * For session-based authentication with cookies, use SessionAuthManager instead.
 *
 * This class is final to ensure consistent token authentication behavior.
 */
final class TokenAuthManager
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly TokenSignerInterface $tokenService,
        private readonly PasswordHasher $passwordHasher,
        private readonly AuthConfig $config,
    ) {
    }

    /**
     * Authenticate and return access + refresh tokens.
     */
    public function signIn(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null || !$user->hasPassword()) {
            throw new InvalidCredentialsException();
        }

        // After hasPassword() check, passwordHash is guaranteed to be non-null
        $passwordHash = $user->passwordHash;
        assert($passwordHash !== null);

        if (!$this->passwordHasher->verify($password, $passwordHash)) {
            throw new InvalidCredentialsException();
        }

        return $this->createTokenPair($user);
    }

    /**
     * Refresh access token using refresh token.
     */
    public function refresh(string $refreshTokenValue): array
    {
        $refreshToken = $this->refreshTokenRepository->findByToken($refreshTokenValue);

        if ($refreshToken === null || !$refreshToken->isValid()) {
            throw new InvalidTokenException('Invalid or expired refresh token');
        }

        $user = $this->userRepository->findById($refreshToken->userId);
        if ($user === null) {
            throw new InvalidTokenException('User not found');
        }

        // Revoke old refresh token
        $newRefreshTokenValue = Crypto::randomToken(32);
        $this->refreshTokenRepository->revoke($refreshTokenValue, $newRefreshTokenValue);

        // Create new token pair
        return $this->createTokenPair($user, $newRefreshTokenValue);
    }

    /**
     * Verify access token and return user.
     */
    public function verify(string $accessToken): User
    {
        $payload = $this->tokenService->verify($accessToken);

        if ($payload === null || !isset($payload['sub'])) {
            throw new InvalidTokenException();
        }

        $user = $this->userRepository->findById($payload['sub']);
        if ($user === null) {
            throw new InvalidTokenException('User not found');
        }

        return $user;
    }

    /**
     * Revoke all tokens for a user (logout from all devices).
     */
    public function revokeAllTokens(string $userId): int
    {
        return $this->refreshTokenRepository->revokeAllForUser($userId);
    }

    /**
     * Create tokens for an existing user without password verification.
     * Useful for OAuth, magic links, or automatic login after registration.
     */
    public function createTokensForUser(User $user): array
    {
        return $this->createTokenPair($user);
    }

    /**
     * Create access and refresh token pair.
     */
    private function createTokenPair(User $user, ?string $refreshTokenValue = null): array
    {
        // Create access token
        $accessToken = $this->tokenService->sign(
            [
                'sub' => $user->id,
                'type' => 'access',
                'data' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
            ],
            $this->config->tokenLifetime
        );

        // Create refresh token
        if ($refreshTokenValue === null) {
            $refreshTokenValue = Crypto::randomToken(32);
        }

        $expiresAt = new DateTimeImmutable("+{$this->config->refreshTokenLifetime} seconds");

        $refreshToken = $this->refreshTokenRepository->create([
            'token' => $refreshTokenValue,
            'userId' => $user->id,
            'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->tokenLifetime,
        ];
    }
}
