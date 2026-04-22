<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Ecourty\TokenBundle\Command\PurgeTokensCommand;
use Ecourty\TokenBundle\EventListener\RequiresTokenListener;
use Ecourty\TokenBundle\EventListener\TokenAccessDeniedListener;
use Ecourty\TokenBundle\Repository\TokenRepository;
use Ecourty\TokenBundle\Resolver\HeaderTokenResolver;
use Ecourty\TokenBundle\Resolver\QueryStringTokenResolver;
use Ecourty\TokenBundle\Service\TokenManager;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(TokenRepository::class)
        ->tag('doctrine.repository_service');

    $services->set(TokenManager::class)
        ->arg('$tokenLength', '%token.token_length%');

    $services->set(PurgeTokensCommand::class)
        ->tag('console.command');

    $services->set(HeaderTokenResolver::class);

    $services->set(QueryStringTokenResolver::class);

    $services->set(RequiresTokenListener::class)
        ->arg('$resolverLocator', tagged_locator('token.resolver'));

    $services->set(TokenAccessDeniedListener::class);
};
