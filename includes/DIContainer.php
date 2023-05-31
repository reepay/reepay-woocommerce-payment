<?php
/**
 * Dependency injection container
 *
 * @package Reepay\Checkout
 */

namespace Reepay\Checkout;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use stdClass;

defined( 'ABSPATH' ) || exit();

/**
 * Class Container
 * @package Reepay\Checkout
 */
class DIContainer implements ContainerInterface {
	/**
	 * Array of classes for replacement with child classes or implementing interfaces
	 * Parent class name => Child class name
	 * or
	 * Interface name => Interface implementor name
	 *
	 * @var array
	 */
	private array $classes = [];

	/**
	 * Array of created objects
	 *
	 * @var array<string, object>
	 */
	private array $cache = [];

	/**
	 * Set replacement of base class or interface. Clear cached object if it was created before
	 *
	 * @param string $base_name  base class name.
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
	public function has( string $id ): bool {
		return isset( $this->classes[ $id ] ) || class_exists( $id );
	}

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return object Entry.
	 */
	public function get( string $id ): object {
		if ( isset( $this->cache[ $id ] ) ) {
			return $this->cache[ $id ];
		}

		if ( isset( $this->classes[ $id ] ) ) {
			$id = $this->classes[ $id ];
		}

		if ( ! $this->has( $id ) ) {
			return new stdClass();
		}

		return $this->prepareObject( $id );
	}

	/**
	 * Prepare
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return object Entry.
	 */
	private function prepareObject( string $id ): object {
		$classReflector = new ReflectionClass( $id );

		$constructReflector = $classReflector->getConstructor();

		if ( empty( $constructReflector ) ) {
			return new $id;
		}

		$constructArguments = $constructReflector->getParameters();
		if ( empty( $constructArguments ) ) {
			return new $id;
		}

		$args = [];
		foreach ( $constructArguments as $argument ) {
			// Получаем тип аргумента
			$argumentType = $argument->getType()->getName();
			// Получаем сам аргумент по его типу из контейнера
			$args[ $argument->getName() ] = $this->get( $argumentType );
		}

		$object = new $id( ...$args );

		$this->cache[ $id ] = $object;

		return $object;
	}

}