<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Resolver;

use Ecourty\TokenBundle\Contract\TokenResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts the token from the X-Token header.
 *
 * This is the default resolver used by #[RequiresToken] when no resolver is specified.
 */
final class HeaderTokenResolver implements TokenResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $value = $request->headers->get('X-Token');

        return \is_string($value) ? $value : null;
    }
}
