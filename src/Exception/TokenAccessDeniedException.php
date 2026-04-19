<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Exception;

final class TokenAccessDeniedException extends AbstractTokenException
{
    public function __construct(
        string $message = 'Token access denied.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
