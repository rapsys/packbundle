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
	//The project dir
	private $projectDir;

	//Constructor required to derivate prefix from kernel.project_dir
	public function __construct($projectDir) {
		$this->projectDir = $projectDir;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getConfigTreeBuilder() {
		//Get TreeBuilder object
		$treeBuilder = new TreeBuilder('rapsys_pack');

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
				'name' => 'asset_url',
				'scheme' => 'https://',
				'timeout' => (int)ini_get('default_socket_timeout'),
				'agent' => (string)ini_get('user_agent')?:'rapsys_pack/0.1.1',
				'redirect' => 5
			],
			'output' => [
				'css' => 'css/*.pack.css',
				'js' => 'js/*.pack.js',
				'img' => 'img/*.pack.jpg'
			],
			'filters' => [
				'css' => [
					'class' => 'Rapsys\PackBundle\Twig\Filter\CPackFilter',
					'args' => [
						$finder->find('cpack', '/usr/local/bin/cpack'),
						'minify'
					]
				],
				'js' => [
					'class' => 'Rapsys\PackBundle\Twig\Filter\JPackFilter',
					'args' => [
						$finder->find('jpack', '/usr/local/bin/jpack'),
						'best'
					]
				],
				'img' => [
					'class' => 'Rapsys\PackBundle\Twig\Filter\IPackFilter',
					'args' => []
				],
			]
		];

		//Here we define the parameters that are allowed to configure the bundle.
		$treeBuilder
			//Parameters
			->getRootNode()
				#->cannotBeEmpty()
				->addDefaultsIfNotSet()
				->children()
					->arrayNode('config')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('prefix')->cannotBeEmpty()->defaultValue($defaults['config']['prefix'])->end()
							->scalarNode('name')->cannotBeEmpty()->defaultValue($defaults['config']['name'])->end()
							->scalarNode('scheme')->cannotBeEmpty()->defaultValue($defaults['config']['scheme'])->end()
							->integerNode('timeout')->min(0)->max(300)->defaultValue($defaults['config']['timeout'])->end()
							->scalarNode('agent')->cannotBeEmpty()->defaultValue($defaults['config']['agent'])->end()
							->integerNode('redirect')->min(1)->max(30)->defaultValue($defaults['config']['redirect'])->end()
						->end()
					->end()
					->arrayNode('output')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('css')->cannotBeEmpty()->defaultValue($defaults['output']['css'])->end()
							->scalarNode('js')->cannotBeEmpty()->defaultValue($defaults['output']['js'])->end()
							->scalarNode('img')->cannotBeEmpty()->defaultValue($defaults['output']['img'])->end()
						->end()
					->end()
					->arrayNode('filters')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('css')
								#XXX: undocumented, see Symfony/Component/Config/Definition/Builder/ArrayNodeDefinition.php +513
								->addDefaultChildrenIfNoneSet()
								->arrayPrototype()
									->children()
										->scalarNode('class')
											->isRequired()
											->cannotBeEmpty()
											->defaultValue($defaults['filters']['css']['class'])
										->end()
										->arrayNode('args')
											/*->isRequired()*/
											->treatNullLike(array())
											->defaultValue($defaults['filters']['css']['args'])
											->scalarPrototype()->end()
										->end()
									->end()
								->end()
							->end()
							->arrayNode('js')
								#XXX: undocumented, see Symfony/Component/Config/Definition/Builder/ArrayNodeDefinition.php +513
								->addDefaultChildrenIfNoneSet()
								->arrayPrototype()
									->children()
										->scalarNode('class')
											->isRequired()
											->cannotBeEmpty()
											->defaultValue($defaults['filters']['js']['class'])
										->end()
										->arrayNode('args')
											->treatNullLike(array())
											->defaultValue($defaults['filters']['js']['args'])
											->scalarPrototype()->end()
										->end()
									->end()
								->end()
							->end()
							->arrayNode('img')
								#XXX: undocumented, see Symfony/Component/Config/Definition/Builder/ArrayNodeDefinition.php +513
								->addDefaultChildrenIfNoneSet()
								->arrayPrototype()
									->children()
										->scalarNode('class')
											->isRequired()
											->cannotBeEmpty()
											->defaultValue($defaults['filters']['img']['class'])
										->end()
										->arrayNode('args')
											->treatNullLike(array())
											->defaultValue($defaults['filters']['img']['args'])
											->scalarPrototype()->end()
										->end()
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
