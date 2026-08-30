<?php
/**
 * Správa rolí a oprávnení pluginu.
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_Roles
 */
class CDV_Roles {

	const CLIENT_ROLE = 'cdv_client';

	const CAP_VIEW_OWN    = 'cdv_view_own_documents';
	const CAP_UPLOAD      = 'cdv_upload_document';
	const CAP_MANAGE_ALL  = 'cdv_manage_all_documents';

	/**
	 * Zaregistruje rolu "Klient", ak ešte neexistuje.
	 */
	public static function register_role() {
		if ( null === get_role( self::CLIENT_ROLE ) ) {
			add_role(
				self::CLIENT_ROLE,
				__( 'Klient', 'client-document-vault' ),
				array(
					'read'              => true,
					self::CAP_VIEW_OWN  => true,
					self::CAP_UPLOAD    => true,
				)
			);
		} else {
			$role = get_role( self::CLIENT_ROLE );
			$role->add_cap( self::CAP_VIEW_OWN );
			$role->add_cap( self::CAP_UPLOAD );
		}
	}

	/**
	 * Zosynchronizuje oprávnenie "spravovať všetky dokumenty" na roliach
	 * označených v nastaveniach ako "pracovníci účtovnej spoločnosti".
	 * Administrátor má toto oprávnenie vždy.
	 */
	public static function sync_staff_capabilities() {
		$staff_roles = get_option( 'cdv_staff_roles', array( 'administrator' ) );

		if ( ! is_array( $staff_roles ) ) {
			$staff_roles = array( 'administrator' );
		}

		if ( ! in_array( 'administrator', $staff_roles, true ) ) {
			$staff_roles[] = 'administrator';
		}

		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}

		foreach ( $wp_roles->roles as $role_slug => $role_data ) {
			if ( self::CLIENT_ROLE === $role_slug ) {
				continue;
			}

			$role = get_role( $role_slug );

			if ( ! $role ) {
				continue;
			}

			if ( in_array( $role_slug, $staff_roles, true ) ) {
				$role->add_cap( self::CAP_MANAGE_ALL );
				// Pracovníci potrebujú aj CAP_VIEW_OWN, aby sa im zobrazilo menu pluginu.
				$role->add_cap( self::CAP_VIEW_OWN );
			} else {
				$role->remove_cap( self::CAP_MANAGE_ALL );
				$role->remove_cap( self::CAP_VIEW_OWN );
			}
		}
	}

	/**
	 * Zistí, či je používateľ "pracovník" s prístupom ku všetkým dokumentom.
	 *
	 * @param int|null $user_id ID používateľa, prázdne = aktuálny.
	 * @return bool
	 */
	public static function is_staff( $user_id = null ) {
		if ( null === $user_id ) {
			return current_user_can( self::CAP_MANAGE_ALL );
		}

		return user_can( $user_id, self::CAP_MANAGE_ALL );
	}

	/**
	 * Zistí, či je používateľ klient (má vlastný priečinok dokumentov).
	 *
	 * @param int|null $user_id ID používateľa, prázdne = aktuálny.
	 * @return bool
	 */
	public static function is_client( $user_id = null ) {
		if ( null === $user_id ) {
			return current_user_can( self::CAP_VIEW_OWN );
		}

		return user_can( $user_id, self::CAP_VIEW_OWN );
	}
}
