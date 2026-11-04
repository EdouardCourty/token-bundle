<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\Tests\App;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CustomTableNameKernel extends TestKernel
{
    public const TABLE_NAME = 'custom_tokens';

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/App/var/cache/custom_table_' . $this->environment;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->loadFromExtension('token', ['table_name' => self::TABLE_NAME]);
    }
}
