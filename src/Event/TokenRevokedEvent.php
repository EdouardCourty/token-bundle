<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Event;

use Ecourty\TokenBundle\Entity\Token;

final class TokenRevokedEvent extends AbstractTokenEvent
{
    public readonly \DateTimeImmutable $revokedAt;

    public function __construct(Token $token)
    {
        $revokedAt = $token->getRevokedAt();

        if ($revokedAt === null) {
            throw new \LogicException('Cannot create TokenRevokedEvent: the token has not been revoked yet.');
        }

        parent::__construct($token);
        $this->revokedAt = $revokedAt;
    }
}
