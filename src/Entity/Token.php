<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ecourty\TokenBundle\Repository\TokenRepository;

#[ORM\Entity(repositoryClass: TokenRepository::class)]
#[ORM\Table(name: 'tokens')]
#[ORM\Index(name: 'idx_token_type', columns: ['type'])]
#[ORM\Index(name: 'idx_token_subject', columns: ['subject_type', 'subject_id', 'type'])]
#[ORM\UniqueConstraint(name: 'uniq_token_string', columns: ['token'])]
class Token
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore property.onlyRead */
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $type;

    #[ORM\Column(type: 'string', length: 512, unique: true)]
    private string $token;

    #[ORM\Column(name: 'subject_type', type: 'string', length: 255)]
    private string $subjectType;

    #[ORM\Column(name: 'subject_id', type: 'string', length: 255)]
    private string $subjectId;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload;

    #[ORM\Column(name: 'single_use', type: 'boolean')]
    private bool $singleUse;

    #[ORM\Column(name: 'max_uses', type: 'integer', nullable: true)]
    private ?int $maxUses;

    #[ORM\Column(name: 'use_count', type: 'integer')]
    private int $useCount = 0;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'consumed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        string $type,
        string $token,
        string $subjectType,
        string $subjectId,
        \DateTimeImmutable $expiresAt,
        bool $singleUse = false,
        ?int $maxUses = null,
        ?array $payload = null,
    ) {
        $this->type = $type;
        $this->token = $token;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->expiresAt = $expiresAt;
        $this->singleUse = $singleUse;
        $this->maxUses = $maxUses;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    /** @return array<string, mixed>|null */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function isSingleUse(): bool
    {
        return $this->singleUse;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function getUseCount(): int
    {
        return $this->useCount;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isMaxUsesReached(): bool
    {
        return $this->maxUses !== null && $this->useCount >= $this->maxUses;
    }

    public function isValid(): bool
    {
        return !$this->isExpired()
            && !$this->isConsumed()
            && !$this->isRevoked()
            && !$this->isMaxUsesReached();
    }

    public function markConsumed(?\DateTimeImmutable $at = null): void
    {
        $this->consumedAt = $at ?? new \DateTimeImmutable();
    }

    public function markRevoked(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function incrementUseCount(): void
    {
        ++$this->useCount;
    }
}
