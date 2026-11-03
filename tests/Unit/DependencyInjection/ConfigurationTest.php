<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\DependencyInjection;

use Ecourty\TokenBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame('tokens', $config['table_name']);
        self::assertSame(64, $config['token_length']);
    }

    public function testCustomTableName(): void
    {
        self::assertSame('app_tokens', $this->process(['table_name' => 'app_tokens'])['table_name']);
    }

    #[DataProvider('invalidTableNames')]
    public function testInvalidTableName(string $name): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['table_name' => $name]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTableNames(): iterable
    {
        yield 'empty' => [''];
        yield 'leading digit' => ['1tokens'];
        yield 'space' => ['my tokens'];
        yield 'dash' => ['my-tokens'];
        yield 'quote' => ['tokens`; DROP TABLE x'];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
