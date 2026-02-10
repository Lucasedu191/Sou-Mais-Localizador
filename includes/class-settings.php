<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Settings {
	use Singleton;

	const OPTION_KEY = 'soumais_locator_settings';

	protected function init() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Sou Mais Localizador', 'soumais-localizador' ),
			__( 'Sou Mais', 'soumais-localizador' ),
			'manage_options',
			'soumais_locator',
			[ $this, 'render_page' ],
			'dashicons-location-alt',
			58
		);

		add_submenu_page(
			'soumais_locator',
			__( 'Configurações', 'soumais-localizador' ),
			__( 'Configurações', 'soumais-localizador' ),
			'manage_options',
			'soumais_locator',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'soumais_locator', self::OPTION_KEY, [ $this, 'sanitize' ] );

		add_settings_section( 'general', __( 'Configurações Gerais', 'soumais-localizador' ), '__return_null', 'soumais_locator' );
		add_settings_field( 'base_url', __( 'Base URL Tecnofit', 'soumais-localizador' ), [ $this, 'field_text' ], 'soumais_locator', 'general', [ 'id' => 'base_url' ] );
		add_settings_field( 'default_radius', __( 'Raio padrão (km)', 'soumais-localizador' ), [ $this, 'field_number' ], 'soumais_locator', 'general', [ 'id' => 'default_radius', 'min' => 1, 'max' => 50 ] );
		add_settings_field( 'results_limit', __( 'Número de resultados', 'soumais-localizador' ), [ $this, 'field_number' ], 'soumais_locator', 'general', [ 'id' => 'results_limit', 'min' => 1, 'max' => 20 ] );

		add_settings_section( 'integrations', __( 'Integrações', 'soumais-localizador' ), '__return_null', 'soumais_locator' );
		add_settings_field( 'webhook_url', __( 'URL do webhook', 'soumais-localizador' ), [ $this, 'field_text' ], 'soumais_locator', 'integrations', [ 'id' => 'webhook_url' ] );
		add_settings_field( 'webhook_enabled', __( 'Enviar webhook', 'soumais-localizador' ), [ $this, 'field_toggle' ], 'soumais_locator', 'integrations', [ 'id' => 'webhook_enabled' ] );
		add_settings_field( 'recaptcha_key', __( 'Chave reCAPTCHA v3', 'soumais-localizador' ), [ $this, 'field_text' ], 'soumais_locator', 'integrations', [ 'id' => 'recaptcha_key' ] );
		add_settings_field( 'github_token', __( 'Token GitHub (opcional)', 'soumais-localizador' ), [ $this, 'field_text' ], 'soumais_locator', 'integrations', [ 'id' => 'github_token', 'description' => __( 'Informe um token pessoal para evitar limites da API GitHub.', 'soumais-localizador' ) ] );

		add_settings_section( 'ui', __( 'Interface', 'soumais-localizador' ), '__return_null', 'soumais_locator' );
		add_settings_field( 'lgpd_message', __( 'Mensagem LGPD', 'soumais-localizador' ), [ $this, 'field_textarea' ], 'soumais_locator', 'ui', [ 'id' => 'lgpd_message' ] );
		add_settings_field( 'cta_label', __( 'Texto do botão', 'soumais-localizador' ), [ $this, 'field_text' ], 'soumais_locator', 'ui', [ 'id' => 'cta_label' ] );
	}

	public function sanitize( $options ) {
		$defaults = [
			'base_url'        => '',
			'default_radius'  => 10,
			'results_limit'   => 6,
			'webhook_url'     => defined( 'SOUMAIS_LOCATOR_DEFAULT_WEBHOOK_URL' ) ? SOUMAIS_LOCATOR_DEFAULT_WEBHOOK_URL : '',
			'webhook_enabled' => 1,
			'recaptcha_key'   => '',
			'github_token'    => '',
			'lgpd_message'    => __( 'Autorizo o contato da Academia Sou Mais.', 'soumais-localizador' ),
			'cta_label'       => __( 'Ver planos', 'soumais-localizador' ),
		];

		$options = wp_parse_args( $options, $defaults );

		$options['base_url']        = esc_url_raw( $options['base_url'] );
		$options['webhook_url']     = esc_url_raw( $options['webhook_url'] );
		$options['default_radius']  = absint( $options['default_radius'] );
		$options['results_limit']   = absint( $options['results_limit'] );
		$options['webhook_enabled'] = ! empty( $options['webhook_enabled'] ) ? 1 : 0;
		$options['lgpd_message']    = wp_kses_post( $options['lgpd_message'] );
		$options['cta_label']       = sanitize_text_field( $options['cta_label'] );
		$options['recaptcha_key']   = sanitize_text_field( $options['recaptcha_key'] );
		$options['github_token']    = sanitize_text_field( $options['github_token'] );
		if ( '' === trim( $options['cta_label'] ) ) {
			$options['cta_label'] = __( 'Ver planos', 'soumais-localizador' );
		}

		return $options;
	}

	public function render_page() {
		?>
		<div class="wrap soumais-locator-admin">
			<h1><?php esc_html_e( 'Sou Mais Localizador', 'soumais-localizador' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'soumais_locator' );
				do_settings_sections( 'soumais_locator' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function field_text( $args ) {
		$id    = esc_attr( $args['id'] );
		$value = esc_attr( $this->get_option( $id ) );
		$description = ! empty( $args['description'] ) ? '<p class="description">' . esc_html( $args['description'] ) . '</p>' : '';
		?>
		<input type="text" id="<?php echo $id; ?>" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $id . ']' ); ?>" value="<?php echo $value; ?>" class="regular-text">
		<?php echo $description; ?>
		<?php
	}

	public function field_number( $args ) {
		$id    = esc_attr( $args['id'] );
		$value = esc_attr( $this->get_option( $id, $args['min'] ?? 0 ) );
		$min   = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$max   = isset( $args['max'] ) ? (int) $args['max'] : 0;
		?>
		<input type="number" id="<?php echo $id; ?>" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $id . ']' ); ?>" value="<?php echo $value; ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>">
		<?php
	}

	public function field_toggle( $args ) {
		$id      = esc_attr( $args['id'] );
		$checked = checked( 1, $this->get_option( $id, 0 ), false );
		?>
		<label for="<?php echo $id; ?>">
			<input type="checkbox" id="<?php echo $id; ?>" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $id . ']' ); ?>" value="1" <?php echo $checked; ?>>
			<?php esc_html_e( 'Ativar', 'soumais-localizador' ); ?>
		</label>
		<?php
	}

	public function field_textarea( $args ) {
		$id    = esc_attr( $args['id'] );
		$value = esc_textarea( $this->get_option( $id ) );
		?>
		<textarea id="<?php echo $id; ?>" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $id . ']' ); ?>" rows="3" class="large-text"><?php echo $value; ?></textarea>
		<?php
	}

	public function get_option( $key, $default = '' ) {
		$options = get_option( self::OPTION_KEY, [] );

		return $options[ $key ] ?? $default;
	}

	public function webhook_enabled() {
		$fallback = $this->get_webhook_url() ? 1 : 0;
		return (bool) $this->get_option( 'webhook_enabled', $fallback );
	}

	public function get_webhook_url() {
		$default = defined( 'SOUMAIS_LOCATOR_DEFAULT_WEBHOOK_URL' ) ? SOUMAIS_LOCATOR_DEFAULT_WEBHOOK_URL : '';
		return $this->get_option( 'webhook_url', $default );
	}

	public function recaptcha_enabled() {
		return (bool) $this->get_option( 'recaptcha_key', false );
	}

	public function github_token() {
		return trim( $this->get_option( 'github_token', '' ) );
	}
}
