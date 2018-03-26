<?php
// src/Rapsys/PackBundle/Twig/PackExtension.php
namespace Rapsys\PackBundle\Twig;

use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Asset\Packages;
use Twig\TwigFilter;

class PackExtension extends \Twig_Extension {
	public function __construct(FileLocator $fileLocator, ContainerInterface $containerInterface, Packages $assetsPackages) {
		//Set file locator
		$this->fileLocator = $fileLocator;
		//Set container interface
		$this->containerInterface = $containerInterface;
		//Set assets packages
		$this->assetsPackages = $assetsPackages;

		//Set default prefix
		//XXX: require symfony 3.3
		$this->prefix = $this->containerInterface->getParameter('kernel.project_dir').'/web/';

		//Set default coutput
		$this->coutput = 'css/*.pack.css';
		//Set default joutput
		$this->joutput = 'js/*.pack.js';
		//Set default ioutput
		$this->ioutput = 'img/*.pack.jpg';

		//Set default cfilter
		$this->cfilter = array('CPackFilter');
		//Set default jfilter
		$this->jfilter = array('JPackFilter');
		//Set default ifilter
		$this->ifilter = array('IPackFilter');

		//Load configuration
		if ($containerInterface->hasParameter('rapsys_pack')) {
			if ($parameters = $containerInterface->getParameter('rapsys_pack')) {
				foreach($parameters as $k => $v) {
					if (isset($this->$k) && !empty($v)) {
						$this->$k = $v;
					}
				}
			}
		}

		//Fix prefix
		$this->prefix = $this->fileLocator->locate($this->prefix);
	}

	public function getTokenParsers() {
		return array(
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->assetsPackages, $this->prefix, 'stylesheet', $this->coutput, $this->cfilter),
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->assetsPackages, $this->prefix, 'javascript', $this->joutput, $this->jfilter),
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->assetsPackages, $this->prefix, 'image', $this->ioutput, $this->ifilter)
		);
	}

	public function getFilters() {
		return array(
			new TwigFilter(
				'bb2html',
				function($text) {
					$ctx = bbcode_create(
						array(
							'' => array('type' => BBCODE_TYPE_ROOT),
							'code' => array(
								'type' => BBCODE_TYPE_OPTARG,
								'open_tag' => '<pre class="{PARAM}">',
								'close_tag' => '</pre>',
								'default_arg' => '{CONTENT}'
							),
							'ul' => array(
								'type' => BBCODE_TYPE_NOARG,
								'open_tag' => '<ul>',
								'close_tag' => '</ul>',
								'childs' => 'li'
							),
							'li' => array(
								'type' => BBCODE_TYPE_NOARG,
								'open_tag' => '<li>',
								'close_tag' => '</li>',
								'parent' => 'ul',
								'childs' => 'url'
							),
							'url' => array(
								'type' => BBCODE_TYPE_OPTARG,
								'open_tag' => '<a href="{PARAM}">',
								'close_tag' => '</a>',
								'default_arg' => '{CONTENT}',
								'parent' => 'p,li'
							)
						)
					);
					$text = nl2br(bbcode_parse($ctx, htmlspecialchars($text)));
					if (preg_match_all('#\<pre[^>]*\>(.*?)\</pre\>#s', $text, $matches) && !empty($matches[1])) {
						foreach($matches[1] as $string) {
							$text = str_replace($string, str_replace('<br />', '', $string), $text);
						}
					}
					if (preg_match_all('#\<ul[^>]*\>(.*?)\</ul\>#s', $text, $matches) && !empty($matches[1])) {
						foreach($matches[1] as $string) {
							$text = str_replace($string, str_replace('<br />', '', $string), $text);
						}
					}
					$text = preg_replace(
						array('#(<br />(\r?\n?))*<pre#s', '#</pre>(<br />(\r?\n?))*#', '#(<br />(\r?\n?))*<ul#s', '#</ul>(<br />(\r?\n?))*#', '#(<br />(\r?\n?)){2,}#'),
						array('</p>\2<pre', '</pre>\2<p>', '</p>\2<ul', '</ul>\2<p>', '</p>\2<p>'),
						$text
					);
					return $text;
				},
				array('is_safe' => array('html'))
			)
		);
	}
}
