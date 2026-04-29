<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $ormConfig = [
        'auto_generate_proxy_classes' => true,
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
    ];

    // PHP 8.4+ supports native lazy objects; required on Symfony 8 where
    // var-exporter dropped ProxyHelper::generateLazyGhost() (the legacy fallback).
    if (\PHP_VERSION_ID >= 80400) {
        $ormConfig['enable_native_lazy_objects'] = true;
    }

    $container->extension('doctrine', [
        'dbal' => [
            'driver' => 'pdo_sqlite',
            'url' => 'sqlite:///:memory:',
        ],
        'orm' => $ormConfig,
    ]);
};
