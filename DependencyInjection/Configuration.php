<?php

namespace Teneleven\Bundle\FormHandlerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('teneleven_form_handler');

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('types')
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('from')->defaultValue('no-reply@example.com')->end()
                                ->arrayNode('to')
                                    ->prototype('scalar')
                                    ->end()
                                ->end()
                                ->scalarNode('subject')->defaultValue('Form Submission Received')->end()
                                ->scalarNode('content_type')->defaultValue('text/html')->end()
                                ->scalarNode('template')->defaultValue('TenelevenFormHandlerBundle:Submission:default.html.twig')->end()
                                ->scalarNode('email_template')->defaultValue('TenelevenFormHandlerBundle:Submission:email.html.twig')->end()
                                ->scalarNode('thanks_template')->defaultValue('TenelevenFormHandlerBundle:Submission:thanks.html.twig')->end()
                                ->arrayNode('values')
                                    ->prototype('array')
                                            ->prototype('array')
                                                ->children()
                                                    ->arrayNode('to')
                                                        ->prototype('scalar')
                                                        ->end()
                                                    ->end()
                                                    ->scalarNode('subject')->end()
                                                    ->scalarNode('content_type')->end()
                                                    ->scalarNode('template')->end()
                                                    ->scalarNode('email_template')->end()
                                                    ->scalarNode('thanks_template')->end()
                                                ->end()
                                            ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end() //children
                    ->end() //prototype
                ->end() //arrayNode
            ->end() //children
        ;
        // more information on that topic.

        return $treeBuilder;
    }
}