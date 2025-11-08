<?php
namespace SouMais\Locator;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class REST_API {
	use Singleton;

	const ENDPOINT_NAMESPACE = 'soumais/v1';

	protected function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			self::ENDPOINT_NAMESPACE,
			'/unidades',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_units' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'query'  => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'lat'    => [ 'validate_callback' => [ $this, 'validate_coordinate' ] ],
					'lng'    => [ 'validate_callback' => [ $this, 'validate_coordinate' ] ],
					'radius' => [ 'validate_callback' => [ $this, 'validate_number' ] ],
					'limit'  => [ 'validate_callback' => [ $this, 'validate_number' ], 'default' => Settings::instance()->get_option( 'results_limit', 6 ) ],
				],
			]
		);

		register_rest_route(
			self::ENDPOINT_NAMESPACE,
			'/lead',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_lead' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function validate_coordinate( $value, $request = null, $param = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return is_numeric( $value ) && $value >= -180 && $value <= 180;
	}

	public function validate_number( $value, $request = null, $param = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return is_numeric( $value );
	}

	public function get_units( WP_REST_Request $request ) {
		$params            = $request->get_params();
		$search_query      = isset( $params['query'] ) ? trim( (string) $params['query'] ) : '';
		$normalized_search = Helpers::normalize_string( $search_query );
		$digits_search     = Helpers::normalize_digits( $search_query );
		$is_numeric_search = '' !== $digits_search && ctype_digit( $digits_search );

		$limit = isset( $params['limit'] ) ? (int) $params['limit'] : Settings::instance()->get_option( 'results_limit', 6 );

		$args = [
			'limit'        => -1,
			'include_meta' => true,
		];

		$args = apply_filters( 'soumais_locator_units_query_args', $args, $params );

		$use_cache = empty( $search_query ) && empty( $params['lat'] ) && empty( $params['lng'] ) && empty( $params['radius'] );
		$cache_key = Helpers::build_cache_key( array_merge( $args, $params ) );
		$cached    = $use_cache ? get_transient( $cache_key ) : false;

		if ( $use_cache && false !== $cached ) {
			return new WP_REST_Response( $cached );
		}

		$raw_units = Helpers::get_active_units( $args['limit'], ! empty( $args['include_meta'] ) );
		$units     = [];

		foreach ( $raw_units as $unit ) {
			$meta     = $unit['_meta'] ?? [];
			$lat      = isset( $meta['lat'] ) ? (float) $meta['lat'] : 0.0;
			$lng      = isset( $meta['lng'] ) ? (float) $meta['lng'] : 0.0;
			$distance = null;

			if ( $search_query ) {
				if ( $is_numeric_search ) {
					$cep_digits = $meta['cep_digits'] ?? '';
					if ( empty( $cep_digits ) || false === strpos( $cep_digits, $digits_search ) ) {
						continue;
					}
				} else {
					$haystack = $meta['search_blob'] ?? '';
					if ( empty( $haystack ) || false === strpos( $haystack, $normalized_search ) ) {
						continue;
					}
				}
			}

			$distance = null;
			if ( isset( $params['lat'], $params['lng'] ) && $lat && $lng ) {
				$distance = Helpers::calculate_distance(
					(float) $params['lat'],
					(float) $params['lng'],
					$lat,
					$lng
				);

				if ( ! empty( $params['radius'] ) && $distance > (float) $params['radius'] ) {
					continue;
				}
			}

			$unit['distance'] = $distance;
			unset( $unit['_meta'] );
			$units[] = $unit;
		}

		$units = Helpers::sort_by_distance( $units );

		if ( $use_cache ) {
			set_transient( $cache_key, $units, MINUTE_IN_SECONDS * 5 );
			$this->store_cache_key( $cache_key );
		}

		if ( empty( $search_query ) && $limit > 0 ) {
			$units = array_slice( $units, 0, $limit );
		}

		return new WP_REST_Response( $units );
	}

	public function create_lead( WP_REST_Request $request ) {
		$params = $request->get_params();
		$params = is_array( $params ) && ! empty( $params ) ? $params : $request->get_json_params();
		$params = is_array( $params ) ? $params : [];

		$params['ip']         = Helpers::get_request_ip();
		$params['data_envio'] = current_time( 'mysql' );

		$verified = Helpers::validate_lead_payload( $params );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$params = $verified;

		if ( Settings::instance()->recaptcha_enabled() ) {
			if ( empty( $params['recaptcha'] ) || ! Helpers::apply_recaptcha_check( $params ) ) {
				return new WP_Error( 'recaptcha_failed', __( 'Não foi possível validar o reCAPTCHA.', 'soumais-localizador' ), [ 'status' => 400 ] );
			}
		}

		$unit_id   = (int) $params['unidade'];
		$unit_post = $unit_id ? get_post( $unit_id ) : null;

		if ( ! $unit_post || Post_Type_Unidade::CPT !== $unit_post->post_type ) {
			return new WP_Error( 'invalid_unit', __( 'Unidade inválida.', 'soumais-localizador' ), [ 'status' => 400 ] );
		}

		$params['unidade_nome'] = get_the_title( $unit_post );
		$params['unidade_url']  = get_post_meta( $unit_id, '_sou_tecno_url', true );

		$lead_id = wp_insert_post(
			[
				'post_type'   => Post_Type_Lead::CPT,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%1$s - %2$s', sanitize_text_field( $params['nome'] ), current_time( 'mysql' ) ),
			]
		);

		if ( is_wp_error( $lead_id ) ) {
			return $lead_id;
		}

		do_action( 'soumais_locator_before_lead_save', $lead_id, $params );

		Helpers::persist_lead_meta( $lead_id, $params );

		do_action( 'soumais_locator_after_lead_save', $lead_id, $params );

		Webhook::instance()->dispatch( $params );

		$redirect = ! empty( $params['unidade_url'] ) ? $params['unidade_url'] : Helpers::build_redirect_url( $params );

		return new WP_REST_Response(
			[
				'lead_id'      => $lead_id,
				'redirect_url' => esc_url_raw( $redirect ),
				'message'      => __( 'Lead criado com sucesso.', 'soumais-localizador' ),
			],
			201
		);
	}

	protected function store_cache_key( $key ) {
		$cache_keys = get_transient( 'soumais_locator_cache_keys' );
		if ( ! is_array( $cache_keys ) ) {
			$cache_keys = [];
		}

		if ( ! in_array( $key, $cache_keys, true ) ) {
			$cache_keys[] = $key;
			set_transient( 'soumais_locator_cache_keys', $cache_keys, DAY_IN_SECONDS );
		}
	}
}
