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
		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
		add_action( 'save_post_' . self::CPT, [ $this, 'save_meta' ] );
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
				'show_in_menu'  => 'soumais_locator',
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
			'_sou_image_id'   => [
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
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

	public function register_metaboxes() {
		add_meta_box(
			'soumais_unidade_dados',
			__( 'Dados da Unidade', 'soumais-localizador' ),
			[ $this, 'render_metabox' ],
			self::CPT,
			'normal',
			'high'
		);
	}

	protected function get_metabox_fields() {
		return [
			'endereco' => [
				'label' => __( 'Endereço', 'soumais-localizador' ),
				'type'  => 'text',
			],
			'bairro'  => [
				'label' => __( 'Bairro', 'soumais-localizador' ),
				'type'  => 'text',
			],
			'cidade'  => [
				'label' => __( 'Cidade', 'soumais-localizador' ),
				'type'  => 'text',
			],
			'uf'      => [
				'label' => __( 'UF', 'soumais-localizador' ),
				'type'  => 'text',
				'attrs' => [
					'maxlength' => 2,
					'style'     => 'width:80px;text-transform:uppercase;',
				],
			],
			'cep'     => [
				'label' => __( 'CEP', 'soumais-localizador' ),
				'type'  => 'text',
				'attrs' => [
					'placeholder' => '00000-000',
				],
			],
			'tel'     => [
				'label' => __( 'Telefone', 'soumais-localizador' ),
				'type'  => 'text',
			],
			'whatsapp' => [
				'label' => __( 'WhatsApp', 'soumais-localizador' ),
				'type'  => 'text',
			],
			'tecno_url' => [
				'label' => __( 'URL Tecnofit', 'soumais-localizador' ),
				'type'  => 'url',
			],
			'horario' => [
				'label' => __( 'Horário de funcionamento', 'soumais-localizador' ),
				'type'  => 'textarea',
				'attrs' => [
					'rows' => 3,
				],
			],
			'image_id' => [
				'label' => __( 'Imagem da unidade', 'soumais-localizador' ),
				'type'  => 'media',
			],
			'status'  => [
				'label'   => __( 'Status', 'soumais-localizador' ),
				'type'    => 'select',
				'options' => [
					'active'   => __( 'Ativa', 'soumais-localizador' ),
					'inactive' => __( 'Inativa', 'soumais-localizador' ),
				],
			],
			'lat'     => [
				'label' => __( 'Latitude', 'soumais-localizador' ),
				'type'  => 'number',
				'attrs' => [
					'step' => '0.000001',
				],
			],
			'lng'     => [
				'label' => __( 'Longitude', 'soumais-localizador' ),
				'type'  => 'number',
				'attrs' => [
					'step' => '0.000001',
				],
			],
		];
	}

	public function render_metabox( $post ) {
		wp_nonce_field( 'soumais_unidade_meta', 'soumais_unidade_nonce' );

		$fields = $this->get_metabox_fields();

		echo '<table class="form-table soumais-unidade-metabox"><tbody>';

		foreach ( $fields as $key => $field ) {
			$meta_key = '_sou_' . $key;
			$value    = get_post_meta( $post->ID, $meta_key, true );
			$attrs    = '';

			if ( ! empty( $field['attrs'] ) && is_array( $field['attrs'] ) ) {
				foreach ( $field['attrs'] as $attr_key => $attr_value ) {
					$attrs .= sprintf( ' %s="%s"', esc_attr( $attr_key ), esc_attr( $attr_value ) );
				}
			}

			echo '<tr>';
			echo '<th scope="row"><label for="soumais_' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th>';
			echo '<td>';

			switch ( $field['type'] ) {
				case 'textarea':
					printf(
						'<textarea id="soumais_%1$s" name="soumais[%1$s]"%3$s>%2$s</textarea>',
						esc_attr( $key ),
						esc_textarea( $value ),
						$attrs
					);
					break;
				case 'media':
					$image_id  = absint( $value );
					$image_src = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
					echo '<div class="soumais-media-field" data-target="soumais_' . esc_attr( $key ) . '">';
					printf(
						'<div class="soumais-media-preview">%1$s</div>',
						$image_src ? '<img src="' . esc_url( $image_src ) . '" alt="" />' : '<span class="soumais-media-placeholder">' . esc_html__( 'Nenhuma imagem selecionada.', 'soumais-localizador' ) . '</span>'
					);
					printf(
						'<input type="hidden" id="soumais_%1$s" name="soumais[%1$s]" value="%2$s" />',
						esc_attr( $key ),
						esc_attr( $image_id )
					);
					echo '<p class="soumais-media-actions">';
					echo '<button type="button" class="button soumais-media-select">' . esc_html__( 'Selecionar imagem', 'soumais-localizador' ) . '</button> ';
					echo '<button type="button" class="button-link soumais-media-remove">' . esc_html__( 'Remover', 'soumais-localizador' ) . '</button>';
					echo '</p></div>';
					break;
				case 'select':
					echo '<select id="soumais_' . esc_attr( $key ) . '" name="soumais[' . esc_attr( $key ) . ']"' . $attrs . '>';
					foreach ( $field['options'] as $option_value => $label ) {
						printf(
							'<option value="%1$s"%3$s>%2$s</option>',
							esc_attr( $option_value ),
							esc_html( $label ),
							selected( $value, $option_value, false )
						);
					}
					echo '</select>';
					break;
				default:
					$type = in_array( $field['type'], [ 'text', 'url', 'number' ], true ) ? $field['type'] : 'text';
					printf(
						'<input type="%4$s" id="soumais_%1$s" name="soumais[%1$s]" value="%2$s"%3$s />',
						esc_attr( $key ),
						esc_attr( $value ),
						$attrs,
						esc_attr( $type )
					);
					break;
			}

			if ( 'status' === $key ) {
				echo '<p class="description">' . esc_html__( 'Somente unidades ativas serão exibidas no localizador.', 'soumais-localizador' ) . '</p>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['soumais_unidade_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['soumais_unidade_nonce'] ) ), 'soumais_unidade_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = $this->get_metabox_fields();
		$data   = isset( $_POST['soumais'] ) && is_array( $_POST['soumais'] ) ? wp_unslash( $_POST['soumais'] ) : [];

		foreach ( $fields as $key => $field ) {
			$meta_key = '_sou_' . $key;
			$value    = $data[ $key ] ?? '';

			switch ( $key ) {
				case 'horario':
					$clean = wp_kses_post( $value );
					break;
				case 'lat':
				case 'lng':
					$clean = ( '' === $value ) ? '' : (string) floatval( str_replace( ',', '.', $value ) );
					break;
				case 'tecno_url':
					$clean = esc_url_raw( $value );
					break;
				case 'status':
					$clean = in_array( $value, [ 'active', 'inactive' ], true ) ? $value : 'inactive';
					break;
				case 'image_id':
					$clean = absint( $value );
					break;
				default:
					$clean = sanitize_text_field( $value );
					break;
			}

			update_post_meta( $post_id, $meta_key, $clean );
		}
	}
}
