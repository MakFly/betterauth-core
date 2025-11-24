# BetterAuth Core

[![CI](https://github.com/MakFly/betterauth-core/actions/workflows/tests.yml/badge.svg?label=CI)](https://github.com/MakFly/betterauth-core/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/betterauth/core?label=stable)](https://packagist.org/packages/betterauth/core)
[![Total Downloads](https://img.shields.io/packagist/dt/betterauth/core?label=downloads)](https://packagist.org/packages/betterauth/core)
[![License](https://img.shields.io/packagist/l/betterauth/core?label=license)](https://github.com/MakFly/betterauth-core/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/betterauth/core?label=php)](https://packagist.org/packages/betterauth/core)

Framework-agnostic authentication library for PHP 8.2+.

## ✨ Features

- 🔐 **Multiple authentication methods**: Email/Password, Magic Link, OAuth, Passkeys (WebAuthn), TOTP
- 🌍 **OAuth Providers**: Google, GitHub, Facebook, Microsoft, Discord
- 👥 **Multi-tenant capabilities**: Organizations, Teams, Members, Invitations with RBAC
- 🔒 **Secure by default**: Paseto V4 tokens, Argon2id hashing
- 💾 **Multiple storage adapters**: PDO, Doctrine
- 🎯 **Framework-agnostic core**: Use with any PHP framework
- 🆔 **UUID v7 support**: Time-ordered, non-guessable IDs
- 🔌 **Plugin system**: Extensible architecture
- 📊 **Security audit trail**: Events logging & monitoring

## 📦 Installation

```bash
composer require betterauth/core
```

## 🚀 Framework Integrations

BetterAuth Core is framework-agnostic with official integrations:

- **Symfony** (✅ Production Ready): [`betterauth/symfony-bundle`](https://github.com/MakFly/betterauth-symfony)
- **Vanilla PHP**: Use this package directly with PDO storage adapters

## 🔧 Requirements

- PHP 8.2 or higher
- ext-json
- ext-openssl
- ramsey/uuid ^4.7
- paragonie/paseto ^3.1

## 🚀 Quick Start (Vanilla PHP with PDO)

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

## 💾 Storage Adapters

### PDO (Vanilla PHP)

```php
use BetterAuth\Storage\Pdo\PdoUserRepository;
use BetterAuth\Storage\Pdo\PdoSessionRepository;

$pdo = new PDO('mysql:host=localhost;dbname=auth', 'user', 'password');
$userRepo = new PdoUserRepository($pdo);
$sessionRepo = new PdoSessionRepository($pdo);
```

### Doctrine (Symfony)

Use the [`betterauth/symfony-bundle`](https://github.com/MakFly/betterauth-symfony) which provides Doctrine integration.

## 🔑 Authentication Providers

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

## 🔒 Security Features

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

## ⚙️ Configuration

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

## 👥 Multi-Tenancy

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

## 🧪 Testing

```bash
# Run PHPUnit tests
composer test

# Run PHPStan static analysis
composer phpstan

# Run Behat BDD scenarios
vendor/bin/behat

# Run code style fixer
composer cs-fix
```

## 📊 CI/CD

BetterAuth Core includes comprehensive CI/CD with GitHub Actions:

- ✅ PHPUnit tests (PHP 8.2, 8.3, 8.4)
- ✅ PHPStan static analysis (level 5)
- ✅ Security checks (Composer audit + Symfony security checker)
- ✅ Behat BDD scenarios
- ✅ Code quality checks (PHP CS Fixer)

All tests run on every push and pull request. View the [latest CI results](https://github.com/MakFly/betterauth-core/actions).

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 🔒 Security

If you discover any security-related issues, please create an issue on [GitHub](https://github.com/MakFly/betterauth-core/issues) with the `security` label.

## 📄 License

The MIT License (MIT). Please see [LICENSE](LICENSE) file for details.

## 🔗 Links

- **Packagist**: https://packagist.org/packages/betterauth/core
- **GitHub**: https://github.com/MakFly/betterauth-core
- **Issues**: https://github.com/MakFly/betterauth-core/issues
- **Symfony Bundle**: https://github.com/MakFly/betterauth-symfony

## 🙏 Credits

- [BackToTheFutur Team](https://github.com/MakFly/betterauth-core/contributors)
- All the amazing people who contribute to open source

---

Made with ❤️ by the BackToTheFutur Team
