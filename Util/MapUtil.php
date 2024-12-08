<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Util;

use Psr\Container\ContainerInterface;

use Rapsys\PackBundle\RapsysPackBundle;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;

/**
 * Manages map
 */
class MapUtil {
	/**
	 * Alias string
	 */
	protected string $alias;

	/**
	 * Config array
	 */
	protected array $config;

	/**
	 * Creates a new image util
	 *
	 * @param ContainerInterface $container The container instance
	 * @param RouterInterface $router The RouterInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 */
	public function __construct(protected ContainerInterface $container, protected RouterInterface $router, protected SluggerUtil $slugger) {
		//Retrieve config
		$this->config = $container->getParameter($this->alias = RapsysPackBundle::getAlias());
	}
}
