<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\DependencyInjection;

use Rapsys\PackBundle\RapsysPackBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 *
 * {@inheritdoc}
 */
class RapsysPackExtension extends Extension {
	/**
	 * {@inheritdoc}
	 */
	public function load(array $configs, ContainerBuilder $container): void {
		//Load configuration
		$configuration = $this->getConfiguration($configs, $container);

		//Process the configuration to get merged config
		$config = $this->processConfiguration($configuration, $configs);

		//Set bundle alias
		$alias = RapsysPackBundle::getAlias();

		//Detect when no user configuration is provided
		if ($configs === [[]]) {
			//Prepend default config
			$container->prependExtensionConfig($alias, $config);
		}

		//Save configuration in parameters
		$container->setParameter($alias, $config);

		//Set rapsyspack.alias key
		$container->setParameter($alias.'.alias', $alias);

		//Set rapsyspack.cache key
		$container->setParameter($alias.'.cache', $config['cache']);

		//Set rapsyspack.public key
		$container->setParameter($alias.'.public', $config['public']);

		//Set rapsyspack.version key
		$container->setParameter($alias.'.version', RapsysPackBundle::getVersion());
	}

	/**
	 * {@inheritdoc}
	 *
	 * @xxx Required by kernel to load renamed alias configuration
	 */
	public function getAlias(): string {
		return RapsysPackBundle::getAlias();
	}
}
