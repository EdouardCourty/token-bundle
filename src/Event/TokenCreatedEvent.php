<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Event;

use Ecourty\TokenBundle\Entity\Token;

final class TokenCreatedEvent extends AbstractTokenEvent
{
    public readonly \DateTimeImmutable $createdAt;

    public function __construct(Token $token)
    {
        parent::__construct($token);
        $this->createdAt = $token->getCreatedAt();
    }
}
