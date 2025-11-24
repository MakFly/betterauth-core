<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * RefreshToken entity for JWT token refresh.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    protected string $token;

    #[ORM\Column(type: 'string', length: 36)]
    protected string $userId;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    protected bool $revoked = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $replacedBy = null;

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
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

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;
        return $this;
    }

    public function getReplacedBy(): ?string
    {
        return $this->replacedBy;
    }

    public function setReplacedBy(?string $replacedBy): self
    {
        $this->replacedBy = $replacedBy;
        return $this;
    }

    public static function fromArray(array $data): self
    {
        $token = new self();
        $token->setToken($data['token']);
        $token->setUserId($data['user_id']);
        $token->setExpiresAt(new DateTimeImmutable($data['expires_at']));
        $token->setCreatedAt(new DateTimeImmutable($data['created_at'] ?? 'now'));
        $token->setRevoked($data['revoked'] ?? false);
        $token->setReplacedBy($data['replaced_by'] ?? null);
        
        return $token;
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
}
