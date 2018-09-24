<?php

namespace Ideasoft\HttpBatchBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class HttpBatchExtension extends Extension {


	public function load( array $configs, ContainerBuilder $container ) {

		$loader = new YamlFileLoader(
			$container,
			new FileLocator( __DIR__ . '/../Resources/config' )
		);

		$loader->load( 'services.yml' );

		$configuration = new HttpBatchConfiguration();

		$config = $this->processConfiguration( $configuration, $configs );

		$container->getDefinition( 'http_batch.handler' )
		          ->replaceArgument( '$max_calls', $config[ 'max_calls_in_a_request' ] );

	} // load

} // HttpBatchExtension
