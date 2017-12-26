<?php

namespace Rapsys\PackBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('rapsys_pack');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

	//TODO: see https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Configuration.php for default value and description
	//TODO: see http://symfony.com/doc/current/components/config/definition.html
	//TODO: use bin/console config:dump-reference to dump class infos
	$rootNode
		->children()
			->scalarNode('jpack')->end()
			->scalarNode('cpack')->end()
			->scalarNode('prefix')->end()
			->scalarNode('scheme')->end()
			->integerNode('timeout')->end()
		->end();

        return $treeBuilder;
    }
}
