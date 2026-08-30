<?php
/**
 * Vykoná sa pri úplnom odinštalovaní pluginu (nie iba deaktivácii).
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = get_option( 'cdv_delete_data_on_uninstall', 0 );

// Options, ktoré vždy upraceme (nie sú citlivé dáta klientov).
$options_to_remove = array(
	'cdv_categories',
	'cdv_max_filesize_mb',
	'cdv_notify_email',
	'cdv_staff_roles',
	'cdv_delete_data_on_uninstall',
	'cdv_db_version',
);

foreach ( $options_to_remove as $option_name ) {
	delete_option( $option_name );
}

if ( ! $delete_data ) {
	// Administrátor si neželal zmazať dokumenty - DB tabuľka aj súbory
	// ostávajú na serveri, aby sa dali obnoviť opätovnou inštaláciou.
	return;
}

global $wpdb;

$table = $wpdb->prefix . 'cdv_documents';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

// Zmazanie súkromného priečinka so všetkými nahratými dokumentmi.
require_once ABSPATH . 'wp-admin/includes/file.php';

$upload_dir = wp_upload_dir();
$base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'cdv-private';

if ( is_dir( $base_dir ) ) {
	global $wp_filesystem;

	if ( empty( $wp_filesystem ) ) {
		WP_Filesystem();
	}

	$wp_filesystem->delete( $base_dir, true );
}

// Odstránenie tokenov priečinkov z user meta a vlastnej role.
$users = get_users( array( 'fields' => array( 'ID' ) ) );

foreach ( $users as $user ) {
	delete_user_meta( $user->ID, '_cdv_folder_token' );
}

remove_role( 'cdv_client' );
