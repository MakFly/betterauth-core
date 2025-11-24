<?php

declare(strict_types=1);

namespace BetterAuth\Providers\DeviceManagementProvider;

final class DeviceFingerprintService
{
    public function generate(?string $userAgent, ?string $ipAddress, ?array $additionalData = null): string
    {
        $parts = [
            $userAgent ?? 'unknown',
            $ipAddress ?? 'unknown',
        ];

        if ($additionalData !== null) {
            ksort($additionalData);
            $parts[] = json_encode($additionalData);
        }

        return hash('sha256', implode('|', $parts));
    }
}
