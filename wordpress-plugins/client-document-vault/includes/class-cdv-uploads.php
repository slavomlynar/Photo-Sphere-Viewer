<?php
/**
 * Spracovanie nahrávania dokumentov (AJAX endpoint).
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_Uploads
 */
class CDV_Uploads {

	/**
	 * Zaregistruje AJAX akcie pre prihlásených používateľov.
	 */
	public static function init() {
		add_action( 'wp_ajax_cdv_upload_document', array( __CLASS__, 'handle_upload' ) );
	}

	/**
	 * Spracuje AJAX požiadavku na nahratie dokumentu.
	 */
	public static function handle_upload() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Musíte byť prihlásený.', 'client-document-vault' ) ), 401 );
		}

		check_ajax_referer( 'cdv_upload_document', 'nonce' );

		$current_user_id = get_current_user_id();
		$is_staff        = CDV_Roles::is_staff( $current_user_id );
		$is_client        = CDV_Roles::is_client( $current_user_id );

		if ( ! $is_staff && ! $is_client ) {
			wp_send_json_error( array( 'message' => __( 'Nemáte oprávnenie nahrávať dokumenty.', 'client-document-vault' ) ), 403 );
		}

		// Určenie cieľového klienta (priečinka) a smeru dokumentu.
		$direction = CDV_DB::DIRECTION_TO_OFFICE;

		if ( $is_staff && ! empty( $_POST['client_id'] ) ) {
			$client_id = absint( $_POST['client_id'] );

			if ( ! CDV_Roles::is_client( $client_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Vybraný klient neexistuje.', 'client-document-vault' ) ), 400 );
			}

			$direction = isset( $_POST['direction'] ) && 'to_client' === $_POST['direction']
				? CDV_DB::DIRECTION_TO_CLIENT
				: CDV_DB::DIRECTION_TO_OFFICE;
		} elseif ( $is_client ) {
			$client_id = $current_user_id;
		} else {
			wp_send_json_error( array( 'message' => __( 'Chýba identifikácia klienta.', 'client-document-vault' ) ), 400 );
		}

		if ( empty( $_FILES['cdv_file'] ) || ! isset( $_FILES['cdv_file']['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Nebol vybraný žiadny súbor.', 'client-document-vault' ) ), 400 );
		}

		$file = $_FILES['cdv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error( array( 'message' => self::upload_error_message( $file['error'] ) ), 400 );
		}

		$validation = self::validate_file( $file );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ), 400 );
		}

		$extension = $validation['extension'];
		$mime_type = $validation['mime_type'];

		$token = CDV_Storage::get_client_folder_token( $client_id );
		$dir   = CDV_Storage::get_or_create_client_dir( $token );

		$stored_filename = CDV_Storage::generate_stored_filename( $extension );
		$target_path     = trailingslashit( $dir ) . $stored_filename;

		if ( ! is_uploaded_file( $file['tmp_name'] ) || ! move_uploaded_file( $file['tmp_name'], $target_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			wp_send_json_error( array( 'message' => __( 'Súbor sa nepodarilo uložiť na server.', 'client-document-vault' ) ), 500 );
		}

		// Uzamkneme oprávnenia na súbore (len čítanie/zápis vlastníkom procesu).
		chmod( $target_path, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$original_filename = sanitize_file_name( wp_unslash( $file['name'] ) );

		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$period   = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		$doc_id = CDV_DB::insert(
			array(
				'client_id'         => $client_id,
				'uploaded_by'       => $current_user_id,
				'direction'         => $direction,
				'original_filename' => $original_filename,
				'stored_filename'   => $stored_filename,
				'mime_type'         => $mime_type,
				'filesize'          => (int) $file['size'],
				'category'          => $category,
				'period'            => $period,
				'note'              => $note,
			)
		);

		if ( ! $doc_id ) {
			// Ak sa nepodarilo zapísať do DB, súbor po sebe upraceme.
			wp_delete_file( $target_path );
			wp_send_json_error( array( 'message' => __( 'Nepodarilo sa uložiť záznam o dokumente.', 'client-document-vault' ) ), 500 );
		}

		$document = CDV_DB::get( $doc_id );

		if ( CDV_DB::DIRECTION_TO_CLIENT === $direction ) {
			CDV_Notifications::notify_client( $document );
		} else {
			CDV_Notifications::notify_office( $document );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Dokument bol úspešne nahraný.', 'client-document-vault' ),
				'row'     => CDV_Admin::render_document_row( $document, $is_staff ),
			)
		);
	}

	/**
	 * Overí, či je nahrávaný súbor povoleného typu, veľkosti a či jeho
	 * skutočný obsah (MIME) zodpovedá deklarovanej prípone.
	 *
	 * @param array $file Položka z $_FILES.
	 * @return array|WP_Error Pole s 'extension' a 'mime_type', alebo WP_Error.
	 */
	private static function validate_file( array $file ) {
		$max_bytes = (int) get_option( 'cdv_max_filesize_mb', 20 ) * 1024 * 1024;

		if ( $file['size'] > $max_bytes ) {
			return new WP_Error(
				'cdv_file_too_large',
				sprintf(
					/* translators: %s: maximálna povolená veľkosť súboru. */
					__( 'Súbor je príliš veľký. Maximálna povolená veľkosť je %s.', 'client-document-vault' ),
					size_format( $max_bytes )
				)
			);
		}

		$filename  = sanitize_file_name( wp_unslash( $file['name'] ) );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		$allowed = CDV_Storage::allowed_types();

		if ( '' === $extension || ! isset( $allowed[ $extension ] ) ) {
			return new WP_Error(
				'cdv_invalid_extension',
				__( 'Tento typ súboru nie je povolený. Povolené sú PDF, obrázky (JPG/PNG/GIF/WEBP) a Excel/CSV.', 'client-document-vault' )
			);
		}

		// Skutočná kontrola obsahu súboru cez fileinfo - ochrana proti
		// súborom s podvrhnutou príponou (napr. .php premenované na .pdf).
		$finfo         = finfo_open( FILEINFO_MIME_TYPE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$detected_mime = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $detected_mime, $allowed[ $extension ], true ) ) {
			return new WP_Error(
				'cdv_mime_mismatch',
				__( 'Obsah súboru nezodpovedá jeho prípone. Nahrajte prosím originálny, nepoškodený súbor.', 'client-document-vault' )
			);
		}

		return array(
			'extension' => $extension,
			'mime_type' => $detected_mime,
		);
	}

	/**
	 * Preloží PHP kód chyby nahrávania na zrozumiteľnú správu.
	 *
	 * @param int $error_code Kód z $_FILES['...']['error'].
	 * @return string
	 */
	private static function upload_error_message( $error_code ) {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'Súbor je príliš veľký.', 'client-document-vault' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'Súbor sa nahral iba čiastočne, skúste to znova.', 'client-document-vault' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'Nebol vybraný žiadny súbor.', 'client-document-vault' );
			default:
				return __( 'Pri nahrávaní súboru nastala chyba.', 'client-document-vault' );
		}
	}
}
