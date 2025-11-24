<?php

declare(strict_types=1);

namespace BetterAuth\Providers\PasswordResetProvider;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Core\Exceptions\UserNotFoundException;
use BetterAuth\Core\Interfaces\EmailSenderInterface;
use BetterAuth\Core\Interfaces\PasswordResetStorageInterface;
use BetterAuth\Core\Interfaces\RateLimiterInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\Utils\Crypto;

/**
 * Password reset provider for handling password reset flows.
 */
final class PasswordResetProvider
{
    private const TOKEN_EXPIRY = 3600; // 1 hour

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetStorageInterface $passwordResetStorage,
        private readonly EmailSenderInterface $emailSender,
        private readonly AuthManager $authManager,
        private readonly ?RateLimiterInterface $rateLimiter = null,
    ) {
    }

    /**
     * Request a password reset by sending an email with reset link.
     *
     * @param string $email The user's email
     * @param string $callbackUrl The callback URL (token will be appended)
     * @return bool True if email sent successfully
     * @throws RateLimitException
     * @throws \Exception
     */
    public function requestReset(string $email, string $callbackUrl): bool
    {
        // Rate limiting
        $rateLimitKey = "password_reset:$email";
        if ($this->rateLimiter?->tooManyAttempts($rateLimitKey, 3, 3600)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);

            throw new RateLimitException(
                message: 'Too many password reset attempts. Please try again later.',
                retryAfter: $retryAfter
            );
        }

        // Check if user exists
        $user = $this->userRepository->findByEmail($email);

        // Don't reveal if user exists or not (security best practice)
        // Always return true but only send email if user exists
        if ($user === null) {
            $this->rateLimiter?->hit($rateLimitKey, 3600);

            return true;
        }

        $this->rateLimiter?->hit($rateLimitKey, 3600);

        // Invalidate any existing reset tokens for this email
        $this->passwordResetStorage->deleteByEmail($email);

        // Generate new token
        $token = Crypto::randomToken(32);

        // Store token
        $this->passwordResetStorage->store($token, $email, self::TOKEN_EXPIRY);

        // Build reset link URL
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';
        $resetLink = $callbackUrl . $separator . 'token=' . urlencode($token);

        // Send email
        return $this->emailSender->sendPasswordReset($email, $resetLink);
    }

    /**
     * Verify a password reset token.
     *
     * @param string $token The reset token
     * @return bool True if token is valid
     */
    public function verifyToken(string $token): bool
    {
        $resetToken = $this->passwordResetStorage->findByToken($token);

        return $resetToken !== null && $resetToken->isValid();
    }

    /**
     * Reset password using a valid token.
     *
     * @param string $token The reset token
     * @param string $newPassword The new password
     * @return bool True if password was reset successfully
     * @throws InvalidTokenException
     * @throws UserNotFoundException
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        // Find and validate token
        $resetToken = $this->passwordResetStorage->findByToken($token);

        if ($resetToken === null || !$resetToken->isValid()) {
            throw new InvalidTokenException('Invalid or expired password reset token');
        }

        // Find user
        $user = $this->userRepository->findByEmail($resetToken->email);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        // Update password
        $this->authManager->updatePassword($user->id, $newPassword);

        // Mark token as used
        $this->passwordResetStorage->markAsUsed($token);

        // Delete all other reset tokens for this email
        $this->passwordResetStorage->deleteByEmail($resetToken->email);

        // Clear rate limit
        $this->rateLimiter?->clear("password_reset:{$resetToken->email}");

        return true;
    }

    /**
     * Cancel a password reset by deleting the token.
     *
     * @param string $token The reset token
     * @return bool True if cancelled, false otherwise
     */
    public function cancelReset(string $token): bool
    {
        return $this->passwordResetStorage->delete($token);
    }
}
