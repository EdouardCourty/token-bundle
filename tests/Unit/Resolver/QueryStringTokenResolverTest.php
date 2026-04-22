<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\Resolver;

use Ecourty\TokenBundle\Resolver\QueryStringTokenResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class QueryStringTokenResolverTest extends TestCase
{
    private QueryStringTokenResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new QueryStringTokenResolver();
    }

    public function testResolvesFromQueryString(): void
    {
        $request = Request::create('/test?token=my-secret-token');

        $this->assertSame('my-secret-token', $this->resolver->resolve($request));
    }

    public function testReturnsNullWithoutTokenParam(): void
    {
        $request = Request::create('/test');

        $this->assertNull($this->resolver->resolve($request));
    }

    public function testReturnsNullForWrongParamName(): void
    {
        $request = Request::create('/test?api_key=my-secret-token');

        $this->assertNull($this->resolver->resolve($request));
    }
}
