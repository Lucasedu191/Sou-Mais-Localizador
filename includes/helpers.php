<?php
namespace SouMais\Locator;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Helpers {
	public static function calculate_distance( $lat1, $lng1, $lat2, $lng2 ) {
		$earth_radius = 6371; // quilômetros.

		$dlat = deg2rad( $lat2 - $lat1 );
		$dlng = deg2rad( $lng2 - $lng1 );

		$a = sin( $dlat / 2 ) * sin( $dlat / 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) * sin( $dlng / 2 );
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return round( $earth_radius * $c, 2 );
	}

	public static function sort_by_distance( array $units ) {
		usort(
			$units,
			static function ( $a, $b ) {
				if ( null === $a['distance'] ) {
					return 1;
				}
				if ( null === $b['distance'] ) {
					return -1;
				}
				if ( $a['distance'] === $b['distance'] ) {
					return 0;
				}
				return ( $a['distance'] < $b['distance'] ) ? -1 : 1;
			}
		);

		return $units;
	}

	public static function format_unit_address( $post_id ) {
		$parts = array_filter(
			[
				get_post_meta( $post_id, '_sou_endereco', true ),
				get_post_meta( $post_id, '_sou_bairro', true ),
				get_post_meta( $post_id, '_sou_cidade', true ),
				get_post_meta( $post_id, '_sou_uf', true ),
			]
		);

		return implode( ' - ', $parts );
	}

	public static function build_cache_key( array $args ) {
		ksort( $args );

		return 'soumais_locator_' . md5( wp_json_encode( $args ) );
	}

	public static function validate_lead_payload( array $params ) {
		$required = [
			'nome',
			'email',
			'telefone',
			'unidade',
		];

		foreach ( $required as $field ) {
			if ( empty( $params[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Campo obrigatório ausente: %s', 'soumais-localizador' ), $field ), [ 'status' => 400 ] );
			}
		}

		if ( ! is_email( $params['email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'E-mail inválido.', 'soumais-localizador' ), [ 'status' => 400 ] );
		}

		$params['telefone'] = self::sanitize_phone( $params['telefone'] );
		if ( strlen( preg_replace( '/\D/', '', $params['telefone'] ) ) < 10 ) {
			return new WP_Error( 'invalid_phone', __( 'Telefone inválido.', 'soumais-localizador' ), [ 'status' => 400 ] );
		}

		if ( ! wp_verify_nonce( $params['nonce'] ?? '', 'soumais_locator' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Não foi possível validar a requisição.', 'soumais-localizador' ), [ 'status' => 403 ] );
		}

		$clean = [
			'nome'      => sanitize_text_field( $params['nome'] ),
			'email'     => sanitize_email( $params['email'] ),
			'telefone'  => $params['telefone'],
			'unidade'   => absint( $params['unidade'] ),
			'origem'    => sanitize_text_field( $params['origem'] ?? '' ),
			'utm_source'=> sanitize_text_field( $params['utm_source'] ?? '' ),
			'utm_medium'=> sanitize_text_field( $params['utm_medium'] ?? '' ),
			'utm_campaign' => sanitize_text_field( $params['utm_campaign'] ?? '' ),
			'utm_term'     => sanitize_text_field( $params['utm_term'] ?? '' ),
			'utm_content'  => sanitize_text_field( $params['utm_content'] ?? '' ),
			'ip'        => sanitize_text_field( $params['ip'] ?? '' ),
			'data_envio'=> sanitize_text_field( $params['data_envio'] ?? '' ),
			'aceite'    => ! empty( $params['aceite'] ) ? 1 : 0,
			'redirect'  => ! empty( $params['redirect'] ) ? esc_url_raw( $params['redirect'] ) : '',
		];

		if ( ! empty( $params['recaptcha'] ) ) {
			$clean['recaptcha'] = sanitize_text_field( $params['recaptcha'] );
		}

		return $clean;
	}

	public static function persist_lead_meta( $lead_id, array $params ) {
		$map = [
			'_sou_nome'        => 'nome',
			'_sou_email'       => 'email',
			'_sou_telefone'    => 'telefone',
			'_sou_unidade'     => 'unidade',
			'_sou_origem'      => 'origem',
			'_sou_utm_source'  => 'utm_source',
			'_sou_utm_medium'  => 'utm_medium',
			'_sou_utm_campaign'=> 'utm_campaign',
			'_sou_utm_term'    => 'utm_term',
			'_sou_utm_content' => 'utm_content',
			'_sou_ip'          => 'ip',
			'_sou_data'        => 'data_envio',
			'_sou_aceite'      => 'aceite',
		];

		foreach ( $map as $meta_key => $param_key ) {
			if ( isset( $params[ $param_key ] ) ) {
				$value = $params[ $param_key ];
				if ( in_array( $meta_key, [ '_sou_unidade', '_sou_aceite' ], true ) ) {
					update_post_meta( $lead_id, $meta_key, absint( $value ) );
				} else {
					update_post_meta( $lead_id, $meta_key, sanitize_text_field( wp_unslash( $value ) ) );
				}
			}
		}
	}

	public static function build_redirect_url( array $params ) {
		$base = ! empty( $params['redirect'] ) ? $params['redirect'] : Settings::instance()->get_option( 'base_url', home_url() );

		$query = [
			'nome'     => $params['nome'] ?? '',
			'telefone' => $params['telefone'] ?? '',
			'unidade'  => $params['unidade_nome'] ?? '',
			'email'    => $params['email'] ?? '',
		];

		$query = array_filter( $query );

		return add_query_arg( array_map( 'rawurlencode', $query ), $base );
	}

	public static function get_request_ip() {
		foreach ( [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}

		return '';
	}

	public static function sanitize_phone( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );

		if ( strlen( $digits ) > 11 ) {
			$digits = substr( $digits, 0, 11 );
		}

		return $digits;
	}

	public static function apply_recaptcha_check( array $params ) {
		$token = $params['recaptcha'] ?? '';
		$key   = Settings::instance()->get_option( 'recaptcha_key' );

		if ( empty( $token ) || empty( $key ) ) {
			return false;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			[
				'body' => [
					'secret'   => $key,
					'response' => $token,
					'remoteip' => self::get_request_ip(),
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return ! empty( $body['success'] );
	}

	public static function inject_frontend_localized_data( array $context ) {
		wp_localize_script(
			'soumais-locator-frontend',
			'SouMaisLocator',
			[
				'strings'  => $context['strings'],
				'settings' => $context['settings'],
				'initialUnits' => $context['initial_units'] ?? [],
				'nonce'    => wp_create_nonce( 'soumais_locator' ),
				'assets'   => [
					'placeholder' => 'data:image/svg+xml;utf8,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 200"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#2e5bff"/><stop offset="100%" stop-color="#ff2d9b"/></linearGradient></defs><rect width="320" height="200" fill="url(#g)"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family=\"Arial, sans-serif\" font-size=\"28\">Sou Mais</text></svg>' ),
				],
			]
		);
	}

	public static function load_template( $template, array $context = [] ) {
		$path = SOUMAIS_LOCATOR_PATH . 'templates/' . $template;

		if ( ! file_exists( $path ) ) {
			return;
		}

		extract( $context, EXTR_SKIP );

		include $path;
	}

	public static function normalize_string( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = remove_accents( $value );
		return strtolower( trim( $value ) );
	}

	public static function normalize_digits( $value ) {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	public static function prepare_unit_payload( $post_id, $include_meta = false ) {
		$post_id   = absint( $post_id );
		$image_id  = (int) get_post_meta( $post_id, '_sou_image_id', true );
		$thumbnail = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : get_the_post_thumbnail_url( $post_id, 'medium' );
		$lat       = (float) get_post_meta( $post_id, '_sou_lat', true );
		$lng       = (float) get_post_meta( $post_id, '_sou_lng', true );

		$payload = [
			'id'        => $post_id,
			'title'     => get_the_title( $post_id ),
			'address'   => self::format_unit_address( $post_id ),
			'phone'     => get_post_meta( $post_id, '_sou_tel', true ),
			'whatsapp'  => get_post_meta( $post_id, '_sou_whatsapp', true ),
			'hours'     => wp_kses_post( get_post_meta( $post_id, '_sou_horario', true ) ),
			'url'       => esc_url_raw( get_post_meta( $post_id, '_sou_tecno_url', true ) ),
			'distance'  => null,
			'thumbnail' => $thumbnail,
		];

		if ( $include_meta ) {
			$payload['_meta'] = [
				'lat'         => $lat,
				'lng'         => $lng,
				'cep_digits'  => self::normalize_digits( get_post_meta( $post_id, '_sou_cep', true ) ),
				'search_blob' => self::normalize_string(
					sprintf(
						'%s %s %s %s',
						get_the_title( $post_id ),
						get_post_meta( $post_id, '_sou_endereco', true ),
						get_post_meta( $post_id, '_sou_cidade', true ),
						get_post_meta( $post_id, '_sou_bairro', true )
					)
				),
			];
		}

		return $payload;
	}

	public static function get_active_units( $limit = -1, $include_meta = false ) {
		$query = new \WP_Query(
			[
				'post_type'      => Post_Type_Unidade::CPT,
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'   => '_sou_status',
						'value' => 'active',
					],
				],
			]
		);

		$units = [];
		foreach ( $query->posts as $post ) {
			$units[] = self::prepare_unit_payload( $post->ID, $include_meta );
		}

		wp_reset_postdata();

		return $units;
	}
}
