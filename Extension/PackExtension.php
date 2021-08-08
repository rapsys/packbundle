<?php
// src/Rapsys/PackBundle/Twig/PackExtension.php
namespace Rapsys\PackBundle\Twig;

use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Asset\PackageInterface;
use Twig\Extension\AbstractExtension;

class PackExtension extends AbstractExtension {
	//The config
	private $config;

	//The output
	private $output;

	//The filter
	private $filters;

	//The file locator
	protected $locator;

	//The assets package
	protected $package;

	public function __construct(FileLocator $locator, ContainerInterface $container, PackageInterface $package) {
		//Set file locator
		$this->locator = $locator;

		//Set assets packages
		$this->package = $package;

		//Retrieve bundle config
		if ($parameters = $container->getParameter($this->getAlias())) {
			//Set config, output and filters arrays
			foreach(['config', 'output', 'filters'] as $k) {
				$this->$k = $parameters[$k];
			}
		}
	}

	public function getTokenParsers() {
		return [
			new PackTokenParser($this->locator, $this->package, $this->config, 'stylesheet', $this->output['css'], $this->filters['css']),
			new PackTokenParser($this->locator, $this->package, $this->config, 'javascript', $this->output['js'], $this->filters['js']),
			new PackTokenParser($this->locator, $this->package, $this->config, 'image', $this->output['img'], $this->filters['img'])
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias() {
		return 'rapsys_pack';
	}
}
