<?php
/**
 * Hlavná orchestračná trieda pluginu.
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_Plugin
 */
final class CDV_Plugin {

	/**
	 * Jediná inštancia (singleton).
	 *
	 * @var CDV_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Vráti jedinú inštanciu pluginu a spustí všetky moduly.
	 *
	 * @return CDV_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Konštruktor - registrácia jednotlivých modulov pluginu.
	 */
	private function __construct() {
		$this->maybe_upgrade();

		// Role a oprávnenia.
		add_action( 'admin_init', array( 'CDV_Roles', 'sync_staff_capabilities' ) );

		// Administračné rozhranie (menu, stránky, obmedzenie menu pre klientov).
		CDV_Admin::init();

		// Spracovanie nahrávania súborov (AJAX).
		CDV_Uploads::init();

		// Bezpečné sťahovanie a mazanie súborov.
		CDV_Download::init();

		// Načítanie CSS/JS pre admin obrazovky pluginu.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Skontroluje verziu DB schémy a v prípade potreby ju aktualizuje.
	 */
	private function maybe_upgrade() {
		$installed = get_option( 'cdv_db_version' );

		if ( CDV_DB_VERSION !== $installed ) {
			CDV_DB::create_or_upgrade_table();
			update_option( 'cdv_db_version', CDV_DB_VERSION );
		}
	}

	/**
	 * Načíta CSS/JS len na stránkach pluginu.
	 *
	 * @param string $hook Aktuálny "hook" administračnej stránky.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'client-document-vault' ) && false === strpos( $hook, 'cdv-' ) ) {
			return;
		}

		wp_enqueue_style( 'cdv-admin', CDV_PLUGIN_URL . 'assets/admin.css', array(), CDV_VERSION );
		wp_enqueue_script( 'cdv-admin', CDV_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), CDV_VERSION, true );

		wp_localize_script(
			'cdv-admin',
			'CDV_Data',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'upload_nonce'  => wp_create_nonce( 'cdv_upload_document' ),
				'max_filesize'  => (int) get_option( 'cdv_max_filesize_mb', 20 ) * 1024 * 1024,
				'i18n'          => array(
					'uploading'   => __( 'Nahrávam…', 'client-document-vault' ),
					'error'       => __( 'Nahrávanie zlyhalo.', 'client-document-vault' ),
					'too_large'   => __( 'Súbor je príliš veľký.', 'client-document-vault' ),
					'select_file' => __( 'Najprv vyberte súbor.', 'client-document-vault' ),
				),
			)
		);
	}
}
