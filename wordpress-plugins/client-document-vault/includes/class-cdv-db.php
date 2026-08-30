<?php
/**
 * Práca s vlastnou databázovou tabuľkou pre metadáta dokumentov.
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_DB
 */
class CDV_DB {

	const DIRECTION_TO_OFFICE = 'to_office';
	const DIRECTION_TO_CLIENT = 'to_client';

	/**
	 * Vráti celý názov tabuľky s prefixom.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'cdv_documents';
	}

	/**
	 * Vytvorí alebo aktualizuje štruktúru tabuľky (dbDelta je "safe to re-run").
	 */
	public static function create_or_upgrade_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id BIGINT UNSIGNED NOT NULL,
			uploaded_by BIGINT UNSIGNED NOT NULL,
			direction VARCHAR(20) NOT NULL DEFAULT 'to_office',
			original_filename VARCHAR(255) NOT NULL,
			stored_filename VARCHAR(255) NOT NULL,
			mime_type VARCHAR(100) NOT NULL,
			filesize BIGINT UNSIGNED NOT NULL DEFAULT 0,
			category VARCHAR(191) NOT NULL DEFAULT '',
			period VARCHAR(20) NOT NULL DEFAULT '',
			note TEXT NULL,
			created_at DATETIME NOT NULL,
			download_count INT UNSIGNED NOT NULL DEFAULT 0,
			last_downloaded_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY direction (direction),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Vloží nový záznam o dokumente.
	 *
	 * @param array $data Dáta dokumentu.
	 * @return int|false ID nového záznamu alebo false.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$defaults = array(
			'client_id'          => 0,
			'uploaded_by'        => 0,
			'direction'          => self::DIRECTION_TO_OFFICE,
			'original_filename'  => '',
			'stored_filename'    => '',
			'mime_type'          => '',
			'filesize'           => 0,
			'category'           => '',
			'period'             => '',
			'note'               => '',
			'created_at'         => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table_name(),
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Nájde jeden dokument podľa ID.
	 *
	 * @param int $id ID dokumentu.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);
	}

	/**
	 * Vráti zoznam dokumentov pre daného klienta, zoradené od najnovších.
	 *
	 * @param int $client_id ID klienta.
	 * @return array
	 */
	public static function get_for_client( $client_id ) {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} WHERE client_id = %d ORDER BY created_at DESC", $client_id )
		);
	}

	/**
	 * Vráti zoznam klientov (WP používateľov s rolou klienta) spolu s počtom
	 * a dátumom posledného nahratého dokumentu.
	 *
	 * @return array
	 */
	public static function get_clients_overview() {
		global $wpdb;

		$table = self::table_name();

		$clients = get_users( array( 'role' => CDV_Roles::CLIENT_ROLE ) );

		$overview = array();

		foreach ( $clients as $client ) {
			$stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					"SELECT COUNT(*) AS total, MAX(created_at) AS last_upload
					 FROM {$table} WHERE client_id = %d",
					$client->ID
				)
			);

			$overview[] = array(
				'user'        => $client,
				'total'       => $stats ? (int) $stats->total : 0,
				'last_upload' => $stats ? $stats->last_upload : null,
			);
		}

		return $overview;
	}

	/**
	 * Zaznamená stiahnutie dokumentu (počítadlo + posledný čas).
	 *
	 * @param int $id ID dokumentu.
	 */
	public static function mark_downloaded( $id ) {
		global $wpdb;

		$table = self::table_name();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"UPDATE {$table} SET download_count = download_count + 1, last_downloaded_at = %s WHERE id = %d",
				current_time( 'mysql' ),
				$id
			)
		);
	}

	/**
	 * Odstráni záznam z databázy.
	 *
	 * @param int $id ID dokumentu.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
