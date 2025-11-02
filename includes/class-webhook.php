<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Webhook {
	use Singleton;

	protected function init() {
		add_filter( 'soumais_locator_webhook_payload', [ $this, 'append_timestamp' ] );
	}

	public function dispatch( array $payload ) {
		if ( ! Settings::instance()->webhook_enabled() ) {
			return;
		}

		$url = Settings::instance()->get_option( 'webhook_url' );
		if ( empty( $url ) ) {
			return;
		}

		$body = apply_filters( 'soumais_locator_webhook_payload', $payload );

		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json; charset=utf-8',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 8,
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
			do_action( 'soumais_locator_webhook_failed', $body, $response );
		} else {
			do_action( 'soumais_locator_webhook_sent', $body, $response );
		}
	}

	public function append_timestamp( $payload ) {
		$payload['data_envio'] = current_time( 'c', true );
		return $payload;
	}
}
