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
					'radius' => [ 'validate_callback' => 'is_numeric' ],
					'limit'  => [ 'validate_callback' => 'is_numeric', 'default' => Settings::instance()->get_option( 'results_limit', 6 ) ],
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

	public function validate_coordinate( $value ) {
		return is_numeric( $value ) && $value >= -180 && $value <= 180;
	}

	public function get_units( WP_REST_Request $request ) {
		$params = $request->get_params();
		$args   = [
			'post_type'      => Post_Type_Unidade::CPT,
			'posts_per_page' => isset( $params['limit'] ) ? (int) $params['limit'] : Settings::instance()->get_option( 'results_limit', 6 ),
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'   => '_sou_status',
					'value' => 'active',
				],
			],
		];

		if ( ! empty( $params['query'] ) ) {
			$args['s'] = $params['query'];
		}

		$args = apply_filters( 'soumais_locator_units_query_args', $args, $params );

		$cache_key = Helpers::build_cache_key( array_merge( $args, $params ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return new WP_REST_Response( $cached );
		}

		$query = new WP_Query( $args );
		$units = [];

		foreach ( $query->posts as $post ) {
			$lat = (float) get_post_meta( $post->ID, '_sou_lat', true );
			$lng = (float) get_post_meta( $post->ID, '_sou_lng', true );

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

			$units[] = [
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'address'   => Helpers::format_unit_address( $post->ID ),
				'phone'     => get_post_meta( $post->ID, '_sou_tel', true ),
				'whatsapp'  => get_post_meta( $post->ID, '_sou_whatsapp', true ),
				'hours'     => wp_kses_post( get_post_meta( $post->ID, '_sou_horario', true ) ),
				'url'       => esc_url_raw( get_post_meta( $post->ID, '_sou_tecno_url', true ) ),
				'distance'  => $distance,
				'thumbnail' => get_the_post_thumbnail_url( $post, 'medium' ),
			];
		}

		$units = Helpers::sort_by_distance( $units );

		set_transient( $cache_key, $units, MINUTE_IN_SECONDS * 5 );
		$this->store_cache_key( $cache_key );

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
