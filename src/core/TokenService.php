<?php

declare(strict_types=1);

namespace BetterAuth\Core;

use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Core\Utils\Crypto;

/**
 * Token service for signing and verifying tokens.
 * This is a simplified implementation. For production, consider using paragonie/paseto.
 */
class TokenService implements TokenSignerInterface
{
    private const HEADER = 'v4.local.';

    public function __construct(
        private readonly string $secretKey
    ) {
        if (strlen($secretKey) < 32) {
            throw new \InvalidArgumentException('Secret key must be at least 32 characters');
        }
    }

    public function sign(array $payload, int $expiresIn): string
    {
        $now = time();
        $tokenPayload = [
            'sub' => $payload['sub'] ?? '',
            'iat' => $now,
            'exp' => $now + $expiresIn,
            'type' => $payload['type'] ?? 'access',
            'data' => $payload['data'] ?? null,
        ];

        $json = json_encode($tokenPayload);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode token payload');
        }

        // Simplified encryption (for production, use Paseto library)
        $nonce = random_bytes(24);
        $encrypted = $this->encrypt($json, $nonce);

        return self::HEADER . Crypto::base64UrlEncode($nonce . $encrypted);
    }

    public function verify(string $token): ?array
    {
        if (!str_starts_with($token, self::HEADER)) {
            return null;
        }

        $data = Crypto::base64UrlDecode(substr($token, strlen(self::HEADER)));
        if ($data === false || strlen($data) < 24) {
            return null;
        }

        $nonce = substr($data, 0, 24);
        $encrypted = substr($data, 24);

        try {
            $json = $this->decrypt($encrypted, $nonce);
            $payload = json_decode($json, true);

            if (!is_array($payload)) {
                return null;
            }

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            return $payload;
        } catch (\Exception) {
            return null;
        }
    }

    public function decode(string $token): ?array
    {
        // For this simplified version, decode is the same as verify but without expiration check
        if (!str_starts_with($token, self::HEADER)) {
            return null;
        }

        $data = Crypto::base64UrlDecode(substr($token, strlen(self::HEADER)));
        if ($data === false || strlen($data) < 24) {
            return null;
        }

        $nonce = substr($data, 0, 24);
        $encrypted = substr($data, 24);

        try {
            $json = $this->decrypt($encrypted, $nonce);

            return json_decode($json, true) ?: null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Simplified encryption using XChaCha20-Poly1305.
     *
     * @param string $plaintext
     * @param string $nonce
     * @return string
     */
    private function encrypt(string $plaintext, string $nonce): string
    {
        $key = hash('sha256', $this->secretKey, true);

        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            return sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plaintext,
                '',
                $nonce,
                $key
            );
        }

        // Fallback to simpler encryption if libsodium not available
        return openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($nonce, 0, 12), $tag) . $tag;
    }

    /**
     * Simplified decryption.
     *
     * @param string $ciphertext
     * @param string $nonce
     * @return string
     * @throws InvalidTokenException
     */
    private function decrypt(string $ciphertext, string $nonce): string
    {
        $key = hash('sha256', $this->secretKey, true);

        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            $decrypted = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                '',
                $nonce,
                $key
            );

            if ($decrypted === false) {
                throw new InvalidTokenException();
            }

            return $decrypted;
        }

        // Fallback decryption
        if (strlen($ciphertext) < 16) {
            throw new InvalidTokenException();
        }

        $tag = substr($ciphertext, -16);
        $ciphertext = substr($ciphertext, 0, -16);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            substr($nonce, 0, 12),
            $tag
        );

        if ($decrypted === false) {
            throw new InvalidTokenException();
        }

        return $decrypted;
    }
}
