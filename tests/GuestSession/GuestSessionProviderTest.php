<?php

declare(strict_types=1);

namespace BetterAuth\Tests\GuestSession;

use BetterAuth\Core\Entities\GuestSession;
use BetterAuth\Core\Interfaces\GuestSessionRepositoryInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class GuestSessionProviderTest extends TestCase
{
    private GuestSessionProvider $provider;
    private GuestSessionRepositoryInterface $guestRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->guestRepo = $this->createMock(GuestSessionRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->provider = new GuestSessionProvider($this->guestRepo, $this->userRepo, 86400);
    }

    public function testCreateGuestSession(): void
    {
        $this->guestRepo->expects($this->once())
            ->method('generateId')
            ->willReturn('guest-123');

        $guestSession = new GuestSession(
            id: 'guest-123',
            token: 'guest-token-123',
            deviceInfo: 'Test Browser',
            ipAddress: '127.0.0.1',
            createdAt: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            expiresAt: (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s')
        );

        $this->guestRepo->expects($this->once())
            ->method('create')
            ->willReturn($guestSession);

        $result = $this->provider->createGuestSession('127.0.0.1', 'Test Browser');

        $this->assertInstanceOf(GuestSession::class, $result);
        $this->assertEquals('guest-token-123', $result->token);
    }
}
