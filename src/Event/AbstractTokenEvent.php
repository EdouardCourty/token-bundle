<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Event;

use Ecourty\TokenBundle\Entity\Token;

abstract class AbstractTokenEvent
{
    public function __construct(
        public readonly Token $token,
    ) {
    }
}
