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
			//XXX: use a path relative to __DIR__ as console and index do not have the same execution directory
			//XXX: use realpath on var/cache only as alias subdirectory may not yet exists
			'cache' => realpath(dirname(__DIR__).'/../../../var/cache').'/'.$alias,
			'captcha' => [
				'background' => 'white',
				'fill' => '#cff',
				'height' => 52,
				'size' => 45,
				'border' => '#00c3f9',
				'thickness' => 2,
				'width' => 192
			],
			'context' => [
				'http' => [
					'max_redirects' => $_ENV['RAPSYSPACK_REDIRECT'] ?? 20,
					'timeout' => $_ENV['RAPSYSPACK_TIMEOUT'] ?? (($timeout = ini_get('default_socket_timeout')) !== false && $timeout !== '' ? (float)$timeout : 60),
					'user_agent' => $_ENV['RAPSYSPACK_AGENT'] ?? (($agent = ini_get('user_agent')) !== false && $agent !== '' ? (string)$agent : $alias.'/'.($version = RapsysPackBundle::getVersion()))
				]
			],
			'facebook' => [
				'align' => 'center',
				'fill' => 'white',
				'font' => 'default',
				'height' => 630,
				'size' => 60,
				'source' => dirname(__DIR__).'/public/facebook/source.png',
				'border' => '#00c3f9',
				'thickness' => 15,
				'width' => 1200
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
			'fonts' => [
				'default' => '/usr/share/fonts/TTF/dejavu/DejaVuSans.ttf',
				#TODO: move these in veranda config ? with *: %rapsyspack.public%/woff2/*.woff2 ?
				'droidsans' => dirname(__DIR__).'/public/woff2/droidsans.regular.woff2',
				'droidsansb' => dirname(__DIR__).'/public/woff2/droidsans.bold.woff2',
				'droidsansi' => dirname(__DIR__).'/public/woff2/droidserif.italic.woff2',
				'droidsansm' => dirname(__DIR__).'/public/woff2/droidsansmono.regular.woff2',
				'droidserif' => dirname(__DIR__).'/public/woff2/droidserif.regular.woff2',
				'droidserifb' => dirname(__DIR__).'/public/woff2/droidserif.bold.woff2',
				'droidserifbi' => dirname(__DIR__).'/public/woff2/droidserif.bolditalic.woff2',
				'irishgrover' => dirname(__DIR__).'/public/woff2/irishgrover.v10.woff2',
				'lemon' => dirname(__DIR__).'/public/woff2/lemon.woff2',
				'notoemoji' => dirname(__DIR__).'/public/woff2/notoemoji.woff2'
			],
			'map' => [
				'border' => '#00c3f9',
				'fill' => '#cff',
				'height' => 640,
				'quality' => 70,
				'radius' => 5,
				'server' => 'osm',
				'thickness' => 2,
				'tz' => 256,
				'width' => 640,
				'zoom' => 17
			],
			'multi' => [
				'border' => '#00c3f9',
				'fill' => '#cff',
				'height' => 640,
				'highborder' => '#3333c3',
				'highfill' => '#c3c3f9',
				'highradius' => 6,
				'highsize' => 30,
				'highthickness' => 4,
				'quality' => 70,
				'radius' => 5,
				'server' => 'osm',
				'size' => 20,
				'thickness' => 2,
				'tz' => 256,
				'width' => 640,
				'zoom' => 17
			],
			'prefixes' => [
				'captcha' => 'captcha',
				'css' => 'css',
				'facebook' => 'facebook',
				'img' => 'img',
				'map' => 'map',
				'multi' => 'multi',
				'pack' => 'pack',
				'thumb' => 'thumb',
				'js' => 'js'
			],
			//XXX: use a path relative to __DIR__ as console and index do not have the same execution directory
			'public' => dirname(__DIR__).'/public',
			'routes' => [
				'css' => 'rapsyspack_css',
				'img' => 'rapsyspack_img',
				'js' => 'rapsyspack_js'
			],
			'servers' => [
				'cycle' => 'http://a.tile.thunderforest.com/cycle/{Z}/{X}/{Y}.png',
				'osm' => 'https://tile.openstreetmap.org/{Z}/{X}/{Y}.png',
				'transport' => 'http://a.tile.thunderforest.com/transport/{Z}/{X}/{Y}.png'
			],
			'thumb' => [
				'height' => 128,
				'width' => 128
			],
			'tokens' => [
				'css' => 'asset',
				'img' => 'asset',
				'js' => 'asset'
			]
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
					->scalarNode('cache')->cannotBeEmpty()->defaultValue($defaults['cache'])->end()
					->arrayNode('captcha')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('background')->cannotBeEmpty()->defaultValue($defaults['captcha']['background'])->end()
							->scalarNode('fill')->cannotBeEmpty()->defaultValue($defaults['captcha']['fill'])->end()
							->scalarNode('height')->cannotBeEmpty()->defaultValue($defaults['captcha']['height'])->end()
							->scalarNode('size')->cannotBeEmpty()->defaultValue($defaults['captcha']['size'])->end()
							->scalarNode('border')->cannotBeEmpty()->defaultValue($defaults['captcha']['border'])->end()
							->scalarNode('thickness')->cannotBeEmpty()->defaultValue($defaults['captcha']['thickness'])->end()
							->scalarNode('width')->cannotBeEmpty()->defaultValue($defaults['captcha']['width'])->end()
						->end()
					->end()
					->arrayNode('context')
						->addDefaultsIfNotSet()
						->children()
							->arrayNode('http')
							->addDefaultsIfNotSet()
							->children()
								->scalarNode('max_redirects')->defaultValue($defaults['captcha']['max_redirects'])->end()
								->scalarNode('timeout')->defaultValue($defaults['captcha']['timeout'])->end()
								->scalarNode('user_agent')->cannotBeEmpty()->defaultValue($defaults['captcha']['user_agent'])->end()
							->end()
						->end()
					->end()
					->arrayNode('facebook')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('align')->cannotBeEmpty()->defaultValue($defaults['facebook']['align'])->end()
							->scalarNode('fill')->cannotBeEmpty()->defaultValue($defaults['facebook']['fill'])->end()
							->scalarNode('font')->cannotBeEmpty()->defaultValue($defaults['facebook']['font'])->end()
							->scalarNode('height')->cannotBeEmpty()->defaultValue($defaults['facebook']['height'])->end()
							->scalarNode('size')->cannotBeEmpty()->defaultValue($defaults['facebook']['size'])->end()
							->scalarNode('source')->cannotBeEmpty()->defaultValue($defaults['facebook']['source'])->end()
							->scalarNode('border')->cannotBeEmpty()->defaultValue($defaults['facebook']['border'])->end()
							->scalarNode('thickness')->cannotBeEmpty()->defaultValue($defaults['facebook']['thickness'])->end()
							->scalarNode('width')->cannotBeEmpty()->defaultValue($defaults['facebook']['width'])->end()
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
					->arrayNode('fonts')
						->treatNullLike([])
						->defaultValue($defaults['fonts'])
						->scalarPrototype()->end()
					->end()
					->arrayNode('map')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('border')->cannotBeEmpty()->defaultValue($defaults['map']['border'])->end()
							->scalarNode('fill')->cannotBeEmpty()->defaultValue($defaults['map']['fill'])->end()
							->scalarNode('height')->cannotBeEmpty()->defaultValue($defaults['map']['height'])->end()
							->scalarNode('quality')->cannotBeEmpty()->defaultValue($defaults['map']['quality'])->end()
							->scalarNode('radius')->cannotBeEmpty()->defaultValue($defaults['map']['radius'])->end()
							->scalarNode('server')->cannotBeEmpty()->defaultValue($defaults['map']['server'])->end()
							->scalarNode('thickness')->cannotBeEmpty()->defaultValue($defaults['map']['thickness'])->end()
							->scalarNode('tz')->cannotBeEmpty()->defaultValue($defaults['map']['tz'])->end()
							->scalarNode('width')->cannotBeEmpty()->defaultValue($defaults['map']['width'])->end()
							->scalarNode('zoom')->cannotBeEmpty()->defaultValue($defaults['map']['zoom'])->end()
						->end()
					->end()
					->arrayNode('multi')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('border')->cannotBeEmpty()->defaultValue($defaults['multi']['border'])->end()
							->scalarNode('fill')->cannotBeEmpty()->defaultValue($defaults['multi']['fill'])->end()
							->scalarNode('height')->cannotBeEmpty()->defaultValue($defaults['multi']['height'])->end()
							->scalarNode('highborder')->cannotBeEmpty()->defaultValue($defaults['multi']['highborder'])->end()
							->scalarNode('highfill')->cannotBeEmpty()->defaultValue($defaults['multi']['highfill'])->end()
							->scalarNode('highradius')->cannotBeEmpty()->defaultValue($defaults['multi']['highradius'])->end()
							->scalarNode('highsize')->cannotBeEmpty()->defaultValue($defaults['multi']['highsize'])->end()
							->scalarNode('highthickness')->cannotBeEmpty()->defaultValue($defaults['multi']['highthickness'])->end()
							->scalarNode('quality')->cannotBeEmpty()->defaultValue($defaults['multi']['quality'])->end()
							->scalarNode('radius')->cannotBeEmpty()->defaultValue($defaults['multi']['radius'])->end()
							->scalarNode('server')->cannotBeEmpty()->defaultValue($defaults['multi']['server'])->end()
							->scalarNode('size')->cannotBeEmpty()->defaultValue($defaults['multi']['size'])->end()
							->scalarNode('thickness')->cannotBeEmpty()->defaultValue($defaults['multi']['thickness'])->end()
							->scalarNode('tz')->cannotBeEmpty()->defaultValue($defaults['multi']['tz'])->end()
							->scalarNode('width')->cannotBeEmpty()->defaultValue($defaults['multi']['width'])->end()
							->scalarNode('zoom')->cannotBeEmpty()->defaultValue($defaults['multi']['zoom'])->end()
						->end()
					->end()
					->arrayNode('prefixes')
						->treatNullLike([])
						->defaultValue($defaults['prefixes'])
						->scalarPrototype()->end()
					->end()
					->scalarNode('public')->cannotBeEmpty()->defaultValue($defaults['public'])->end()
					->arrayNode('routes')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('css')->cannotBeEmpty()->defaultValue($defaults['routes']['css'])->end()
							->scalarNode('img')->cannotBeEmpty()->defaultValue($defaults['routes']['img'])->end()
							->scalarNode('js')->cannotBeEmpty()->defaultValue($defaults['routes']['js'])->end()
						->end()
					->end()
					->arrayNode('servers')
						->treatNullLike([])
						->defaultValue($defaults['servers'])
						->scalarPrototype()->end()
					->end()
					->arrayNode('thumb')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('height')->cannotBeEmpty()->defaultValue($defaults['thumb']['height'])->end()
							->scalarNode('width')->cannotBeEmpty()->defaultValue($defaults['thumb']['width'])->end()
						->end()
					->end()
					->arrayNode('tokens')
						->addDefaultsIfNotSet()
						->children()
							->scalarNode('css')->cannotBeEmpty()->defaultValue($defaults['tokens']['css'])->end()
							->scalarNode('img')->cannotBeEmpty()->defaultValue($defaults['tokens']['img'])->end()
							->scalarNode('js')->cannotBeEmpty()->defaultValue($defaults['tokens']['js'])->end()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
