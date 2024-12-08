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

use Psr\Container\ContainerInterface;

use Rapsys\PackBundle\Parser\TokenParser;
use Rapsys\PackBundle\RapsysPackBundle;
use Rapsys\PackBundle\Util\IntlUtil;
use Rapsys\PackBundle\Util\SluggerUtil;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\Config\FileLocator;

use Twig\Extension\AbstractExtension;

/**
 * {@inheritdoc}
 */
class PackExtension extends AbstractExtension {
	/**
	 * Config array
	 */
	protected array $config;

	/**
	 * The stream context instance
	 */
	protected mixed $ctx;

	/**
	 * Creates pack extension
	 *
	 * {@inheritdoc}
	 *
	 * @link https://twig.symfony.com/doc/2.x/advanced.html
	 *
	 * @param ContainerInterface $container The ContainerInterface instance
	 * @param IntlUtil $intl The IntlUtil instance
	 * @param FileLocator $locator The FileLocator instance
	 * @param RouterInterface $router The RouterInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 */
	public function __construct(protected ContainerInterface $container, protected IntlUtil $intl, protected FileLocator $locator, protected RouterInterface $router, protected SluggerUtil $slugger) {
		//Retrieve config
		$this->config = $container->getParameter(RapsysPackBundle::getAlias());

		//Set ctx
		$this->ctx = stream_context_create($this->config['context']);
	}

	/**
	 * Returns a filter array to add to the existing list.
	 *
	 * @return \Twig\TwigFilter[]
	 */
	public function getTokenParsers(): array {
		return [
			new TokenParser($this->container, $this->locator, $this->router, $this->slugger, $this->config, $this->ctx, 'css', 'stylesheet'),
			new TokenParser($this->container, $this->locator, $this->router, $this->slugger, $this->config, $this->ctx, 'js', 'javascript'),
			new TokenParser($this->container, $this->locator, $this->router, $this->slugger, $this->config, $this->ctx, 'img', 'image')
		];
	}

	/**
	 * Returns a filter array to add to the existing list.
	 *
	 * @return \Twig\TwigFilter[]
	 */
	public function getFilters(): array {
		return [
			new \Twig\TwigFilter('base64_decode', 'base64_decode'),
			new \Twig\TwigFilter('base64_encode', 'base64_encode'),
			new \Twig\TwigFilter('download', 'file_get_contents', [false, null]),
			new \Twig\TwigFilter('hash', [$this->slugger, 'hash']),
			new \Twig\TwigFilter('intlcurrency', [$this->intl, 'currency']),
			new \Twig\TwigFilter('intldate', [$this->intl, 'date'], ['needs_environment' => true]),
			new \Twig\TwigFilter('intlnumber', [$this->intl, 'number']),
			new \Twig\TwigFilter('lcfirst', 'lcfirst'),
			new \Twig\TwigFilter('short', [$this->slugger, 'short']),
			new \Twig\TwigFilter('slug', [$this->slugger, 'slug']),
			new \Twig\TwigFilter('ucfirst', 'ucfirst'),
			new \Twig\TwigFilter('unshort', [$this->slugger, 'unshort'])
		];
	}
}
