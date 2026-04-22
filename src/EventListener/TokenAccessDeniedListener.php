<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\EventListener;

use Ecourty\TokenBundle\Event\TokenAccessDeniedEvent;
use Ecourty\TokenBundle\Exception\TokenAccessDeniedException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
final class TokenAccessDeniedListener
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof TokenAccessDeniedException) {
            return;
        }

        $request = $event->getRequest();
        /** @var string $tokenType */
        $tokenType = $request->attributes->get('_token_type', '');

        $accessDeniedEvent = new TokenAccessDeniedEvent(
            $request,
            $exception->getPrevious() ?? $exception,
            $tokenType,
        );

        $this->eventDispatcher->dispatch($accessDeniedEvent);

        if ($accessDeniedEvent->getResponse() !== null) {
            $event->setResponse($accessDeniedEvent->getResponse());
        }
    }
}
