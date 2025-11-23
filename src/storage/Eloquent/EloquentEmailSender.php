<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Eloquent;

use BetterAuth\Core\Interfaces\EmailSenderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Eloquent implementation of EmailSenderInterface.
 * Uses Laravel Mail facade to send emails.
 * Falls back to Log for development environments.
 */
class EloquentEmailSender implements EmailSenderInterface
{
    public function sendMagicLink(string $email, string $magicLink): bool
    {
        try {
            if (config('mail.mailers.smtp.host') === null) {
                // Log instead if mail not configured
                Log::info("Magic link for {$email}: {$magicLink}");

                return true;
            }

            Mail::raw(
                "Click here to login: {$magicLink}",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Your Magic Link');
                }
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send magic link to {$email}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendVerificationEmail(string $email, string $verificationLink): bool
    {
        try {
            if (config('mail.mailers.smtp.host') === null) {
                Log::info("Verification email for {$email}: {$verificationLink}");

                return true;
            }

            Mail::raw(
                "Verify your email by clicking: {$verificationLink}",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Verify Your Email');
                }
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send verification email to {$email}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendPasswordReset(string $email, string $resetLink): bool
    {
        try {
            if (config('mail.mailers.smtp.host') === null) {
                Log::info("Password reset for {$email}: {$resetLink}");

                return true;
            }

            Mail::raw(
                "Reset your password by clicking: {$resetLink}",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Reset Your Password');
                }
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send password reset email to {$email}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendTwoFactorCode(string $email, string $code): bool
    {
        try {
            if (config('mail.mailers.smtp.host') === null) {
                Log::info("Two-factor code for {$email}: {$code}");

                return true;
            }

            Mail::raw(
                "Your two-factor authentication code is: {$code}",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Your Two-Factor Code');
                }
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send two-factor code to {$email}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
