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
		add_action( 'manage_posts_extra_tablenav', [ $this, 'render_export_button' ] );
		add_action( 'admin_post_soumais_export_leads', [ $this, 'handle_export' ] );
		add_filter( 'bulk_actions-edit-' . self::CPT, [ $this, 'register_bulk_actions' ] );
		add_action( 'load-edit.php', [ $this, 'maybe_export_selected' ] );
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

	public function render_export_button( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		global $typenow;
		if ( self::CPT !== $typenow ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'soumais_export_leads',
				],
				admin_url( 'admin-post.php' )
			),
			'soumais_export_leads'
		);

		echo '<div class="alignleft actions soumais-export-leads">';
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Exportar todos (CSV)', 'soumais-localizador' ) . '</a>';
		echo '</div>';
	}

	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para exportar leads.', 'soumais-localizador' ) );
		}

		check_admin_referer( 'soumais_export_leads' );

		$this->export_csv();
	}

	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['soumais_export_selected'] = __( 'Exportar selecionados (CSV)', 'soumais-localizador' );
		return $bulk_actions;
	}

	public function maybe_export_selected() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base || self::CPT !== $screen->post_type ) {
			return;
		}

		$action = $_REQUEST['action'] ?? '';
		if ( 'soumais_export_selected' !== $action ) {
			$action = $_REQUEST['action2'] ?? '';
		}

		if ( 'soumais_export_selected' !== $action ) {
			return;
		}

		if ( empty( $_REQUEST['post'] ) || ! is_array( $_REQUEST['post'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para exportar leads.', 'soumais-localizador' ) );
		}

		$post_ids = array_map( 'absint', wp_unslash( $_REQUEST['post'] ) );
		$post_ids = array_filter( $post_ids );
		if ( empty( $post_ids ) ) {
			return;
		}

		$this->export_csv( $post_ids );
	}

	protected function export_csv( array $post_ids = [] ) {
		$args = [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];

		if ( ! empty( $post_ids ) ) {
			$args['post__in'] = $post_ids;
			$args['orderby']  = 'post__in';
			$args['order']    = 'ASC';
		}

		$query = new \WP_Query( $args );

		$filename = 'soumais-leads-' . gmdate( 'Ymd-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$handle  = fopen( 'php://output', 'w' );
		$headers = [
			__( 'Nome', 'soumais-localizador' ),
			__( 'E-mail', 'soumais-localizador' ),
			__( 'Telefone', 'soumais-localizador' ),
			__( 'Unidade', 'soumais-localizador' ),
			__( 'Data', 'soumais-localizador' ),
			__( 'Origem', 'soumais-localizador' ),
			'UTM Source',
			'UTM Medium',
			'UTM Campaign',
			'UTM Term',
			'UTM Content',
			'IP',
		];
		fputcsv( $handle, $headers, ';' );

		foreach ( $query->posts as $post ) {
			$unit_id = (int) get_post_meta( $post->ID, '_sou_unidade', true );
			$row     = [
				$this->csv_value( get_post_meta( $post->ID, '_sou_nome', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_email', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_telefone', true ) ),
				$this->csv_value( $unit_id ? get_the_title( $unit_id ) : '' ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_data', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_origem', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_utm_source', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_utm_medium', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_utm_campaign', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_utm_term', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_utm_content', true ) ),
				$this->csv_value( get_post_meta( $post->ID, '_sou_ip', true ) ),
			];

			fputcsv( $handle, $row, ';' );
		}

		wp_reset_postdata();
		fclose( $handle );
		exit;
	}

	private function csv_value( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( "/\r|\n/", ' ', $value );

		return trim( $value );
	}
}
