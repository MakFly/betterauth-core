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
class MagicLinkProvider
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
     * @param string $callbackUrl The callback URL (magic link will be appended)
     * @return bool True if sent successfully
     * @throws RateLimitException
     * @throws \Exception
     */
    public function sendMagicLink(string $email, string $callbackUrl): bool
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

        // Build magic link URL
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';
        $magicLink = $callbackUrl . $separator . 'token=' . urlencode($token);

        // Send email
        return $this->emailSender->sendMagicLink($email, $magicLink);
    }

    /**
     * Verify a magic link token and create a session.
     *
     * @param string $token The magic link token
     * @param string $ipAddress The user's IP address
     * @param string $userAgent The user's user agent
     * @return array{user: User, session: Session} The user and session
     * @throws InvalidTokenException
     * @throws \Exception
     */
    public function verifyMagicLink(string $token, string $ipAddress, string $userAgent): array
    {
        // Find token
        $magicLinkToken = $this->magicLinkStorage->findByToken($token);

        if ($magicLinkToken === null || !$magicLinkToken->isValid()) {
            throw new InvalidTokenException('Invalid or expired magic link');
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
            'user' => $user,
            'session' => $session,
        ];
    }
}
