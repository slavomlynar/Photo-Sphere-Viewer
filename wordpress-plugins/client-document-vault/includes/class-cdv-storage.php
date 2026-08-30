<?php
/**
 * Práca so súborovým systémom - súkromné úložisko dokumentov klientov.
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_Storage
 */
class CDV_Storage {

	/**
	 * Zoznam povolených prípon a im zodpovedajúcich MIME typov.
	 * Používa sa na dvojitú validáciu (prípona + skutočný obsah súboru).
	 *
	 * @return array
	 */
	public static function allowed_types() {
		return array(
			'pdf'  => array( 'application/pdf' ),
			'jpg'  => array( 'image/jpeg' ),
			'jpeg' => array( 'image/jpeg' ),
			'png'  => array( 'image/png' ),
			'gif'  => array( 'image/gif' ),
			'webp' => array( 'image/webp' ),
			'xls'  => array( 'application/vnd.ms-excel', 'application/msword', 'application/x-ole-storage' ),
			'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ),
			'csv'  => array( 'text/csv', 'text/plain' ),
		);
	}

	/**
	 * Základný priečinok pre súkromné úložisko (mimo verejného media-library).
	 *
	 * @return string Absolútna cesta bez lomítka na konci.
	 */
	public static function base_dir() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . 'cdv-private';
	}

	/**
	 * Zaistí, že základný priečinok existuje a je chránený pred priamym
	 * prístupom z webu (Apache aj všeobecná ochrana cez index.php).
	 */
	public static function ensure_base_directory_protected() {
		$base = self::base_dir();

		if ( ! file_exists( $base ) ) {
			wp_mkdir_p( $base );
		}

		self::write_protection_files( $base );
	}

	/**
	 * Vytvorí (ak treba) a ochráni priečinok pre konkrétny "token" klienta.
	 *
	 * @param string $token Náhodný identifikátor priečinka klienta.
	 * @return string Absolútna cesta k priečinku klienta.
	 */
	public static function get_or_create_client_dir( $token ) {
		$token = sanitize_key( $token );
		$dir   = trailingslashit( self::base_dir() ) . $token;

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		self::write_protection_files( $dir );

		return $dir;
	}

	/**
	 * Zapíše .htaccess (Apache) a prázdny index.php do priečinka, aby sa
	 * zabránilo výpisu obsahu priečinka a priamemu spusteniu súborov.
	 * POZNÁMKA: Pri Nginx serveri .htaccess nefunguje - je nutné zablokovať
	 * prístup k /wp-content/uploads/cdv-private/ priamo v konfigurácii servera,
	 * viď readme.txt.
	 *
	 * @param string $dir Priečinok, ktorý sa má ochrániť.
	 */
	private static function write_protection_files( $dir ) {
		$htaccess = trailingslashit( $dir ) . '.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			$rules = "Require all denied\n" .
			         "<IfModule !mod_authz_core.c>\n" .
			         "  Order allow,deny\n" .
			         "  Deny from all\n" .
			         "</IfModule>\n";

			file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$index = trailingslashit( $dir ) . 'index.php';

		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Získa (alebo vytvorí) náhodný, neuhádnuteľný token priečinka klienta,
	 * uložený v jeho user meta. Vďaka tomu cesta k súborom neprezrádza ID
	 * používateľa.
	 *
	 * @param int $client_id ID klienta.
	 * @return string
	 */
	public static function get_client_folder_token( $client_id ) {
		$token = get_user_meta( $client_id, '_cdv_folder_token', true );

		if ( empty( $token ) || ! is_string( $token ) ) {
			$token = wp_generate_password( 32, false, false );
			update_user_meta( $client_id, '_cdv_folder_token', $token );
		}

		return $token;
	}

	/**
	 * Vygeneruje bezpečný, náhodný názov súboru na disku (bez pôvodného mena).
	 *
	 * @param string $extension Prípona súboru (bez bodky).
	 * @return string
	 */
	public static function generate_stored_filename( $extension ) {
		$extension = strtolower( preg_replace( '/[^a-z0-9]/i', '', $extension ) );

		return wp_generate_password( 24, false, false ) . '.' . $extension;
	}

	/**
	 * Absolútna cesta k súboru dokumentu na disku.
	 *
	 * @param object $document Riadok z DB tabuľky dokumentov.
	 * @return string
	 */
	public static function get_document_path( $document ) {
		$token = self::get_client_folder_token( $document->client_id );
		$dir   = trailingslashit( self::base_dir() ) . $token;

		return trailingslashit( $dir ) . $document->stored_filename;
	}

	/**
	 * Zmaže súbor z disku, ak existuje.
	 *
	 * @param object $document Riadok z DB tabuľky dokumentov.
	 * @return bool
	 */
	public static function delete_document_file( $document ) {
		$path = self::get_document_path( $document );

		if ( file_exists( $path ) ) {
			return wp_delete_file( $path ) || ! file_exists( $path );
		}

		return true;
	}

	/**
	 * Naformátuje veľkosť súboru na čitateľný reťazec.
	 *
	 * @param int $bytes Veľkosť v bajtoch.
	 * @return string
	 */
	public static function format_filesize( $bytes ) {
		return size_format( (int) $bytes, 1 );
	}
}
