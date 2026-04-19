<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\App\Controller;

use Ecourty\TokenBundle\Attribute\RequiresToken;
use Ecourty\TokenBundle\Entity\Token;
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

    #[RequiresToken(type: 'access', parameter: 'api_token')]
    public function customParameterAction(Request $request): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[RequiresToken(type: 'access', resolver: \Ecourty\TokenBundle\Tests\App\Resolver\HeaderTokenResolver::class)]
    public function resolverAction(Request $request): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function publicAction(): JsonResponse
    {
        return new JsonResponse(['status' => 'public']);
    }
}
