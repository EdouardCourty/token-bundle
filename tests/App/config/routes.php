<?php

declare(strict_types=1);

use Ecourty\TokenBundle\Tests\App\Controller\TokenTestController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('protected', '/protected')
        ->controller([TokenTestController::class, 'protectedAction']);

    $routes->add('query_string', '/query-string')
        ->controller([TokenTestController::class, 'queryStringAction']);

    $routes->add('custom_resolver', '/custom-resolver')
        ->controller([TokenTestController::class, 'customResolverAction']);

    $routes->add('public', '/public')
        ->controller([TokenTestController::class, 'publicAction']);
};
