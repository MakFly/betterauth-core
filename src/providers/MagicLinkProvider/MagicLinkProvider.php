<?php

declare(strict_types=1);

namespace BetterAuth\Providers\MagicLinkProvider;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Core\Interfaces\EmailSenderInterface;
use BetterAuth\Core\Interfaces\MagicLinkStorageInterface;
use BetterAuth\Core\Interfaces\RateLimiterInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\SessionService;
use BetterAuth\Core\Utils\Crypto;
use BetterAuth\Core\Utils\IdGenerator;

/**
 * Magic link (passwordless) authentication provider.
 */
final class MagicLinkProvider
{
    private const TOKEN_EXPIRY = 600; // 10 minutes

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly MagicLinkStorageInterface $magicLinkStorage,
        private readonly EmailSenderInterface $emailSender,
        private readonly SessionService $sessionService,
        private readonly ?RateLimiterInterface $rateLimiter = null,
    ) {
    }

    /**
     * Send a magic link to the user's email.
     *
     * @param string $email The user's email
     * @param string $ipAddress The user's IP address
     * @param string $userAgent The user's user agent
     * @param string|null $callbackUrl Optional callback URL (magic link will be appended)
     * @return array{success: bool, expiresIn: int} Result with expiration time
     * @throws RateLimitException
     * @throws \Exception
     */
    public function sendMagicLink(string $email, string $ipAddress, string $userAgent, ?string $callbackUrl = null): array
    {
        // Rate limiting
        $rateLimitKey = "magic_link:$email";
        if ($this->rateLimiter?->tooManyAttempts($rateLimitKey, 3, 300)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);

            throw new RateLimitException(retryAfter: $retryAfter);
        }

        $this->rateLimiter?->hit($rateLimitKey, 300);

        // Generate token
        $token = Crypto::randomToken(32);

        // Store token
        $this->magicLinkStorage->store($token, $email, self::TOKEN_EXPIRY);

        if ($callbackUrl !== null) {
            // Build magic link URL
            $separator = str_contains($callbackUrl, '?') ? '&' : '?';
            $magicLink = $callbackUrl . $separator . 'token=' . urlencode($token);

            // Send email
            $this->emailSender->sendMagicLink($email, $magicLink);
        }

        return ['success' => true, 'expiresIn' => self::TOKEN_EXPIRY];
    }

    /**
     * Verify a magic link token and create a session.
     *
     * @param string $token The magic link token
     * @param string $ipAddress The user's IP address
     * @param string $userAgent The user's user agent
     * @return array{success: bool, error?: string, access_token?: string, refresh_token?: string, expires_in?: int, user?: array} Result of verification
     * @throws \Exception
     */
    public function verifyMagicLink(string $token, string $ipAddress, string $userAgent): array
    {
        // Find token
        $magicLinkToken = $this->magicLinkStorage->findByToken($token);

        if ($magicLinkToken === null || !$magicLinkToken->isValid()) {
            return ['success' => false, 'error' => 'Invalid or expired magic link'];
        }

        // Mark token as used
        $this->magicLinkStorage->markAsUsed($token);

        // Find or create user
        $user = $this->userRepository->findByEmail($magicLinkToken->email);

        if ($user === null) {
            // Auto-create user for magic link
            $user = $this->userRepository->create([
                'id' => IdGenerator::ulid(),
                'email' => $magicLinkToken->email,
                'password_hash' => null,
                'email_verified' => true, // Magic link confirms email ownership
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            // Verify email if not already verified
            if (!$user->emailVerified) {
                $this->userRepository->verifyEmail($user->id);
                $updatedUser = $this->userRepository->findById($user->id);
                if ($updatedUser !== null) {
                    $user = $updatedUser;
                }
            }
        }

        // Create session
        $session = $this->sessionService->create($user, $ipAddress, $userAgent);

        return [
            'success' => true,
            'access_token' => $session->sessionToken,
            'refresh_token' => $session->sessionToken, // Using session token as both
            'expires_in' => 604800, // 7 days default
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'emailVerified' => $user->emailVerified,
            ],
        ];
    }
}
