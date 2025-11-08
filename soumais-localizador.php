<?php
/**
 * Plugin Name: Sou Mais Localizador
 * Description: Localiza unidades, captura leads e integra com Tecnofit.
 * Version: 1.0.22
 * Author: Sou Mais
 * Text Domain: soumais-localizador
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/Lucasedu191/Sou-Mais-Localizador
 * GitHub Release Asset: true
 */

defined( 'ABSPATH' ) || exit;

define( 'SOUMAIS_LOCATOR_VERSION', '1.0.22' );
define( 'SOUMAIS_LOCATOR_FILE', __FILE__ );
define( 'SOUMAIS_LOCATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOUMAIS_LOCATOR_URL', plugin_dir_url( __FILE__ ) );
// test
require_once SOUMAIS_LOCATOR_PATH . 'includes/trait-singleton.php';
require_once SOUMAIS_LOCATOR_PATH . 'includes/class-plugin.php';

$pucc_composer = __DIR__ . '/vendor/autoload.php';
$pucc_embedded = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

if ( file_exists( $pucc_composer ) ) {
	require $pucc_composer;
} elseif ( file_exists( $pucc_embedded ) ) {
	require $pucc_embedded;
} else {
	error_log( '[SouMais Localizador] Plugin Update Checker não encontrado; updates automáticos desativados.' );
}

$factory = null;
if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$factory = '\YahnisElsts\PluginUpdateChecker\v5\PucFactory';
} elseif ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5p6\PucFactory' ) ) {
	$factory = '\YahnisElsts\PluginUpdateChecker\v5p6\PucFactory';
}

if ( $factory ) {
	$update_checker = $factory::buildUpdateChecker(
		'https://github.com/Lucasedu191/Sou-Mais-Localizador',
		__FILE__,
		'soumais-localizador'
	);

	if ( method_exists( $update_checker, 'setBranch' ) ) {
		$update_checker->setBranch( 'main' );
	}

	$api = $update_checker->getVcsApi();
	if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
		$api->enableReleaseAssets();
	}

	$token = null;
	if ( defined( 'SOUMAIS_LOCATOR_GITHUB_TOKEN' ) && SOUMAIS_LOCATOR_GITHUB_TOKEN ) {
		$token = SOUMAIS_LOCATOR_GITHUB_TOKEN;
	} elseif ( getenv( 'SOUMAIS_LOCATOR_GITHUB_TOKEN' ) ) {
		$token = getenv( 'SOUMAIS_LOCATOR_GITHUB_TOKEN' );
	}

	if ( $token ) {
		$update_checker->setAuthentication( trim( $token ) );
	}
}

add_action(
	'plugins_loaded',
	static function () {
		SouMais\Locator\Plugin::instance();
	}
);
