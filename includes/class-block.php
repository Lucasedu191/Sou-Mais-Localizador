<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Block {
	use Singleton;

	protected function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block() {
		register_block_type(
			SOUMAIS_LOCATOR_PATH . 'blocks/localizador',
			[
				'render_callback' => [ $this, 'render' ],
				'editor_script'   => 'soumais-locator-block',
			]
		);
	}

	public function render( $attributes ) {
		$atts = [
			'layout'        => $attributes['layout'] ?? 'full',
			'radius'        => $attributes['radius'] ?? Settings::instance()->get_option( 'default_radius', 10 ),
			'show_whatsapp' => ! empty( $attributes['show_whatsapp'] ) ? 'yes' : 'no',
			'redirect'      => $attributes['redirect'] ?? Settings::instance()->get_option( 'base_url', home_url() ),
		];

		return Shortcode::instance()->render( $atts );
	}
}
