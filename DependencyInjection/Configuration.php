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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\ExecutableFinder;

use Rapsys\PackBundle\RapsysPackBundle;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 *
 * {@inheritdoc}
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
			'config' => [
				'name' => 'asset_url',
				'scheme' => 'https://',
				'timeout' => (int)ini_get('default_socket_timeout'),
				'agent' => (string)ini_get('user_agent')?:'rapsys_pack/0.2.1',
				'redirect' => 5
			],
			#TODO: migrate to public.path, public.url and router->generateUrl ?
			#XXX: that would means dropping the PathPackage stuff and use static route like rapsys_pack_facebook
			'output' => [
				'css' => '@RapsysPack/css/*.pack.css',
				'js' =>  '@RapsysPack/js/*.pack.js',
				'img' => '@RapsysPack/img/*.pack.jpg'
			],
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
				'js' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Filter\JPackFilter',
						'args' => [
							$finder->find('jpack', '/usr/local/bin/jpack'),
							'best'
						]
					]
				],
				'img' => [
					0 => [
						'class' => 'Rapsys\PackBundle\Filter\IPackFilter',
						'args' => []
					]
				],
			],
			'path' => dirname(__DIR__).'/Resources/public',
		];

		/**
		 * Defines parameters allowed to configure the bundle
		 *
		 * @link https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php
		 * @link http://symfony.com/doc/current/components/config/definition.html
		 * @link https://github.com/symfony/assetic-bundle/blob/master/DependencyInjection/Configuration.php#L63
		 *
		 * @see php bin/console config:dump-reference rapsys_pack to dump default config
		 * @see php bin/console debug:config rapsys_pack to dump config
		 */
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
						->end()
					->end()
					->scalarNode('path')->cannotBeEmpty()->defaultValue($defaults['path'])->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
