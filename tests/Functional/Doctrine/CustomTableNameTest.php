<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Functional\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Ecourty\TokenBundle\Entity\Token;
use Ecourty\TokenBundle\Tests\App\CustomTableNameKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CustomTableNameTest extends KernelTestCase
{
    /**
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new CustomTableNameKernel('test', false);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testTokenTableUsesConfiguredName(): void
    {
        self::bootKernel();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        self::assertSame(CustomTableNameKernel::TABLE_NAME, $em->getClassMetadata(Token::class)->getTableName());

        $sql = implode("\n", (new SchemaTool($em))->getCreateSchemaSql([$em->getClassMetadata(Token::class)]));
        self::assertStringContainsString('CREATE TABLE ' . CustomTableNameKernel::TABLE_NAME, $sql);
        self::assertStringContainsString('idx_token_subject', $sql);
        self::assertStringContainsString('uniq_token_string', $sql);

        $em->close();
    }
}
