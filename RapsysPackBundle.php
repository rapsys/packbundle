<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * {@inheritdoc}
 */
class RapsysPackBundle extends Bundle {
	/**
	 * Return bundle alias
	 *
	 * @return string The bundle alias
	 */
	public static function getAlias(): string {
		//With namespace
		if ($npos = strrpos(static::class, '\\')) {
			//Set name pos
			$npos++;
		//Without namespace
		} else {
			$npos = 0;
		}

		//With trailing bundle
		if (substr(static::class, -strlen('Bundle'), strlen('Bundle')) === 'Bundle') {
			//Set bundle pos
			$bpos = strlen(static::class) - $npos - strlen('Bundle');
		//Without bundle
		} else {
			//Set bundle pos
			$bpos = strlen(static::class) - $npos;
		}

		//Return underscored lowercase bundle alias
		return Container::underscore(substr(static::class, $npos, $bpos));
	}
}
