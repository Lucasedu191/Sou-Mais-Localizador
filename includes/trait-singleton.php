<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

trait Singleton {
	protected static $instance = null;

	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
			static::$instance->init();
		}

		return static::$instance;
	}

	protected function init() {}

	private function __clone() {}

	private function __wakeup() {}
}
