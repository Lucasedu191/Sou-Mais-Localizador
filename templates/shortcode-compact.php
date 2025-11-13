<?php
/**
 * Template: Shortcode Compact Layout
 *
 * @package SouMais\Locator
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sm-locator sm-locator--compact" data-layout="compact">
	<form class="sm-locator__search" novalidate>
		<input
			type="text"
			class="sm-input sm-input--query"
			name="query"
			placeholder="<?php echo esc_attr( $strings['search_placeholder'] ); ?>"
			autocomplete="off"
		>
		<div class="sm-locator__search-actions">
			<button type="submit" class="sm-button sm-button--primary"><?php esc_html_e( 'Buscar', 'soumais-localizador' ); ?></button>
			<button type="button" class="sm-button sm-button--location"><?php echo esc_html( $strings['use_location'] ); ?></button>
		</div>
	</form>

	<?php $initial_units = $initial_units ?? []; ?>
	<div class="sm-carousel">
		<button type="button" class="sm-carousel__nav sm-carousel__nav--prev" aria-label="<?php esc_attr_e( 'Unidade anterior', 'soumais-localizador' ); ?>">
			<span aria-hidden="true">‹</span>
		</button>
		<div class="sm-locator__carousel" aria-live="polite">
			<?php if ( ! empty( $initial_units ) ) : ?>
				<?php foreach ( array_slice( $initial_units, 0, 15 ) as $unit ) : ?>
					<?php
					$title     = esc_html( $unit['title'] ?? '' );
					$address   = esc_html( $unit['address'] ?? '' );
					$thumb     = esc_url( $unit['thumbnail'] ?? '' );
					$phone_url = ! empty( $unit['whatsapp'] ) ? sprintf( 'https://wa.me/%s?text=%s', rawurlencode( $unit['whatsapp'] ), rawurlencode( sprintf( __( 'Olá! Tenho interesse na unidade %s', 'soumais-localizador' ), $unit['title'] ?? '' ) ) ) : '';
					?>
					<article class="sm-card sm-card--carousel">
						<div class="sm-card__halo"></div>
						<div class="sm-card__inner">
							<figure class="sm-card__media">
								<img src="<?php echo $thumb ?: esc_url( SOUMAIS_LOCATOR_URL . 'assets/img/placeholder.png' ); ?>" alt="<?php echo $title; ?>">
							</figure>
							<div class="sm-card__body">
								<h3 class="sm-card__title"><?php echo $title; ?></h3>
								<p class="sm-card__address"><?php echo $address; ?></p>
							</div>
							<div class="sm-card__actions">
								<button
									type="button"
									class="sm-card__cta"
									data-unit="<?php echo esc_attr( $unit['id'] ); ?>"
									data-unit-name="<?php echo esc_attr( $unit['title'] ?? '' ); ?>"
								>
									<?php echo esc_html( $strings['plans_label'] ); ?>
								</button>
								<?php if ( $settings['show_whatsapp'] && ! empty( $unit['whatsapp'] ) ) : ?>
									<a class="sm-card__whatsapp" href="<?php echo esc_url( $phone_url ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( $strings['whatsapp_label'] ); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="sm-locator__empty sm-locator__empty--carousel"><?php echo esc_html( $strings['empty'] ); ?></p>
			<?php endif; ?>
		</div>
		<button type="button" class="sm-carousel__nav sm-carousel__nav--next" aria-label="<?php esc_attr_e( 'Próxima unidade', 'soumais-localizador' ); ?>">
			<span aria-hidden="true">›</span>
		</button>
	</div>

	<div class="sm-locator__results" aria-live="polite"></div>
	<div class="sm-locator__status" role="status"></div>

	<div class="sm-modal" hidden>
		<div class="sm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sm-lead-title">
			<button class="sm-modal__close" type="button" aria-label="<?php esc_attr_e( 'Fechar', 'soumais-localizador' ); ?>">&times;</button>
			<h3 class="sm-modal__title" id="sm-lead-title"><?php esc_html_e( 'Quer saber mais?', 'soumais-localizador' ); ?></h3>
			<p class="sm-modal__description">
				<?php esc_html_e( 'Informe seus dados para continuar com o plano selecionado.', 'soumais-localizador' ); ?>
			</p>
			<div class="sm-modal__unit">
				<?php esc_html_e( 'Unidade selecionada:', 'soumais-localizador' ); ?>
				<span class="sm-lead__unit"><?php echo esc_html( $strings['choose_unit'] ); ?></span>
			</div>

			<form class="sm-locator__lead-form">
				<div class="sm-field">
					<label for="sm-lead-name"><?php esc_html_e( 'Nome completo', 'soumais-localizador' ); ?><span class="sm-required" aria-hidden="true">*</span></label>
					<input type="text" id="sm-lead-name" name="nome" class="sm-input" required autocomplete="name">
				</div>

				<div class="sm-field">
					<label for="sm-lead-email"><?php esc_html_e( 'E-mail', 'soumais-localizador' ); ?><span class="sm-required" aria-hidden="true">*</span></label>
					<input type="email" id="sm-lead-email" name="email" class="sm-input" required autocomplete="email">
				</div>

				<div class="sm-field">
					<label for="sm-lead-phone"><?php esc_html_e( 'Telefone', 'soumais-localizador' ); ?><span class="sm-required" aria-hidden="true">*</span></label>
					<input type="tel" id="sm-lead-phone" name="telefone" class="sm-input" required autocomplete="tel">
				</div>

				<input type="hidden" name="unidade" value="">
				<input type="hidden" name="origem" value="">
				<input type="hidden" name="utm_source" value="">
				<input type="hidden" name="utm_medium" value="">
				<input type="hidden" name="utm_campaign" value="">
				<input type="hidden" name="utm_term" value="">
				<input type="hidden" name="utm_content" value="">
				<input type="hidden" name="redirect" value="<?php echo esc_url( $atts['redirect'] ); ?>">

				<div class="sm-field sm-checkbox">
					<input type="checkbox" id="sm-lead-lgpd" name="aceite" value="1" required>
					<label for="sm-lead-lgpd"><?php echo wp_kses_post( $strings['lgpd'] ); ?></label>
				</div>

				<button type="submit" class="sm-submit">
					<?php echo esc_html( $strings['plans_label'] ); ?>
				</button>
			</form>
		</div>
	</div>
</div>
