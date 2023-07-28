<?php
/**
 * Dependency injection container
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout;

use ReflectionClass;
use stdClass;

defined( 'ABSPATH' ) || exit();

/**
 * Class Container
 *
 * @package Reepay\Checkout
 */
class DIContainer {
	/**
	 * Array of classes for replacement with child classes or implementing interfaces
	 * Parent class name => Child class name
	 * or
	 * Interface name => Interface implementor name
	 *
	 * @var array
	 */
	private array $classes = array();

	/**
	 * Array of created objects
	 *
	 * @var array<string, object>
	 */
	private array $cache = array();

	/**
	 * Set replacement of base class or interface. Clear cached object if it was created before
	 *
	 * @param string        $base_name  base class name.
	 * @param string|object $write write class name or class instance.
	 */
	public function set( string $base_name, $write ) {
		if ( isset( $this->cache[ $base_name ] ) ) {
			unset( $this->cache[ $base_name ] );
		}

		if ( is_string( $write ) ) {
			$this->classes[ $base_name ] = $write;
		} else {
			$this->classes[ $base_name ] = get_class( $write );
			$this->cache[ $base_name ]   = $write;
		}
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
	 * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( $id ): bool {
		return isset( $this->classes[ $id ] ) || class_exists( $id );
	}

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return object Entry.
	 */
	public function get( $id ) {
		if ( isset( $this->cache[ $id ] ) ) {
			return $this->cache[ $id ];
		}

		if ( isset( $this->classes[ $id ] ) ) {
			$id = $this->classes[ $id ];
		}

		if ( ! $this->has( $id ) ) {
			return new stdClass();
		}

		return $this->prepare_object( $id );
	}

	/**
	 * Prepare
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return object Entry.
	 */
	private function prepare_object( string $id ): object {
		$class_reflector = new ReflectionClass( $id );

		$construct_reflector = $class_reflector->getConstructor();

		if ( empty( $construct_reflector ) ) {
			return new $id();
		}

		$construct_arguments = $construct_reflector->getParameters();
		if ( empty( $construct_arguments ) ) {
			return new $id();
		}

		$args = array();
		foreach ( $construct_arguments as $argument ) {
			$argument_type                = $argument->getType()->getName();
			$args[ $argument->getName() ] = $this->get( $argument_type );
		}

		$object = new $id( ...$args );

		$this->cache[ $id ] = $object;

		return $object;
	}

}
