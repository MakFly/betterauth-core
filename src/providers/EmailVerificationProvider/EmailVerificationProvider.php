<?php

declare(strict_types=1);

namespace BetterAuth\Providers\EmailVerificationProvider;

use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Core\Exceptions\UserNotFoundException;
use BetterAuth\Core\Interfaces\EmailSenderInterface;
use BetterAuth\Core\Interfaces\EmailVerificationStorageInterface;
use BetterAuth\Core\Interfaces\RateLimiterInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\Utils\Crypto;

final class EmailVerificationProvider
{
    private const TOKEN_EXPIRY = 86400; // 24 hours

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailVerificationStorageInterface $verificationStorage,
        private readonly EmailSenderInterface $emailSender,
        private readonly ?RateLimiterInterface $rateLimiter = null,
    ) {
    }

    public function sendVerificationEmail(string $email, string $callbackUrl): bool
    {
        $rateLimitKey = "email_verification:$email";
        if ($this->rateLimiter?->tooManyAttempts($rateLimitKey, 3, 3600)) {
            throw new RateLimitException(retryAfter: $this->rateLimiter->availableIn($rateLimitKey));
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            throw new UserNotFoundException();
        }

        if ($user->emailVerified) {
            return true; // Already verified
        }

        $this->rateLimiter?->hit($rateLimitKey, 3600);
        $this->verificationStorage->deleteByEmail($email);

        $token = Crypto::randomToken(32);
        $this->verificationStorage->store($token, $email, self::TOKEN_EXPIRY);

        $separator = str_contains($callbackUrl, '?') ? '&' : '?';
        $verificationLink = $callbackUrl . $separator . 'token=' . urlencode($token);

        return $this->emailSender->sendVerificationEmail($email, $verificationLink);
    }

    public function verifyEmail(string $token): bool
    {
        $verificationToken = $this->verificationStorage->findByToken($token);

        if ($verificationToken === null || !$verificationToken->isValid()) {
            throw new InvalidTokenException('Invalid or expired verification token');
        }

        $user = $this->userRepository->findByEmail($verificationToken->email);
        if ($user === null) {
            throw new UserNotFoundException();
        }

        $this->userRepository->verifyEmail($user->id);
        $this->verificationStorage->markAsUsed($token);
        $this->verificationStorage->deleteByEmail($verificationToken->email);
        $this->rateLimiter?->clear("email_verification:{$verificationToken->email}");

        return true;
    }
}
