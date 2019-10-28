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
		#$loader = new Loader\YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
		$loader = new Loader\YamlFileLoader($container, new FileLocator('config/packages'));
		$loader->load($this->getAlias().'.yaml');

		//Load configuration
		$configuration = $this->getConfiguration($configs, $container);
		$config = $this->processConfiguration($configuration, $configs);

		//Set default config in parameter
		if (!$container->hasParameter($alias = $this->getAlias())) {
			$container->setParameter($alias, $config[$alias]);
		//Fill missing entries
		} else {
			//Change in config flag
			$change = false;

			//Iterate on each user configuration keys
			foreach($container->getParameter($alias) as $k => $v) {
				//Check if value is an array
				if (is_array($v)) {
					//Iterate on each array keys
					foreach($v as $sk => $sv) {
						//Check if sub value is an array
						if (is_array($sv)) {
							//TODO: implement sub sub key merging ? (or recursive ?)
							@trigger_error('Nested level > 2 not yet implemented here', E_USER_ERROR);
						//Save sub value
						} else {
							//Trigger changed flag
							$change = true;
							//Replace default value with user provided one
							$config[$alias][$k][$sk] = $sv;
						}
					}
				//Save value
				} else {
					//Trigger changed flag
					$change = true;
					//Replace default value with user provided one
					$config[$alias][$k] = $v;
				}
			}

			//Check if change occured
			if ($change) {
				//Save parameters
				$container->setParameter($alias, $config[$alias]);
			}
		}
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
