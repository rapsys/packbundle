<?php

namespace Rapsys\PackBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class RapsysPackExtension extends Extension {
	/**
	 * {@inheritdoc}
	 */
	public function load(array $configs, ContainerBuilder $container) {
		//Load configuration
		$loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
		$loader->load('services.yml');

		//Load configuration
		$configuration = $this->getConfiguration($configs, $container);
		$config = $this->processConfiguration($configuration, $configs);

		//Set default config in parameter
		if (!$container->hasParameter($alias = $this->getAlias())) {
			$container->setParameter($alias, $config[$alias]);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfiguration(array $configs, ContainerBuilder $container) {
		//Get configuration instance with resolved web path
		return new Configuration($container->getParameter('kernel.project_dir').'/web/');
	}
}
