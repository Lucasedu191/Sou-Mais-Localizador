<?php
namespace SouMais\Locator;

defined( 'ABSPATH' ) || exit;

class Shortcode {
	use Singleton;

	const TAG = 'soumais_locator';

	protected function init() {
		add_shortcode( self::TAG, [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			[
				'layout'        => 'full',
				'radius'        => Settings::instance()->get_option( 'default_radius', 10 ),
				'show_whatsapp' => 'no',
				'redirect'      => Settings::instance()->get_option( 'base_url', home_url() ),
			],
			$atts,
			self::TAG
		);

		wp_enqueue_style( 'soumais-locator-frontend' );
		wp_enqueue_script( 'soumais-locator-frontend' );

		$context = [
			'atts' => $atts,
			'initial_units' => Helpers::get_active_units( max( Settings::instance()->get_option( 'results_limit', 6 ), 50 ) ),
			'strings' => [
				'use_location'       => __( 'Usar localização atual', 'soumais-localizador' ),
				'search_placeholder' => __( 'Busque por cidade, bairro ou CEP', 'soumais-localizador' ),
				'whatsapp_label'     => __( 'Falar no WhatsApp', 'soumais-localizador' ),
				'plans_label'        => Settings::instance()->get_option( 'cta_label', __( 'Ver planos', 'soumais-localizador' ) ),
				'lgpd'               => Settings::instance()->get_option( 'lgpd_message', __( 'Autorizo o contato da Academia Sou Mais.', 'soumais-localizador' ) ),
				'success_message'    => __( 'Redirecionando para os planos...', 'soumais-localizador' ),
				'error_message'      => __( 'Não foi possível enviar seus dados. Tente novamente.', 'soumais-localizador' ),
				'empty'              => __( 'Nenhuma unidade encontrada no momento.', 'soumais-localizador' ),
				'choose_unit'        => __( 'Escolha uma unidade', 'soumais-localizador' ),
			],
			'settings' => [
				'rest_units'   => esc_url_raw( rest_url( REST_API::ENDPOINT_NAMESPACE . '/unidades' ) ),
				'rest_lead'    => esc_url_raw( rest_url( REST_API::ENDPOINT_NAMESPACE . '/lead' ) ),
				'radius'        => (int) $atts['radius'],
				'results_limit' => Settings::instance()->get_option( 'results_limit', 6 ),
				'redirect_base' => esc_url_raw( $atts['redirect'] ),
				'show_whatsapp' => ( 'yes' === $atts['show_whatsapp'] ),
				'recaptcha_key' => Settings::instance()->get_option( 'recaptcha_key' ),
			],
		];

		Helpers::inject_frontend_localized_data( $context );

		$template = ( 'compact' === $atts['layout'] ) ? 'shortcode-compact.php' : 'shortcode-full.php';

		ob_start();
		Helpers::load_template( $template, $context );
		return ob_get_clean();
	}
}
