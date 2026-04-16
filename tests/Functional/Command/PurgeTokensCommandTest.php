<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Functional\Command;

use Ecourty\TokenBundle\Entity\Token;
use Ecourty\TokenBundle\Tests\App\Entity\TestUser;
use Ecourty\TokenBundle\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeTokensCommandTest extends IntegrationTestCase
{
    private Application $application;
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = static::$kernel;
        \assert($kernel !== null);
        $this->application = new Application($kernel);

        $this->user = new TestUser('cmd@example.com');
        $this->em->persist($this->user);
        $this->em->flush();
    }

    private function createToken(
        string $type = 'test',
        string $expiresIn = '+1 hour',
        bool $revoked = false,
        bool $consumed = false,
    ): Token {
        $token = new Token(
            type: $type,
            token: bin2hex(random_bytes(32)),
            subjectType: TestUser::class,
            subjectId: $this->user->getTokenSubjectId(),
            expiresAt: new \DateTimeImmutable($expiresIn),
        );

        if ($revoked) {
            $token->markRevoked();
        }

        if ($consumed) {
            $token->markConsumed();
        }

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    public function testPurgeDeletesExpiredConsumedAndRevoked(): void
    {
        $this->createToken(expiresIn: '-1 second');
        $this->createToken(consumed: true);
        $this->createToken(revoked: true);
        $this->createToken();

        $tester = new CommandTester($this->application->find('token:purge'));
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('3 token(s) purged', $tester->getDisplay());
    }

    public function testPurgeWithTypeFilter(): void
    {
        $this->createToken(type: 'reset', expiresIn: '-1 second');
        $this->createToken(type: 'verify', expiresIn: '-1 second');

        $tester = new CommandTester($this->application->find('token:purge'));
        $tester->execute(['--type' => 'reset']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('1 token(s) purged', $tester->getDisplay());
    }

    public function testDryRunDoesNotDeleteTokens(): void
    {
        $this->createToken(expiresIn: '-1 second');
        $this->createToken(expiresIn: '-1 second');

        $tester = new CommandTester($this->application->find('token:purge'));
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[DRY RUN]', $tester->getDisplay());
        $this->assertStringContainsString('2 token(s) would be purged', $tester->getDisplay());

        $remaining = $this->em->getRepository(Token::class)->count([]);
        $this->assertSame(2, $remaining);
    }

    public function testPurgeWithNothingToPurge(): void
    {
        $this->createToken();

        $tester = new CommandTester($this->application->find('token:purge'));
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 token(s) purged', $tester->getDisplay());
    }

    public function testPurgeWithBeforeFilter(): void
    {
        // expired 2 days ago → should be purged with --before=yesterday
        $this->createToken(expiresIn: '-2 days');
        // expired 1 hour ago → should NOT be purged with --before=yesterday
        $this->createToken(expiresIn: '-1 hour');
        // consumed (always purged regardless of --before)
        $this->createToken(consumed: true);

        $tester = new CommandTester($this->application->find('token:purge'));
        $tester->execute(['--before' => 'yesterday']);

        $this->assertSame(0, $tester->getStatusCode());
        // 2 purged: expired 2 days ago + consumed token
        $this->assertStringContainsString('2 token(s) purged', $tester->getDisplay());

        // The token expired 1 hour ago should still be in the database
        $remaining = $this->em->getRepository(Token::class)->count([]);
        $this->assertSame(1, $remaining);
    }

    public function testPurgeWithInvalidBeforeDate(): void
    {
        $tester = new CommandTester($this->application->find('token:purge'));
        $tester->execute(['--before' => 'not-a-date']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid date format', $tester->getDisplay());
    }
}
