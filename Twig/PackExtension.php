<?php
// src/Rapsys/PackBundle/Twig/PackExtension.php
namespace Rapsys\PackBundle\Twig;

use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PackExtension extends \Twig_Extension {
	public function __construct(FileLocator $fileLocator, ContainerInterface $containerInterface) {
		//Set file locator
		$this->fileLocator = $fileLocator;
		//Set container interface
		$this->containerInterface = $containerInterface;

		//Set default prefix
		$this->prefix = '@RapsysPackBundle/Resources/public/';

		//Set default coutput
		$this->coutput = 'css/*.pack.css';
		//Set default joutput
		$this->joutput = 'js/*.pack.js';

		//Set default cpack
		$this->cpack = '/usr/local/bin/cpack';
		//Set default jpack
		$this->jpack = '/usr/local/bin/jpack';

		//Load configuration
		if ($containerInterface->hasParameter('rapsys_pack')) {
			if ($parameters = $containerInterface->getParameter('rapsys_pack')) {
				foreach($parameters as $k => $v) {
					if (isset($this->$k)) {
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
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->prefix, 'stylesheets', $this->coutput, $this->cpack),
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->prefix, 'javascripts', $this->joutput, $this->jpack),
			#new PackTokenParser($this->fileLocator, $this->containerInterface, $this->prefix, 'image', '*.pack.{tld}'),
		);
	}
}
