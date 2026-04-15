<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WLD_Form_Handler {

	public static function init() {
		add_action( 'wp_ajax_wld_submit_lead',              [ __CLASS__, 'handle_submit_lead' ] );
		add_action( 'wp_ajax_nopriv_wld_submit_lead',       [ __CLASS__, 'handle_submit_lead' ] );
		add_action( 'wp_ajax_wld_verify_otp',               [ __CLASS__, 'handle_verify_otp' ] );
		add_action( 'wp_ajax_nopriv_wld_verify_otp',        [ __CLASS__, 'handle_verify_otp' ] );
		add_action( 'wp_ajax_wld_resend_otp',               [ __CLASS__, 'handle_resend_otp' ] );
		add_action( 'wp_ajax_nopriv_wld_resend_otp',        [ __CLASS__, 'handle_resend_otp' ] );
		add_action( 'wp_ajax_wld_returning_user',           [ __CLASS__, 'handle_returning_user' ] );
		add_action( 'wp_ajax_nopriv_wld_returning_user',    [ __CLASS__, 'handle_returning_user' ] );
		add_action( 'wp_ajax_wld_download_file',            [ __CLASS__, 'handle_download_file' ] );
		add_action( 'wp_ajax_nopriv_wld_download_file',     [ __CLASS__, 'handle_download_file' ] );
	}

	// -------------------------------------------------------------------------
	// wld_submit_lead — validate, rate-limit, generate OTP, email it
	// -------------------------------------------------------------------------
	public static function handle_submit_lead() {
		check_ajax_referer( 'wld_nonce', 'nonce' );

		$download_id = isset( $_POST['download_id'] ) ? absint( $_POST['download_id'] )                              : 0;
		$full_name   = isset( $_POST['full_name'] )   ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) )    : '';
		$email       = isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) )             : '';
		$phone       = isset( $_POST['phone'] )       ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )        : '';

		// Validate
		if ( ! $download_id || ! is_email( $email ) || empty( $full_name ) || empty( $phone ) ) {
			wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'wp-lead-download' ) ] );
		}

		$phone = preg_replace( '/[^0-9+\-\s()]/', '', $phone );
		if ( strlen( $phone ) < 7 ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid phone number.', 'wp-lead-download' ) ] );
		}

		$post = get_post( $download_id );
		if ( ! $post || $post->post_type !== 'wld_download' ) {
			wp_send_json_error( [ 'message' => __( 'Download not found.', 'wp-lead-download' ) ] );
		}
		if ( get_post_meta( $download_id, '_wld_active', true ) !== '1' ) {
			wp_send_json_error( [ 'message' => __( 'This download is currently unavailable.', 'wp-lead-download' ) ] );
		}

		// Rate limit: enforce cooldown per email + download
		if ( self::is_rate_limited( $email, $download_id ) ) {
			wp_send_json_error( [ 'message' => __( 'OTP already sent. Please wait before requesting another.', 'wp-lead-download' ) ] );
		}

		// Hourly brute-force limit
		$attempts = WLD_DB_Setup::get_otp_attempt_count( $email, $download_id );
		$max      = absint( get_option( 'wld_max_otp_attempts', 5 ) );
		if ( $attempts >= $max ) {
			wp_send_json_error( [ 'message' => __( 'Too many attempts. Please try again after 1 hour.', 'wp-lead-download' ) ] );
		}

		$otp = self::generate_otp();

		WLD_DB_Setup::create_otp(
			$email,
			$otp,
			$download_id,
			wp_json_encode( [ 'full_name' => $full_name, 'phone' => $phone ] )
		);

		// Test Mode: skip email, return OTP directly so the form works without SMTP.
		if ( get_option( 'wld_test_mode' ) === '1' ) {
			wp_send_json_success( [
				'step'     => 'otp_sent',
				'message'  => __( 'TEST MODE: No email sent. Use the code shown below.', 'wp-lead-download' ),
				'test_otp' => $otp,
			] );
		}

		$sent = WLD_Email_Notifier::send_otp_email( $email, $full_name, $otp );

		if ( ! $sent ) {
			wp_send_json_error( [ 'message' => __( 'Failed to send verification email. Please try again.', 'wp-lead-download' ) ] );
		}

		wp_send_json_success( [
			'step'    => 'otp_sent',
			'message' => __( 'Code sent to your email.', 'wp-lead-download' ),
		] );
	}

	// -------------------------------------------------------------------------
	// wld_verify_otp — verify code, save lead, return file URL
	// -------------------------------------------------------------------------
	public static function handle_verify_otp() {
		check_ajax_referer( 'wld_nonce', 'nonce' );

		$email       = isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) )           : '';
		$otp_code    = isset( $_POST['otp_code'] )    ? sanitize_text_field( wp_unslash( $_POST['otp_code'] ) )   : '';
		$download_id = isset( $_POST['download_id'] ) ? absint( $_POST['download_id'] )                           : 0;

		if ( ! is_email( $email ) || ! $download_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request data.', 'wp-lead-download' ) ] );
		}
		if ( ! preg_match( '/^[0-9]{6}$/', $otp_code ) ) {
			wp_send_json_error( [ 'message' => __( 'OTP must be a 6-digit number.', 'wp-lead-download' ) ] );
		}

		$otp_row = WLD_DB_Setup::verify_otp( $email, $otp_code, $download_id );
		if ( ! $otp_row ) {
			wp_send_json_error( [ 'message' => __( 'Invalid or expired OTP.', 'wp-lead-download' ) ] );
		}

		// Verify file URL is configured and valid BEFORE committing anything
		$download_post = get_post( $download_id );
		$file_url      = esc_url_raw( get_post_meta( $download_id, '_wld_file_url', true ) );
		$thank_you     = get_post_meta( $download_id, '_wld_thank_you_msg', true );

		if ( empty( $file_url ) || ! filter_var( $file_url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( [ 'message' => __( 'Download file is not available. Please contact support.', 'wp-lead-download' ) ] );
			return;
		}

		// Consume the OTP
		WLD_DB_Setup::mark_otp_used( $otp_row->id );

		// Clean up any other unused OTPs for this email + download
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'wld_otps',
			[ 'email' => $email, 'download_id' => $download_id, 'is_used' => 0 ],
			[ '%s', '%d', '%d' ]
		);

		// Recover lead fields stored in the OTP record
		$lead_data = json_decode( $otp_row->lead_data, true );
		$full_name = isset( $lead_data['full_name'] ) ? sanitize_text_field( $lead_data['full_name'] ) : '';
		$phone     = isset( $lead_data['phone'] )     ? sanitize_text_field( $lead_data['phone'] )     : '';
		$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';

		// Duplicate prevention: skip insert if same email+download already exists (all time).
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wld_leads
				 WHERE email = %s AND download_id = %d",
				$email,
				$download_id
			)
		) ?: 0;

		if ( $existing > 0 ) {
			wp_send_json_success( [
				'file_url' => esc_url( $file_url ),
				'message'  => $thank_you ?: __( 'Thank you! Your download is starting.', 'wp-lead-download' ),
			] );
		}

		// Persist the lead
		$wpdb->insert(
			$wpdb->prefix . 'wld_leads',
			[
				'download_id'   => $download_id,
				'full_name'     => $full_name,
				'email'         => $email,
				'phone'         => $phone,
				'downloaded_at' => current_time( 'mysql' ),
				'ip_address'    => $ip,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		// Notifications
		if ( $download_post ) {
			WLD_Email_Notifier::send_admin_email(
				[ 'full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'ip_address' => $ip ],
				$download_post
			);
		}

		if ( get_option( 'wld_send_user_email' ) === '1' ) {
			WLD_Email_Notifier::send_user_email( [ 'full_name' => $full_name, 'email' => $email ] );
		}

		wp_send_json_success( [
			'file_url' => esc_url( $file_url ),
			'message'  => $thank_you ?: __( 'Thank you! Your download is starting.', 'wp-lead-download' ),
		] );
	}

	// -------------------------------------------------------------------------
	// wld_resend_otp — rate-limit, delete old, regenerate, email
	// -------------------------------------------------------------------------
	public static function handle_resend_otp() {
		check_ajax_referer( 'wld_nonce', 'nonce' );

		$email       = isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$download_id = isset( $_POST['download_id'] ) ? absint( $_POST['download_id'] )                 : 0;

		if ( ! is_email( $email ) || ! $download_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request data.', 'wp-lead-download' ) ] );
		}

		if ( self::is_rate_limited( $email, $download_id ) ) {
			wp_send_json_error( [ 'message' => __( 'OTP already sent. Please wait before requesting another.', 'wp-lead-download' ) ] );
		}

		global $wpdb;

		// Retrieve full_name from the most recent OTP record for this email
		// (do this BEFORE deleting old OTPs)
		$last_lead_data = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT lead_data FROM {$wpdb->prefix}wld_otps WHERE email = %s ORDER BY created_at DESC LIMIT 1",
				$email
			)
		);
		$lead_data = $last_lead_data ? json_decode( $last_lead_data, true ) : [];
		$full_name = isset( $lead_data['full_name'] ) ? sanitize_text_field( $lead_data['full_name'] ) : '';

		// Delete previous unused OTPs for this email + download
		$wpdb->delete(
			$wpdb->prefix . 'wld_otps',
			[ 'email' => $email, 'download_id' => $download_id, 'is_used' => 0 ],
			[ '%s', '%d', '%d' ]
		);

		$otp = self::generate_otp();

		WLD_DB_Setup::create_otp(
			$email,
			$otp,
			$download_id,
			wp_json_encode( $lead_data )
		);

		WLD_Email_Notifier::send_otp_email( $email, $full_name, $otp );

		wp_send_json_success( [ 'message' => __( 'New code sent.', 'wp-lead-download' ) ] );
	}

	// -------------------------------------------------------------------------
	// wld_returning_user — check if email already has a lead, return file URL
	// -------------------------------------------------------------------------
	public static function handle_returning_user() {
		check_ajax_referer( 'wld_nonce', 'nonce' );

		$email       = isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$download_id = isset( $_POST['download_id'] ) ? absint( $_POST['download_id'] )                 : 0;

		if ( ! is_email( $email ) || ! $download_id ) {
			wp_send_json_error( [ 'status' => 'invalid' ] );
		}

		global $wpdb;
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wld_leads
				 WHERE email = %s AND download_id = %d",
				$email,
				$download_id
			)
		);

		if ( ! $exists ) {
			wp_send_json_error( [ 'status' => 'not_found' ] );
		}

		$post     = get_post( $download_id );
		$file_url = esc_url_raw( get_post_meta( $download_id, '_wld_file_url', true ) );

		if ( empty( $file_url ) || ! filter_var( $file_url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( [ 'status' => 'no_file' ] );
		}

		$thank_you = get_post_meta( $download_id, '_wld_thank_you_msg', true );

		wp_send_json_success( [
			'file_url' => esc_url( $file_url ),
			'message'  => $thank_you ?: __( 'Thank you! Your download is starting.', 'wp-lead-download' ),
		] );
	}

	// -------------------------------------------------------------------------
	// wld_download_file — stream file with Content-Disposition: attachment
	// -------------------------------------------------------------------------
	public static function handle_download_file() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wld_nonce' ) ) {
			status_header( 403 );
			exit;
		}

		$email       = isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$download_id = isset( $_POST['download_id'] ) ? absint( $_POST['download_id'] )                 : 0;

		if ( ! is_email( $email ) || ! $download_id ) {
			status_header( 400 );
			exit;
		}

		// Confirm this email has a verified lead for this download.
		global $wpdb;
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wld_leads
				 WHERE email = %s AND download_id = %d",
				$email,
				$download_id
			)
		);

		if ( ! $exists ) {
			status_header( 403 );
			exit;
		}

		$file_url = get_post_meta( $download_id, '_wld_file_url', true );
		if ( empty( $file_url ) ) {
			status_header( 404 );
			exit;
		}

		// Convert URL → absolute server path.
		// Uses parse_url() on the URL path only — immune to HTTP/HTTPS mismatches
		// and domain differences that break simple str_replace approaches.
		$parsed    = wp_parse_url( $file_url );
		$url_path  = isset( $parsed['path'] ) ? $parsed['path'] : '';
		$file_path = '';

		if ( $url_path ) {
			// Method 1: strip site path prefix, prepend ABSPATH.
			$site_path = wp_parse_url( site_url(), PHP_URL_PATH );
			$site_path = $site_path ? trailingslashit( $site_path ) : '/';
			$rel_path  = ltrim( str_replace( rtrim( $site_path, '/' ), '', $url_path ), '/' );
			$file_path = rtrim( ABSPATH, '/' ) . '/' . $rel_path;
		}

		// Method 2: uploads dir mapping (more precise for media library files).
		if ( ! file_exists( $file_path ) ) {
			$upload_dir = wp_upload_dir();
			$base_url   = set_url_scheme( trailingslashit( $upload_dir['baseurl'] ), 'https' );
			$file_url_s = set_url_scheme( $file_url, 'https' );
			if ( strpos( $file_url_s, $base_url ) === 0 ) {
				$file_path = $upload_dir['basedir'] . '/' . ltrim( str_replace( $base_url, '', $file_url_s ), '/' );
			}
		}

		if ( empty( $file_path ) || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			status_header( 404 );
			exit;
		}

		$filename  = basename( $file_path );
		$file_size = filesize( $file_path );
		$mime      = function_exists( 'mime_content_type' )
			? mime_content_type( $file_path )
			: 'application/octet-stream';

		// Force download — browser must save the file, not open it.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . $file_size );

		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Generate a cryptographically-secure 6-digit OTP string.
	 *
	 * @return string  Zero-padded 6-digit code.
	 */
	private static function generate_otp() {
		return str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Check if the email has made an OTP request within the cooldown window.
	 *
	 * @param string $email
	 * @param int    $download_id
	 * @return bool
	 */
	private static function is_rate_limited( $email, $download_id ) {
		global $wpdb;
		$cooldown = absint( get_option( 'wld_otp_cooldown', 60 ) );
		$table    = $wpdb->prefix . 'wld_otps';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table
				 WHERE email = %s AND download_id = %d
				   AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)
				 LIMIT 1",
				sanitize_email( $email ),
				absint( $download_id ),
				$cooldown
			)
		);

		return ! empty( $exists );
	}
}
