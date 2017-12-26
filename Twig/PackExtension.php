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
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->prefix, 'stylesheet', $this->coutput, $this->cfilter),
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->prefix, 'javascript', $this->joutput, $this->jfilter),
			new PackTokenParser($this->fileLocator, $this->containerInterface, $this->prefix, 'image', $this->ioutput, $this->ifilter),
		);
	}
}
