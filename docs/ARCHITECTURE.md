# BetterAuth Architecture - Authentication Managers

## Overview

BetterAuth provides **three authentication managers** with clear responsibilities to avoid confusion:

```
AuthManager (Facade)
    ├── SessionAuthManager (Stateful)
    └── TokenAuthManager (Stateless)
```

## Architecture Explained

### 1. SessionAuthManager - Stateful Authentication

**Purpose**: Traditional session-based authentication for web applications

**Use Case**:
- Traditional web apps with cookies
- Server-side rendered pages
- Monolithic applications

**How it works**:
- Creates sessions stored in database
- Returns `{user, session}` on login
- Session token stored in cookie
- Server validates session on each request

**Example**:
```php
use BetterAuth\Core\SessionAuthManager;

$sessionAuth = new SessionAuthManager($userRepo, $sessionService, $passwordHasher);

// Login
$result = $sessionAuth->signIn($email, $password, $ip, $userAgent);
// Returns: ['user' => User, 'session' => Session]

// Get current user
$user = $sessionAuth->getCurrentUser($sessionToken);

// Logout
$sessionAuth->signOut($sessionToken);
```

### 2. TokenAuthManager - Stateless Authentication

**Purpose**: Token-based authentication for APIs and microservices

**Use Case**:
- REST APIs
- Single Page Applications (SPAs)
- Mobile apps
- Microservices

**How it works**:
- Creates JWT/Paseto tokens (access + refresh)
- No server-side session storage
- Client stores tokens
- Server validates token signature on each request

**Example**:
```php
use BetterAuth\Core\TokenAuthManager;

$tokenAuth = new TokenAuthManager($userRepo, $refreshTokenRepo, $tokenService, $passwordHasher, $config);

// Login
$result = $tokenAuth->signIn($email, $password);
// Returns: ['user' => User, 'access_token' => string, 'refresh_token' => string, 'expires_in' => int]

// Verify token
$user = $tokenAuth->verify($accessToken);

// Refresh access token
$newTokens = $tokenAuth->refresh($refreshToken);

// Logout all devices
$tokenAuth->revokeAllTokens($userId);
```

### 3. AuthManager - Unified Facade

**Purpose**: Automatic delegation to the right manager based on configuration

**Use Case**:
- When you want to switch between modes easily
- When building framework integrations (Laravel, Symfony)
- When you need both session and token auth in the same app

**How it works**:
- Detects mode from `AuthConfig` (session vs api)
- Delegates to `SessionAuthManager` or `TokenAuthManager`
- Provides unified API

**Example**:
```php
use BetterAuth\Core\AuthManager;

$config = AuthConfig::forMonolith($secret); // or forApi($secret)
$auth = new AuthManager($config, $sessionAuth, $tokenAuth);

// Same API for both modes
$result = $auth->signIn($email, $password, $ip, $userAgent);

// Access underlying managers
$auth->session(); // Get SessionAuthManager
$auth->token();   // Get TokenAuthManager

// Check mode
$auth->isSessionMode(); // true or false
$auth->isApiMode();     // true or false
```

## Framework Integration

### Laravel

```php
// In a controller - automatic mode detection
public function __construct(AuthManager $auth) {
    $this->auth = $auth;
}

// Or explicit manager
public function __construct(SessionAuthManager $auth) {
    $this->auth = $auth; // Explicit session mode
}

public function __construct(TokenAuthManager $auth) {
    $this->auth = $auth; // Explicit token mode
}

// Using facade
use BetterAuth\Laravel\Facades\BetterAuth;

$result = BetterAuth::signIn($email, $password, $ip, $userAgent);
```

### Symfony

```php
// services.yaml - all three managers available
services:
    BetterAuth\Core\SessionAuthManager: ~
    BetterAuth\Core\TokenAuthManager: ~
    BetterAuth\Core\AuthManager: ~

// In a controller
public function __construct(
    private AuthManager $auth
) {}

// Or explicit
public function __construct(
    private TokenAuthManager $tokenAuth,
    private SessionAuthManager $sessionAuth
) {}
```

## Decision Tree: Which Manager to Use?

```
┌─────────────────────────────────────┐
│ What are you building?              │
└──────────────┬──────────────────────┘
               │
       ┌───────┴────────┐
       │                │
   ┌───▼────┐      ┌────▼────┐
   │ Web App│      │   API   │
   │ (HTML) │      │  (JSON) │
   └───┬────┘      └────┬────┘
       │                │
       │                │
   ┌───▼────────────┐ ┌─▼──────────────┐
   │SessionAuth     │ │TokenAuth       │
   │Manager         │ │Manager         │
   │                │ │                │
   │- Cookies       │ │- JWT/Paseto    │
   │- Sessions      │ │- Stateless     │
   │- Server state  │ │- Mobile-friendly│
   └────────────────┘ └────────────────┘

       ┌────────────────────────┐
       │ Both? Framework?       │
       │ Want flexibility?      │
       └───────────┬────────────┘
                   │
            ┌──────▼──────────┐
            │  AuthManager    │
            │  (Facade)       │
            │                 │
            │  Auto-delegates │
            │  based on mode  │
            └─────────────────┘
```

## Key Benefits

### 1. **Clarity**
- Names explicitly state the mode: `SessionAuthManager` vs `TokenAuthManager`
- No confusion about which to inject

### 2. **Flexibility**
- Use managers directly for explicit control
- Use `AuthManager` for automatic delegation

### 3. **Type Safety**
- IDEs autocomplete shows correct methods
- Type hints prevent mistakes

### 4. **Separation of Concerns**
- Session logic in `SessionAuthManager`
- Token logic in `TokenAuthManager`
- Delegation logic in `AuthManager`

## Migration Guide

### From Old Code

If you had:
```php
use BetterAuth\Core\ApiAuthManager; // ❌ OLD
```

Change to:
```php
use BetterAuth\Core\TokenAuthManager; // ✅ NEW
```

If you had:
```php
use BetterAuth\Providers\AuthManager\AuthManager; // ❌ OLD (didn't exist)
```

Change to:
```php
use BetterAuth\Core\SessionAuthManager; // ✅ NEW
// or
use BetterAuth\Core\AuthManager; // ✅ NEW (facade)
```

## Summary

| Manager | Mode | Returns | Use Case |
|---------|------|---------|----------|
| **SessionAuthManager** | Stateful | `{user, session}` | Web apps with cookies |
| **TokenAuthManager** | Stateless | `{user, access_token, refresh_token}` | APIs, SPAs, mobile |
| **AuthManager** | Both | Depends on mode | Automatic delegation |

**Rule of thumb**:
- Building an API? → `TokenAuthManager`
- Building a web app? → `SessionAuthManager`
- Need flexibility or both? → `AuthManager`
- Using Laravel/Symfony? → `AuthManager` (auto-configured)
