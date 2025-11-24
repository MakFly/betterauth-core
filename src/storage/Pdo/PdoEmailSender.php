<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Interfaces\EmailSenderInterface;

/**
 * PDO-based email sender using PHP's mail() function.
 *
 * This is a reference implementation for sending verification and notification emails.
 * For production, replace with a proper mail service (SendGrid, Mailgun, etc.).
 */
class PdoEmailSender implements EmailSenderInterface
{
    public function __construct(
        private readonly string $fromAddress = 'noreply@example.com',
        private readonly string $fromName = 'BetterAuth',
        private readonly bool $logOnly = false,
    ) {
    }

    public function sendMagicLink(string $email, string $link): bool
    {
        $subject = 'Your Magic Login Link';
        $body = $this->getMagicLinkTemplate($email, $link);

        return $this->send($email, $subject, $body);
    }

    public function sendVerificationEmail(string $email, string $verificationLink): bool
    {
        $subject = 'Verify Your Email Address';
        $body = $this->getVerificationEmailTemplate($email, $verificationLink);

        return $this->send($email, $subject, $body);
    }

    public function sendPasswordReset(string $email, string $resetLink): bool
    {
        $subject = 'Reset Your Password';
        $body = $this->getPasswordResetTemplate($email, $resetLink);

        return $this->send($email, $subject, $body);
    }

    public function sendTwoFactorCode(string $email, string $code, ?int $expiresIn = null): bool
    {
        $subject = '2FA Code';
        $body = $this->getTwoFactorCodeTemplate($email, $code, $expiresIn);

        return $this->send($email, $subject, $body);
    }

    private function send(string $to, string $subject, string $body): bool
    {
        if ($this->logOnly) {
            error_log("Email (logOnly): To: $to, Subject: $subject");

            return true;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            "From: {$this->fromName} <{$this->fromAddress}>",
            "Reply-To: {$this->fromAddress}",
        ];

        $success = mail($to, $subject, $body, implode("\r\n", $headers));

        if (!$success) {
            error_log("Failed to send email to: $to, Subject: $subject");
        }

        return $success;
    }

    private function getMagicLinkTemplate(string $email, string $link): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Magic Login Link</h2>
                    <p>Hi $email,</p>
                    <p>Click the link below to log in to your account:</p>
                    <p><a href="$link" class="button">Login Now</a></p>
                    <p>Or copy and paste this link: <a href="$link">$link</a></p>
                    <p>This link expires in 15 minutes.</p>
                    <p>If you didn't request this link, please ignore this email.</p>
                    <hr>
                    <p><small>Sent by {$this->fromName}</small></p>
                </div>
            </body>
            </html>
            HTML;
    }

    private function getVerificationEmailTemplate(string $email, string $verificationLink): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Verify Your Email Address</h2>
                    <p>Hi $email,</p>
                    <p>Thank you for signing up! Please verify your email address by clicking the link below:</p>
                    <p><a href="$verificationLink" class="button">Verify Email</a></p>
                    <p>Or copy and paste this link: <a href="$verificationLink">$verificationLink</a></p>
                    <p>This link expires in 24 hours.</p>
                    <p>If you didn't create this account, please ignore this email.</p>
                    <hr>
                    <p><small>Sent by {$this->fromName}</small></p>
                </div>
            </body>
            </html>
            HTML;
    }

    private function getPasswordResetTemplate(string $email, string $resetLink): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { display: inline-block; padding: 10px 20px; background-color: #ffc107; color: #333; text-decoration: none; border-radius: 5px; }
                    .warning { color: #dc3545; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Reset Your Password</h2>
                    <p>Hi $email,</p>
                    <p>We received a request to reset your password. Click the link below to create a new password:</p>
                    <p><a href="$resetLink" class="button">Reset Password</a></p>
                    <p>Or copy and paste this link: <a href="$resetLink">$resetLink</a></p>
                    <p>This link expires in 1 hour.</p>
                    <p class="warning"><strong>If you didn't request this, please ignore this email.</strong> Your password will remain unchanged.</p>
                    <hr>
                    <p><small>Sent by {$this->fromName}</small></p>
                </div>
            </body>
            </html>
            HTML;
    }

    private function getTwoFactorCodeTemplate(string $email, string $code, ?int $expiresIn = null): string
    {
        $expireText = $expiresIn ? " (expires in $expiresIn seconds)" : '';

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .code-box { background-color: #f0f0f0; padding: 20px; text-align: center; border-radius: 5px; margin: 20px 0; }
                    .code { font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #007bff; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Two-Factor Authentication Code</h2>
                    <p>Hi $email,</p>
                    <p>Your 2FA code is below. Do not share this code with anyone.</p>
                    <div class="code-box">
                        <div class="code">$code</div>
                    </div>
                    <p>This code is valid for $expireText.</p>
                    <p>If you didn't request this code, please ignore this email and check your account security.</p>
                    <hr>
                    <p><small>Sent by {$this->fromName}</small></p>
                </div>
            </body>
            </html>
            HTML;
    }
}
