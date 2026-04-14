<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

if ( get_option( 'wld_delete_data_on_uninstall' ) == '1' ) {

	// Drop custom tables
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wld_leads" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wld_otps" );

	// Delete all wld_download CPT posts and their meta
	$posts = get_posts( [
		'post_type'      => 'wld_download',
		'numberposts'    => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	] );
	foreach ( $posts as $id ) {
		wp_delete_post( $id, true );
	}

	// Delete all plugin options
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wld\_%'" );
}
