# AGENTS.md - Coding Guidelines for AI Agents

## 🎯 Core Concept

**Token Bundle** (`ecourty/token-bundle`) is a Symfony bundle for managing secure, typed, and revocable tokens attached to any entity.

### Problem Solved

In most Symfony projects, developers repeatedly implement ad-hoc token systems for password resets, email verification, share links, and resource access. There is no generic, reusable solution that handles expiration, revocation, payload, and multi-use logic cleanly.

### Solution

A Doctrine-backed token system where any entity can become a **subject** of a token by implementing `TokenSubjectInterface`. Tokens are typed, carry an arbitrary JSON payload, support single-use or max-use limits, and can be revoked individually or in bulk.

---

## 🏗️ Architecture

### Overview

The bundle stores tokens in a single Doctrine-managed table. A `TokenManager` service handles all creation, consumption, and revocation logic, dispatching Symfony events on each action.

### Main Components

- **`Token` entity** — Doctrine entity stored in `tokens` table with the following fields:
  - `id` (integer, auto-increment), `type` (string), `token` (unique string)
  - `subject_type` (FQCN), `subject_id` (string)
  - `payload` (json, nullable), `single_use` (bool)
  - `max_uses` (nullable int), `use_count` (int, default 0)
  - `expires_at` (datetime, **required** — no permanent tokens), `consumed_at` (nullable datetime)
  - `revoked_at` (nullable datetime), `created_at` (datetime)
  - Composite index on `(subject_type, subject_id, type)` for efficient lookups

- **`TokenSubjectInterface`** — Implemented by any entity that can be a token subject. Single method: `getTokenSubjectId(): string`. The subject FQCN + ID are stored to identify the entity.

- **`TokenManager`** — Main service. Methods:
  - `create(string $type, TokenSubjectInterface $subject, string $expiresIn, bool $singleUse, ?int $maxUses, ?array $payload): Token`
  - `get(string $tokenString, string $type): Token`
  - `consume(string|Token $tokenOrString, ?string $type = null): Token`
  - `revoke(string $tokenString): void`
  - `revokeAll(TokenSubjectInterface $subject, ?string $type): int`
  - `findValid(TokenSubjectInterface $subject, string $type): ?Token`
  - `resolveSubject(Token $token): ?TokenSubjectInterface`

- **Events** (dispatched via Symfony EventDispatcher):
  - `TokenCreatedEvent`
  - `TokenConsumedEvent`
  - `TokenRevokedEvent`

- **Console Command** — `php bin/console token:purge` — deletes expired and consumed tokens. Options: `--dry-run`, `--type=<type>`, `--before=<date>` (date cutoff for expired tokens, e.g. `-30 days`).

- **Bundle configuration** (`config/packages/token.yaml`):
  ```yaml
  token:
    token_length: 64  # min: 16
  ```

---

## 🚀 Typical Use Cases

- **Password reset** — single-use token, 1-hour TTL, sent by email
- **Email verification** — single-use token, 24-hour TTL
- **Share link** — multi-use token attached to a `Document` entity, no TTL or fixed expiry
- **Resource access** — token with `max_uses: 10` giving limited access to a private resource
- **Temporary API access** — token with payload carrying permissions

---

## 💡 Design Patterns Used

- **Interface segregation** — `TokenSubjectInterface` decouples any Doctrine entity from the bundle without inheritance
- **Event-driven** — all side effects (logging, emails, alerts) are handled via Symfony events, not inline
- **Decorator-friendly** — `TokenManager` can be decorated for custom behavior

---

## Project breakdown

```
src/
  Entity/
    Token.php                  # Doctrine entity (table: tokens)
  Contract/
    TokenSubjectInterface.php  # Interface for subject entities
  Repository/
    TokenRepository.php        # Queries, atomic increment, bulk revoke, purge
  Service/
    TokenManager.php           # Core service (create/consume/revoke/findValid)
  Exception/
    AbstractTokenException.php # Base exception (extends RuntimeException)
    TokenNotFoundException.php
    TokenExpiredException.php
    TokenAlreadyConsumedException.php
    TokenRevokedException.php
    TokenMaxUsesReachedException.php
  Event/
    AbstractTokenEvent.php     # Base event (carries Token)
    TokenCreatedEvent.php
    TokenConsumedEvent.php
    TokenRevokedEvent.php
  Command/
    PurgeTokensCommand.php     # token:purge (--dry-run, --type)
  DependencyInjection/
    Configuration.php          # token_length config node
    TokenExtension.php         # PrependExtensionInterface for Doctrine mapping
  Resources/
    config/
      services.php
  TokenBundle.php
```

```
tests/
  App/
    Entity/TestUser.php        # Fixture entity implementing TokenSubjectInterface
    TestKernel.php             # Minimal test kernel (Framework + Doctrine + TokenBundle, no hacks)
    bin/console
    config/
      services.php             # DI defaults only (no compiler pass, no public overrides)
      packages/
        framework.php
        doctrine.php           # SQLite in-memory + native lazy objects
      routes.php
  Unit/
    Entity/TokenTest.php
  Integration/
    IntegrationTestCase.php    # Base class: boot kernel, wire TokenRepository directly, create schema, teardown
    Repository/TokenRepositoryTest.php
    Service/TokenManagerTest.php
  Functional/
    Command/PurgeTokensCommandTest.php
```

**IMPORTANT**: This section should evolve with the project. When a new feature is created, updated or removed, this section should too.

## 🧪 Testing

This bundle should be covered by unit, integration and functional tests.
The tests are located in the `tests/{Unit|Integration|Functional}` folder.
Unit tests can use mocks or stubs if needed.

### Testing private bundle services

Symfony's compiler inlines private services with a single consumer (e.g. `TokenManager` is only consumed by `PurgeTokensCommand`). Once inlined, these services are inaccessible via the test container — even with `framework.test: true`. Do NOT add a compiler pass to the test kernel to work around this.

Instead, **instantiate bundle services directly in integration tests**:
- `EntityManagerInterface` and `ManagerRegistry` are public Doctrine services — get them from the container.
- `TokenRepository` is constructed with `ManagerRegistry` directly.
- `TokenManager` is instantiated with its dependencies, using a dedicated `EventDispatcher` instance so tests can track dispatched events.

This mirrors exactly how a real application wires these services via DI.

---

## Remarks & Guidelines

### General

- NEVER commit or push the git repository.
- When unsure about something, you MUST ask the user for clarification. Same goes it the user request is unclear.
- When facing a problem that has an easy "hacky" solution, and a more robust but more difficult to implement one, always choose the robust one:
  - Easy hacky fixes become technical debt, and can lead to issues down the road
  - Robust solutions means the project will remain serious and well-built.
- ALWAYS write tests for the important components. Better safe than sorry!
- Do NOT write ANY type documentation unless explicitly asked.
- Once a feature is complete, update the @README.md and @AGENTS.md accordingly.
- The @README.md file should consist of a project overview for end-users, not a technical explanation of the project. It should include:
  - Table of contents
  - Quick start / Installation
  - Core features
  - Configuration reference
  - Usage
  - Development / Contribution guidelines

### Symfony Bundles

- Symfony bundles are meant to be re-used and integrated in other Symfony projects. When developing features, keep this in mind.  
- Architecture, naming, design, extensibility and easiness to install and use should be key priorities to consider when developing this project.

## 📚 References

- **Source code**: `/src`
- **Tests**: `/tests`
- **README**: User documentation
- **Symfony Docs**: https://symfony.com/doc/current/bundles.html

