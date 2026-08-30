<?php
/**
 * E-mailové notifikácie o nových dokumentoch.
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_Notifications
 */
class CDV_Notifications {

	/**
	 * Upozorní účtovnú spoločnosť, že klient nahral nový podklad.
	 *
	 * @param object $document Riadok z DB tabuľky dokumentov.
	 */
	public static function notify_office( $document ) {
		$to = get_option( 'cdv_notify_email', get_option( 'admin_email' ) );

		if ( empty( $to ) ) {
			return;
		}

		$client = get_userdata( $document->client_id );
		$name   = $client ? $client->display_name : __( 'neznámy klient', 'client-document-vault' );

		$subject = sprintf(
			/* translators: %s: meno klienta. */
			__( '[Klientske dokumenty] Nový podklad od klienta: %s', 'client-document-vault' ),
			$name
		);

		$link = admin_url( 'admin.php?page=cdv-documents&client_id=' . (int) $document->client_id );

		$body = sprintf(
			/* translators: 1: meno klienta, 2: kategória, 3: pôvodný názov súboru, 4: odkaz do administrácie. */
			__(
				"Klient %1\$s nahral nový podklad.\n\nKategória: %2\$s\nSúbor: %3\$s\n\nZobraziť v administrácii: %4\$s",
				'client-document-vault'
			),
			$name,
			$document->category ? $document->category : '—',
			$document->original_filename,
			$link
		);

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Upozorní klienta, že mu účtovná spoločnosť pridala nový dokument.
	 *
	 * @param object $document Riadok z DB tabuľky dokumentov.
	 */
	public static function notify_client( $document ) {
		$client = get_userdata( $document->client_id );

		if ( ! $client || empty( $client->user_email ) ) {
			return;
		}

		$subject = __( 'Máte nový dokument k dispozícii', 'client-document-vault' );
		$link    = admin_url( 'admin.php?page=cdv-documents' );

		$body = sprintf(
			/* translators: 1: názov webu, 2: odkaz na prihlásenie. */
			__(
				"Dobrý deň,\n\nna webe %1\$s vám bol sprístupnený nový dokument. Po prihlásení ho nájdete v sekcii \"Moje dokumenty\":\n%2\$s",
				'client-document-vault'
			),
			get_bloginfo( 'name' ),
			$link
		);

		wp_mail( $client->user_email, $subject, $body );
	}
}
