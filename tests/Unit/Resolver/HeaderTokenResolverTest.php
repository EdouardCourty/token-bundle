<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\Resolver;

use Ecourty\TokenBundle\Resolver\HeaderTokenResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HeaderTokenResolverTest extends TestCase
{
    private HeaderTokenResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new HeaderTokenResolver();
    }

    public function testResolvesFromXTokenHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-Token', 'my-secret-token');

        $this->assertSame('my-secret-token', $this->resolver->resolve($request));
    }

    public function testReturnsNullWithoutHeader(): void
    {
        $request = Request::create('/test');

        $this->assertNull($this->resolver->resolve($request));
    }
}
