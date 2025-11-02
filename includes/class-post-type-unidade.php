<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Post_Type_Unidade {
	use Singleton;

	const CPT = 'sou_unidade';

	protected function init() {
		add_action( 'init', [ $this, 'register' ] );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_action( 'save_post_' . self::CPT, [ $this, 'purge_cache' ], 10, 2 );
		add_action( 'deleted_post', [ $this, 'handle_delete' ] );
	}

	public function register() {
		$labels = [
			'name'               => __( 'Unidades', 'soumais-localizador' ),
			'singular_name'      => __( 'Unidade', 'soumais-localizador' ),
			'add_new_item'       => __( 'Adicionar nova unidade', 'soumais-localizador' ),
			'edit_item'          => __( 'Editar unidade', 'soumais-localizador' ),
			'new_item'           => __( 'Nova unidade', 'soumais-localizador' ),
			'view_item'          => __( 'Ver unidade', 'soumais-localizador' ),
			'search_items'       => __( 'Buscar unidades', 'soumais-localizador' ),
			'not_found'          => __( 'Nenhuma unidade encontrada.', 'soumais-localizador' ),
			'not_found_in_trash' => __( 'Nenhuma unidade encontrada na lixeira.', 'soumais-localizador' ),
		];

		register_post_type(
			self::CPT,
			[
				'labels'        => $labels,
				'public'        => true,
				'show_in_rest'  => true,
				'supports'      => [ 'title', 'thumbnail' ],
				'menu_icon'     => 'dashicons-location-alt',
				'rewrite'       => [ 'slug' => 'unidades' ],
				'has_archive'   => false,
			]
		);

		$this->register_meta();
	}

	protected function register_meta() {
		$text_field = [
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => true,
		];

		$meta_fields = [
			'_sou_endereco'   => $text_field,
			'_sou_bairro'     => $text_field,
			'_sou_cidade'     => $text_field,
			'_sou_uf'         => $text_field,
			'_sou_cep'        => $text_field,
			'_sou_tel'        => $text_field,
			'_sou_whatsapp'   => $text_field,
			'_sou_tecno_url'  => $text_field,
			'_sou_horario'    => [
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'wp_kses_post',
				'show_in_rest'      => true,
			],
			'_sou_status'     => [
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => [ $this, 'sanitize_status' ],
				'show_in_rest'      => true,
				'default'           => 'active',
			],
			'_sou_lat'        => [
				'type'              => 'number',
				'single'            => true,
				'sanitize_callback' => [ $this, 'sanitize_coordinate' ],
				'show_in_rest'      => true,
			],
			'_sou_lng'        => [
				'type'              => 'number',
				'single'            => true,
				'sanitize_callback' => [ $this, 'sanitize_coordinate' ],
				'show_in_rest'      => true,
			],
		];

		foreach ( $meta_fields as $meta_key => $args ) {
			register_post_meta( self::CPT, $meta_key, $args );
		}
	}

	public function sanitize_status( $value ) {
		return in_array( $value, [ 'active', 'inactive' ], true ) ? $value : 'inactive';
	}

	public function sanitize_coordinate( $value ) {
		$value = floatval( $value );

		return ( $value >= -180 && $value <= 180 ) ? $value : 0;
	}

	public function columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['sou_cidade'] = __( 'Cidade', 'soumais-localizador' );
				$new['sou_status'] = __( 'Status', 'soumais-localizador' );
			}
		}

		return $new;
	}

	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'sou_cidade':
				echo esc_html( get_post_meta( $post_id, '_sou_cidade', true ) );
				break;
			case 'sou_status':
				$status = get_post_meta( $post_id, '_sou_status', true );
				echo ( 'active' === $status )
					? '<span class="status-active">' . esc_html__( 'Ativa', 'soumais-localizador' ) . '</span>'
					: '<span class="status-inactive">' . esc_html__( 'Inativa', 'soumais-localizador' ) . '</span>';
				break;
		}
	}

	public function purge_cache( $post_id, $post ) {
		if ( self::CPT !== $post->post_type ) {
			return;
		}

		$cache_keys = get_transient( 'soumais_locator_cache_keys' );
		if ( ! is_array( $cache_keys ) ) {
			return;
		}

		foreach ( $cache_keys as $key ) {
			delete_transient( $key );
		}

		delete_transient( 'soumais_locator_cache_keys' );
	}

	public function handle_delete( $post_id ) {
		if ( self::CPT === get_post_type( $post_id ) ) {
			$this->purge_cache( $post_id, get_post( $post_id ) );
		}
	}
}
