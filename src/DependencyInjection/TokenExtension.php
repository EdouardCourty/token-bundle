<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\DependencyInjection;

use Ecourty\TokenBundle\Contract\TokenResolverInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class TokenExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        \assert(\is_int($config['token_length']));
        $container->setParameter('token.token_length', $config['token_length']);

        $container->registerForAutoconfiguration(TokenResolverInterface::class)
            ->addTag('token.resolver');

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TokenBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__ . '/../Entity',
                        'prefix' => 'Ecourty\\TokenBundle\\Entity',
                        'alias' => 'TokenBundle',
                    ],
                ],
            ],
        ]);
    }
}
