<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\Attribute;

use Ecourty\TokenBundle\Attribute\RequiresToken;
use Ecourty\TokenBundle\Resolver\HeaderTokenResolver;
use Ecourty\TokenBundle\Resolver\QueryStringTokenResolver;
use PHPUnit\Framework\TestCase;

final class RequiresTokenTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $attribute = new RequiresToken(type: 'access');

        $this->assertSame('access', $attribute->type);
        $this->assertSame(HeaderTokenResolver::class, $attribute->resolver);
    }

    public function testCustomResolver(): void
    {
        $attribute = new RequiresToken(
            type: 'share',
            resolver: QueryStringTokenResolver::class,
        );

        $this->assertSame('share', $attribute->type);
        $this->assertSame(QueryStringTokenResolver::class, $attribute->resolver);
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
