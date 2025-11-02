<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Post_Type_Lead {
	use Singleton;

	const CPT = 'sou_lead';

	protected function init() {
		add_action( 'init', [ $this, 'register' ] );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
	}

	public function register() {
		register_post_type(
			self::CPT,
			[
				'labels' => [
					'name'          => __( 'Leads Sou Mais', 'soumais-localizador' ),
					'singular_name' => __( 'Lead Sou Mais', 'soumais-localizador' ),
				],
				'public'              => false,
				'show_ui'             => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => [ 'title' ],
				'show_in_menu'        => 'soumais_locator',
				'menu_position'       => 60,
			]
		);

		$fields = [
			'_sou_nome'         => 'sanitize_text_field',
			'_sou_email'        => 'sanitize_email',
			'_sou_telefone'     => 'sanitize_text_field',
			'_sou_unidade'      => 'absint',
			'_sou_origem'       => 'sanitize_text_field',
			'_sou_utm_source'   => 'sanitize_text_field',
			'_sou_utm_medium'   => 'sanitize_text_field',
			'_sou_utm_campaign' => 'sanitize_text_field',
			'_sou_utm_term'     => 'sanitize_text_field',
			'_sou_utm_content'  => 'sanitize_text_field',
			'_sou_ip'           => 'sanitize_text_field',
			'_sou_data'         => 'sanitize_text_field',
			'_sou_aceite'       => 'absint',
		];

		foreach ( $fields as $meta_key => $callback ) {
			register_post_meta(
				self::CPT,
				$meta_key,
				[
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => $callback,
					'show_in_rest'      => false,
				]
			);
		}
	}

	public function columns( $columns ) {
		$new = [
			'cb'          => $columns['cb'],
			'title'       => __( 'Lead', 'soumais-localizador' ),
			'sou_email'   => __( 'E-mail', 'soumais-localizador' ),
			'sou_telefone'=> __( 'Telefone', 'soumais-localizador' ),
			'sou_unidade' => __( 'Unidade', 'soumais-localizador' ),
			'sou_data'    => __( 'Data', 'soumais-localizador' ),
		];

		return $new;
	}

	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'sou_email':
				echo esc_html( get_post_meta( $post_id, '_sou_email', true ) );
				break;
			case 'sou_telefone':
				echo esc_html( get_post_meta( $post_id, '_sou_telefone', true ) );
				break;
			case 'sou_unidade':
				$unit_id = (int) get_post_meta( $post_id, '_sou_unidade', true );
				echo esc_html( get_the_title( $unit_id ) );
				break;
			case 'sou_data':
				echo esc_html( get_post_meta( $post_id, '_sou_data', true ) );
				break;
		}
	}
}
