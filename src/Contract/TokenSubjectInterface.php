<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Contract;

interface TokenSubjectInterface
{
    public function getTokenSubjectId(): string;
}
