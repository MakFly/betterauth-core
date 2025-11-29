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
    tokenLifetime: 3600, // 1 hour
    refreshLifetime: 2592000 // 30 days
);

// Create auth manager
$auth = new TokenAuthManager($config, $userRepo);

// Sign in (user must be created via SessionAuthManager or directly via repository)
$result = $auth->signIn(
    email: 'user@example.com',
    password: 'SecurePassword123'
);

// Access tokens
$accessToken = $result['access_token'];
$refreshToken = $result['refresh_token'];

// Verify token and get user
$payload = $auth->verify($accessToken);
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
use BetterAuth\Core\SessionAuthManager;

// For registration, use SessionAuthManager
$sessionAuth = new SessionAuthManager($config, $userRepo, $sessionRepo);

$result = $sessionAuth->signUp(
    email: 'user@example.com',
    password: 'SecurePassword123',
    username: 'John Doe',
    ipAddress: $_SERVER['REMOTE_ADDR'],
    userAgent: $_SERVER['HTTP_USER_AGENT']
);

// For API authentication, use TokenAuthManager
$tokenAuth = new TokenAuthManager($config, $userRepo, $refreshTokenRepo);

$result = $tokenAuth->signIn(
    email: 'user@example.com',
    password: 'SecurePassword123'
);
// Returns: ['user' => User, 'access_token' => '...', 'refresh_token' => '...', 'expires_in' => 3600]
```

### OAuth 2.0

> **Note:** Only Google OAuth is fully tested and production-ready (`[STABLE]`). Other providers (GitHub, Facebook, Microsoft, Discord) are in `[DRAFT]` status.

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

// Handle callback - creates or updates user automatically
$result = $oauth->handleCallback('google', $_GET['code']);
// Returns: ['user' => User, 'isNewUser' => bool, 'providerUser' => ProviderUser]
```

### Magic Link

```php
use BetterAuth\Providers\MagicLinkProvider\MagicLinkProvider;

$magicLink = new MagicLinkProvider($config, $userRepo, $magicLinkStorage, $emailSender);

// Create magic link token
$token = $magicLink->createToken('user@example.com');
// Token is automatically sent via configured EmailSender

// Verify magic link and authenticate user
$result = $magicLink->verify($token);
// Returns: ['user' => User, 'session' => Session] or tokens depending on mode
```

### TOTP (Two-Factor Authentication)

```php
use BetterAuth\Providers\TotpProvider\TotpProvider;

$totp = new TotpProvider($config, $totpStorage);

// Generate secret and QR code for user
$result = $totp->generateSecret($userId, 'user@example.com');
// Returns: [
//   'secret' => 'JBSWY3DPEHPK3PXP',
//   'qrCode' => 'data:image/png;base64,...',
//   'qrCodeUrl' => 'otpauth://totp/...'
// ]

// Enable TOTP after user confirms with first code
$totp->enable($userId, $result['secret'], '123456');

// Verify TOTP code during login
$isValid = $totp->verify($userId, '123456');
```

### Passkeys (WebAuthn)

```php
use BetterAuth\Providers\PasskeyProvider\PasskeyProvider;

$passkey = new PasskeyProvider($config, $passkeyStorage);

// Register passkey - Step 1: Generate options
$options = $passkey->generateRegistrationOptions($userId, 'user@example.com');
// Send $options to client (JavaScript WebAuthn API)

// Register passkey - Step 2: Verify client response
$passkey->verifyRegistration($userId, $clientResponse);

// Authenticate - Step 1: Generate options
$options = $passkey->generateAuthenticationOptions($userId);
// Send $options to client

// Authenticate - Step 2: Verify and get user
$result = $passkey->verifyAuthentication($clientResponse);
// Returns: ['user' => User, 'credentialId' => '...']
```

## 🔒 Security Features

### Token Management

BetterAuth uses **Paseto V4** tokens (encrypted, authenticated):

```php
// Access token (short-lived)
$accessToken = $result['access_token']; // Valid for 1 hour (default)

// Refresh token (long-lived)
$refreshToken = $result['refresh_token']; // Valid for 30 days

// Refresh access token
$newResult = $auth->refresh($refreshToken);
// Returns new access_token and optionally rotated refresh_token
```

### Password Hashing

Passwords are hashed using **Argon2id** (memory-hard, resistant to GPU attacks):

```php
// Automatic during signUp
$result = $sessionAuth->signUp(
    email: 'user@example.com',
    password: 'SecurePassword123', // Hashed with Argon2id
    ipAddress: $_SERVER['REMOTE_ADDR'],
    userAgent: $_SERVER['HTTP_USER_AGENT']
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
    tokenLifetime: 3600,        // Access token: 1 hour (default)
    refreshLifetime: 2592000,   // Refresh token: 30 days
    passwordMinLength: 8,
    requireEmailVerification: true,
    enableDeviceTrust: true,
    enableSecurityNotifications: true
);
```

### Rate Limiting

BetterAuth includes built-in rate limiting protection (enabled by default):

- **Max attempts:** 5 per IP/email combination
- **Decay time:** 300 seconds (5 minutes)

```php
// Rate limiting is automatically enforced on signIn attempts
// After 5 failed attempts, the user must wait 5 minutes
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
