<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('doctrine', [
        'dbal' => [
            'driver' => 'pdo_sqlite',
            'url' => 'sqlite:///:memory:',
        ],
        'orm' => [
            'auto_generate_proxy_classes' => true,
            'enable_native_lazy_objects' => true,
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
            'mappings' => [
                'TestApp' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/App/Entity',
                    'prefix' => 'Ecourty\TokenBundle\Tests\App\Entity',
                    'alias' => 'TestApp',
                ],
            ],
        ],
    ]);
};
