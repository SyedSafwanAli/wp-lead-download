<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WLD_DB_Setup {

	/**
	 * Create both custom tables. Skipped if DB version already matches.
	 */
	public static function create_table() {
		if ( get_option( 'wld_db_version' ) === WLD_DB_VERSION ) return;

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$leads           = $wpdb->prefix . 'wld_leads';
		$otps            = $wpdb->prefix . 'wld_otps';

		$sql = "CREATE TABLE $leads (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			download_id BIGINT UNSIGNED NOT NULL,
			full_name VARCHAR(100) NOT NULL,
			email VARCHAR(150) NOT NULL,
			phone VARCHAR(20) NOT NULL,
			downloaded_at DATETIME NOT NULL,
			ip_address VARCHAR(45),
			PRIMARY KEY  (id)
		) $charset_collate;

		CREATE TABLE $otps (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(150) NOT NULL,
			otp_code VARCHAR(6) NOT NULL,
			download_id BIGINT UNSIGNED NOT NULL,
			lead_data LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			is_used TINYINT(1) NOT NULL DEFAULT 0,
			ip_address VARCHAR(45),
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wld_db_version', WLD_DB_VERSION );
	}

	/**
	 * Insert a new OTP record.
	 *
	 * @param string $email
	 * @param string $otp_code       6-digit zero-padded string.
	 * @param int    $download_id
	 * @param string $lead_data_json JSON-encoded lead fields.
	 * @return int|false  Insert ID or false on failure.
	 */
	public static function create_otp( $email, $otp_code, $download_id, $lead_data_json ) {
		global $wpdb;

		$expiry_minutes = absint( get_option( 'wld_otp_expiry', 10 ) );
		$expires_at     = gmdate( 'Y-m-d H:i:s', time() + $expiry_minutes * 60 );

		$result = $wpdb->insert(
			$wpdb->prefix . 'wld_otps',
			[
				'email'       => sanitize_email( $email ),
				'otp_code'    => sanitize_text_field( $otp_code ),
				'download_id' => absint( $download_id ),
				'lead_data'   => $lead_data_json,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
				'expires_at'  => $expires_at,
				'is_used'     => 0,
				'ip_address'  => isset( $_SERVER['REMOTE_ADDR'] )
					? sanitize_text_field( $_SERVER['REMOTE_ADDR'] )
					: '',
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Find a valid (unused, non-expired) OTP row.
	 *
	 * @param string $email
	 * @param string $otp_code
	 * @param int    $download_id
	 * @return object|null
	 */
	public static function verify_otp( $email, $otp_code, $download_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wld_otps';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table
				 WHERE email = %s AND otp_code = %s AND download_id = %d
				   AND is_used = 0 AND expires_at > UTC_TIMESTAMP()
				 LIMIT 1",
				sanitize_email( $email ),
				sanitize_text_field( $otp_code ),
				absint( $download_id )
			)
		);
	}

	/**
	 * Mark an OTP row as used so it cannot be replayed.
	 *
	 * @param int $id
	 */
	public static function mark_otp_used( $id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wld_otps',
			[ 'is_used' => 1 ],
			[ 'id'      => absint( $id ) ],
			[ '%d' ],
			[ '%d' ]
		);
	}

	/**
	 * Delete all expired or already-used OTP rows. Called by daily cron.
	 */
	public static function cleanup_expired_otps() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wld_otps WHERE expires_at < UTC_TIMESTAMP() OR is_used = 1" );
	}

	/* -------------------------------------------------------------------------
	   Upgrade System
	------------------------------------------------------------------------- */

	/**
	 * Registry of incremental upgrade steps keyed by the DB version they produce.
	 * Add a new entry here (and a corresponding private method) for each future
	 * schema change. Steps run in version order and only when the stored
	 * wld_db_version is lower than the target version.
	 */
	private static $upgrades = [
		'1.1' => 'upgrade_to_1_1',
		'1.2' => 'upgrade_to_1_2',
	];

	/**
	 * Run any pending upgrade steps, then stamp the current DB version.
	 * Called on admin_init after create_table() is checked.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'wld_db_version', '0' );

		if ( version_compare( $installed, WLD_DB_VERSION, '>=' ) ) return;

		foreach ( self::$upgrades as $target_ver => $method ) {
			if ( version_compare( $installed, $target_ver, '<' ) ) {
				self::$method();
			}
		}

		update_option( 'wld_db_version', WLD_DB_VERSION );
	}

	/**
	 * v1.1 — Add an index on wld_leads.email to speed up duplicate checks.
	 * Safe to call multiple times (checks whether the index already exists).
	 */
	private static function upgrade_to_1_1() {
		global $wpdb;
		$table = $wpdb->prefix . 'wld_leads';

		$index_exists = $wpdb->get_results(
			$wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", 'idx_email' )
		);

		if ( empty( $index_exists ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD INDEX idx_email (email)" );
		}
	}

	/**
	 * v1.2 — Add composite index on wld_otps (email, download_id) for faster
	 * rate-limit and brute-force queries.
	 */
	private static function upgrade_to_1_2() {
		global $wpdb;
		$table = $wpdb->prefix . 'wld_otps';

		$index_exists = $wpdb->get_results(
			$wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", 'idx_email_dl' )
		);

		if ( empty( $index_exists ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD INDEX idx_email_dl (email, download_id)" );
		}
	}

	/* -------------------------------------------------------------------------
	   Lead Queries
	------------------------------------------------------------------------- */

	/**
	 * Fetch a paginated list of leads with optional filters.
	 *
	 * @param array $args  Keys: download_id, search, per_page, paged, orderby, order.
	 * @return array{ items: object[], total: int }
	 */
	public static function get_leads( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'download_id' => 0,
			'search'      => '',
			'per_page'    => 20,
			'paged'       => 1,
			'orderby'     => 'downloaded_at',
			'order'       => 'DESC',
		] );

		$table   = $wpdb->prefix . 'wld_leads';
		$wheres  = [];
		$params  = [];

		if ( absint( $args['download_id'] ) > 0 ) {
			$wheres[] = 'download_id = %d';
			$params[] = absint( $args['download_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$wheres[] = '(full_name LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';
		$allowed   = [ 'id', 'downloaded_at', 'full_name', 'email', 'phone' ];
		$orderby   = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'downloaded_at';
		$order     = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$per_page  = absint( $args['per_page'] );
		$offset    = ( max( 1, absint( $args['paged'] ) ) - 1 ) * $per_page;

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where_sql", $params ) );
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d",
					array_merge( $params, [ $per_page, $offset ] )
				)
			) ?: [];
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table ORDER BY $orderby $order LIMIT %d OFFSET %d",
					[ $per_page, $offset ]
				)
			) ?: [];
		}

		return [ 'items' => $items, 'total' => $total ];
	}

	/**
	 * Permanently delete a single lead row.
	 *
	 * @param int $id
	 */
	public static function delete_lead( $id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'wld_leads', [ 'id' => absint( $id ) ], [ '%d' ] );
	}

	/**
	 * Count OTP requests for an email in the past hour (brute-force protection).
	 *
	 * @param string $email
	 * @param int    $download_id
	 * @return int
	 */
	public static function get_otp_attempt_count( $email, $download_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wld_otps
				 WHERE email = %s AND download_id = %d
				   AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)",
				sanitize_email( $email ),
				absint( $download_id )
			)
		) ?: 0;
	}

	/**
	 * Count leads created after the last time the admin viewed the Leads page.
	 * Used for the unread count badge in the admin menu.
	 *
	 * @return int
	 */
	public static function get_new_leads_count() {
		global $wpdb;
		$last_viewed = get_option( 'wld_last_viewed_leads', '0000-00-00 00:00:00' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wld_leads WHERE downloaded_at > %s",
				$last_viewed
			)
		) ?: 0;
	}

	/**
	 * Count total leads for a specific download.
	 *
	 * @param int $download_id
	 * @return int
	 */
	public static function get_lead_count_by_download( $download_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wld_leads WHERE download_id = %d",
				absint( $download_id )
			)
		) ?: 0;
	}

	/**
	 * Fetch the most recent leads for a specific download.
	 *
	 * @param int $download_id
	 * @param int $limit
	 * @return object[]
	 */
	public static function get_recent_leads( $download_id, $limit = 5 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT full_name, email, downloaded_at FROM {$wpdb->prefix}wld_leads
				 WHERE download_id = %d ORDER BY id DESC LIMIT %d",
				absint( $download_id ),
				absint( $limit )
			)
		) ?: [];
	}
}
