<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Contract;

use Symfony\Component\HttpFoundation\Request;

interface TokenResolverInterface
{
    /**
     * Resolves a token string from the given HTTP request.
     *
     * @return string|null the token string, or null if not found in the request
     */
    public function resolve(Request $request): ?string;
}
