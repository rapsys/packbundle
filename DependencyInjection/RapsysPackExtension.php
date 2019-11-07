<?php

namespace Rapsys\PackBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

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
		$configuration = $this->getConfiguration($configs, $container);

		//Process the configuration to get merged config
		$config = $this->processConfiguration($configuration, $configs);

		//Detect when no user configuration is provided
		if ($configs === [[]]) {
			//Prepend default config
			$container->prependExtensionConfig($this->getAlias(), $config);
		}

		//Save configuration in parameters
		$container->setParameter($this->getAlias(), $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfiguration(array $configs, ContainerBuilder $container) {
		//Get configuration instance with resolved public path
		return new Configuration($container->getParameter('kernel.project_dir').'/public/');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias() {
		return 'rapsys_pack';
	}
}
