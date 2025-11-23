<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;

/**
 * User entity representing an authenticated user.
 */
class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $passwordHash,
        public readonly ?string $name,
        public readonly ?string $avatar,
        public readonly bool $emailVerified,
        public readonly ?DateTimeImmutable $emailVerifiedAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?array $metadata = null,
    ) {
    }

    /**
     * Create a User from an array of data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            email: $data['email'],
            passwordHash: $data['password_hash'] ?? null,
            name: $data['name'] ?? null,
            avatar: $data['avatar'] ?? null,
            emailVerified: $data['email_verified'] ?? false,
            emailVerifiedAt: isset($data['email_verified_at'])
                ? new DateTimeImmutable($data['email_verified_at'])
                : null,
            createdAt: new DateTimeImmutable($data['created_at'] ?? 'now'),
            updatedAt: new DateTimeImmutable($data['updated_at'] ?? 'now'),
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Convert the User to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'email_verified' => $this->emailVerified,
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if the user has a password set.
     *
     * @return bool
     */
    public function hasPassword(): bool
    {
        return $this->passwordHash !== null;
    }
}
