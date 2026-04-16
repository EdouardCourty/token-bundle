<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Ecourty\TokenBundle\Repository\TokenRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class IntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected TokenRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel(['debug' => false]);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $registry = static::getContainer()->get('doctrine');
        \assert($registry instanceof ManagerRegistry);
        $this->repository = new TokenRepository($registry);

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
