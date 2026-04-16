<?php

declare(strict_types=1);

namespace Ecourty\TokenBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('token');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('token_length')
                    ->defaultValue(64)
                    ->min(16)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
