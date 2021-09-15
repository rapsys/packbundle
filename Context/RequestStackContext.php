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

use Symfony\Component\Asset\Context\RequestStackContext as BaseRequestStackContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * {@inheritdoc}
 */
class RequestStackContext extends BaseRequestStackContext {
	//RequestStack instance
	private $requestStack;

	//Base path
	private $basePath;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(RequestStack $requestStack, string $basePath = '', bool $secure = false) {
		//Call parent constructor
		parent::__construct($requestStack, $basePath, $secure);

		//Set request stack
		$this->requestStack = $requestStack;

		//Set base path
		$this->basePath = $basePath;
	}

	/**
	 * Returns the base url
	 *
	 * @return string The base url
	 */
	public function getBaseUrl(): string {
		//Without request
		if (!$request = $this->requestStack->getMainRequest()) {
			//Return base path
			return $this->basePath;
		}

		//Return base uri
		return $request->getSchemeAndHttpHost().$request->getBaseUrl();
	}
}
