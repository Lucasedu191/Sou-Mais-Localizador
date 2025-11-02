<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

trait Singleton {
	protected static $instance = null;

	protected function __construct() {}

	final public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
			if ( method_exists( static::$instance, 'init' ) ) {
				static::$instance->init();
			}
		}

		return static::$instance;
	}

	protected function init() {}

	final public function __clone() {
		throw new \Error( 'Cannot clone singleton ' . static::class );
	}

	final public function __wakeup() {
		throw new \Error( 'Cannot unserialize singleton ' . static::class );
	}
}
