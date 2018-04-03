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
		//Get TreeBuilder object
		$treeBuilder = new TreeBuilder();

		//Get ExecutableFinder object
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

		//TODO: see https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php for default value and description
		//TODO: see http://symfony.com/doc/current/components/config/definition.html
		//TODO: see https://github.com/symfony/assetic-bundle/blob/master/DependencyInjection/Configuration.php#L63
		//TODO: use bin/console config:dump-reference to dump class infos

		//The bundle default values
		$defaults = [
			'config' => [
				'prefix' => $this->projectDir,
				'scheme' => 'https://',
				'timeout' => (int)ini_get('default_socket_timeout'),
				'agent' => (string)ini_get('user_agent')?:'rapsys_pack/0.0.2',
				'redirect' => 5
			],
			'output' => [
				'css' => 'css/*.pack.css',
				'js' => 'js/*.pack.js',
				'img' => 'img/*.pack.jpg'
			],
			'filter' => [
				'css' => [
					'Rapsys\PackBundle\Twig\Filter\CPackFilter' => [$finder->find('cpack', '/usr/local/bin/cpack')]
				],
				'js' => [
					'Rapsys\PackBundle\Twig\Filter\JPackFilter' => [$finder->find('jpack', '/usr/local/bin/jpack')]
				],
				'img' => [
					'Rapsys\PackBundle\Twig\Filter\IPackFilter' => []
				],
			]
		];

		//Here we define the parameters that are allowed to configure the bundle.
		$treeBuilder
			//Parameters
			->root('parameters')
				->isRequired()
				->addDefaultsIfNotSet()
				->children()
					->arrayNode('rapsys_pack')
						->isRequired()
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('config')
								->isRequired()
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('prefix')->isRequired()->defaultValue($defaults['config']['prefix'])->end()
									->scalarNode('scheme')->isRequired()->defaultValue($defaults['config']['scheme'])->end()
									->integerNode('timeout')->isRequired()->min(0)->defaultValue($defaults['config']['timeout'])->end()
									->scalarNode('agent')->isRequired()->defaultValue($defaults['config']['agent'])->end()
									->integerNode('redirect')->isRequired()->min(1)->defaultValue($defaults['config']['redirect'])->end()
								->end()
							->end()
							->arrayNode('output')
								->isRequired()
								->addDefaultsIfNotSet()
								->children()
									->scalarNode('css')->isRequired()->defaultValue($defaults['output']['css'])->end()
									->scalarNode('js')->isRequired()->defaultValue($defaults['output']['js'])->end()
									->scalarNode('img')->isRequired()->defaultValue($defaults['output']['img'])->end()
								->end()
							->end()
							->arrayNode('filter')
								->isRequired()
								->addDefaultsIfNotSet()
								->children()
									->arrayNode('css')
										->isRequired()
										->treatNullLike(array())
										->scalarPrototype()->end()
										->defaultValue($defaults['filter']['css'])
									->end()
									->arrayNode('js')
										->isRequired()
										->treatNullLike(array())
										->scalarPrototype()->end()
										->defaultValue($defaults['filter']['js'])
									->end()
									->arrayNode('img')
										->isRequired()
										->treatNullLike(array())
										->scalarPrototype()->end()
										->defaultValue($defaults['filter']['img'])
									->end()
								->end()
							->end()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
