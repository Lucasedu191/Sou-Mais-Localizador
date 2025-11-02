<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package SouMais\Locator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'soumais_locator_settings' );
delete_transient( 'soumais_locator_cache_keys' );
