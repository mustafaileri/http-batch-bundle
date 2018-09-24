<?php

declare( strict_types=1 );


namespace Ideasoft\HttpBatchBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class HttpBatchConfiguration implements ConfigurationInterface {


	public function getConfigTreeBuilder() : TreeBuilder {

		$treeBuilder = new TreeBuilder();
		$rootNode    = $treeBuilder->root( 'http_batch' );

		// @formatter:off
		$rootNode
			->children()
				->integerNode( 'max_calls_in_a_request' )
					->info( 'Limits the amount of calls in a single batch request' )
					->defaultValue( 100 )
					->min( 1 )
				->end()
			->end();
		// @formatter:on

		return $treeBuilder;

	} // getConfigTreeBuilder


} // HttpBatchConfiguration