<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\Entity;

use Ecourty\TokenBundle\Entity\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $payload
     */
    private function makeToken(
        string $expiresIn = '+1 hour',
        bool $singleUse = false,
        ?int $maxUses = null,
        ?array $payload = null,
    ): Token {
        return new Token(
            type: 'test',
            token: 'abc123',
            subjectType: 'App\Entity\User',
            subjectId: '42',
            expiresAt: new \DateTimeImmutable($expiresIn),
            singleUse: $singleUse,
            maxUses: $maxUses,
            payload: $payload,
        );
    }

    public function testIsValidByDefault(): void
    {
        $token = $this->makeToken();

        $this->assertTrue($token->isValid());
        $this->assertFalse($token->isExpired());
        $this->assertFalse($token->isConsumed());
        $this->assertFalse($token->isRevoked());
        $this->assertFalse($token->isMaxUsesReached());
    }

    public function testIsExpired(): void
    {
        $token = $this->makeToken('-1 second');

        $this->assertTrue($token->isExpired());
        $this->assertFalse($token->isValid());
    }

    public function testMarkConsumed(): void
    {
        $token = $this->makeToken(singleUse: true);

        $this->assertFalse($token->isConsumed());
        $this->assertNull($token->getConsumedAt());

        $token->markConsumed();

        $this->assertTrue($token->isConsumed());
        $this->assertFalse($token->isValid());
        $this->assertInstanceOf(\DateTimeImmutable::class, $token->getConsumedAt());
    }

    public function testMarkRevoked(): void
    {
        $token = $this->makeToken();

        $this->assertFalse($token->isRevoked());
        $this->assertNull($token->getRevokedAt());

        $token->markRevoked();

        $this->assertTrue($token->isRevoked());
        $this->assertFalse($token->isValid());
        $this->assertInstanceOf(\DateTimeImmutable::class, $token->getRevokedAt());
    }

    public function testMaxUsesNotReachedWhenBelowLimit(): void
    {
        $token = $this->makeToken(maxUses: 3);

        $this->assertFalse($token->isMaxUsesReached());
        $this->assertTrue($token->isValid());

        $token->incrementUseCount();
        $token->incrementUseCount();

        $this->assertFalse($token->isMaxUsesReached());
        $this->assertSame(2, $token->getUseCount());
    }

    public function testMaxUsesReachedWhenAtLimit(): void
    {
        $token = $this->makeToken(maxUses: 2);

        $token->incrementUseCount();
        $token->incrementUseCount();

        $this->assertTrue($token->isMaxUsesReached());
        $this->assertFalse($token->isValid());
    }

    public function testNoMaxUsesNeverReached(): void
    {
        $token = $this->makeToken();

        $this->assertNull($token->getMaxUses());
        $this->assertFalse($token->isMaxUsesReached());
    }

    public function testPayload(): void
    {
        $payload = ['action' => 'reset_password', 'user_id' => 42];
        $token = $this->makeToken(payload: $payload);

        $this->assertSame($payload, $token->getPayload());
    }

    public function testNullPayload(): void
    {
        $token = $this->makeToken();

        $this->assertNull($token->getPayload());
    }

    public function testGetters(): void
    {
        $token = $this->makeToken(singleUse: true, maxUses: 5, payload: ['foo' => 'bar']);

        $this->assertSame('test', $token->getType());
        $this->assertSame('abc123', $token->getToken());
        $this->assertSame('App\Entity\User', $token->getSubjectType());
        $this->assertSame('42', $token->getSubjectId());
        $this->assertTrue($token->isSingleUse());
        $this->assertSame(5, $token->getMaxUses());
        $this->assertSame(0, $token->getUseCount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $token->getExpiresAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $token->getCreatedAt());
    }
}
