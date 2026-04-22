<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Attribute;

use Ecourty\TokenBundle\Resolver\HeaderTokenResolver;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class RequiresToken
{
    /**
     * @param string       $type     the token type to validate (e.g. 'password_reset')
     * @param class-string $resolver FQCN of a TokenResolverInterface implementation
     */
    public function __construct(
        public readonly string $type,
        public readonly string $resolver = HeaderTokenResolver::class,
    ) {
    }
}
