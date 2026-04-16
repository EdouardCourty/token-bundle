<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Event;

use Ecourty\TokenBundle\Entity\Token;

final class TokenConsumedEvent extends AbstractTokenEvent
{
    public function __construct(
        Token $token,
        public readonly \DateTimeImmutable $consumedAt,
    ) {
        parent::__construct($token);
    }
}
