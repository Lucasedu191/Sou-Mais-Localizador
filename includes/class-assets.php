<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Assets {
	use Singleton;

	protected function init() {
		add_action( 'init', [ $this, 'register_block_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function register_frontend_assets() {
		$suffix      = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$style_file  = file_exists( SOUMAIS_LOCATOR_PATH . "assets/css/frontend{$suffix}.css" ) ? "frontend{$suffix}.css" : 'frontend.css';
		$script_file = file_exists( SOUMAIS_LOCATOR_PATH . "assets/js/frontend{$suffix}.js" ) ? "frontend{$suffix}.js" : 'frontend.js';

		wp_register_style(
			'soumais-locator-frontend',
			SOUMAIS_LOCATOR_URL . 'assets/css/' . $style_file,
			[],
			SOUMAIS_LOCATOR_VERSION
		);

		wp_register_script(
			'soumais-locator-frontend',
			SOUMAIS_LOCATOR_URL . 'assets/js/' . $script_file,
			[ 'wp-api-fetch' ],
			SOUMAIS_LOCATOR_VERSION,
			true
		);
	}

	public function enqueue_admin_assets( $hook ) {
		$should_enqueue = false;
		$dependencies   = [ 'jquery' ];

		if ( 'toplevel_page_soumais_locator' === $hook ) {
			$should_enqueue = true;
		}

		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			$screen = get_current_screen();
			if ( $screen && Post_Type_Unidade::CPT === $screen->post_type ) {
				$should_enqueue = true;
				wp_enqueue_media();
				$dependencies[] = 'wp-media-utils';
			}
		}

		if ( ! $should_enqueue ) {
			return;
		}

		wp_enqueue_style(
			'soumais-locator-admin',
			SOUMAIS_LOCATOR_URL . 'assets/css/admin.css',
			[],
			SOUMAIS_LOCATOR_VERSION
		);

		wp_enqueue_script(
			'soumais-locator-admin',
			SOUMAIS_LOCATOR_URL . 'assets/js/admin.js',
			$dependencies,
			SOUMAIS_LOCATOR_VERSION,
			true
		);
	}

	public function register_block_assets() {
		$suffix      = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$script_file = file_exists( SOUMAIS_LOCATOR_PATH . "assets/js/block{$suffix}.js" ) ? "block{$suffix}.js" : 'block.js';

		wp_register_script(
			'soumais-locator-block',
			SOUMAIS_LOCATOR_URL . 'assets/js/' . $script_file,
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ],
			SOUMAIS_LOCATOR_VERSION,
			true
		);
	}
}
