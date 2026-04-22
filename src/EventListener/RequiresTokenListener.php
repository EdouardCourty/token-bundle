<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\EventListener;

use Ecourty\TokenBundle\Attribute\RequiresToken;
use Ecourty\TokenBundle\Contract\TokenResolverInterface;
use Ecourty\TokenBundle\Exception\AbstractTokenException;
use Ecourty\TokenBundle\Exception\TokenAccessDeniedException;
use Ecourty\TokenBundle\Exception\TokenNotFoundException;
use Ecourty\TokenBundle\Service\TokenManager;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

#[AsEventListener]
final class RequiresTokenListener
{
    public function __construct(
        private readonly TokenManager $tokenManager,
        #[TaggedLocator('token.resolver')]
        private readonly ContainerInterface $resolverLocator,
    ) {
    }

    public function __invoke(ControllerArgumentsEvent $event): void
    {
        /** @var list<RequiresToken> $attributes */
        $attributes = $event->getAttributes()[RequiresToken::class] ?? [];

        if ($attributes === []) {
            return;
        }

        $attribute = $attributes[0];
        $request = $event->getRequest();
        $tokenString = $this->resolveTokenString($attribute, $request);

        if ($tokenString === null) {
            $request->attributes->set('_token_type', $attribute->type);
            throw new TokenAccessDeniedException(
                'No token provided.',
                new TokenNotFoundException('No token provided.'),
            );
        }

        try {
            $token = $this->tokenManager->get($tokenString, $attribute->type);
            $request->attributes->set('_token', $token);
        } catch (AbstractTokenException $e) {
            $request->attributes->set('_token_type', $attribute->type);
            throw new TokenAccessDeniedException($e->getMessage(), $e);
        }
    }

    private function resolveTokenString(RequiresToken $attribute, Request $request): ?string
    {
        /** @var TokenResolverInterface $resolver */
        $resolver = $this->resolverLocator->get($attribute->resolver);

        return $resolver->resolve($request);
    }
}
