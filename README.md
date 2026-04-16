# Token Bundle

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A Symfony bundle for managing secure, typed, and revocable tokens attached to any entity — for password resets, email verification, share links, and more.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Core Features](#core-features)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Making an Entity a Token Subject](#making-an-entity-a-token-subject)
  - [Creating a Token](#creating-a-token)
  - [Consuming a Token](#consuming-a-token)
  - [Revoking Tokens](#revoking-tokens)
  - [Finding a Valid Token](#finding-a-valid-token)
- [Events](#events)
- [Exceptions](#exceptions)
- [Console Command](#console-command)
- [Development](#development)

---

## Requirements

- PHP **≥ 8.3**
- Symfony **≥ 7.0**
- Doctrine ORM **≥ 3.0**

---

## Installation

```bash
composer require ecourty/token-bundle
```

Register the bundle in `config/bundles.php` (if not using Symfony Flex):

```php
return [
    // ...
    Ecourty\TokenBundle\TokenBundle::class => ['all' => true],
];
```

Create the `tokens` table with a Doctrine migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

> The bundle automatically registers its Doctrine entity mapping — no manual configuration required.

---

## Core Features

- **Typed tokens** — each token has a `type` (e.g. `password_reset`, `email_verify`, `share`)
- **Any entity as subject** — attach a token to any Doctrine entity via `TokenSubjectInterface`
- **Expiration** — every token requires an expiry date (no permanent tokens)
- **Single-use** — tokens can be flagged as single-use, automatically consumed after first use
- **Max-uses** — tokens can be limited to N uses, auto-consumed when the limit is reached
- **JSON payload** — attach arbitrary data to any token
- **Revocation** — revoke individual tokens or all tokens for a subject (optionally filtered by type)
- **Event-driven** — hook into `TokenCreatedEvent`, `TokenConsumedEvent`, `TokenRevokedEvent`
- **Purge command** — `token:purge` to clean up expired, consumed, and revoked tokens
- **Race-safe** — atomic increment for multi-use tokens prevents overconsumption

---

## Configuration

```yaml
# config/packages/token.yaml
token:
    token_length: 64  # default: 64, minimum: 16
```

| Option         | Type  | Default | Description                                     |
|----------------|-------|---------|-------------------------------------------------|
| `token_length` | `int` | `64`    | Length of the generated token string (min: `16`) |

---

## Usage

### Making an Entity a Token Subject

Any Doctrine entity can become a token subject by implementing `TokenSubjectInterface`:

```php
use Ecourty\TokenBundle\Contract\TokenSubjectInterface;

class User implements TokenSubjectInterface
{
    public function getTokenSubjectId(): string
    {
        return (string) $this->id;
    }
}
```

### Creating a Token

Inject `TokenManager` and call `create()`:

```php
use Ecourty\TokenBundle\Service\TokenManager;

class PasswordResetService
{
    public function __construct(private TokenManager $tokenManager) {}

    public function sendResetLink(User $user): void
    {
        $token = $this->tokenManager->create(
            type: 'password_reset',
            subject: $user,
            expiresIn: '+1 hour',
            singleUse: true,
        );

        // $token->getToken() — the secure random string to include in a reset link
    }
}
```

**With payload and max-uses:**

```php
$token = $this->tokenManager->create(
    type: 'share',
    subject: $document,
    expiresIn: '+7 days',
    singleUse: false,
    maxUses: 10,
    payload: ['permissions' => ['read']],
);
```

### Consuming a Token

```php
use Ecourty\TokenBundle\Exception\TokenAlreadyConsumedException;
use Ecourty\TokenBundle\Exception\TokenExpiredException;
use Ecourty\TokenBundle\Exception\TokenMaxUsesReachedException;
use Ecourty\TokenBundle\Exception\TokenNotFoundException;
use Ecourty\TokenBundle\Exception\TokenRevokedException;

public function resetPassword(string $tokenString, string $newPassword): void
{
    try {
        $token = $this->tokenManager->consume($tokenString, 'password_reset');
        // Token is valid — proceed with password reset
        // $token->getSubjectId() gives you the user ID
    } catch (TokenNotFoundException) {
        // Token does not exist or wrong type
    } catch (TokenExpiredException) {
        // Token has expired
    } catch (TokenRevokedException) {
        // Token was manually revoked
    } catch (TokenAlreadyConsumedException) {
        // Single-use token already used
    } catch (TokenMaxUsesReachedException) {
        // Max uses reached
    }
}
```

> **Tip:** All token exceptions extend `AbstractTokenException` (a `RuntimeException`), so you can catch them all at once if needed.

### Revoking Tokens

```php
// Revoke a specific token by its string value
$this->tokenManager->revoke($tokenString);

// Revoke all password_reset tokens for a user
$count = $this->tokenManager->revokeAll($user, 'password_reset');

// Revoke ALL tokens for a subject regardless of type
$count = $this->tokenManager->revokeAll($user);
```

### Finding a Valid Token

Returns the first valid (not expired, not consumed, not revoked, not at max uses) token for the given subject and type:

```php
$token = $this->tokenManager->findValid($user, 'password_reset');

if ($token === null) {
    // No valid token exists — create a new one
}
```

---

## Events

The bundle dispatches Symfony events on token lifecycle actions:

| Event                | Dispatched when                      | Extra properties     |
|----------------------|--------------------------------------|----------------------|
| `TokenCreatedEvent`  | After a token is created & persisted | `$createdAt`         |
| `TokenConsumedEvent` | After a token is successfully consumed | `$consumedAt`      |
| `TokenRevokedEvent`  | After a single token is revoked via `revoke()` | `$revokedAt` |

> **Note:** `revokeAll()` performs a bulk SQL `UPDATE` for performance and does **not** dispatch individual `TokenRevokedEvent` per token.

All events carry the `Token` entity via `$event->token`.

**Example listener:**

```php
use Ecourty\TokenBundle\Event\TokenCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class TokenCreatedListener
{
    public function __invoke(TokenCreatedEvent $event): void
    {
        // e.g. log, send notification, audit trail...
    }
}
```

---

## Exceptions

All exceptions extend `AbstractTokenException` (`RuntimeException`):

| Exception                       | Thrown when                                  |
|---------------------------------|----------------------------------------------|
| `TokenNotFoundException`        | Token string not found or type mismatch      |
| `TokenExpiredException`         | Token has expired                            |
| `TokenRevokedException`        | Token was revoked                            |
| `TokenAlreadyConsumedException` | Single-use token already consumed            |
| `TokenMaxUsesReachedException`  | Token has reached its maximum number of uses |

---

## Console Command

```bash
# Purge all expired, consumed, and revoked tokens
php bin/console token:purge

# Preview what would be deleted without actually deleting
php bin/console token:purge --dry-run

# Purge only tokens of a specific type
php bin/console token:purge --type=password_reset

# Only purge tokens that expired before a given date
php bin/console token:purge --before="2026-01-01"
php bin/console token:purge --before="-30 days"
```

---

## Development

```bash
composer install

# Run all tests
composer test

# Run specific test suites
composer test-unit
composer test-integration
composer test-functional

# Static analysis (PHPStan, level max)
composer phpstan

# Code style (PHP CS Fixer)
composer cs-fix       # fix
composer cs-check     # dry-run check

# Full QA pipeline (PHPStan + CS check + tests)
composer qa
```

---

## License

This bundle is released under the [MIT License](LICENSE).
