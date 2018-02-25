<?php
// src/Rapsys/PackBundle/Twig/Base64Extension.php
namespace Rapsys\PackBundle\Twig;

class Base64Extension extends \Twig_Extension {
	public function getFilters() {
		return array(
			new \Twig_SimpleFilter('base64_encode', 'base64_encode'),
			new \Twig_SimpleFilter('base64_decode', 'base64_decode')
		);
	}
}
