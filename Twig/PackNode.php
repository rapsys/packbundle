<?php

namespace Rapsys\PackBundle\Twig;

class PackNode extends \Twig\Node\Node {
	/**
	 * Compile the body node
	 *
	 * @param $compiler
	 */
	public function compile(\Twig\Compiler $compiler) {
		//Add $context['<name>'] = '<value>'; in twig template
		$compiler
			->addDebugInfo($this)
			->write('$context[\''.$this->getAttribute('name').'\'] = ')
			->repr($this->getAttribute('output'))
			->raw(";\n")
			->subcompile($this->getNode('value'))
			->raw(";\n");
	}
}
