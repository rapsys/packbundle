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
	 * @link https://twig.symfony.com/doc/2.x/advanced.html
	 *
	 * {@inheritdoc}
	 */
	public function __construct(protected IntlUtil $intl, protected FileLocator $locator, protected PackageInterface $package, protected SluggerUtil $slugger, protected array $parameters) {
	}

	/**
	 * Returns a filter array to add to the existing list.
	 *
	 * @return \Twig\TwigFilter[]
	 */
	public function getTokenParsers(): array {
		return [
			new TokenParser($this->locator, $this->package, $this->parameters['token'], 'stylesheet', $this->parameters['output']['css'], $this->parameters['filters']['css']),
			new TokenParser($this->locator, $this->package, $this->parameters['token'], 'javascript', $this->parameters['output']['js'], $this->parameters['filters']['js']),
			new TokenParser($this->locator, $this->package, $this->parameters['token'], 'image', $this->parameters['output']['img'], $this->parameters['filters']['img'])
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
