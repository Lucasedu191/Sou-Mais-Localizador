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

		$url = Settings::instance()->get_webhook_url();
		if ( empty( $url ) ) {
			return;
		}

		$body = [
			'event'  => 'lead_created',
			'source' => 'soumais-localizador',
			'site'   => home_url(),
			'lead'   => $payload,
		];

		$body = apply_filters( 'soumais_locator_webhook_payload', $body );

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
			$this->log_failed_dispatch( $url, $body, $response );
			do_action( 'soumais_locator_webhook_failed', $body, $response );
		} else {
			do_action( 'soumais_locator_webhook_sent', $body, $response );
		}
	}

	public function append_timestamp( $payload ) {
		$payload['sent_at'] = current_time( 'c', true );
		return $payload;
	}

	protected function log_failed_dispatch( $url, $body, $response ) {
		$status = is_wp_error( $response ) ? 'wp_error' : (string) wp_remote_retrieve_response_code( $response );
		$error  = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );

		error_log(
			sprintf(
				'[SouMais Localizador] Falha no envio do webhook para %1$s | status=%2$s | erro=%3$s | payload=%4$s',
				(string) $url,
				$status,
				wp_strip_all_tags( (string) $error ),
				wp_json_encode( $body )
			)
		);
	}
}
