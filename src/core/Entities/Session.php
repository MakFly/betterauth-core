<?php

declare(strict_types=1);

namespace BetterAuth\Core\Entities;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Session entity representing a user session.
 */
#[ORM\MappedSuperclass]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    protected string $token;

    #[ORM\Column(type: 'string', length: 36)]
    protected string $userId;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'string', length: 45)]
    protected string $ipAddress;

    #[ORM\Column(type: 'string', length: 500)]
    protected string $userAgent;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $metadata = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    protected ?string $activeOrganizationId = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    protected ?string $activeTeamId = null;

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

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
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

    public function getActiveOrganizationId(): ?string
    {
        return $this->activeOrganizationId;
    }

    public function setActiveOrganizationId(?string $activeOrganizationId): self
    {
        $this->activeOrganizationId = $activeOrganizationId;
        return $this;
    }

    public function getActiveTeamId(): ?string
    {
        return $this->activeTeamId;
    }

    public function setActiveTeamId(?string $activeTeamId): self
    {
        $this->activeTeamId = $activeTeamId;
        return $this;
    }

    public static function fromArray(array $data): self
    {
        $session = new self();
        $session->setToken($data['token']);
        $session->setUserId($data['user_id']);
        $session->setExpiresAt(new DateTimeImmutable($data['expires_at']));
        $session->setIpAddress($data['ip_address']);
        $session->setUserAgent($data['user_agent']);
        $session->setCreatedAt(new DateTimeImmutable($data['created_at'] ?? 'now'));
        $session->setUpdatedAt(new DateTimeImmutable($data['updated_at'] ?? 'now'));
        $session->setMetadata($data['metadata'] ?? null);
        $session->setActiveOrganizationId($data['active_organization_id'] ?? null);
        $session->setActiveTeamId($data['active_team_id'] ?? null);
        
        return $session;
    }

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
}
