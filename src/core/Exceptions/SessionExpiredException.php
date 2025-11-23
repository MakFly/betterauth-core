<?php

declare(strict_types=1);

namespace BetterAuth\Core\Exceptions;

/**
 * Exception thrown when a session has expired.
 */
class SessionExpiredException extends AuthException
{
    public function __construct(string $message = 'Session has expired')
    {
        parent::__construct($message, 401);
    }
}
