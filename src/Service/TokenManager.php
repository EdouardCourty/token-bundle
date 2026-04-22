<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Ecourty\TokenBundle\Contract\TokenSubjectInterface;
use Ecourty\TokenBundle\Entity\Token;
use Ecourty\TokenBundle\Event\TokenConsumedEvent;
use Ecourty\TokenBundle\Event\TokenCreatedEvent;
use Ecourty\TokenBundle\Event\TokenRevokedEvent;
use Ecourty\TokenBundle\Exception\TokenAlreadyConsumedException;
use Ecourty\TokenBundle\Exception\TokenExpiredException;
use Ecourty\TokenBundle\Exception\TokenMaxUsesReachedException;
use Ecourty\TokenBundle\Exception\TokenNotFoundException;
use Ecourty\TokenBundle\Exception\TokenRevokedException;
use Ecourty\TokenBundle\Repository\TokenRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TokenManager
{
    public function __construct(
        private readonly TokenRepository $tokenRepository,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly int $tokenLength,
    ) {
    }

    private static function mask(string $tokenString): string
    {
        $reveal = max(1, min(8, intdiv(\strlen($tokenString), 4)));

        return substr($tokenString, 0, $reveal) . '…';
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function create(
        string $type,
        TokenSubjectInterface $subject,
        string $expiresIn,
        bool $singleUse = false,
        ?int $maxUses = null,
        ?array $payload = null,
    ): Token {
        if ($maxUses !== null && $maxUses < 1) {
            throw new \InvalidArgumentException(\sprintf('maxUses must be at least 1, got %d.', $maxUses));
        }

        if ($singleUse && $maxUses !== null) {
            throw new \InvalidArgumentException('Cannot set both singleUse and maxUses. A single-use token is inherently limited to one use.');
        }

        $byteLength = max(1, (int) ceil($this->tokenLength / 2));
        $tokenString = substr(bin2hex(random_bytes($byteLength)), 0, $this->tokenLength);

        $expiresAt = new \DateTimeImmutable($expiresIn);

        $token = new Token(
            type: $type,
            token: $tokenString,
            subjectType: $subject::class,
            subjectId: $subject->getTokenSubjectId(),
            expiresAt: $expiresAt,
            singleUse: $singleUse,
            maxUses: $maxUses,
            payload: $payload,
        );

        $this->em->persist($token);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new TokenCreatedEvent($token));

        return $token;
    }

    /**
     * Retrieves a token by its string value and type, and validates that it is still usable.
     *
     * @throws TokenNotFoundException        if the token does not exist or the type does not match
     * @throws TokenRevokedException         if the token has been revoked
     * @throws TokenExpiredException         if the token has expired
     * @throws TokenAlreadyConsumedException if the token has already been consumed
     * @throws TokenMaxUsesReachedException  if the token has reached its maximum number of uses
     */
    public function get(string $tokenString, string $type): Token
    {
        $token = $this->tokenRepository->findByTokenStringAndType($tokenString, $type);

        if ($token === null) {
            throw new TokenNotFoundException(\sprintf('Token "%s" of type "%s" not found.', self::mask($tokenString), $type));
        }

        $this->validateToken($token);

        return $token;
    }

    /**
     * @throws TokenRevokedException
     * @throws TokenExpiredException
     * @throws TokenAlreadyConsumedException
     * @throws TokenMaxUsesReachedException
     */
    private function validateToken(Token $token): void
    {
        $masked = self::mask($token->getToken());

        if ($token->isRevoked()) {
            throw new TokenRevokedException(\sprintf('Token "%s" has been revoked.', $masked));
        }

        if ($token->isExpired()) {
            throw new TokenExpiredException(\sprintf('Token "%s" has expired.', $masked));
        }

        if ($token->isMaxUsesReached()) {
            throw new TokenMaxUsesReachedException(\sprintf('Token "%s" has reached its maximum number of uses.', $masked));
        }

        if ($token->isConsumed()) {
            throw new TokenAlreadyConsumedException(\sprintf('Token "%s" has already been consumed.', $masked));
        }
    }

    public function consume(string|Token $tokenOrString, ?string $type = null): Token
    {
        if ($tokenOrString instanceof Token) {
            $token = $tokenOrString;
            $this->validateToken($token);
        } else {
            if ($type === null) {
                throw new \InvalidArgumentException('The $type argument is required when $tokenOrString is a string.');
            }
            $token = $this->get($tokenOrString, $type);
        }

        $tokenString = $token->getToken();

        if ($token->isSingleUse()) {
            $consumed = $this->tokenRepository->atomicConsumeSingleUse($token);
            if (!$consumed) {
                $this->em->refresh($token);
                $this->validateToken($token);
                throw new TokenAlreadyConsumedException(\sprintf('Token "%s" has already been consumed.', self::mask($tokenString)));
            }
        } elseif ($token->getMaxUses() !== null) {
            $incremented = $this->tokenRepository->atomicIncrementUseCount($token);
            if (!$incremented) {
                $this->em->refresh($token);
                $this->validateToken($token);
                throw new TokenMaxUsesReachedException(\sprintf('Token "%s" has reached its maximum number of uses.', self::mask($tokenString)));
            }
        } else {
            $incremented = $this->tokenRepository->atomicIncrementUseCount($token);
            if (!$incremented) {
                $this->em->refresh($token);
                $this->validateToken($token);
                throw new \LogicException(\sprintf('Token "%s" could not be incremented despite passing validation; the DB state may be inconsistent.', self::mask($tokenString)));
            }
        }

        // Refresh from DB to sync Doctrine's UnitOfWork after the atomic DQL UPDATE,
        // preventing stale overwrites on subsequent flush.
        $this->em->refresh($token);

        $this->eventDispatcher->dispatch(
            new TokenConsumedEvent($token, $token->getConsumedAt() ?? new \DateTimeImmutable()),
        );

        return $token;
    }

    public function revoke(string $tokenString): void
    {
        $token = $this->tokenRepository->findByTokenString($tokenString);

        if ($token === null) {
            throw new TokenNotFoundException(\sprintf('Token "%s" not found.', self::mask($tokenString)));
        }

        if ($token->isRevoked()) {
            return;
        }

        $token->markRevoked();
        $this->em->flush();

        $this->eventDispatcher->dispatch(new TokenRevokedEvent($token));
    }

    /**
     * Revokes all matching tokens via a bulk DQL UPDATE for performance.
     * Note: no TokenRevokedEvent is dispatched for bulk revocations.
     */
    public function revokeAll(TokenSubjectInterface $subject, ?string $type = null): int
    {
        return $this->tokenRepository->revokeAllBySubject($subject, $type);
    }

    public function findValid(TokenSubjectInterface $subject, string $type): ?Token
    {
        return $this->tokenRepository->findValidBySubjectAndType($subject, $type);
    }

    /**
     * Resolves the subject entity from a token using Doctrine.
     *
     * @return TokenSubjectInterface|null the subject entity, or null if not found
     */
    public function resolveSubject(Token $token): ?TokenSubjectInterface
    {
        /** @var class-string $subjectType */
        $subjectType = $token->getSubjectType();

        $entity = $this->em->find($subjectType, $token->getSubjectId());

        if ($entity instanceof TokenSubjectInterface) {
            return $entity;
        }

        return null;
    }
}
