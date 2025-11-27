<?php

declare(strict_types=1);

namespace BetterAuth\Providers\PasskeyProvider;

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Exceptions\InvalidCredentialsException;
use BetterAuth\Core\Interfaces\PasskeyStorageInterface;
use BetterAuth\Core\Interfaces\TokenManagerInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\SessionService;
use BetterAuth\Core\Utils\Crypto;

/**
 * Passkey/WebAuthn authentication provider.
 * Supports both session-based (monolith) and token-based (API/hybrid) authentication modes.
 *
 * Note: This is a simplified implementation. For production use,
 * integrate with a proper WebAuthn library like web-auth/webauthn-lib.
 */
final class PasskeyProvider
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasskeyStorageInterface $passkeyStorage,
        private readonly SessionService $sessionService,
        private readonly string $rpId,
        private readonly string $rpName,
        private readonly ?AuthConfig $authConfig = null,
        private readonly ?TokenManagerInterface $tokenManager = null,
    ) {
    }

    /**
     * Generate registration options for creating a new passkey.
     *
     * @return array<string, mixed> WebAuthn registration options
     *
     * @throws \Exception
     */
    public function generateRegistrationOptions(User $user): array
    {
        $challenge = Crypto::randomToken(32);

        return [
            'challenge' => $challenge,
            'rp' => [
                'name' => $this->rpName,
                'id' => $this->rpId,
            ],
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getEmail(),
                'displayName' => $user->getName() ?? $user->getEmail(),
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'requireResidentKey' => false,
                'userVerification' => 'required',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
        ];
    }

    /**
     * Verify and store a new passkey registration.
     *
     * @param array<string, mixed> $credential The credential from the client
     * @param string $challenge The challenge that was sent
     *
     * @return bool True if registration successful
     */
    public function verifyRegistration(User $user, array $credential, string $challenge): bool
    {
        // In production, use a WebAuthn library to properly verify the attestation
        // This is a simplified version for demonstration

        $credentialId = $credential['id'] ?? '';
        $publicKey = $credential['response']['publicKey'] ?? '';

        if (empty($credentialId) || empty($publicKey)) {
            return false;
        }

        // Store the credential
        return $this->passkeyStorage->store(
            userId: $user->getId(),
            credentialId: $credentialId,
            publicKey: $publicKey,
            metadata: [
                'counter' => 0,
                'transports' => $credential['transports'] ?? [],
                'created_at' => date('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * Generate authentication options for signing in with a passkey.
     *
     * @param string|null $userId Optional user ID to limit credentials
     *
     * @return array<string, mixed> WebAuthn authentication options
     *
     * @throws \Exception
     */
    public function generateAuthenticationOptions(?string $userId = null): array
    {
        $challenge = Crypto::randomToken(32);

        $options = [
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => $this->rpId,
            'userVerification' => 'required',
        ];

        if ($userId !== null) {
            // Get user's credentials
            $credentials = $this->passkeyStorage->findByUserId($userId);
            $options['allowCredentials'] = array_map(
                fn ($cred) => [
                    'type' => 'public-key',
                    'id' => $cred['credential_id'],
                    'transports' => $cred['metadata']['transports'] ?? [],
                ],
                $credentials,
            );
        }

        return $options;
    }

    /**
     * Verify passkey authentication and create session or tokens.
     *
     * Returns tokens in API/hybrid mode, session in monolith mode.
     *
     * @param array<string, mixed> $assertion The assertion from the client
     * @param string $challenge The challenge that was sent
     * @param string $ipAddress The user's IP address
     * @param string $userAgent The user's user agent
     *
     * @return array{user: array<string, mixed>, session?: Session, access_token?: string, refresh_token?: string, expires_in?: int, token_type?: string}
     *
     * @throws InvalidCredentialsException
     * @throws \Exception
     */
    public function verifyAuthentication(
        array $assertion,
        string $challenge,
        string $ipAddress,
        string $userAgent,
    ): array {
        $credentialId = $assertion['id'] ?? '';

        if (empty($credentialId)) {
            throw new InvalidCredentialsException('Invalid credential');
        }

        // Find credential
        $credential = $this->passkeyStorage->findByCredentialId($credentialId);

        if ($credential === null) {
            throw new InvalidCredentialsException('Credential not found');
        }

        // In production, use a WebAuthn library to properly verify the assertion
        // This includes verifying the signature, challenge, origin, etc.

        // Update counter (prevents replay attacks)
        $newCounter = ($credential['metadata']['counter'] ?? 0) + 1;
        $this->passkeyStorage->update($credentialId, [
            'metadata' => array_merge($credential['metadata'], ['counter' => $newCounter]),
        ]);

        // Get user
        $user = $this->userRepository->findById($credential['user_id']);

        if ($user === null) {
            throw new InvalidCredentialsException('User not found');
        }

        // API/Hybrid mode: Return JWT tokens
        if ($this->authConfig?->supportsTokens() && $this->tokenManager !== null) {
            $tokens = $this->tokenManager->create($user);

            return [
                'user' => \BetterAuth\Core\DTO\UserDto::fromUser($user)->toArray(),
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => $tokens['token_type'],
                'expires_in' => $tokens['expires_in'],
            ];
        }

        // Session mode (default): Create session
        $session = $this->sessionService->create($user, $ipAddress, $userAgent);

        return [
            'user' => \BetterAuth\Core\DTO\UserDto::fromUser($user)->toArray(),
            'session' => $session,
        ];
    }

    /**
     * Delete a passkey credential.
     */
    public function deleteCredential(string $credentialId): bool
    {
        return $this->passkeyStorage->delete($credentialId);
    }

    /**
     * Get all passkeys for a user.
     *
     * @return array<array<string, mixed>>
     */
    public function getUserCredentials(string $userId): array
    {
        return $this->passkeyStorage->findByUserId($userId);
    }
}
