<?php
/**
 * Plugin Name: WP Lead Download
 * Plugin URI: https://yoursite.com/wp-lead-download
 * Description: Capture leads via OTP-verified download gate
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: wp-lead-download
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'WLD_VERSION' ) )     define( 'WLD_VERSION',     '1.0.5' );
if ( ! defined( 'WLD_DB_VERSION' ) )  define( 'WLD_DB_VERSION',  '1.0' );
if ( ! defined( 'WLD_PLUGIN_DIR' ) )  define( 'WLD_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
if ( ! defined( 'WLD_PLUGIN_URL' ) )  define( 'WLD_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
if ( ! defined( 'WLD_PLUGIN_FILE' ) ) define( 'WLD_PLUGIN_FILE', __FILE__ );

require_once WLD_PLUGIN_DIR . 'includes/class-db-setup.php';
require_once WLD_PLUGIN_DIR . 'includes/class-email-notifier.php';
require_once WLD_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once WLD_PLUGIN_DIR . 'admin/class-downloads-cpt.php';
// WP_List_Table is an admin-only class; only load it in admin/AJAX context.
if ( is_admin() ) {
	require_once WLD_PLUGIN_DIR . 'admin/class-leads-table.php';
	require_once WLD_PLUGIN_DIR . 'includes/class-updater.php';
}
require_once WLD_PLUGIN_DIR . 'admin/class-settings.php';
require_once WLD_PLUGIN_DIR . 'admin/class-admin-menu.php';
require_once WLD_PLUGIN_DIR . 'public/class-shortcode.php';

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain(
		'wp-lead-download',
		false,
		dirname( plugin_basename( WLD_PLUGIN_FILE ) ) . '/languages/'
	);
} );

register_activation_hook( WLD_PLUGIN_FILE, 'wld_activate' );
function wld_activate() {
	WLD_DB_Setup::create_table();
	WLD_Downloads_CPT::register();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'wld_cleanup_otps_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'wld_cleanup_otps_cron' );
	}
}

register_deactivation_hook( WLD_PLUGIN_FILE, 'wld_deactivate' );
function wld_deactivate() {
	flush_rewrite_rules();
	$timestamp = wp_next_scheduled( 'wld_cleanup_otps_cron' );
	if ( $timestamp ) wp_unschedule_event( $timestamp, 'wld_cleanup_otps_cron' );
}

add_action( 'wld_cleanup_otps_cron', [ 'WLD_DB_Setup', 'cleanup_expired_otps' ] );

// Fallback: create tables on admin_init in case the activation hook was missed
// (e.g. plugin uploaded via FTP instead of activated through the admin UI).
// Also run any pending schema upgrade steps.
add_action( 'admin_init', function () {
	if ( get_option( 'wld_db_version' ) !== WLD_DB_VERSION ) {
		WLD_DB_Setup::create_table();
	}
	WLD_DB_Setup::maybe_upgrade();
} );

// Auto-updater — checks GitHub Releases for new versions (admin only).
if ( is_admin() ) {
	new WLD_Updater( WLD_PLUGIN_FILE, 'SyedSafwanAli', 'wp-lead-download' );
}

WLD_Downloads_CPT::register();
WLD_Admin_Menu::init();
WLD_Shortcode::init();
WLD_Form_Handler::init();

// Nonce endpoint — lets cached pages fetch a fresh nonce before the first AJAX call.
add_action( 'wp_ajax_wld_get_nonce',        function () {
	wp_send_json_success( [ 'nonce' => wp_create_nonce( 'wld_nonce' ) ] );
} );
add_action( 'wp_ajax_nopriv_wld_get_nonce', function () {
	wp_send_json_success( [ 'nonce' => wp_create_nonce( 'wld_nonce' ) ] );
} );
