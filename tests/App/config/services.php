<?php

declare(strict_types=1);

use Ecourty\TokenBundle\Tests\App\Controller\TokenTestController;
use Ecourty\TokenBundle\Tests\App\Resolver\CustomHeaderTokenResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(TokenTestController::class)
        ->tag('controller.service_arguments');

    $services->set(CustomHeaderTokenResolver::class);
};
