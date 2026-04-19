<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TokenAccessDeniedEvent
{
    private ?Response $response = null;

    public function __construct(
        public readonly Request $request,
        public readonly \Throwable $exception,
        public readonly string $tokenType,
    ) {
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
