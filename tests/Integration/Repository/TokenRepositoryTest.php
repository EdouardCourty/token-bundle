<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Integration\Repository;

use Ecourty\TokenBundle\Entity\Token;
use Ecourty\TokenBundle\Tests\App\Entity\TestUser;
use Ecourty\TokenBundle\Tests\Integration\IntegrationTestCase;

final class TokenRepositoryTest extends IntegrationTestCase
{
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new TestUser('repo@example.com');
        $this->em->persist($this->user);
        $this->em->flush();
    }

    private function createToken(
        string $type = 'test',
        string $expiresIn = '+1 hour',
        bool $singleUse = false,
        ?int $maxUses = null,
    ): Token {
        $token = new Token(
            type: $type,
            token: bin2hex(random_bytes(32)),
            subjectType: TestUser::class,
            subjectId: $this->user->getTokenSubjectId(),
            expiresAt: new \DateTimeImmutable($expiresIn),
            singleUse: $singleUse,
            maxUses: $maxUses,
        );

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    public function testFindByTokenString(): void
    {
        $token = $this->createToken();

        $found = $this->repository->findByTokenString($token->getToken());

        $this->assertNotNull($found);
        $this->assertSame($token->getId(), $found->getId());
    }

    public function testFindByTokenStringReturnsNullForUnknown(): void
    {
        $found = $this->repository->findByTokenString('unknown-token');
        $this->assertNull($found);
    }

    public function testFindByTokenStringAndType(): void
    {
        $token = $this->createToken(type: 'reset');

        $found = $this->repository->findByTokenStringAndType($token->getToken(), 'reset');
        $this->assertNotNull($found);

        $notFound = $this->repository->findByTokenStringAndType($token->getToken(), 'other');
        $this->assertNull($notFound);
    }

    public function testFindValidBySubjectAndType(): void
    {
        $token = $this->createToken(type: 'reset');

        $found = $this->repository->findValidBySubjectAndType($this->user, 'reset');

        $this->assertNotNull($found);
        $this->assertSame($token->getId(), $found->getId());
    }

    public function testFindValidExcludesExpired(): void
    {
        $this->createToken(expiresIn: '-1 second');

        $found = $this->repository->findValidBySubjectAndType($this->user, 'test');
        $this->assertNull($found);
    }

    public function testFindValidExcludesConsumed(): void
    {
        $token = $this->createToken();
        $token->markConsumed();
        $this->em->flush();

        $found = $this->repository->findValidBySubjectAndType($this->user, 'test');
        $this->assertNull($found);
    }

    public function testFindValidExcludesRevoked(): void
    {
        $token = $this->createToken();
        $token->markRevoked();
        $this->em->flush();

        $found = $this->repository->findValidBySubjectAndType($this->user, 'test');
        $this->assertNull($found);
    }

    public function testRevokeAllBySubjectWithType(): void
    {
        $this->createToken(type: 'reset');
        $this->createToken(type: 'reset');
        $this->createToken(type: 'verify');

        $count = $this->repository->revokeAllBySubject($this->user, 'reset');

        $this->assertSame(2, $count);
        $this->assertNotNull($this->repository->findValidBySubjectAndType($this->user, 'verify'));
    }

    public function testRevokeAllBySubjectWithoutType(): void
    {
        $this->createToken(type: 'reset');
        $this->createToken(type: 'verify');

        $count = $this->repository->revokeAllBySubject($this->user);

        $this->assertSame(2, $count);
    }

    public function testAtomicIncrementUseCount(): void
    {
        $token = $this->createToken(maxUses: 3);

        $result = $this->repository->atomicIncrementUseCount($token);
        $this->assertTrue($result);

        $this->em->refresh($token);
        $this->assertSame(1, $token->getUseCount());
    }

    public function testAtomicIncrementReturnsFalseWhenMaxReached(): void
    {
        $token = $this->createToken(maxUses: 1);
        $token->incrementUseCount();
        $this->em->flush();

        $result = $this->repository->atomicIncrementUseCount($token);
        $this->assertFalse($result);
    }

    public function testPurgeExpiredAndConsumed(): void
    {
        $this->createToken(expiresIn: '-1 second');
        $consumed = $this->createToken();
        $consumed->markConsumed();
        $this->em->flush();
        $revoked = $this->createToken();
        $revoked->markRevoked();
        $this->em->flush();
        $this->createToken(expiresIn: '+1 hour');

        $count = $this->repository->purgeExpiredAndConsumed();

        $this->assertSame(3, $count);
        $this->assertSame(1, $this->repository->count([]));
    }

    public function testPurgeWithTypeFilter(): void
    {
        $this->createToken(type: 'reset', expiresIn: '-1 second');
        $this->createToken(type: 'verify', expiresIn: '-1 second');

        $count = $this->repository->purgeExpiredAndConsumed('reset');

        $this->assertSame(1, $count);
    }

    public function testFindValidExcludesMaxUsesReached(): void
    {
        $token = $this->createToken(maxUses: 2);
        $token->incrementUseCount();
        $token->incrementUseCount();
        $this->em->flush();

        $found = $this->repository->findValidBySubjectAndType($this->user, 'test');
        $this->assertNull($found);
    }

    public function testCountExpiredAndConsumed(): void
    {
        $this->createToken(expiresIn: '-1 second');
        $this->createToken(expiresIn: '-1 second');
        $this->createToken();

        $count = $this->repository->countExpiredAndConsumed();
        $this->assertSame(2, $count);
    }
}
