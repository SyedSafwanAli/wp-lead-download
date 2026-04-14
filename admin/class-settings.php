<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WLD_Settings {

	public static function init() {
		add_action( 'admin_init',                        [ __CLASS__, 'register_settings'    ] );
		add_action( 'admin_post_wld_test_email',         [ __CLASS__, 'handle_test_email'    ] );
		add_action( 'admin_post_wld_export_settings', [ __CLASS__, 'handle_export' ] );
		add_action( 'admin_post_wld_import_settings', [ __CLASS__, 'handle_import' ] );
	}

	/* ========================================================================
	   Settings API Registration
	======================================================================== */

	public static function register_settings() {

		/* ---- Section 1: Email Notifications ---- */
		add_settings_section( 'wld_sec_notifications', __( 'Email Notifications', 'wp-lead-download' ), null, 'wld-settings' );

		register_setting( 'wld_settings_group', 'wld_notify_email',    [ 'sanitize_callback' => 'sanitize_email' ] );
		register_setting( 'wld_settings_group', 'wld_send_user_email', [ 'sanitize_callback' => 'absint', 'default' => 0 ] );

		add_settings_field( 'wld_notify_email',    __( 'Admin Notification Email',  'wp-lead-download' ), [ __CLASS__, 'field_notify_email' ],   'wld-settings', 'wld_sec_notifications' );
		add_settings_field( 'wld_send_user_email', __( 'Send Confirmation to User', 'wp-lead-download' ), [ __CLASS__, 'field_send_user_email' ], 'wld-settings', 'wld_sec_notifications' );

		/* ---- Section 2: User Confirmation Email ---- */
		add_settings_section( 'wld_sec_user_email', __( 'User Confirmation Email', 'wp-lead-download' ), null, 'wld-settings' );

		register_setting( 'wld_settings_group', 'wld_user_email_subject', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'wld_settings_group', 'wld_user_email_body',    [ 'sanitize_callback' => 'wp_kses_post' ] );

		add_settings_field( 'wld_user_email_subject', __( 'Subject', 'wp-lead-download' ), [ __CLASS__, 'field_user_email_subject' ], 'wld-settings', 'wld_sec_user_email' );
		add_settings_field( 'wld_user_email_body',    __( 'Body',    'wp-lead-download' ), [ __CLASS__, 'field_user_email_body' ],    'wld-settings', 'wld_sec_user_email' );

		/* ---- Section 3: SMTP / From Email ---- */
		add_settings_section( 'wld_sec_smtp', __( 'SMTP / From Email', 'wp-lead-download' ), null, 'wld-settings' );

		register_setting( 'wld_settings_group', 'wld_override_from_email', [ 'sanitize_callback' => 'absint', 'default' => 0 ] );
		register_setting( 'wld_settings_group', 'wld_from_name',           [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'wld_settings_group', 'wld_from_address',        [ 'sanitize_callback' => 'sanitize_email' ] );

		add_settings_field( 'wld_override_from_email', __( 'Override From Address', 'wp-lead-download' ), [ __CLASS__, 'field_override_from_email' ], 'wld-settings', 'wld_sec_smtp' );
		add_settings_field( 'wld_from_name',           __( 'From Name',             'wp-lead-download' ), [ __CLASS__, 'field_from_name' ],           'wld-settings', 'wld_sec_smtp' );
		add_settings_field( 'wld_from_address',        __( 'From Email',            'wp-lead-download' ), [ __CLASS__, 'field_from_address' ],         'wld-settings', 'wld_sec_smtp' );

		/* ---- Section 4: OTP Settings ---- */
		add_settings_section( 'wld_sec_otp', __( 'OTP Settings', 'wp-lead-download' ), null, 'wld-settings' );

		register_setting( 'wld_settings_group', 'wld_otp_expiry',       [ 'sanitize_callback' => 'absint', 'default' => 10 ] );
		register_setting( 'wld_settings_group', 'wld_otp_cooldown',     [ 'sanitize_callback' => 'absint', 'default' => 60 ] );
		register_setting( 'wld_settings_group', 'wld_max_otp_attempts', [ 'sanitize_callback' => 'absint', 'default' => 5  ] );

		add_settings_field( 'wld_otp_expiry',       __( 'OTP Expiry (minutes)',               'wp-lead-download' ), [ __CLASS__, 'field_otp_expiry' ],       'wld-settings', 'wld_sec_otp' );
		add_settings_field( 'wld_otp_cooldown',     __( 'Resend Cooldown (seconds)',          'wp-lead-download' ), [ __CLASS__, 'field_otp_cooldown' ],     'wld-settings', 'wld_sec_otp' );
		add_settings_field( 'wld_max_otp_attempts', __( 'Max OTP requests per email per hour', 'wp-lead-download' ), [ __CLASS__, 'field_max_otp_attempts' ], 'wld-settings', 'wld_sec_otp' );

		/* ---- Section 5: Advanced ---- */
		add_settings_section( 'wld_sec_advanced', __( 'Advanced', 'wp-lead-download' ), null, 'wld-settings' );

		register_setting( 'wld_settings_group', 'wld_delete_data_on_uninstall', [ 'sanitize_callback' => 'absint', 'default' => 0 ] );
		register_setting( 'wld_settings_group', 'wld_test_mode',                [ 'sanitize_callback' => 'absint', 'default' => 0 ] );

		add_settings_field( 'wld_delete_data_on_uninstall', __( 'Delete Data on Uninstall', 'wp-lead-download' ), [ __CLASS__, 'field_delete_on_uninstall' ], 'wld-settings', 'wld_sec_advanced' );
		add_settings_field( 'wld_test_mode',                __( 'Test Mode (No Email)',      'wp-lead-download' ), [ __CLASS__, 'field_test_mode' ],           'wld-settings', 'wld_sec_advanced' );
	}

	/* ========================================================================
	   Field Renderers
	======================================================================== */

	public static function field_notify_email() {
		$v = get_option( 'wld_notify_email', get_option( 'admin_email' ) );
		echo '<input type="email" name="wld_notify_email" value="' . esc_attr( $v ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Leave blank to use the site admin email.', 'wp-lead-download' ) . '</p>';
	}

	public static function field_send_user_email() {
		$v = get_option( 'wld_send_user_email', 0 );
		echo '<label><input type="checkbox" name="wld_send_user_email" value="1"' . checked( 1, $v, false ) . ' /> ';
		echo esc_html__( 'Send a confirmation email to the user after download.', 'wp-lead-download' ) . '</label>';
	}

	public static function field_user_email_subject() {
		$v = get_option( 'wld_user_email_subject', __( 'Thank you for downloading!', 'wp-lead-download' ) );
		echo '<input type="text" name="wld_user_email_subject" value="' . esc_attr( $v ) . '" class="regular-text" />';
	}

	public static function field_user_email_body() {
		$v = get_option( 'wld_user_email_body', '' );
		echo '<textarea name="wld_user_email_body" rows="5" class="large-text" placeholder="'
			. esc_attr__( "Use {name} as a placeholder for the user's name.", 'wp-lead-download' )
			. '">' . esc_textarea( $v ) . '</textarea>';
	}

	public static function field_override_from_email() {
		$v = get_option( 'wld_override_from_email', 0 );
		echo '<label><input type="checkbox" id="wld_override_from_email" name="wld_override_from_email" value="1"' . checked( 1, $v, false ) . ' /> ';
		echo esc_html__( 'Override the From name and address set below.', 'wp-lead-download' ) . '</label>';
		echo '<p class="description" style="color:#c00;">' . esc_html__( 'Leave UNCHECKED if using WP Mail SMTP or FluentSMTP.', 'wp-lead-download' ) . '</p>';
	}

	public static function field_from_name() {
		$v = get_option( 'wld_from_name', get_bloginfo( 'name' ) );
		echo '<div class="wld-smtp-field"><input type="text" name="wld_from_name" value="' . esc_attr( $v ) . '" class="regular-text" /></div>';
	}

	public static function field_from_address() {
		$v = get_option( 'wld_from_address', get_option( 'admin_email' ) );
		echo '<div class="wld-smtp-field"><input type="email" name="wld_from_address" value="' . esc_attr( $v ) . '" class="regular-text" /></div>';
	}

	public static function field_otp_expiry() {
		$v = absint( get_option( 'wld_otp_expiry', 10 ) );
		echo '<input type="number" name="wld_otp_expiry" value="' . esc_attr( $v ) . '" min="1" max="60" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'How many minutes an OTP remains valid (1–60).', 'wp-lead-download' ) . '</p>';
	}

	public static function field_otp_cooldown() {
		$v = absint( get_option( 'wld_otp_cooldown', 60 ) );
		echo '<input type="number" name="wld_otp_cooldown" value="' . esc_attr( $v ) . '" min="10" max="600" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Minimum seconds a user must wait before requesting another OTP.', 'wp-lead-download' ) . '</p>';
	}

	public static function field_max_otp_attempts() {
		$v = absint( get_option( 'wld_max_otp_attempts', 5 ) );
		echo '<input type="number" name="wld_max_otp_attempts" value="' . esc_attr( $v ) . '" min="1" max="20" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Maximum OTP requests allowed per email per hour (1–20).', 'wp-lead-download' ) . '</p>';
	}

	public static function field_delete_on_uninstall() {
		$v = get_option( 'wld_delete_data_on_uninstall', 0 );
		echo '<label><input type="checkbox" name="wld_delete_data_on_uninstall" value="1"' . checked( 1, $v, false ) . ' /> ';
		echo esc_html__( 'Delete all leads, OTPs, downloads and settings when the plugin is deleted.', 'wp-lead-download' ) . '</label>';
	}

	public static function field_test_mode() {
		$v = get_option( 'wld_test_mode', 0 );
		echo '<label><input type="checkbox" name="wld_test_mode" value="1"' . checked( 1, $v, false ) . ' /> ';
		echo esc_html__( 'Enable Test Mode — skip email delivery and show the OTP code directly in the form.', 'wp-lead-download' ) . '</label>';
		echo '<p class="description" style="color:#c00;font-weight:600;">'
			. esc_html__( 'For local/staging use only. Disable on a live site.', 'wp-lead-download' )
			. '</p>';
	}

	/* ========================================================================
	   Admin-Post Handlers
	======================================================================== */

	public static function handle_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'wld_test_email' );

		$to      = get_option( 'wld_notify_email', get_option( 'admin_email' ) );
		$subject = __( 'WP Lead Download — Test Email', 'wp-lead-download' );
		$body    = '<p style="font-size:15px;color:#333;">'
			. esc_html__( 'This is a test email from your WP Lead Download plugin.', 'wp-lead-download' )
			. '</p><p style="color:#555;font-size:14px;">'
			. esc_html__( 'If you received this, your email configuration is working correctly.', 'wp-lead-download' )
			. '</p>';
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$sent = wp_mail( $to, $subject, $body, $headers );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'wld-settings', 'tab' => 'general', 'wld_test_mail' => $sent ? 'success' : 'failed' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'wld_export_settings' );

		$excluded = [ 'wld_license_key', 'wld_db_version', 'wld_last_viewed_leads', 'wld_mail_last_error' ];

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
			 WHERE option_name LIKE 'wld\_%'",
			ARRAY_A
		);

		$export = [ 'wld_version' => WLD_VERSION ];
		foreach ( (array) $rows as $row ) {
			if ( in_array( $row['option_name'], $excluded, true ) ) continue;
			$export[ $row['option_name'] ] = $row['option_value'];
		}

		$filename = 'wld-settings-' . gmdate( 'Y-m-d' ) . '.json';
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		echo wp_json_encode( $export, JSON_PRETTY_PRINT );
		die();
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'wld_import_settings' );

		$base = admin_url( 'admin.php?page=wld-settings&tab=exportimport' );

		if ( empty( $_FILES['wld_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'wld_import', 'nofile', $base ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $_FILES['wld_import_file']['tmp_name'] );
		if ( false === $raw ) {
			wp_safe_redirect( add_query_arg( 'wld_import', 'nofile', $base ) );
			exit;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['wld_version'] ) ) {
			wp_safe_redirect( add_query_arg( 'wld_import', 'invalid', $base ) );
			exit;
		}

		$never_import = [ 'wld_license_key', 'wld_db_version', 'wld_last_viewed_leads', 'wld_mail_last_error', 'wld_version' ];

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( strpos( $key, 'wld_' ) !== 0 ) continue;
			if ( in_array( $key, $never_import, true ) ) continue;
			update_option( $key, $value );
		}

		wp_safe_redirect( add_query_arg( 'wld_import', 'success', $base ) );
		exit;
	}

	/* ========================================================================
	   Page Renderer (tabbed)
	======================================================================== */

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wp-lead-download' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$tabs = [
			'general'      => __( 'General',        'wp-lead-download' ),
			'sysinfo'      => __( 'System Info',     'wp-lead-download' ),
			'exportimport' => __( 'Export / Import', 'wp-lead-download' ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Lead Download — Settings', 'wp-lead-download' ); ?></h1>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'wp-lead-download' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wld-settings&tab=' . $slug ) ); ?>"
					   class="nav-tab<?php echo $current_tab === $slug ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $current_tab ) {
				case 'sysinfo':
					self::render_tab_sysinfo();
					break;
				case 'exportimport':
					self::render_tab_exportimport();
					break;
				default:
					self::render_tab_general();
			}
			?>
		</div>
		<?php
	}

	/* ========================================================================
	   Tab: General
	======================================================================== */

	private static function render_tab_general() {
		?>
		<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved.', 'wp-lead-download' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['wld_test_mail'] ) ) : ?>
			<?php if ( $_GET['wld_test_mail'] === 'success' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Test email sent successfully!', 'wp-lead-download' ); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e( 'Test email failed. Please check your SMTP settings.', 'wp-lead-download' ); ?></p>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'wld_settings_group' );
			do_settings_sections( 'wld-settings' );
			submit_button();
			?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
			<input type="hidden" name="action" value="wld_test_email" />
			<?php wp_nonce_field( 'wld_test_email' ); ?>
			<?php submit_button( __( 'Send Test Email', 'wp-lead-download' ), 'secondary', 'submit', false ); ?>
			<p class="description" style="margin-top:6px;">
				<?php
				printf(
					/* translators: %s: email address */
					esc_html__( 'Sends a test email to %s to verify your mail configuration.', 'wp-lead-download' ),
					'<strong>' . esc_html( get_option( 'wld_notify_email', get_option( 'admin_email' ) ) ) . '</strong>'
				);
				?>
			</p>
		</form>

		<script>
		(function(){
			var toggle = document.getElementById('wld_override_from_email');
			if (!toggle) return;
			var smtpFields = document.querySelectorAll('.wld-smtp-field');
			function syncVisibility(){
				smtpFields.forEach(function(el){
					el.closest('tr').style.display = toggle.checked ? '' : 'none';
				});
			}
			syncVisibility();
			toggle.addEventListener('change', syncVisibility);
		})();
		</script>
		<?php
	}

	/* ========================================================================
	   Tab: System Info
	======================================================================== */

	private static function render_tab_sysinfo() {
		global $wpdb;

		$leads_tbl    = $wpdb->prefix . 'wld_leads';
		$otps_tbl     = $wpdb->prefix . 'wld_otps';
		$leads_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads_tbl ) ) === $leads_tbl;
		$otps_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $otps_tbl ) )  === $otps_tbl;
		$total_leads  = $leads_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads_tbl}" ) : 0;
		$last_otp     = $otps_exists  ? $wpdb->get_var( "SELECT MAX(created_at) FROM {$otps_tbl}" ) : null;
		$cron_next    = wp_next_scheduled( 'wld_cleanup_otps_cron' );
		$mail_error   = get_option( 'wld_mail_last_error', [] );

		$rows = [
			__( 'Plugin Version',  'wp-lead-download' ) => WLD_VERSION,
			__( 'DB Version',      'wp-lead-download' ) => esc_html( get_option( 'wld_db_version', '—' ) ),
			__( 'WordPress',       'wp-lead-download' ) => esc_html( get_bloginfo( 'version' ) ),
			__( 'PHP Version',     'wp-lead-download' ) => esc_html( PHP_VERSION ),
			__( 'MySQL Version',   'wp-lead-download' ) => esc_html( $wpdb->db_version() ),
			__( 'WP Debug Mode',   'wp-lead-download' ) => ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				? '<span style="color:#c00;font-weight:600;">Yes</span>' : 'No',
			__( 'Memory Limit',    'wp-lead-download' ) => defined( 'WP_MEMORY_LIMIT' ) ? esc_html( WP_MEMORY_LIMIT ) : '—',
			__( 'wld_leads table', 'wp-lead-download' ) => $leads_exists
				? '<span style="color:#0a8a30;font-weight:600;">EXISTS</span>'
				: '<span style="color:#c00;font-weight:600;">MISSING</span>',
			__( 'wld_otps table',  'wp-lead-download' ) => $otps_exists
				? '<span style="color:#0a8a30;font-weight:600;">EXISTS</span>'
				: '<span style="color:#c00;font-weight:600;">MISSING</span>',
			__( 'SMTP Override',   'wp-lead-download' ) => get_option( 'wld_override_from_email' ) == '1'
				? esc_html__( 'Enabled', 'wp-lead-download' ) : esc_html__( 'Disabled', 'wp-lead-download' ),
			__( 'Last Mail Error', 'wp-lead-download' ) => ( is_array( $mail_error ) && ! empty( $mail_error['time'] ) )
				? esc_html( $mail_error['time'] ) . ' &rarr; ' . esc_html( $mail_error['to'] ) : '—',
			__( 'Last OTP Sent',   'wp-lead-download' ) => $last_otp ? esc_html( $last_otp ) : '—',
			__( 'Total Leads',     'wp-lead-download' ) => $total_leads,
			__( 'Cron Scheduled',  'wp-lead-download' ) => $cron_next
				? esc_html( wp_date( 'Y-m-d H:i:s', $cron_next ) )
				: '<span style="color:#c00;">' . esc_html__( 'Not scheduled', 'wp-lead-download' ) . '</span>',
		];

		// Build plain-text version for clipboard copy
		$plain = [];
		foreach ( $rows as $label => $value ) {
			$plain[] = $label . ': ' . wp_strip_all_tags( $value );
		}
		$plain_text = implode( "\n", $plain );
		?>
		<h2 style="margin-top:20px;"><?php esc_html_e( 'System Information', 'wp-lead-download' ); ?></h2>

		<table class="widefat striped" style="max-width:720px;">
			<tbody>
				<?php foreach ( $rows as $label => $value ) : ?>
					<tr>
						<td style="font-weight:600;width:230px;"><?php echo esc_html( $label ); ?></td>
						<td><?php echo $value; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:16px;">
			<textarea id="wld-sysinfo-text" rows="6" class="large-text"
			          readonly style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $plain_text ); ?></textarea>
		</p>
		<button type="button" class="button" id="wld-copy-sysinfo">
			<?php esc_html_e( 'Copy System Info', 'wp-lead-download' ); ?>
		</button>
		<span id="wld-sysinfo-copied" style="margin-left:10px;color:#0a8a30;display:none;">
			<?php esc_html_e( 'Copied!', 'wp-lead-download' ); ?>
		</span>

		<script>
		(function(){
			document.getElementById('wld-copy-sysinfo').addEventListener('click', function(){
				var ta = document.getElementById('wld-sysinfo-text');
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(ta.value).then(function(){
						var el = document.getElementById('wld-sysinfo-copied');
						el.style.display = 'inline';
						setTimeout(function(){ el.style.display = 'none'; }, 2000);
					});
				} else {
					ta.select();
					document.execCommand('copy');
				}
			});
		})();
		</script>
		<?php
	}

	/* ========================================================================
	   Tab: Export / Import
	======================================================================== */

	private static function render_tab_exportimport() {
		if ( isset( $_GET['wld_import'] ) ) {
			$map = [
				'success' => [ 'type' => 'success', 'msg' => __( 'Settings imported successfully.',                                                             'wp-lead-download' ) ],
				'invalid' => [ 'type' => 'error',   'msg' => __( 'Invalid settings file. Please use a file exported from WP Lead Download.',                    'wp-lead-download' ) ],
				'nofile'  => [ 'type' => 'error',   'msg' => __( 'No file selected. Please choose a .json settings file.',                                      'wp-lead-download' ) ],
			];
			$key = sanitize_key( $_GET['wld_import'] );
			if ( isset( $map[ $key ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $map[ $key ]['type'] ) . ' is-dismissible"><p>'
					. esc_html( $map[ $key ]['msg'] ) . '</p></div>';
			}
		}
		?>
		<h2 style="margin-top:20px;"><?php esc_html_e( 'Export Settings', 'wp-lead-download' ); ?></h2>
		<p><?php esc_html_e( 'Download all plugin settings as a JSON file. Use this to copy your configuration to another site.', 'wp-lead-download' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wld_export_settings" />
			<?php wp_nonce_field( 'wld_export_settings' ); ?>
			<?php submit_button( __( 'Export Settings', 'wp-lead-download' ), 'secondary', 'submit', false ); ?>
		</form>

		<hr style="margin:28px 0;" />

		<h2><?php esc_html_e( 'Import Settings', 'wp-lead-download' ); ?></h2>
		<p><?php esc_html_e( 'Upload a settings JSON file exported from another WP Lead Download installation.', 'wp-lead-download' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="wld_import_settings" />
			<?php wp_nonce_field( 'wld_import_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wld_import_file"><?php esc_html_e( 'Settings File', 'wp-lead-download' ); ?></label>
					</th>
					<td><input type="file" id="wld_import_file" name="wld_import_file" accept=".json" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Import Settings', 'wp-lead-download' ), 'primary', 'submit', false ); ?>
			<p class="description">
				<?php esc_html_e( 'Note: License key, DB version, and diagnostic data are never imported.', 'wp-lead-download' ); ?>
			</p>
		</form>
		<?php
	}
}
