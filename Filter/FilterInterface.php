<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Filter;

/**
 * Filter interface definition
 *
 * @todo do we need something else ? (like a constructor that read parameters or else)
 */
interface FilterInterface {
	/**
	 * Process function
	 *
	 * @param string $content The content to process
	 * @return string The processed content
	 */
	public function process(string $content): string;
}
