<?php

namespace Rapsys\PackBundle\Asset;

use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\PathPackage as BasePackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * (@inheritdoc)
 */
class PathPackage extends BasePackage {
	//The base path
	protected $basePath;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(string $basePath, VersionStrategyInterface $versionStrategy, ContextInterface $context = null) {
		parent::__construct($basePath, $versionStrategy, $context);

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
