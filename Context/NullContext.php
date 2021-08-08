<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Context;

use Symfony\Component\Asset\Context\NullContext as BaseNullContext;

/**
 * {@inheritdoc}
 */
class NullContext extends BaseNullContext {
	/**
	 * Returns the base url
	 *
	 * @return string The base url
	 */
	public function getBaseUrl(): string {
		return '';
	}
}
