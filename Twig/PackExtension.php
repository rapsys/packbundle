<?php
// src/Rapsys/PackBundle/Twig/PackExtension.php
namespace Rapsys\PackBundle\Twig;

use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Asset\Packages;

class PackExtension extends \Twig_Extension {
	//The config
	private $config;

	//The output
	private $output;

	//The filter
	private $filters;

	public function __construct(FileLocator $fileLocator, ContainerInterface $containerInterface, Packages $assetsPackages) {
		//Set file locator
		$this->fileLocator = $fileLocator;
		//Set container interface
		$this->containerInterface = $containerInterface;
		//Set assets packages
		$this->assetsPackages = $assetsPackages;

		//Retrieve bundle config
		if ($parameters = $containerInterface->getParameter($this->getAlias())) {
			foreach($parameters as $k => $v) {
				$this->$k = $v;
			}
		}
	}

	public function getTokenParsers() {
		return array(
			new PackTokenParser($this->fileLocator, $this->assetsPackages, $this->config, 'stylesheet', $this->output['css'], $this->filters['css']),
			new PackTokenParser($this->fileLocator, $this->assetsPackages, $this->config, 'javascript', $this->output['js'], $this->filters['js']),
			new PackTokenParser($this->fileLocator, $this->assetsPackages, $this->config, 'image', $this->output['img'], $this->filters['img'])
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias() {
		return 'rapsys_pack';
	}
}
