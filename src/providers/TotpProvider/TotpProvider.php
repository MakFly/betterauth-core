<?php

declare(strict_types=1);

namespace BetterAuth\Providers\TotpProvider;

use BetterAuth\Core\Interfaces\TotpStorageInterface;

/**
 * TOTP (Time-based One-Time Password) provider for two-factor authentication.
 *
 * Note: This is a simplified implementation. For production use,
 * consider using a library like spomky-labs/otphp.
 */
class TotpProvider
{
    private const PERIOD = 30; // 30 seconds
    private const DIGITS = 6;
    private const ALGORITHM = 'sha1';
    private const BACKUP_CODES_COUNT = 10;

    public function __construct(
        private readonly TotpStorageInterface $totpStorage,
        private readonly string $issuer = 'BetterAuth',
    ) {
    }

    /**
     * Generate a new TOTP secret for a user.
     *
     * @param string $userId
     * @return array{secret: string, qrCodeUrl: string, backupCodes: array<string>}
     * @throws \Exception
     */
    public function generateSecret(string $userId): array
    {
        // Generate a random secret (base32 encoded)
        $secret = $this->generateBase32Secret();

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();

        // Store the secret
        $this->totpStorage->store($userId, $secret, [
            'backup_codes' => array_map(fn ($code) => password_hash($code, PASSWORD_DEFAULT), $backupCodes),
            'enabled' => false,
        ]);

        // Generate QR code URL (otpauth:// URI)
        $qrCodeUrl = $this->getQrCodeUrl($userId, $secret);

        return [
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
            'backupCodes' => $backupCodes,
        ];
    }

    /**
     * Verify a TOTP code and enable 2FA.
     *
     * @param string $userId
     * @param string $code
     * @return bool True if verified and enabled
     */
    public function verifyAndEnable(string $userId, string $code): bool
    {
        $totpData = $this->totpStorage->findByUserId($userId);

        if ($totpData === null) {
            return false;
        }

        if ($this->verifyCode($totpData['secret'], $code)) {
            $this->totpStorage->enable($userId);

            return true;
        }

        return false;
    }

    /**
     * Verify a TOTP code for an enabled user.
     *
     * @param string $userId
     * @param string $code
     * @return bool True if code is valid
     */
    public function verify(string $userId, string $code): bool
    {
        $totpData = $this->totpStorage->findByUserId($userId);

        if ($totpData === null || !$totpData['enabled']) {
            return false;
        }

        // Try verifying as TOTP code
        if ($this->verifyCode($totpData['secret'], $code)) {
            return true;
        }

        // Try verifying as backup code
        return $this->totpStorage->useBackupCode($userId, $code);
    }

    /**
     * Disable TOTP for a user.
     *
     * @param string $userId
     * @return bool
     */
    public function disable(string $userId): bool
    {
        return $this->totpStorage->disable($userId);
    }

    /**
     * Regenerate backup codes.
     *
     * @param string $userId
     * @return array<string> New backup codes
     * @throws \Exception
     */
    public function regenerateBackupCodes(string $userId): array
    {
        $totpData = $this->totpStorage->findByUserId($userId);

        if ($totpData === null) {
            throw new \RuntimeException('TOTP not configured for user');
        }

        $backupCodes = $this->generateBackupCodes();

        $this->totpStorage->store($userId, $totpData['secret'], [
            'backup_codes' => array_map(fn ($code) => password_hash($code, PASSWORD_DEFAULT), $backupCodes),
            'enabled' => $totpData['enabled'],
        ]);

        return $backupCodes;
    }

    /**
     * Verify a TOTP code against a secret.
     *
     * @param string $secret
     * @param string $code
     * @return bool
     */
    private function verifyCode(string $secret, string $code): bool
    {
        $timestamp = time();

        // Check current time window and ±1 windows for clock skew
        for ($i = -1; $i <= 1; $i++) {
            $testTime = $timestamp + ($i * self::PERIOD);
            $expectedCode = $this->generateCode($secret, $testTime);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for a given timestamp.
     *
     * @param string $secret
     * @param int $timestamp
     * @return string
     */
    private function generateCode(string $secret, int $timestamp): string
    {
        $time = pack('N*', 0) . pack('N*', floor($timestamp / self::PERIOD));
        $hash = hash_hmac(self::ALGORITHM, $time, $this->base32Decode($secret), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = $value % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random base32-encoded secret.
     *
     * @return string
     * @throws \Exception
     */
    private function generateBase32Secret(): string
    {
        $bytes = random_bytes(20);

        return $this->base32Encode($bytes);
    }

    /**
     * Generate backup codes.
     *
     * @return array<string>
     * @throws \Exception
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $codes[] = substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(8))), 0, 10);
        }

        return $codes;
    }

    /**
     * Get the QR code URL for TOTP setup.
     *
     * @param string $userId
     * @param string $secret
     * @return string
     */
    private function getQrCodeUrl(string $userId, string $secret): string
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $this->issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return sprintf(
            'otpauth://totp/%s:%s?%s',
            urlencode($this->issuer),
            urlencode($userId),
            $params
        );
    }

    /**
     * Base32 encode.
     *
     * @param string $data
     * @return string
     */
    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = '';
        $bits = 0;
        $value = 0;

        foreach (str_split($data) as $char) {
            $value = ($value << 8) | ord($char);
            $bits += 8;

            while ($bits >= 5) {
                $encoded .= $alphabet[($value >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }

        if ($bits > 0) {
            $encoded .= $alphabet[($value << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    /**
     * Base32 decode.
     *
     * @param string $data
     * @return string
     */
    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $decoded = '';
        $bits = 0;
        $value = 0;

        foreach (str_split($data) as $char) {
            if (($pos = strpos($alphabet, $char)) === false) {
                continue;
            }

            $value = ($value << 5) | $pos;
            $bits += 5;

            if ($bits >= 8) {
                $decoded .= chr(($value >> ($bits - 8)) & 255);
                $bits -= 8;
            }
        }

        return $decoded;
    }
}
