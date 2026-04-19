<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class RequiresToken
{
    /**
     * @param string            $type      the token type to validate (e.g. 'password_reset')
     * @param string            $parameter request parameter name containing the token string (query, route, or body)
     * @param class-string|null $resolver  FQCN of a TokenResolverInterface implementation (takes priority over $parameter)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $parameter = 'token',
        public readonly ?string $resolver = null,
    ) {
    }
}
