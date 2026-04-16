<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Integration\Service;

use Ecourty\TokenBundle\Event\TokenConsumedEvent;
use Ecourty\TokenBundle\Event\TokenCreatedEvent;
use Ecourty\TokenBundle\Event\TokenRevokedEvent;
use Ecourty\TokenBundle\Exception\TokenAlreadyConsumedException;
use Ecourty\TokenBundle\Exception\TokenExpiredException;
use Ecourty\TokenBundle\Exception\TokenNotFoundException;
use Ecourty\TokenBundle\Exception\TokenRevokedException;
use Ecourty\TokenBundle\Service\TokenManager;
use Ecourty\TokenBundle\Tests\App\Entity\TestUser;
use Ecourty\TokenBundle\Tests\Integration\IntegrationTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TokenManagerTest extends IntegrationTestCase
{
    private TokenManager $manager;
    private TestUser $user;
    /** @var list<object> */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = new EventDispatcher();

        foreach ([TokenCreatedEvent::class, TokenConsumedEvent::class, TokenRevokedEvent::class] as $eventClass) {
            $dispatcher->addListener($eventClass, function (object $event): void {
                $this->dispatchedEvents[] = $event;
            });
        }

        $this->manager = new TokenManager($this->repository, $this->em, $dispatcher, 32);

        $this->user = new TestUser('test@example.com');
        $this->em->persist($this->user);
        $this->em->flush();
    }

    public function testCreateToken(): void
    {
        $token = $this->manager->create('password_reset', $this->user, '+1 hour', true);

        $this->assertSame('password_reset', $token->getType());
        $this->assertSame(TestUser::class, $token->getSubjectType());
        $this->assertSame($this->user->getTokenSubjectId(), $token->getSubjectId());
        $this->assertTrue($token->isSingleUse());
        $this->assertGreaterThan(0, $token->getId());
        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(TokenCreatedEvent::class, $this->dispatchedEvents[0]);
    }

    public function testConsumeSingleUseToken(): void
    {
        $token = $this->manager->create('email_verify', $this->user, '+1 hour', true);
        $tokenString = $token->getToken();

        $consumed = $this->manager->consume($tokenString, 'email_verify');

        $this->assertTrue($consumed->isConsumed());
        $this->assertFalse($consumed->isValid());

        $eventTypes = array_map(static fn (object $e): string => $e::class, $this->dispatchedEvents);
        $this->assertContains(TokenConsumedEvent::class, $eventTypes);
    }

    public function testConsumedTokenCannotBeConsumedAgain(): void
    {
        $token = $this->manager->create('email_verify', $this->user, '+1 hour', true);
        $tokenString = $token->getToken();

        $this->manager->consume($tokenString, 'email_verify');

        $this->expectException(TokenAlreadyConsumedException::class);
        $this->manager->consume($tokenString, 'email_verify');
    }

    public function testConsumeMultiUseTokenIncrementsCount(): void
    {
        $token = $this->manager->create('share', $this->user, '+7 days', false, 3);
        $tokenString = $token->getToken();

        $this->manager->consume($tokenString, 'share');
        $this->em->refresh($token);
        $this->assertSame(1, $token->getUseCount());

        $this->manager->consume($tokenString, 'share');
        $this->em->refresh($token);
        $this->assertSame(2, $token->getUseCount());
    }

    public function testMultiUseTokenConsumesWhenMaxReached(): void
    {
        $token = $this->manager->create('share', $this->user, '+7 days', false, 2);
        $tokenString = $token->getToken();

        $this->manager->consume($tokenString, 'share');
        $this->manager->consume($tokenString, 'share');

        $this->em->refresh($token);
        $this->assertTrue($token->isConsumed());
        $this->assertFalse($token->isRevoked());

        $this->expectException(TokenAlreadyConsumedException::class);
        $this->manager->consume($tokenString, 'share');
    }

    public function testConsumeExpiredToken(): void
    {
        $token = $this->manager->create('magic_link', $this->user, '-1 second', true);
        $tokenString = $token->getToken();

        $this->expectException(TokenExpiredException::class);
        $this->manager->consume($tokenString, 'magic_link');
    }

    public function testConsumeRevokedToken(): void
    {
        $token = $this->manager->create('password_reset', $this->user, '+1 hour', true);
        $tokenString = $token->getToken();

        $this->manager->revoke($tokenString);

        $this->expectException(TokenRevokedException::class);
        $this->manager->consume($tokenString, 'password_reset');
    }

    public function testConsumeNotFoundToken(): void
    {
        $this->expectException(TokenNotFoundException::class);
        $this->manager->consume('nonexistent-token', 'password_reset');
    }

    public function testRevokeToken(): void
    {
        $token = $this->manager->create('password_reset', $this->user, '+1 hour', true);
        $tokenString = $token->getToken();

        $this->manager->revoke($tokenString);

        $this->em->refresh($token);
        $this->assertTrue($token->isRevoked());
        $this->assertFalse($token->isValid());

        $eventTypes = array_map(static fn (object $e): string => $e::class, $this->dispatchedEvents);
        $this->assertContains(TokenRevokedEvent::class, $eventTypes);
    }

    public function testRevokeNotFoundToken(): void
    {
        $this->expectException(TokenNotFoundException::class);
        $this->manager->revoke('nonexistent-token');
    }

    public function testRevokeAll(): void
    {
        $this->manager->create('password_reset', $this->user, '+1 hour', true);
        $this->manager->create('password_reset', $this->user, '+2 hours', true);
        $this->manager->create('email_verify', $this->user, '+24 hours', true);

        $count = $this->manager->revokeAll($this->user, 'password_reset');

        $this->assertSame(2, $count);
    }

    public function testRevokeAllWithoutType(): void
    {
        $this->manager->create('password_reset', $this->user, '+1 hour', true);
        $this->manager->create('email_verify', $this->user, '+24 hours', true);

        $count = $this->manager->revokeAll($this->user);

        $this->assertSame(2, $count);
    }

    public function testFindValid(): void
    {
        $token = $this->manager->create('password_reset', $this->user, '+1 hour', true);

        $found = $this->manager->findValid($this->user, 'password_reset');

        $this->assertNotNull($found);
        $this->assertSame($token->getId(), $found->getId());
    }

    public function testFindValidReturnsNullForExpired(): void
    {
        $this->manager->create('password_reset', $this->user, '-1 second', true);

        $found = $this->manager->findValid($this->user, 'password_reset');

        $this->assertNull($found);
    }

    public function testFindValidReturnsNullForRevoked(): void
    {
        $token = $this->manager->create('password_reset', $this->user, '+1 hour', true);
        $this->manager->revoke($token->getToken());

        $found = $this->manager->findValid($this->user, 'password_reset');

        $this->assertNull($found);
    }

    public function testTokenPayloadIsStored(): void
    {
        $payload = ['redirect' => '/dashboard', 'role' => 'admin'];
        $token = $this->manager->create('magic_link', $this->user, '+1 hour', true, null, $payload);

        $this->em->clear();
        $found = $this->manager->findValid($this->user, 'magic_link');

        $this->assertNotNull($found);
        $this->assertSame($payload, $found->getPayload());
    }

    public function testConsumeWithWrongTypeThrows(): void
    {
        $token = $this->manager->create('password_reset', $this->user, '+1 hour', true);

        $this->expectException(TokenNotFoundException::class);
        $this->manager->consume($token->getToken(), 'email_verify');
    }

    public function testConsumeUnlimitedUseToken(): void
    {
        $token = $this->manager->create('share', $this->user, '+7 days');
        $tokenString = $token->getToken();

        $this->manager->consume($tokenString, 'share');
        $this->manager->consume($tokenString, 'share');
        $this->manager->consume($tokenString, 'share');

        $this->em->refresh($token);
        $this->assertTrue($token->isValid());
        $this->assertSame(0, $token->getUseCount());
    }

    public function testInvalidMaxUsesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->create('test', $this->user, '+1 hour', false, 0);
    }
}
