<?php

declare(strict_types=1);

namespace BetterAuth\Core\Utils;

use BetterAuth\Core\Interfaces\RateLimiterInterface;

/**
 * Simple in-memory rate limiter implementation.
 * For production use, consider using Redis or another cache backend.
 *
 * This service is final to ensure consistent rate limiting behavior.
 */
final class RateLimiter implements RateLimiterInterface
{
    /** @var array<string, array{attempts: int, reset: int}> */
    private array $storage = [];

    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $this->clearExpired($key);

        if (!isset($this->storage[$key])) {
            return false;
        }

        return $this->storage[$key]['attempts'] >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $this->clearExpired($key);

        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [
                'attempts' => 0,
                'reset' => time() + $decaySeconds,
            ];
        }

        $this->storage[$key]['attempts']++;

        return $this->storage[$key]['attempts'];
    }

    public function attempts(string $key): int
    {
        $this->clearExpired($key);

        return $this->storage[$key]['attempts'] ?? 0;
    }

    public function clear(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);

            return true;
        }

        return false;
    }

    public function availableIn(string $key): int
    {
        if (!isset($this->storage[$key])) {
            return 0;
        }

        $availableIn = $this->storage[$key]['reset'] - time();

        return max(0, $availableIn);
    }

    /**
     * Clear expired entries for a key.
     *
     * @param string $key The rate limit key
     */
    private function clearExpired(string $key): void
    {
        if (isset($this->storage[$key]) && $this->storage[$key]['reset'] < time()) {
            unset($this->storage[$key]);
        }
    }
}
