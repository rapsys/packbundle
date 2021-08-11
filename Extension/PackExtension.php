<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Extension;

use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Twig\Extension\AbstractExtension;

use Rapsys\PackBundle\Parser\TokenParser;
use Rapsys\PackBundle\RapsysPackBundle;
use Rapsys\PackBundle\Util\SluggerUtil;

/**
 * {@inheritdoc}
 */
class PackExtension extends AbstractExtension {
	//The config
	private $config;

	//The output
	private $output;

	//The filter
	private $filters;

	//The file locator
	protected $locator;

	//The slugger instance
	protected $slugger;

	//The assets package
	protected $package;

	/**
	 * @link https://twig.symfony.com/doc/2.x/advanced.html
	 *
	 * {@inheritdoc}
	 */
	public function __construct(FileLocator $locator, ContainerInterface $container, PackageInterface $package, SluggerUtil $slugger) {
		//Set file locator
		$this->locator = $locator;

		//Set slugger
		$this->slugger = $slugger;

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

	/**
	 * Returns a list of filters to add to the existing list.
	 *
	 * @return \Twig\TwigFilter[]
	 */
	public function getTokenParsers(): array {
		return [
			new TokenParser($this->locator, $this->package, $this->config, 'stylesheet', $this->output['css'], $this->filters['css']),
			new TokenParser($this->locator, $this->package, $this->config, 'javascript', $this->output['js'], $this->filters['js']),
			new TokenParser($this->locator, $this->package, $this->config, 'image', $this->output['img'], $this->filters['img'])
		];
	}

	/**
	 * Returns a list of filters to add to the existing list.
	 *
	 * @return \Twig\TwigFilter[]
	 */
	public function getFilters(): array {
		return [
			new \Twig\TwigFilter('hash', [$this->slugger, 'hash']),
			new \Twig\TwigFilter('unshort', [$this->slugger, 'unshort']),
			new \Twig\TwigFilter('short', [$this->slugger, 'short'])
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAlias(): string {
		return RapsysPackBundle::getAlias();
	}
}
