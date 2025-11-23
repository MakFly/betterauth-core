<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;

/**
 * Magic link token entity for passwordless authentication.
 */
class MagicLinkToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly bool $used = false,
    ) {
    }

    /**
     * Create a MagicLinkToken from an array of data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            email: $data['email'],
            expiresAt: new DateTimeImmutable($data['expires_at']),
            createdAt: new DateTimeImmutable($data['created_at'] ?? 'now'),
            used: $data['used'] ?? false,
        );
    }

    /**
     * Convert the MagicLinkToken to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'email' => $this->email,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'used' => $this->used,
        ];
    }

    /**
     * Check if the token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Check if the token is valid (not expired and not used).
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->used;
    }
}
