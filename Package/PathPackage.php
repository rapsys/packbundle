<?php

namespace Rapsys\PackBundle\Asset;

use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * (@inheritdoc)
 */
class PathPackage extends Package {
	//The base path
	protected $basePath;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(string $basePath, VersionStrategyInterface $versionStrategy, ContextInterface $context = null) {
		parent::__construct($versionStrategy, $context);

		if (!$basePath) {
			$this->basePath = '/';
		} else {
			if ('/' != $basePath[0]) {
				$basePath = '/'.$basePath;
			}

			$this->basePath = rtrim($basePath, '/').'/';
		}
	}

	/**
	 * @todo Try retrive public dir from the member function BundleNameBundle::getPublicDir() return value ?
	 * @xxx see https://symfony.com/doc/current/bundles.html#overridding-the-bundle-directory-structure
	 * {@inheritdoc}
	 */
	public function getUrl($path) {
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
}
