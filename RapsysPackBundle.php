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

use Rapsys\PackBundle\DependencyInjection\RapsysPackExtension;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * {@inheritdoc}
 */
class RapsysPackBundle extends Bundle {
	/**
	 * {@inheritdoc}
	 */
	public function getContainerExtension(): ?ExtensionInterface {
		//Return created container extension
		return $this->createContainerExtension();
	}

	/**
	 * Return bundle alias
	 *
	 * @return string The bundle alias
	 */
	public static function getBundleAlias(): string {
		//With namespace
		if ($npos = strrpos(static::class, '\\')) {
			//Set name pos
			$npos++;

			//With single namespace
			$nspos = strpos(static::class, '\\');
			//Without namespace
		} else {
			//Set name pos
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

		//With namespace
		if ($npos) {
			//Return prefixed class name
			return strtolower(substr(static::class, 0, $nspos).'/'.substr(static::class, $npos, $bpos));
		}

		//Return class name
		return strtolower(substr(static::class, $npos, $bpos));
	}

	/**
	 * Return alias
	 *
	 * @return string The alias
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

		//Return lowercase bundle alias
		return strtolower(substr(static::class, $npos, $bpos));
	}

	/**
	 * Return bundle version
	 *
	 * @return string The bundle version
	 */
	public static function getVersion(): string {
		//Return version
		return '0.5.1';
	}
}
