<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\App\Resolver;

use Ecourty\TokenBundle\Contract\TokenResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class HeaderTokenResolver implements TokenResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $header = $request->headers->get('X-Token');

        return \is_string($header) ? $header : null;
    }
}
