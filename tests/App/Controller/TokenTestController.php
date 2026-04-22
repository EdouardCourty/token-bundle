<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\App\Controller;

use Ecourty\TokenBundle\Attribute\RequiresToken;
use Ecourty\TokenBundle\Entity\Token;
use Ecourty\TokenBundle\Resolver\QueryStringTokenResolver;
use Ecourty\TokenBundle\Tests\App\Resolver\CustomHeaderTokenResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class TokenTestController
{
    #[RequiresToken(type: 'access')]
    public function protectedAction(Request $request): JsonResponse
    {
        $token = $request->attributes->get('_token');
        \assert($token instanceof Token);

        return new JsonResponse(['status' => 'ok', 'token_type' => $token->getType()]);
    }

    #[RequiresToken(type: 'access', resolver: QueryStringTokenResolver::class)]
    public function queryStringAction(Request $request): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[RequiresToken(type: 'access', resolver: CustomHeaderTokenResolver::class)]
    public function customResolverAction(Request $request): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function publicAction(): JsonResponse
    {
        return new JsonResponse(['status' => 'public']);
    }
}
