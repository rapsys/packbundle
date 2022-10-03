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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

use Rapsys\PackBundle\RapsysPackBundle;

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

		//Detect when no user configuration is provided
		if ($configs === [[]]) {
			//Prepend default config
			$container->prependExtensionConfig(self::getAlias(), $config);
		}

		//Save configuration in parameters
		$container->setParameter(self::getAlias(), $config);

		//Set rapsys_pack.path key
		$container->setParameter(self::getAlias().'.path', $config['path']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias(): string {
		return RapsysPackBundle::getAlias();
	}
}
