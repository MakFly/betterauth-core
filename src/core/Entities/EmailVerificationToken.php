<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;

/**
 * Email verification token entity.
 */
class EmailVerificationToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly bool $used = false,
    ) {
    }

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

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->used;
    }
}
