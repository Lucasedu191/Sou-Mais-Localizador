<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Updater {
	use Singleton;

	const REPO       = 'Lucasedu191/Sou-Mais-Localizador';
	const API_LATEST = 'https://api.github.com/repos/%s/releases/latest';

	protected function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'maybe_set_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'rename_github_source' ], 10, 4 );
	}

	public function maybe_set_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$plugin_file = plugin_basename( SOUMAIS_LOCATOR_FILE );
		$release     = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->normalize_version( $release['tag_name'] ?? '' );

		if ( ! $remote_version || version_compare( $remote_version, SOUMAIS_LOCATOR_VERSION, '<=' ) ) {
			return $transient;
		}

		$package_url = $this->get_package_url( $release );

		if ( ! $package_url ) {
			return $transient;
		}

		$item              = new \stdClass();
		$item->slug        = $this->get_slug();
		$item->plugin      = $plugin_file;
		$item->new_version = $remote_version;
		$item->url         = $release['html_url'] ?? '';
		$item->package     = $package_url;
		$item->tested      = '';
		$item->requires    = '';

		$transient->response[ $plugin_file ] = $item;

		return $transient;
	}

	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== $this->get_slug() ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$info                = new \stdClass();
		$info->name          = __( 'Sou Mais Localizador', 'soumais-localizador' );
		$info->slug          = $this->get_slug();
		$info->version       = $this->normalize_version( $release['tag_name'] ?? '' );
		$info->author        = '<a href="https://academiasoumais.com.br/">Sou Mais</a>';
		$info->homepage      = $release['html_url'] ?? 'https://github.com/' . self::REPO;
		$info->download_link = $this->get_package_url( $release );
		$info->requires      = '';
		$info->tested        = '';
		$timestamp           = isset( $release['published_at'] ) ? strtotime( $release['published_at'] ) : false;
		$info->last_updated  = $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
		$info->sections      = [
			'description' => wp_kses_post( $release['body'] ?? __( 'Atualização do plugin Sou Mais Localizador.', 'soumais-localizador' ) ),
		];

		return $info;
	}

	protected function get_latest_release() {
		$token   = Settings::instance()->github_token();
		$headers = [
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'SouMaisLocator/' . SOUMAIS_LOCATOR_VERSION,
		];

		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get(
			sprintf( self::API_LATEST, self::REPO ),
			[
				'headers' => $headers,
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return null;
		}

		$body    = wp_remote_retrieve_body( $response );
		$release = json_decode( $body, true );

		return is_array( $release ) ? $release : null;
	}

	protected function get_package_url( array $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'], $asset['name'] ) && 0 === substr_compare( (string) $asset['name'], '.zip', -4, 4, true ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		return $release['zipball_url'] ?? '';
	}

	protected function normalize_version( $tag ) {
		if ( empty( $tag ) ) {
			return '';
		}

		return ltrim( (string) $tag, 'vV' );
	}

	protected function get_slug() {
		return 'soumais-localizador';
	}

	public function rename_github_source( $source, $remote_source, $upgrader, $hook_extra ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== plugin_basename( SOUMAIS_LOCATOR_FILE ) ) {
			return $source;
		}

		$source_basename = basename( untrailingslashit( $source ) );
		$desired_slug    = $this->get_slug();

		if ( $source_basename === $desired_slug ) {
			return $source;
		}

		// Zipball padrão do GitHub vem como Usuario-Repositorio-commit.
		if ( false === stripos( $source_basename, 'Sou-Mais-Localizador' ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$destination = trailingslashit( dirname( untrailingslashit( $source ) ) ) . $desired_slug . '/';

		if ( $wp_filesystem->exists( $destination ) ) {
			$wp_filesystem->delete( $destination, true );
		}

		if ( ! $wp_filesystem->move( $source, $destination, true ) ) {
			return $source;
		}

		return $destination;
	}
}


