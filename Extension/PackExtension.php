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
use Rapsys\PackBundle\Util\IntlUtil;
use Rapsys\PackBundle\Util\SluggerUtil;

/**
 * {@inheritdoc}
 */
class PackExtension extends AbstractExtension {
	/**
	 * The filters array
	 */
	protected array $filters;

	/**
	 * The output array
	 */
	protected array $output;

	/**
	 * The token string
	 */
	protected string $token;

	/**
	 * @link https://twig.symfony.com/doc/2.x/advanced.html
	 *
	 * {@inheritdoc}
	 */
	public function __construct(protected ContainerInterface $container, protected IntlUtil $intl, protected FileLocator $locator, protected PackageInterface $package, protected SluggerUtil $slugger) {
		//Retrieve bundle config
		if ($parameters = $container->getParameter(RapsysPackBundle::getAlias())) {
			//Set filters, output arrays and token string
			foreach(['filters', 'output', 'token'] as $k) {
				$this->$k = $parameters[$k];
			}
		}
	}

	/**
	 * Returns a filter array to add to the existing list.
	 *
	 * @return \Twig\TwigFilter[]
	 */
	public function getTokenParsers(): array {
		return [
			new TokenParser($this->locator, $this->package, $this->token, 'stylesheet', $this->output['css'], $this->filters['css']),
			new TokenParser($this->locator, $this->package, $this->token, 'javascript', $this->output['js'], $this->filters['js']),
			new TokenParser($this->locator, $this->package, $this->token, 'image', $this->output['img'], $this->filters['img'])
		];
	}

	/**
	 * Returns a filter array to add to the existing list.
	 *
	 * @return \Twig\TwigFilter[]
	 */
	public function getFilters(): array {
		return [
			new \Twig\TwigFilter('lcfirst', 'lcfirst'),
			new \Twig\TwigFilter('ucfirst', 'ucfirst'),
			new \Twig\TwigFilter('hash', [$this->slugger, 'hash']),
			new \Twig\TwigFilter('unshort', [$this->slugger, 'unshort']),
			new \Twig\TwigFilter('short', [$this->slugger, 'short']),
			new \Twig\TwigFilter('slug', [$this->slugger, 'slug']),
			new \Twig\TwigFilter('intldate', [$this->intl, 'date'], ['needs_environment' => true]),
			new \Twig\TwigFilter('intlnumber', [$this->intl, 'number']),
			new \Twig\TwigFilter('intlcurrency', [$this->intl, 'currency']),
			new \Twig\TwigFilter('download', 'file_get_contents', [false, null]),
			new \Twig\TwigFilter('base64_encode', 'base64_encode'),
			new \Twig\TwigFilter('base64_decode', 'base64_decode')
		];
	}
}
