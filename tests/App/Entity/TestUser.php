<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ecourty\TokenBundle\Contract\TokenSubjectInterface;

#[ORM\Entity]
#[ORM\Table(name: 'test_users')]
class TestUser implements TokenSubjectInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore property.onlyRead */
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTokenSubjectId(): string
    {
        return (string) $this->id;
    }
}
