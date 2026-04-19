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
        if ($attribute->resolver !== null) {
            /** @var TokenResolverInterface $resolver */
            $resolver = $this->resolverLocator->get($attribute->resolver);

            return $resolver->resolve($request);
        }

        // Check Authorization: Bearer <token> header first
        $authorization = $request->headers->get('Authorization');
        if (\is_string($authorization) && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        $parameter = $attribute->parameter;

        // Fallback to route parameters, then query string, then request body
        $value = $request->attributes->get($parameter)
            ?? $request->query->get($parameter)
            ?? $request->request->get($parameter);

        return \is_string($value) ? $value : null;
    }
}
