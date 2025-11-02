<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Updater {
	use Singleton;

	const REPO       = 'Lucasedu191/Sou-Mais-Localizador';
	const API_LATEST = 'https://api.github.com/repos/%s/releases/latest';
	const CACHE_KEY  = 'soumais_locator_latest_release';
	const CACHE_TTL  = HOUR_IN_SECONDS * 3;

	protected function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'maybe_set_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'purge_cache' ], 10, 2 );
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

		$release = $this->get_latest_release( true );

		if ( ! $release ) {
			return $result;
		}

		$info                 = new \stdClass();
		$info->name           = __( 'Sou Mais Localizador', 'soumais-localizador' );
		$info->slug           = $this->get_slug();
		$info->version        = $this->normalize_version( $release['tag_name'] ?? '' );
		$info->author         = '<a href="https://academiasoumais.com.br/">Sou Mais</a>';
		$info->homepage       = $release['html_url'] ?? 'https://github.com/' . self::REPO;
		$info->download_link  = $this->get_package_url( $release );
		$info->requires       = '';
		$info->tested         = '';
		$timestamp = isset( $release['published_at'] ) ? strtotime( $release['published_at'] ) : false;
		$info->last_updated   = $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
		$info->sections       = [
			'description' => wp_kses_post( $release['body'] ?? __( 'Atualização do plugin Sou Mais Localizador.', 'soumais-localizador' ) ),
		];

		return $info;
	}

	protected function get_latest_release( $force_refresh = false ) {
		$cache = get_site_transient( self::CACHE_KEY );

		if ( $force_refresh || false === $cache ) {
			$response = wp_remote_get(
				sprintf( self::API_LATEST, self::REPO ),
				[
					'headers' => [
						'Accept'     => 'application/vnd.github+json',
						'User-Agent' => 'SouMaisLocator/' . SOUMAIS_LOCATOR_VERSION,
					],
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

			$body  = wp_remote_retrieve_body( $response );
			$cache = json_decode( $body, true );

			if ( ! is_array( $cache ) ) {
				return null;
			}

			set_site_transient( self::CACHE_KEY, $cache, self::CACHE_TTL );
		}

		return $cache;
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

	public function purge_cache( $upgrader, $options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$plugin_file = plugin_basename( SOUMAIS_LOCATOR_FILE );
			if ( in_array( $plugin_file, $options['plugins'], true ) ) {
				delete_site_transient( self::CACHE_KEY );
			}
		}
	}
}
