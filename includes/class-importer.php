<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Importer {
	use Singleton;

	protected function init() {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
	}

	public function register_page() {
		add_submenu_page(
			'soumais_locator',
			__( 'Importar Unidades', 'soumais-localizador' ),
			__( 'Importar Unidades', 'soumais-localizador' ),
			'manage_options',
			'soumais_locator_import',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Você não tem permissão para acessar esta página.', 'soumais-localizador' ) );
		}

		if ( isset( $_POST['soumais_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['soumais_import_nonce'] ) ), 'soumais_locator_import' ) ) {
			$this->handle_upload();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Importar Unidades', 'soumais-localizador' ); ?></h1>
			<?php settings_errors( 'soumais_locator_import' ); ?>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'soumais_locator_import', 'soumais_import_nonce' ); ?>
				<p>
					<label for="soumais_csv"><?php esc_html_e( 'Arquivo CSV', 'soumais-localizador' ); ?></label><br>
					<input type="file" id="soumais_csv" name="soumais_csv" accept=".csv" required>
				</p>
				<?php submit_button( __( 'Importar', 'soumais-localizador' ) ); ?>
			</form>
		</div>
		<?php
	}

	protected function handle_upload() {
		if ( empty( $_FILES['soumais_csv'] ) || ! is_uploaded_file( $_FILES['soumais_csv']['tmp_name'] ) ) {
			add_settings_error( 'soumais_locator_import', 'no_file', __( 'Nenhum arquivo enviado.', 'soumais-localizador' ), 'error' );
			return;
		}

		$file = wp_handle_upload(
			$_FILES['soumais_csv'],
			[
				'test_form' => false,
			]
		);

		if ( isset( $file['error'] ) ) {
			add_settings_error( 'soumais_locator_import', 'upload_error', esc_html( $file['error'] ), 'error' );
			return;
		}

		$count = $this->import_csv( $file['file'] );

		if ( file_exists( $file['file'] ) ) {
			wp_delete_file( $file['file'] );
		}

		add_settings_error(
			'soumais_locator_import',
			'import_success',
			sprintf( _n( '%s unidade importada.', '%s unidades importadas.', $count, 'soumais-localizador' ), number_format_i18n( $count ) ),
			'updated'
		);
	}

	protected function import_csv( $path ) {
		$count = 0;
		if ( ( $handle = fopen( $path, 'r' ) ) !== false ) {
			$header = fgetcsv( $handle, 0, ';' );
			if ( empty( $header ) ) {
				fclose( $handle );
				return 0;
			}

			while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
				$data = array_combine( $header, $row );
				$unit_id = $this->upsert_unit( $data );
				if ( $unit_id ) {
					$count++;
				}
			}
			fclose( $handle );
		}

		return $count;
	}

	protected function upsert_unit( array $data ) {
		$title = sanitize_text_field( $data['nome'] ?? '' );
		if ( empty( $title ) ) {
			return 0;
		}

		$existing = get_page_by_title( $title, OBJECT, Post_Type_Unidade::CPT );

		$post_id = wp_insert_post(
			[
				'ID'          => $existing->ID ?? 0,
				'post_title'  => $title,
				'post_type'   => Post_Type_Unidade::CPT,
				'post_status' => 'publish',
			]
		);

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			return 0;
		}

		$meta_map = [
			'_sou_endereco'  => 'endereco',
			'_sou_bairro'    => 'bairro',
			'_sou_cidade'    => 'cidade',
			'_sou_uf'        => 'uf',
			'_sou_cep'       => 'cep',
			'_sou_tel'       => 'telefone',
			'_sou_whatsapp'  => 'whatsapp',
			'_sou_tecno_url' => 'tecnofit_url',
			'_sou_horario'   => 'horario',
			'_sou_status'    => 'status',
		];

		foreach ( $meta_map as $meta => $key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $post_id, $meta, sanitize_text_field( $data[ $key ] ) );
			}
		}

		if ( isset( $data['lat'] ) ) {
			update_post_meta( $post_id, '_sou_lat', (float) $data['lat'] );
		}
		if ( isset( $data['lng'] ) ) {
			update_post_meta( $post_id, '_sou_lng', (float) $data['lng'] );
		}

		return $post_id;
	}
}
