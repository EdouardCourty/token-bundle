<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ecourty\TokenBundle\Contract\TokenSubjectInterface;
use Ecourty\TokenBundle\Entity\Token;

/**
 * @extends ServiceEntityRepository<Token>
 */
class TokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Token::class);
    }

    public function findByTokenString(string $tokenString): ?Token
    {
        return $this->findOneBy(['token' => $tokenString]);
    }

    public function findByTokenStringAndType(string $tokenString, string $type): ?Token
    {
        return $this->findOneBy(['token' => $tokenString, 'type' => $type]);
    }

    public function findValidBySubjectAndType(TokenSubjectInterface $subject, string $type): ?Token
    {
        $now = new \DateTimeImmutable();

        /** @var Token|null $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.subjectType = :subjectType')
            ->andWhere('t.subjectId = :subjectId')
            ->andWhere('t.type = :type')
            ->andWhere('t.expiresAt > :now')
            ->andWhere('t.consumedAt IS NULL')
            ->andWhere('t.revokedAt IS NULL')
            ->andWhere('(t.maxUses IS NULL OR t.useCount < t.maxUses)')
            ->setParameter('subjectType', $subject::class)
            ->setParameter('subjectId', $subject->getTokenSubjectId())
            ->setParameter('type', $type)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function revokeAllBySubject(TokenSubjectInterface $subject, ?string $type = null): int
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('t')
            ->update()
            ->set('t.revokedAt', ':now')
            ->where('t.subjectType = :subjectType')
            ->andWhere('t.subjectId = :subjectId')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('subjectType', $subject::class)
            ->setParameter('subjectId', $subject->getTokenSubjectId());

        if ($type !== null) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        $result = $qb->getQuery()->execute();

        return \is_int($result) ? $result : 0;
    }

    public function atomicIncrementUseCount(Token $token, ?\DateTimeImmutable $consumedAt = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->update()
            ->set('t.useCount', 't.useCount + 1')
            ->where('t.id = :id')
            ->andWhere('t.maxUses IS NULL OR t.useCount < t.maxUses')
            ->setParameter('id', $token->getId());

        if ($consumedAt !== null) {
            $qb->set('t.consumedAt', ':consumedAt')
               ->setParameter('consumedAt', $consumedAt);
        }

        $result = $qb->getQuery()->execute();

        $success = \is_int($result) && $result > 0;

        if ($success) {
            // Sync in-memory entity to match what was written to DB (avoids stale identity map).
            $token->incrementUseCount();
            if ($consumedAt !== null) {
                $token->markConsumed($consumedAt);
            }
        }

        return $success;
    }

    public function countExpiredAndConsumed(?string $type = null, ?\DateTimeImmutable $before = null): int
    {
        $cutoff = $before ?? new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.expiresAt <= :cutoff OR t.consumedAt IS NOT NULL OR t.revokedAt IS NOT NULL')
            ->setParameter('cutoff', $cutoff);

        if ($type !== null) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function purgeExpiredAndConsumed(?string $type = null, ?\DateTimeImmutable $before = null): int
    {
        $cutoff = $before ?? new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt <= :cutoff OR t.consumedAt IS NOT NULL OR t.revokedAt IS NOT NULL')
            ->setParameter('cutoff', $cutoff);

        if ($type !== null) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        $result = $qb->getQuery()->execute();

        return \is_int($result) ? $result : 0;
    }
}
