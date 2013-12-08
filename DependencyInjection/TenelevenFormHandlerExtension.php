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

        foreach ($config['types'] as $type => $typeConfig) {
            $this->configureType($type, $typeConfig, $container);
        }

    }

    protected function configureType($type, array $config, ContainerBuilder $container)
    {
        $container->setParameter('teneleven_form_handler.'.$type.'.from', $config['from']);

        $container->setParameter('teneleven_form_handler.'.$type.'.to', $config['to']);

        $container->setParameter('teneleven_form_handler.'.$type.'.subject', $config['subject']);

        $container->setParameter('teneleven_form_handler.'.$type.'.content_type', $config['content_type']);

        $container->setParameter('teneleven_form_handler.'.$type.'.template', $config['template']);
    }
}
