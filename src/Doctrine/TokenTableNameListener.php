<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Ecourty\TokenBundle\Entity\Token;

final readonly class TokenTableNameListener
{
    public function __construct(private string $tableName)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (Token::class !== $metadata->getName()) {
            return;
        }

        $metadata->setPrimaryTable(['name' => $this->tableName]);
    }
}
