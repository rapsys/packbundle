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
	/**
	 * {@inheritdoc}
	 */
	public function getConfigTreeBuilder() {
		//Get TreeBuilder object
		$treeBuilder = new TreeBuilder('rapsys_pack');

		//Get ExecutableFinder object
		$finder = new ExecutableFinder();

		/**
		 * XXX: Note about the output schemes
		 *
		 * The output files are written based on the output.<ext> scheme with the * replaced by the hashed path of packed files
		 *
		 * The following service configuration make twig render the output file path with the right '/' basePath prefix:
		 * services:
		 *     assets.pack_package:
		 *         class: Rapsys\PackBundle\Asset\PathPackage
		 *         arguments: [ '/', '@assets.empty_version_strategy', '@assets.context' ]
		 *     rapsys_pack.twig.pack_extension:
		 *         class: Rapsys\PackBundle\Twig\PackExtension
		 *         arguments: [ '@file_locator', '@service_container', '@assets.pack_package' ]
		 *         tags: [ twig.extension ]
		 */

		//The bundle default values
		$defaults = [
			'config' => [
				'name' => 'asset_url',
				'scheme' => 'https://',
				'timeout' => (int)ini_get('default_socket_timeout'),
				'agent' => (string)ini_get('user_agent')?:'rapsys_pack/0.1.8',
				'redirect' => 5
			],
			'output' => [
				'css' => '@RapsysPack/css/*.pack.css',
				'js' =>  '@RapsysPack/js/*.pack.js',
				'img' => '@RapsysPack/img/*.pack.jpg'
			],
			'filters' => [
				'css' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Twig\Filter\CPackFilter',
						'args' => [
							$finder->find('cpack', '/usr/local/bin/cpack'),
							'minify'
						]
					]
				],
				'js' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Twig\Filter\JPackFilter',
						'args' => [
							$finder->find('jpack', '/usr/local/bin/jpack'),
							'best'
						]
					]
				],
				'img' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Twig\Filter\IPackFilter',
						'args' => []
					]
				],
			]
		];

		//Here we define the parameters that are allowed to configure the bundle.
		//XXX: see https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php for default value and description
		//XXX: see http://symfony.com/doc/current/components/config/definition.html
		//XXX: see https://github.com/symfony/assetic-bundle/blob/master/DependencyInjection/Configuration.php#L63
		//XXX: see php bin/console config:dump-reference rapsys_pack to dump default config
		//XXX: see php bin/console debug:config rapsys_pack to dump config
		$treeBuilder
			//Parameters
			->getRootNode()
				->addDefaultsIfNotSet()
				->children()
					->arrayNode('config')
						->addDefaultsIfNotSet()
						->children()
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
											->defaultValue($defaults['filters']['css'][0]['class'])
										->end()
										->arrayNode('args')
											/*->isRequired()*/
											->treatNullLike(array())
											->defaultValue($defaults['filters']['css'][0]['args'])
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
											->defaultValue($defaults['filters']['js'][0]['class'])
										->end()
										->arrayNode('args')
											->treatNullLike(array())
											->defaultValue($defaults['filters']['js'][0]['args'])
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
											->defaultValue($defaults['filters']['img'][0]['class'])
										->end()
										->arrayNode('args')
											->treatNullLike(array())
											->defaultValue($defaults['filters']['img'][0]['args'])
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
