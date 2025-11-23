<?php

declare(strict_types=1);

namespace BetterAuth\Core\Config;

/**
 * Enum defining authentication modes.
 */
enum AuthMode: string
{
    /**
     * Monolithic mode - Traditional session-based auth with cookies.
     * Best for: Traditional web apps, server-side rendered pages
     */
    case MONOLITH = 'monolith';

    /**
     * Microservice API mode - Stateless JWT/token-based auth.
     * Best for: REST APIs, SPAs, Mobile apps, Microservices
     */
    case API = 'api';

    /**
     * Check if current mode is monolith.
     */
    public function isMonolith(): bool
    {
        return $this === self::MONOLITH;
    }

    /**
     * Check if current mode is API.
     */
    public function isApi(): bool
    {
        return $this === self::API;
    }
}
