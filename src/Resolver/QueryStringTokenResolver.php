<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Resolver;

use Ecourty\TokenBundle\Contract\TokenResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts the token from the "token" query string parameter.
 */
final class QueryStringTokenResolver implements TokenResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $value = $request->query->get('token');

        return \is_string($value) ? $value : null;
    }
}
