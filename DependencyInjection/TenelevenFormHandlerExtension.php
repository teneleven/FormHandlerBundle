<?php

namespace Teneleven\Bundle\FormHandlerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class TenelevenFormHandlerExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if (isset($config['types']) AND count($config['types'])) {
            $container->setParameter('teneleven_form_handler_types', array_keys($config['types']));
            foreach ($config['types'] as $type => $type_config) {
                $container->setParameter(sprintf('teneleven_form_handler.%s', $type), $type_config);    
            }           
        }

        unset($config['types']);

        $container->setParameter('teneleven_form_handler', $config);
    }
}
