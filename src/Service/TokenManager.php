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
            throw new TokenNotFoundException(\sprintf('Token "%s" of type "%s" not found.', $tokenString, $type));
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
        $tokenString = $token->getToken();

        if ($token->isRevoked()) {
            throw new TokenRevokedException(\sprintf('Token "%s" has been revoked.', $tokenString));
        }

        if ($token->isExpired()) {
            throw new TokenExpiredException(\sprintf('Token "%s" has expired.', $tokenString));
        }

        if ($token->isConsumed()) {
            throw new TokenAlreadyConsumedException(\sprintf('Token "%s" has already been consumed.', $tokenString));
        }

        if ($token->isMaxUsesReached()) {
            throw new TokenMaxUsesReachedException(\sprintf('Token "%s" has reached its maximum number of uses.', $tokenString));
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
            $token->markConsumed();
            $this->em->flush();
        } elseif ($token->getMaxUses() !== null) {
            $willFillUp = ($token->getUseCount() + 1) >= $token->getMaxUses();
            $consumedAt = $willFillUp ? new \DateTimeImmutable() : null;

            $incremented = $this->tokenRepository->atomicIncrementUseCount($token, $consumedAt);

            if (!$incremented) {
                throw new TokenMaxUsesReachedException(\sprintf('Token "%s" has reached its maximum number of uses.', $tokenString));
            }
        }

        $this->eventDispatcher->dispatch(new TokenConsumedEvent($token, new \DateTimeImmutable()));

        return $token;
    }

    public function revoke(string $tokenString): void
    {
        $token = $this->tokenRepository->findByTokenString($tokenString);

        if ($token === null) {
            throw new TokenNotFoundException(\sprintf('Token "%s" not found.', $tokenString));
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
