<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Package;

use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

use Rapsys\PackBundle\Context\NullContext;

/**
 * {@inheritdoc}
 */
class PathPackage extends Package {
	/**
	 * The base url
	 */
	protected string $baseUrl;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(protected string $basePath, protected VersionStrategyInterface $versionStrategy, protected ?ContextInterface $context = null) {
		//Without context use a null context
		$this->context = $this->context ?? new NullContext();

		//Call parent constructor
		parent::__construct($this->versionStrategy, $this->context);

		//Without base path
		if (empty($basePath)) {
			//Set base path
			$this->basePath = '/';
		//With base path
		} else {
			//With relative base path
			if ('/' != $basePath[0]) {
				//Set base path as absolute
				$basePath = '/'.$basePath;
			}

			//Set base path
			$this->basePath = rtrim($basePath, '/').'/';
		}

		//Set base url
		$this->baseUrl = $this->context->getBaseUrl();
	}

	/**
	 * {@inheritdoc}
	 *
	 * Returns an absolute or root-relative public path
	 *
	 * Transform @BundleBundle to bundle and remove /Resources/public fragment from path
	 * This bundle name conversion and bundle prefix are the same as in asset:install command
	 *
	 * @link https://symfony.com/doc/current/bundles.html#overridding-the-bundle-directory-structure
	 * @see vendor/symfony/framework-bundle/Command/AssetsInstallCommand.php +113
	 * @see vendor/symfony/framework-bundle/Command/AssetsInstallCommand.php +141
	 */
	public function getUrl(string $path): string {
		//Match url starting with a bundle name
		if (preg_match('%^@([A-Z][a-zA-Z]*?)(?:Bundle/Resources/public)?/(.*)$%', $path, $matches)) {
			//Handle empty or without replacement pattern basePath
			if (empty($this->basePath) || strpos($this->basePath, '%s') === false) {
				//Set path from hardcoded format
				$path = '/bundles/'.strtolower($matches[1]).'/'.$matches[2];
			//Proceed with basePath pattern replacement
			} else {
				//Set path from basePath pattern
				//XXX: basePath has a trailing / added by constructor
				$path = sprintf($this->basePath, strtolower($matches[1])).$matches[2];
			}
		}

		//Return parent getUrl result
		return parent::getUrl($path);
	}

	/**
	 * Returns an absolute public path.
	 *
	 * @param string $path A path
	 * @return string The absolute public path
	 */
	public function getAbsoluteUrl(string $path): string {
		//Return concated base url and url from path
		return $this->baseUrl.self::getUrl($path);
	}
}
