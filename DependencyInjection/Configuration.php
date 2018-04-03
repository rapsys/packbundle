<?php

namespace Rapsys\PackBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Process\ExecutableFinder;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface {
	//Constructor required to derivate prefix from kernel.project_dir
	public function __construct($projectDir) {
		$this->projectDir = $projectDir;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfigTreeBuilder() {
		$treeBuilder = new TreeBuilder();
		$finder = new ExecutableFinder();

		#TODO: see how we deal with asset url generation: see Rapsys/PackBundle/Twig/PackTokenParser.php +243
		#framework:
		#    assets:
		#        packages:
		#            rapsys_pack:
		#                base_path: '/'
		#                version: ~
		#
		## Force cache disable to regenerate resources each time
		##twig:
		##    cache: ~

		//Here we define the parameters that are allowed to configure the bundle.
		//TODO: see https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php for default value and description
		//TODO: see http://symfony.com/doc/current/components/config/definition.html
		//TODO: see https://github.com/symfony/assetic-bundle/blob/master/DependencyInjection/Configuration.php#L63
		//TODO: use bin/console config:dump-reference to dump class infos
		$treeBuilder
			//Parameters
			->root('parameters')
				->children()
					->arrayNode('rapsys_pack')
						->children()
							->scalarNode('coutput')->defaultValue('css/*.pack.css')->end()
							->scalarNode('joutput')->defaultValue('js/*.pack.js')->end()
							->scalarNode('ioutput')->defaultValue('img/*.pack.jpg')->end()
							->arrayNode('cfilter')
								->treatNullLike(array())
								->scalarPrototype()->end()
								->defaultValue(array('Rapsys\PackBundle\Twig\Filter\CPackFilter'))
							->end()
							->arrayNode('jfilter')
								->treatNullLike(array())
								->scalarPrototype()->end()
								->defaultValue(array('Rapsys\PackBundle\Twig\Filter\JPackFilter'))
							->end()
							->arrayNode('ifilter')
								->treatNullLike(array())
								->scalarPrototype()->end()
								->defaultValue(array('Rapsys\PackBundle\Twig\Filter\IPackFilter'))
							->end()
							->scalarNode('prefix')->defaultValue($this->projectDir)->end()
							->scalarNode('scheme')->defaultValue('https://')->end()
							->integerNode('timeout')->min(0)->defaultValue((int)ini_get('default_socket_timeout'))->end()
							->scalarNode('agent')->defaultValue(ini_get('user_agent'))->end()
							->integerNode('redirect')->min(1)->defaultValue(20)->end()
						->end()
					->end()
					->arrayNode('rapsys_pack_cpackfilter')
						->children()
							->scalarNode('bin')->defaultValue(function () use ($finder) { return $finder->find('cpack', '/usr/local/bin/cpack'); })->end()
						->end()
					->end()
					->arrayNode('rapsys_pack_jpackfilter')
						->children()
							->scalarNode('bin')->defaultValue(function () use ($finder) { return $finder->find('jpack', '/usr/local/bin/jpack'); })->end()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
