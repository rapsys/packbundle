<?php
// src/Rapsys/PackBundle/Twig/PackTokenParser.php
namespace Rapsys\PackBundle\Twig;

class PackNode extends \Twig_Node {
	#public function __construct($name, $value, $line, $tag = null) {
	#	parent::__construct(array('value' => $value), array('name' => $name), $line, $tag);
	#}
	public function __construct(array $nodes = array(), array $attributes = array(), $lineno = 0, $tag = null) {
		parent::__construct($nodes, $attributes, $lineno, $tag);
		#$this->output = $attributes['output'];
		#$this->setAttribute($this->getAttribute('name'), $attributes['name']);
	}

	public function compile(\Twig_Compiler $compiler) {

		#header('Content-Type: text/plain');
		#var_dump($this->getNode(0));
		#var_dump($this->attributes);
		#var_dump($compiler->subcompile($this->getNode(0)));
		#exit;
		#$compiler->addDebugInfo($this)->write('echo \'<pre>'.json_encode(array('nodes' => $this->nodes, 'attributes' => $this->attributes)).'</pre>\';');
		$compiler
			->addDebugInfo($this)
			->write('$context[\''.$this->getAttribute('name').'\'] = ')
			->repr($this->getAttribute('output'))
			->raw(";\n")
			->subcompile($this->getNode('value'))
			->raw(";\n");
	}
}
