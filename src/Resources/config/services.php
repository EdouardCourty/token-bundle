<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Ecourty\TokenBundle\Command\PurgeTokensCommand;
use Ecourty\TokenBundle\Repository\TokenRepository;
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
};
