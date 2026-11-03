<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Ecourty\TokenBundle\Doctrine\TokenTableNameListener;
use Ecourty\TokenBundle\Entity\Token;
use PHPUnit\Framework\TestCase;

final class TokenTableNameListenerTest extends TestCase
{
    public function testRenamesTokenTableAndKeepsIndexes(): void
    {
        $metadata = new ClassMetadata(Token::class);
        $metadata->setPrimaryTable([
            'name' => 'tokens',
            'indexes' => ['idx_token_type' => ['columns' => ['type']]],
            'uniqueConstraints' => ['uniq_token_string' => ['columns' => ['token']]],
        ]);

        $this->dispatch(new TokenTableNameListener('app_tokens'), $metadata);

        self::assertSame('app_tokens', $metadata->getTableName());
        self::assertArrayHasKey('idx_token_type', $metadata->table['indexes'] ?? []);
        self::assertArrayHasKey('uniq_token_string', $metadata->table['uniqueConstraints'] ?? []);
    }

    public function testIgnoresOtherEntities(): void
    {
        $metadata = new ClassMetadata(\stdClass::class);
        $metadata->setPrimaryTable(['name' => 'other']);

        $this->dispatch(new TokenTableNameListener('app_tokens'), $metadata);

        self::assertSame('other', $metadata->getTableName());
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function dispatch(TokenTableNameListener $listener, ClassMetadata $metadata): void
    {
        $listener->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->createStub(EntityManagerInterface::class)));
    }
}
