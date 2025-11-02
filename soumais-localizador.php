<?php
/**
 * Plugin Name: Sou Mais Localizador
 * Description: Localiza unidades, captura leads e integra com Tecnofit.
 * Version: 1.0.1
 * Author: Sou Mais
 * Text Domain: soumais-localizador
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SOUMAIS_LOCATOR_VERSION', '1.0.0' );
define( 'SOUMAIS_LOCATOR_FILE', __FILE__ );
define( 'SOUMAIS_LOCATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOUMAIS_LOCATOR_URL', plugin_dir_url( __FILE__ ) );

require_once SOUMAIS_LOCATOR_PATH . 'includes/trait-singleton.php';
require_once SOUMAIS_LOCATOR_PATH . 'includes/class-plugin.php';

SouMais\Locator\Plugin::instance();
