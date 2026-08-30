<?php
/**
 * Bezpečné sťahovanie a mazanie dokumentov cez admin-post.php.
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_Download
 */
class CDV_Download {

	/**
	 * Zaregistruje admin-post akcie pre prihlásených používateľov.
	 */
	public static function init() {
		add_action( 'admin_post_cdv_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_cdv_delete', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Overí, či aktuálny používateľ smie pristupovať k danému dokumentu.
	 *
	 * @param object $document Riadok z DB tabuľky dokumentov.
	 * @return bool
	 */
	private static function current_user_can_access( $document ) {
		if ( CDV_Roles::is_staff() ) {
			return true;
		}

		return CDV_Roles::is_client() && (int) $document->client_id === get_current_user_id();
	}

	/**
	 * Vybaví stiahnutie súboru - kontrola oprávnení a bezpečné odoslanie
	 * obsahu súboru bez odhalenia reálnej cesty na disku.
	 */
	public static function handle_download() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Musíte byť prihlásený.', 'client-document-vault' ), 401 );
		}

		$doc_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		check_admin_referer( 'cdv_download_' . $doc_id );

		$document = CDV_DB::get( $doc_id );

		if ( ! $document ) {
			wp_die( esc_html__( 'Dokument nebol nájdený.', 'client-document-vault' ), 404 );
		}

		if ( ! self::current_user_can_access( $document ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na stiahnutie tohto dokumentu.', 'client-document-vault' ), 403 );
		}

		$path = CDV_Storage::get_document_path( $document );

		if ( ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Súbor sa na serveri nenašiel.', 'client-document-vault' ), 404 );
		}

		CDV_DB::mark_downloaded( $doc_id );

		nocache_headers();
		header( 'Content-Type: ' . $document->mime_type );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $document->original_filename ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		// Vyčistíme prípadný výstupný buffer, aby sa súbor neposlal poškodený.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Vybaví trvalé zmazanie dokumentu. Dostupné iba pre pracovníkov
	 * (rola s oprávnením cdv_manage_all_documents) - klient dokumenty
	 * nemôže mazať, aby zostala zachovaná história podkladov.
	 */
	public static function handle_delete() {
		if ( ! CDV_Roles::is_staff() ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na zmazanie dokumentov.', 'client-document-vault' ), 403 );
		}

		$doc_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		check_admin_referer( 'cdv_delete_' . $doc_id );

		$document = CDV_DB::get( $doc_id );

		if ( ! $document ) {
			wp_die( esc_html__( 'Dokument nebol nájdený.', 'client-document-vault' ), 404 );
		}

		CDV_Storage::delete_document_file( $document );
		CDV_DB::delete( $doc_id );

		$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : admin_url( 'admin.php?page=cdv-documents' );

		wp_safe_redirect( add_query_arg( 'cdv_deleted', '1', $redirect ) );
		exit;
	}
}
