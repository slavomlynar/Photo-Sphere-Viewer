<?php
/**
 * Administračné rozhranie pluginu - menu, stránky, nastavenia.
 *
 * @package ClientDocumentVault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDV_Admin
 */
class CDV_Admin {

	/**
	 * Zaregistruje všetky potrebné admin hooky.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'restrict_menu_for_clients' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_client_dashboard' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_save_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_notices' ) );
	}

	/**
	 * Zaregistruje položky menu. Hlavnú stránku vidia klienti aj pracovníci,
	 * nastavenia iba používatelia s "manage_options" (typicky administrátor).
	 */
	public static function register_menu() {
		$menu_title = CDV_Roles::is_staff() ? __( 'Dokumenty klientov', 'client-document-vault' ) : __( 'Moje dokumenty', 'client-document-vault' );

		add_menu_page(
			__( 'Klientske dokumenty', 'client-document-vault' ),
			$menu_title,
			CDV_Roles::CAP_VIEW_OWN,
			'cdv-documents',
			array( __CLASS__, 'render_documents_page' ),
			'dashicons-media-document',
			26
		);

		add_submenu_page(
			'cdv-documents',
			$menu_title,
			__( 'Dokumenty', 'client-document-vault' ),
			CDV_Roles::CAP_VIEW_OWN,
			'cdv-documents',
			array( __CLASS__, 'render_documents_page' )
		);

		add_submenu_page(
			'cdv-documents',
			__( 'Nastavenia trezoru dokumentov', 'client-document-vault' ),
			__( 'Nastavenia', 'client-document-vault' ),
			'manage_options',
			'cdv-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Pre používateľov, ktorí sú výhradne "klient" (nemajú prístup ku
	 * všetkým dokumentom ani manage_options), skryjeme štandardné WP menu,
	 * aby videli iba svoje dokumenty a svoj profil.
	 */
	public static function restrict_menu_for_clients() {
		if ( ! CDV_Roles::is_client() || CDV_Roles::is_staff() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$hide = array(
			'index.php',
			'edit.php',
			'upload.php',
			'edit-comments.php',
			'themes.php',
			'plugins.php',
			'users.php',
			'tools.php',
			'options-general.php',
			'edit.php?post_type=page',
		);

		foreach ( $hide as $menu_slug ) {
			remove_menu_page( $menu_slug );
		}
	}

	/**
	 * Klientov po vstupe do administrácie presmerujeme rovno na ich
	 * dokumenty namiesto štandardného dashboardu.
	 */
	public static function maybe_redirect_client_dashboard() {
		global $pagenow;

		if ( 'index.php' !== $pagenow ) {
			return;
		}

		if ( ! CDV_Roles::is_client() || CDV_Roles::is_staff() || current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=cdv-documents' ) );
		exit;
	}

	/**
	 * Vypíše informačné hlášky (napr. po zmazaní dokumentu).
	 */
	public static function maybe_show_notices() {
		$screen = get_current_screen();

		if ( ! $screen || false === strpos( $screen->id, 'cdv-documents' ) ) {
			return;
		}

		if ( isset( $_GET['cdv_deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Dokument bol zmazaný.', 'client-document-vault' ) . '</p></div>';
		}
	}

	/**
	 * Vykreslí hlavnú stránku - podľa role buď prehľad klientov (pracovník)
	 * alebo vlastné dokumenty (klient).
	 */
	public static function render_documents_page() {
		if ( ! current_user_can( CDV_Roles::CAP_VIEW_OWN ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie zobraziť túto stránku.', 'client-document-vault' ) );
		}

		echo '<div class="wrap cdv-wrap">';

		if ( CDV_Roles::is_staff() ) {
			$client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $client_id && CDV_Roles::is_client( $client_id ) ) {
				self::render_client_detail_for_staff( $client_id );
			} else {
				self::render_clients_overview();
			}
		} else {
			self::render_own_documents( get_current_user_id() );
		}

		echo '</div>';
	}

	/**
	 * Prehľad všetkých klientov pre pracovníkov účtovnej spoločnosti.
	 */
	private static function render_clients_overview() {
		$overview = CDV_DB::get_clients_overview();

		echo '<h1>' . esc_html__( 'Dokumenty klientov', 'client-document-vault' ) . '</h1>';

		if ( empty( $overview ) ) {
			echo '<p>' . esc_html__( 'Zatiaľ nemáte založených žiadnych klientov. Vytvorte používateľa s rolou "Klient" v sekcii Používatelia.', 'client-document-vault' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped cdv-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Klient', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'E-mail', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'Počet dokumentov', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'Posledné nahratie', 'client-document-vault' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $overview as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['user']->display_name ); ?></td>
						<td><?php echo esc_html( $row['user']->user_email ); ?></td>
						<td><?php echo esc_html( $row['total'] ); ?></td>
						<td>
							<?php
							echo $row['last_upload']
								? esc_html( mysql2date( 'j. n. Y H:i', $row['last_upload'] ) )
								: esc_html__( '—', 'client-document-vault' );
							?>
						</td>
						<td>
							<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cdv-documents&client_id=' . $row['user']->ID ) ); ?>">
								<?php esc_html_e( 'Zobraziť dokumenty', 'client-document-vault' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Detail jedného klienta pre pracovníka - zoznam dokumentov a možnosť
	 * nahrať súbor v mene klienta alebo mu poslať dokument naspäť.
	 *
	 * @param int $client_id ID klienta.
	 */
	private static function render_client_detail_for_staff( $client_id ) {
		$client = get_userdata( $client_id );

		echo '<a href="' . esc_url( admin_url( 'admin.php?page=cdv-documents' ) ) . '">&larr; ' . esc_html__( 'Späť na zoznam klientov', 'client-document-vault' ) . '</a>';
		echo '<h1>' . esc_html( sprintf( /* translators: %s: meno klienta */ __( 'Dokumenty klienta: %s', 'client-document-vault' ), $client->display_name ) ) . '</h1>';

		self::render_upload_form( $client_id, true );
		self::render_documents_table( CDV_DB::get_for_client( $client_id ), true );
	}

	/**
	 * Vlastné dokumenty klienta.
	 *
	 * @param int $client_id ID prihláseného klienta.
	 */
	private static function render_own_documents( $client_id ) {
		echo '<h1>' . esc_html__( 'Moje dokumenty', 'client-document-vault' ) . '</h1>';
		echo '<p>' . esc_html__( 'Tu môžete nahrať podklady pre svoju účtovníčku a nájdete tu aj dokumenty, ktoré vám poslala naspäť.', 'client-document-vault' ) . '</p>';

		self::render_upload_form( $client_id, false );
		self::render_documents_table( CDV_DB::get_for_client( $client_id ), false );
	}

	/**
	 * Formulár na nahratie súboru.
	 *
	 * @param int  $client_id ID klienta, ktorému sa dokument priradí.
	 * @param bool $is_staff  Či formulár vypĺňa pracovník (má navyše výber smeru).
	 */
	private static function render_upload_form( $client_id, $is_staff ) {
		$categories = get_option( 'cdv_categories', array() );
		?>
		<div class="cdv-upload-box">
			<h2><?php esc_html_e( 'Nahrať nový dokument', 'client-document-vault' ); ?></h2>
			<form id="cdv-upload-form" method="post" enctype="multipart/form-data">
				<input type="hidden" name="client_id" value="<?php echo esc_attr( $client_id ); ?>" />

				<p>
					<label for="cdv_file"><?php esc_html_e( 'Súbor (PDF, obrázok, Excel/CSV):', 'client-document-vault' ); ?></label><br />
					<input type="file" name="cdv_file" id="cdv_file" required
						accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.csv" />
				</p>

				<p>
					<label for="cdv_category"><?php esc_html_e( 'Kategória:', 'client-document-vault' ); ?></label><br />
					<select name="category" id="cdv_category">
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label for="cdv_period"><?php esc_html_e( 'Obdobie (napr. 08/2026):', 'client-document-vault' ); ?></label><br />
					<input type="text" name="period" id="cdv_period" placeholder="MM/RRRR" />
				</p>

				<p>
					<label for="cdv_note"><?php esc_html_e( 'Poznámka (nepovinné):', 'client-document-vault' ); ?></label><br />
					<textarea name="note" id="cdv_note" rows="2" class="large-text"></textarea>
				</p>

				<?php if ( $is_staff ) : ?>
					<p>
						<label>
							<input type="radio" name="direction" value="to_office" checked="checked" />
							<?php esc_html_e( 'Nahrávam v mene klienta (podklad prijatý napr. osobne/mailom)', 'client-document-vault' ); ?>
						</label><br />
						<label>
							<input type="radio" name="direction" value="to_client" />
							<?php esc_html_e( 'Posielam dokument klientovi (napr. hotové priznanie, výkaz)', 'client-document-vault' ); ?>
						</label>
					</p>
				<?php endif; ?>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Nahrať dokument', 'client-document-vault' ); ?></button>
					<span class="cdv-upload-status" aria-live="polite"></span>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Tabuľka dokumentov.
	 *
	 * @param array $documents Zoznam dokumentov (riadky z DB).
	 * @param bool  $is_staff  Či tabuľku zobrazuje pracovník (vidí aj tlačidlo zmazať).
	 */
	private static function render_documents_table( $documents, $is_staff ) {
		?>
		<h2><?php esc_html_e( 'Nahraté dokumenty', 'client-document-vault' ); ?></h2>
		<table class="widefat striped cdv-table" id="cdv-documents-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Názov súboru', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'Kategória', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'Obdobie', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'Smer', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'Veľkosť', 'client-document-vault' ); ?></th>
					<th><?php esc_html_e( 'Nahraté', 'client-document-vault' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $documents ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'Zatiaľ tu nie sú žiadne dokumenty.', 'client-document-vault' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $documents as $document ) : ?>
						<?php echo self::render_document_row( $document, $is_staff ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Vykreslí jeden riadok tabuľky dokumentov (používa sa aj pri AJAX odpovedi
	 * po úspešnom nahratí, aby sa zoznam aktualizoval bez obnovenia stránky).
	 *
	 * @param object $document Riadok z DB tabuľky dokumentov.
	 * @param bool   $is_staff Či riadok zobrazuje pracovník.
	 * @return string HTML markup jedného <tr> riadku.
	 */
	public static function render_document_row( $document, $is_staff ) {
		$download_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'cdv_download',
					'id'     => $document->id,
				),
				admin_url( 'admin-post.php' )
			),
			'cdv_download_' . $document->id
		);

		$direction_label = CDV_DB::DIRECTION_TO_CLIENT === $document->direction
			? __( 'Od účtovníčky', 'client-document-vault' )
			: __( 'Od klienta', 'client-document-vault' );

		ob_start();
		?>
		<tr data-doc-id="<?php echo esc_attr( $document->id ); ?>">
			<td>
				<a href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html( $document->original_filename ); ?></a>
				<?php if ( ! empty( $document->note ) ) : ?>
					<br /><small><?php echo esc_html( $document->note ); ?></small>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $document->category ); ?></td>
			<td><?php echo esc_html( $document->period ); ?></td>
			<td><?php echo esc_html( $direction_label ); ?></td>
			<td><?php echo esc_html( CDV_Storage::format_filesize( $document->filesize ) ); ?></td>
			<td><?php echo esc_html( mysql2date( 'j. n. Y H:i', $document->created_at ) ); ?></td>
			<td>
				<a class="button" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Stiahnuť', 'client-document-vault' ); ?></a>
				<?php if ( $is_staff ) : ?>
					<?php
					$delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'action'   => 'cdv_delete',
								'id'       => $document->id,
								'redirect' => admin_url( 'admin.php?page=cdv-documents&client_id=' . $document->client_id ),
							),
							admin_url( 'admin-post.php' )
						),
						'cdv_delete_' . $document->id
					);
					?>
					<a class="button cdv-delete-link" href="<?php echo esc_url( $delete_url ); ?>"
						onclick="return confirm('<?php echo esc_js( __( 'Naozaj chcete tento dokument natrvalo zmazať?', 'client-document-vault' ) ); ?>');">
						<?php esc_html_e( 'Zmazať', 'client-document-vault' ); ?>
					</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Uloží nastavenia pluginu po odoslaní formulára na stránke nastavení.
	 */
	public static function maybe_save_settings() {
		if ( ! isset( $_POST['cdv_settings_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! check_admin_referer( 'cdv_save_settings', 'cdv_settings_nonce' ) ) {
			return;
		}

		$categories_raw = isset( $_POST['cdv_categories'] ) ? wp_unslash( $_POST['cdv_categories'] ) : '';
		$categories     = array_filter( array_map( 'trim', explode( "\n", $categories_raw ) ) );
		update_option( 'cdv_categories', array_values( $categories ) );

		$max_filesize = isset( $_POST['cdv_max_filesize_mb'] ) ? absint( $_POST['cdv_max_filesize_mb'] ) : 20;
		update_option( 'cdv_max_filesize_mb', max( 1, $max_filesize ) );

		$notify_email = isset( $_POST['cdv_notify_email'] ) ? sanitize_email( wp_unslash( $_POST['cdv_notify_email'] ) ) : '';
		update_option( 'cdv_notify_email', $notify_email );

		$staff_roles = isset( $_POST['cdv_staff_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['cdv_staff_roles'] ) ) : array();

		if ( ! in_array( 'administrator', $staff_roles, true ) ) {
			$staff_roles[] = 'administrator';
		}

		update_option( 'cdv_staff_roles', $staff_roles );

		update_option( 'cdv_delete_data_on_uninstall', isset( $_POST['cdv_delete_data_on_uninstall'] ) ? 1 : 0 );

		CDV_Roles::sync_staff_capabilities();

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Nastavenia boli uložené.', 'client-document-vault' ) . '</p></div>';
			}
		);
	}

	/**
	 * Vykreslí stránku nastavení pluginu.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie zobraziť túto stránku.', 'client-document-vault' ) );
		}

		$categories   = implode( "\n", get_option( 'cdv_categories', array() ) );
		$max_filesize = get_option( 'cdv_max_filesize_mb', 20 );
		$notify_email = get_option( 'cdv_notify_email', get_option( 'admin_email' ) );
		$staff_roles  = get_option( 'cdv_staff_roles', array( 'administrator' ) );
		$delete_data  = get_option( 'cdv_delete_data_on_uninstall', 0 );

		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
		?>
		<div class="wrap cdv-wrap">
			<h1><?php esc_html_e( 'Nastavenia trezoru dokumentov', 'client-document-vault' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'cdv_save_settings', 'cdv_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="cdv_notify_email"><?php esc_html_e( 'E-mail na notifikácie', 'client-document-vault' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="cdv_notify_email" name="cdv_notify_email" value="<?php echo esc_attr( $notify_email ); ?>" />
							<p class="description"><?php esc_html_e( 'Sem príde upozornenie, keď klient nahrá nový podklad.', 'client-document-vault' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cdv_max_filesize_mb"><?php esc_html_e( 'Max. veľkosť súboru (MB)', 'client-document-vault' ); ?></label></th>
						<td><input type="number" min="1" max="500" id="cdv_max_filesize_mb" name="cdv_max_filesize_mb" value="<?php echo esc_attr( $max_filesize ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="cdv_categories"><?php esc_html_e( 'Kategórie dokumentov', 'client-document-vault' ); ?></label></th>
						<td>
							<textarea id="cdv_categories" name="cdv_categories" rows="6" class="large-text"><?php echo esc_textarea( $categories ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Jedna kategória na riadok (napr. Faktúry, Bankové výpisy, Mzdy...).', 'client-document-vault' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Roly pracovníkov (vidia všetkých klientov)', 'client-document-vault' ); ?></th>
						<td>
							<?php foreach ( $wp_roles->roles as $role_slug => $role_data ) : ?>
								<?php if ( CDV_Roles::CLIENT_ROLE === $role_slug ) { continue; } ?>
								<label style="display:block;">
									<input type="checkbox" name="cdv_staff_roles[]" value="<?php echo esc_attr( $role_slug ); ?>"
										<?php checked( in_array( $role_slug, (array) $staff_roles, true ) ); ?>
										<?php disabled( 'administrator' === $role_slug ); ?> />
									<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Administrátor má prístup vždy.', 'client-document-vault' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Pri odinštalovaní pluginu', 'client-document-vault' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cdv_delete_data_on_uninstall" value="1" <?php checked( $delete_data, 1 ); ?> />
								<?php esc_html_e( 'Natrvalo zmazať všetky nahraté dokumenty a záznamy o nich (nevratné).', 'client-document-vault' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Uložiť nastavenia', 'client-document-vault' ) ); ?>
			</form>
		</div>
		<?php
	}
}
