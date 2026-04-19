<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Unit\Event;

use Ecourty\TokenBundle\Event\TokenAccessDeniedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TokenAccessDeniedEventTest extends TestCase
{
    public function testProperties(): void
    {
        $request = Request::create('/test');
        $exception = new \RuntimeException('Token invalid');

        $event = new TokenAccessDeniedEvent($request, $exception, 'password_reset');

        $this->assertSame($request, $event->request);
        $this->assertSame($exception, $event->exception);
        $this->assertSame('password_reset', $event->tokenType);
        $this->assertNull($event->getResponse());
    }

    public function testSetResponse(): void
    {
        $event = new TokenAccessDeniedEvent(
            Request::create('/test'),
            new \RuntimeException('Token invalid'),
            'access',
        );

        $response = new Response('Denied', 403);
        $event->setResponse($response);

        $this->assertSame($response, $event->getResponse());
    }
}
