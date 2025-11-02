<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Plugin {
	use Singleton;

	protected function init() {
		$this->includes();

		register_activation_hook( SOUMAIS_LOCATOR_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( SOUMAIS_LOCATOR_FILE, [ $this, 'deactivate' ] );

		Post_Type_Unidade::instance();
		Post_Type_Lead::instance();
		Settings::instance();
		Assets::instance();
		Shortcode::instance();
		Block::instance();
		REST_API::instance();
		Importer::instance();
		Webhook::instance();

		add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	protected function includes() {
		require_once SOUMAIS_LOCATOR_PATH . 'includes/trait-singleton.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/helpers.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-assets.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-post-type-unidade.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-post-type-lead.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-settings.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-shortcode.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-block.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-rest-api.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-importer.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-webhook.php';
		require_once SOUMAIS_LOCATOR_PATH . 'includes/class-updater.php';
	}

	public function activate() {
		Post_Type_Unidade::instance()->register();
		Post_Type_Lead::instance()->register();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'soumais-localizador',
			false,
			dirname( plugin_basename( SOUMAIS_LOCATOR_FILE ) ) . '/languages'
		);
	}
}
