# BetterAuth Core

[![Tests](https://github.com/betterauth/betterauth-core/workflows/Tests/badge.svg)](https://github.com/betterauth/betterauth-core/actions)
[![Latest Stable Version](https://poser.pugx.org/betterauth/core/v/stable)](https://packagist.org/packages/betterauth/core)
[![Total Downloads](https://poser.pugx.org/betterauth/core/downloads)](https://packagist.org/packages/betterauth/core)
[![License](https://poser.pugx.org/betterauth/core/license)](https://packagist.org/packages/betterauth/core)

Framework-agnostic authentication library for PHP 8.2+.

## Features

- Multiple authentication methods (Email/Password, Magic Link, OAuth, Passkeys, TOTP)
- SSO/OIDC Provider support
- Multi-tenant capabilities (Organizations, Teams, Members, Invitations)
- Secure by default (Paseto V4 tokens, Argon2id hashing)
- Multiple storage adapters (PDO, Eloquent, Doctrine)
- Framework-agnostic core
- UUID v7 support for time-ordered, non-guessable IDs

## Installation

```bash
composer require betterauth/core
```

## Framework Integrations

For framework-specific integrations, use:

- **Laravel**: [`betterauth/laravel`](https://github.com/betterauth/betterauth-laravel)
- **Symfony**: [`betterauth/symfony-bundle`](https://github.com/betterauth/betterauth-symfony)
- **Vanilla PHP**: Use this package directly with PDO storage adapters

## Quick Start (Vanilla PHP with PDO)

```php
<?php

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\TokenAuthManager;
use BetterAuth\Storage\Pdo\PdoUserRepository;

// Database setup
$pdo = new PDO('sqlite:database.db');

// Create repositories
$userRepo = new PdoUserRepository($pdo);

// Configure authentication
$config = new AuthConfig(
    secret: 'your-256-bit-secret-key',
    tokenLifetime: 7200, // 2 hours
    refreshLifetime: 2592000 // 30 days
);

// Create auth manager
$auth = new TokenAuthManager($config, $userRepo);

// Register a user
$user = $auth->register(
    email: 'user@example.com',
    password: 'SecurePassword123',
    name: 'John Doe'
);

// Login
$tokens = $auth->login(
    email: 'user@example.com',
    password: 'SecurePassword123'
);

// Access user with token
$currentUser = $auth->getUserFromToken($tokens['accessToken']);
```

## Storage Adapters

### PDO (Vanilla PHP)

```php
use BetterAuth\Storage\Pdo\PdoUserRepository;
use BetterAuth\Storage\Pdo\PdoSessionRepository;

$pdo = new PDO('mysql:host=localhost;dbname=auth', 'user', 'password');
$userRepo = new PdoUserRepository($pdo);
$sessionRepo = new PdoSessionRepository($pdo);
```

### Eloquent (Laravel)

```php
use BetterAuth\Storage\Eloquent\EloquentUserRepository;

$userRepo = new EloquentUserRepository();
```

### Doctrine (Symfony)

Use the [`betterauth/symfony-bundle`](https://github.com/betterauth/betterauth-symfony) which provides Doctrine integration.

## Authentication Providers

### Email/Password

```php
$auth->register(
    email: 'user@example.com',
    password: 'SecurePassword123',
    name: 'John Doe'
);

$tokens = $auth->login(
    email: 'user@example.com',
    password: 'SecurePassword123'
);
```

### OAuth 2.0

```php
use BetterAuth\Providers\OAuthProvider\OAuthManager;

$oauthConfig = [
    'google' => [
        'clientId' => 'your-google-client-id',
        'clientSecret' => 'your-google-client-secret',
        'redirectUri' => 'https://yourapp.com/auth/google/callback',
    ],
];

$oauth = new OAuthManager($oauthConfig, $userRepo);

// Redirect to OAuth provider
$authUrl = $oauth->getAuthorizationUrl('google');
header("Location: $authUrl");

// Handle callback
$userData = $oauth->handleCallback('google', $_GET['code']);
$user = $auth->createOrUpdateOAuthUser($userData);
```

### Magic Link

```php
use BetterAuth\Providers\MagicLinkProvider\MagicLinkManager;

$magicLink = new MagicLinkManager($config, $userRepo);

// Send magic link
$token = $magicLink->createMagicLink('user@example.com');
// Send $token via email

// Verify magic link
$user = $magicLink->verifyMagicLink($token);
```

### TOTP (Two-Factor Authentication)

```php
use BetterAuth\Providers\TotpProvider\TotpManager;

$totp = new TotpManager($userRepo);

// Enable TOTP for user
$secret = $totp->enableTotp($userId);
$qrCode = $totp->getQrCode($user, $secret);

// Verify TOTP code
$isValid = $totp->verifyTotp($userId, '123456');
```

### Passkeys (WebAuthn)

```php
use BetterAuth\Providers\PasskeyProvider\PasskeyManager;

$passkey = new PasskeyManager($config, $userRepo);

// Register passkey
$options = $passkey->generateRegistrationOptions($userId);
// Send $options to client

// Verify registration
$passkey->verifyRegistration($userId, $clientResponse);

// Authenticate with passkey
$options = $passkey->generateAuthenticationOptions();
// Send $options to client

// Verify authentication
$user = $passkey->verifyAuthentication($clientResponse);
```

## Security Features

### Token Management

BetterAuth uses **Paseto V4** tokens (encrypted, authenticated):

```php
// Access token (short-lived)
$accessToken = $tokens['accessToken']; // Valid for 2 hours

// Refresh token (long-lived)
$refreshToken = $tokens['refreshToken']; // Valid for 30 days

// Refresh access token
$newTokens = $auth->refresh($refreshToken);
```

### Password Hashing

Passwords are hashed using **Argon2id** (memory-hard, resistant to GPU attacks):

```php
// Automatic during registration
$user = $auth->register(
    email: 'user@example.com',
    password: 'SecurePassword123' // Hashed with Argon2id
);
```

### UUID v7 IDs

BetterAuth supports **time-ordered UUIDs** for better database performance:

```php
// Example UUID v7 (time-ordered, non-guessable)
$user->id; // "019ab13e-40f1-7b21-a672-f403d5277ec7"

// Benefits:
// - Chronologically sortable
// - Non-guessable (secure)
// - No index fragmentation (fast DB queries)
```

## Configuration

```php
use BetterAuth\Core\Config\AuthConfig;

$config = new AuthConfig(
    secret: 'your-256-bit-secret-key',
    tokenLifetime: 7200,        // Access token: 2 hours
    refreshLifetime: 2592000,   // Refresh token: 30 days
    passwordMinLength: 8,
    requireEmailVerification: true,
    enableDeviceTrust: true,
    enableSecurityNotifications: true
);
```

## Multi-Tenancy

```php
use BetterAuth\Providers\AccountLinkProvider\OrganizationManager;

$orgManager = new OrganizationManager($userRepo);

// Create organization
$org = $orgManager->createOrganization(
    name: 'Acme Inc',
    ownerId: $userId
);

// Invite members
$orgManager->inviteMember(
    organizationId: $org->id,
    email: 'member@example.com',
    role: 'admin'
);

// Accept invitation
$orgManager->acceptInvitation($token);
```

## Testing

```bash
# Run tests
composer test

# Run PHPStan
composer phpstan

# Run code style fixer
composer cs-fix
```

## Documentation

- [Installation Guide](https://github.com/betterauth/betterauth-core/wiki/Installation)
- [API Reference](https://github.com/betterauth/betterauth-core/wiki/API-Reference)
- [Security Best Practices](https://github.com/betterauth/betterauth-core/wiki/Security)
- [UUID v7 vs INT](https://github.com/betterauth/betterauth-core/wiki/UUID-vs-INT)

## Requirements

- PHP 8.2 or higher
- ext-json
- ext-openssl
- ramsey/uuid ^4.7
- paragonie/paseto ^3.1

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email security@betterauth.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [BetterAuth Team](https://github.com/betterauth)
- [All Contributors](https://github.com/betterauth/betterauth-core/contributors)
