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
                ->scalarNode('table_name')
                    ->defaultValue('tokens')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !\is_string($value) || 1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value))
                        ->thenInvalid('The table name must be a valid identifier (letters, digits and underscores, not starting with a digit).')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
