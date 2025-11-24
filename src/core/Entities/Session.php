<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;

/**
 * Session entity representing an active user session.
 *
 * This is a readonly immutable value object.
 */
readonly class Session
{
    public function __construct(
        public string $token,
        public string $userId,
        public DateTimeImmutable $expiresAt,
        public string $ipAddress,
        public string $userAgent,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?array $metadata = null,
        public ?string $activeOrganizationId = null,
        public ?string $activeTeamId = null,
    ) {
    }

    /**
     * Create a Session from an array of data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            userId: $data['user_id'],
            expiresAt: new DateTimeImmutable($data['expires_at']),
            ipAddress: $data['ip_address'],
            userAgent: $data['user_agent'],
            createdAt: new DateTimeImmutable($data['created_at'] ?? 'now'),
            updatedAt: new DateTimeImmutable($data['updated_at'] ?? 'now'),
            metadata: $data['metadata'] ?? null,
            activeOrganizationId: $data['active_organization_id'] ?? $data['activeOrganizationId'] ?? null,
            activeTeamId: $data['active_team_id'] ?? $data['activeTeamId'] ?? null,
        );
    }

    /**
     * Convert the Session to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user_id' => $this->userId,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
            'active_organization_id' => $this->activeOrganizationId,
            'active_team_id' => $this->activeTeamId,
        ];
    }

    /**
     * Check if the session is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Check if the session is still valid.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
}
