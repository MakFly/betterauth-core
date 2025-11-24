<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * User entity representing an authenticated user.
 */
#[ORM\MappedSuperclass]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    protected string $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    protected string $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $passwordHash = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $name = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    protected ?string $avatar = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $emailVerified = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $metadata = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): self
    {
        $this->emailVerified = $emailVerified;
        return $this;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?DateTimeImmutable $emailVerifiedAt): self
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Create a User from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->setId($data['id']);
        $user->setEmail($data['email']);
        $user->setPasswordHash($data['password_hash'] ?? null);
        $user->setName($data['name'] ?? null);
        $user->setAvatar($data['avatar'] ?? null);
        $user->setEmailVerified($data['email_verified'] ?? false);
        $user->setEmailVerifiedAt(
            isset($data['email_verified_at']) 
                ? new DateTimeImmutable($data['email_verified_at']) 
                : null
        );
        $user->setCreatedAt(new DateTimeImmutable($data['created_at'] ?? 'now'));
        $user->setUpdatedAt(new DateTimeImmutable($data['updated_at'] ?? 'now'));
        $user->setMetadata($data['metadata'] ?? null);
        
        return $user;
    }

    /**
     * Convert to array.
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
}
