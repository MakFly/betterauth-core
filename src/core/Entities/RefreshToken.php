<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;

/**
 * Refresh token entity for API mode.
 *
 * This is a readonly immutable value object.
 */
readonly class RefreshToken
{
    public function __construct(
        public string $token,
        public string $userId,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $createdAt,
        public bool $revoked = false,
        public ?string $replacedBy = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            userId: $data['user_id'],
            expiresAt: new DateTimeImmutable($data['expires_at']),
            createdAt: new DateTimeImmutable($data['created_at'] ?? 'now'),
            revoked: $data['revoked'] ?? false,
            replacedBy: $data['replaced_by'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user_id' => $this->userId,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'revoked' => $this->revoked,
            'replaced_by' => $this->replacedBy,
        ];
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->revoked;
    }
}
