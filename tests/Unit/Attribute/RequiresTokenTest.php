<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\Attribute;

use Ecourty\TokenBundle\Attribute\RequiresToken;
use PHPUnit\Framework\TestCase;

final class RequiresTokenTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $attribute = new RequiresToken(type: 'access');

        $this->assertSame('access', $attribute->type);
        $this->assertSame('token', $attribute->parameter);
        $this->assertNull($attribute->resolver);
    }

    public function testCustomValues(): void
    {
        $attribute = new RequiresToken(
            type: 'share',
            parameter: 'api_key',
            resolver: self::class,
        );

        $this->assertSame('share', $attribute->type);
        $this->assertSame('api_key', $attribute->parameter);
        $this->assertSame(self::class, $attribute->resolver);
    }

    public function testIsPhpAttribute(): void
    {
        $reflection = new \ReflectionClass(RequiresToken::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS, $attr->flags);
    }
}
