<?php
/**
 * Solid Backups Container.
 * Used for Dependency Injection
 *
 * @package Solid_Backups
 * @since 9.1.6
 */

namespace Solid_Backups;

use Solid_Backups\Strauss\StellarWP\ContainerContract\ContainerInterface;
use Solid_Backups\Strauss\lucatume\DI52\Container as DI52Container;

/**
 * @method mixed getVar(string $key, mixed|null $default = null)
 * @method void register(string $serviceProviderClass, string ...$alias)
 * @method self when(string $class)
 * @method self needs(string $id)
 * @method void give(mixed $implementation)
 */
class Container implements ContainerInterface {
	/**
	 * @var DI52Container
	 */
	protected $container;

	/**
	 * Container constructor.
	 *
	 * @param DI52Container $container The container to use.
	 */
	public function __construct( $container = null ) {
		$this->container = $container ?: new DI52Container();
	}

	/**
	 * @inheritDoc
	 */
	public function bind( string $id, $implementation = null, array $afterBuildMethods = null ) {
		$this->container->bind( $id, $implementation, $afterBuildMethods );
	}

	/**
	 * @inheritDoc
	 */
	public function get( string $id ) {
		return $this->container->get( $id );
	}

	/**
	 * @return DI52Container
	 */
	public function get_container() {
		return $this->container;
	}

	/**
	 * @inheritDoc
	 */
	public function has( string $id ) {
		return $this->container->has( $id );
	}

	/**
	 * @inheritDoc
	 */
	public function singleton( string $id, $implementation = null, array $afterBuildMethods = null ) {
		$this->container->singleton( $id, $implementation, $afterBuildMethods );
	}

	/**
	 * Defer all other calls to the container object.
	 */
	public function __call( $name, $args ) {
		return $this->container->{$name}( ...$args );
	}
}
