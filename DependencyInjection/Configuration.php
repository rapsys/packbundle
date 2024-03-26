<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\DependencyInjection;

use Rapsys\PackBundle\RapsysPackBundle;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\ExecutableFinder;

/**
 * {@inheritdoc}
 *
 * This is the class that validates and merges configuration from your app/config files.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/configuration.html
 */
class Configuration implements ConfigurationInterface {
	/**
	 * {@inheritdoc}
	 */
	public function getConfigTreeBuilder(): TreeBuilder {
		//Get TreeBuilder object
		$treeBuilder = new TreeBuilder($alias = RapsysPackBundle::getAlias());

		//Get ExecutableFinder object
		$finder = new ExecutableFinder();

		//The bundle default values
		$defaults = [
			'filters' => [
				'css' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Filter\CPackFilter',
						'args' => [
							$finder->find('cpack', '/usr/local/bin/cpack'),
							'minify'
						]
					]
				],
				'img' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Filter\IPackFilter',
						'args' => []
					]
				],
				'js' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Filter\JPackFilter',
						'args' => [
							$finder->find('jpack', '/usr/local/bin/jpack'),
							'best'
						]
					]
				]
			],
			#TODO: migrate to public.path, public.url and router->generateUrl ?
			#XXX: that would means dropping the PathPackage stuff and use static route like rapsyspack_facebook
			'output' => [
				'css' => '@RapsysPack/css/*.pack.css',
				'img' => '@RapsysPack/img/*.pack.jpg',
				'js' =>  '@RapsysPack/js/*.pack.js'
			],
			'path' => dirname(__DIR__).'/Resources/public',
			'token' => 'asset_url'
		];

		/**
		 * Defines parameters allowed to configure the bundle
		 *
		 * @link https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php
		 * @link http://symfony.com/doc/current/components/config/definition.html
		 * @link https://github.com/symfony/assetic-bundle/blob/master/DependencyInjection/Configuration.php#L63
		 *
		 * @see bin/console config:dump-reference rapsyspack to dump default config
		 * @see bin/console debug:config rapsyspack to dump config
		 */
		$treeBuilder
			//Parameters
			->getRootNode()
				->addDefaultsIfNotSet()
				->children()
					->arrayNode('filters')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('css')
								/**
								 * Undocumented
								 *
								 * @see Symfony/Component/Config/Definition/Builder/ArrayNodeDefinition.php +513
								 */
								->addDefaultChildrenIfNoneSet()
								->arrayPrototype()
									->children()
										->scalarNode('class')
											->isRequired()
											->cannotBeEmpty()
											->defaultValue($defaults['filters']['css'][0]['class'])
										->end()
										->arrayNode('args')
											//->isRequired()
											->treatNullLike([])
											->defaultValue($defaults['filters']['css'][0]['args'])
											->scalarPrototype()->end()
										->end()
									->end()
								->end()
							->end()
							->arrayNode('img')
								/**
								 * Undocumented
								 *
								 * @see Symfony/Component/Config/Definition/Builder/ArrayNodeDefinition.php +513
								 */
								->addDefaultChildrenIfNoneSet()
								->arrayPrototype()
									->children()
										->scalarNode('class')
											->isRequired()
											->cannotBeEmpty()
											->defaultValue($defaults['filters']['img'][0]['class'])
										->end()
										->arrayNode('args')
											->treatNullLike([])
											->defaultValue($defaults['filters']['img'][0]['args'])
											->scalarPrototype()->end()
										->end()
									->end()
								->end()
							->end()
							->arrayNode('js')
								/**
								 * Undocumented
								 *
								 * @see Symfony/Component/Config/Definition/Builder/ArrayNodeDefinition.php +513
								 */
								->addDefaultChildrenIfNoneSet()
								->arrayPrototype()
									->children()
										->scalarNode('class')
											->isRequired()
											->cannotBeEmpty()
											->defaultValue($defaults['filters']['js'][0]['class'])
										->end()
										->arrayNode('args')
											->treatNullLike([])
											->defaultValue($defaults['filters']['js'][0]['args'])
											->scalarPrototype()->end()
										->end()
									->end()
								->end()
							->end()
						->end()
					->end()
					->arrayNode('output')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('css')->cannotBeEmpty()->defaultValue($defaults['output']['css'])->end()
							->scalarNode('img')->cannotBeEmpty()->defaultValue($defaults['output']['img'])->end()
							->scalarNode('js')->cannotBeEmpty()->defaultValue($defaults['output']['js'])->end()
						->end()
					->end()
					->scalarNode('path')->cannotBeEmpty()->defaultValue($defaults['path'])->end()
					->scalarNode('token')->cannotBeEmpty()->defaultValue($defaults['token'])->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
