<?php
/**
 * Plugin Name:       Client Document Vault
 * Plugin URI:        https://github.com/slavomlynar/client-document-vault
 * Description:       Súkromný priestor pre klientov účtovnej spoločnosti na nahrávanie podkladov (PDF, obrázky, Excel) po prihlásení do administrácie. Každý klient vidí a nahráva iba do svojho vlastného priečinka.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Účtovná spoločnosť
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       client-document-vault
 * Domain Path:       /languages
 *
 * @package ClientDocumentVault
 */

// Bezpečnostná poistka - súbor sa nesmie volať priamo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CDV_VERSION', '1.0.0' );
define( 'CDV_PLUGIN_FILE', __FILE__ );
define( 'CDV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CDV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CDV_DB_VERSION', '1.0.0' );

/**
 * Autoload tried interné triedy pluginu.
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'CDV_' ) ) {
			return;
		}

		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		$path      = CDV_PLUGIN_DIR . 'includes/' . $file_name;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Aktivácia pluginu - vytvorenie DB tabuľky, rolí a úložiska.
 */
function cdv_activate_plugin() {
	require_once CDV_PLUGIN_DIR . 'includes/class-cdv-db.php';
	require_once CDV_PLUGIN_DIR . 'includes/class-cdv-roles.php';
	require_once CDV_PLUGIN_DIR . 'includes/class-cdv-storage.php';

	CDV_DB::create_or_upgrade_table();
	CDV_Roles::register_role();
	CDV_Roles::sync_staff_capabilities();
	CDV_Storage::ensure_base_directory_protected();

	if ( false === get_option( 'cdv_categories' ) ) {
		update_option(
			'cdv_categories',
			array(
				'Faktúry prijaté',
				'Faktúry vydané',
				'Bankové výpisy',
				'Mzdy a personalistika',
				'Zmluvy',
				'Iné podklady',
			)
		);
	}

	if ( false === get_option( 'cdv_max_filesize_mb' ) ) {
		update_option( 'cdv_max_filesize_mb', 20 );
	}

	if ( false === get_option( 'cdv_notify_email' ) ) {
		update_option( 'cdv_notify_email', get_option( 'admin_email' ) );
	}

	if ( false === get_option( 'cdv_staff_roles' ) ) {
		update_option( 'cdv_staff_roles', array( 'administrator' ) );
	}

	if ( false === get_option( 'cdv_delete_data_on_uninstall' ) ) {
		update_option( 'cdv_delete_data_on_uninstall', 0 );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cdv_activate_plugin' );

/**
 * Deaktivácia pluginu - dáta ostávajú zachované, iba sa vyčistia dočasné veci.
 */
function cdv_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cdv_deactivate_plugin' );

/**
 * Naštartovanie pluginu po načítaní všetkých pluginov.
 */
function cdv_run_plugin() {
	load_plugin_textdomain( 'client-document-vault', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	CDV_Plugin::instance();
}
add_action( 'plugins_loaded', 'cdv_run_plugin' );
