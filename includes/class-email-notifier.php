<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WLD_Email_Notifier {

	/**
	 * Build mail headers. From header is only added when the plugin's own
	 * SMTP override is enabled — avoids conflict with WP Mail SMTP / FluentSMTP.
	 *
	 * @return string[]
	 */
	private static function get_headers() {
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		if ( get_option( 'wld_override_from_email' ) === '1' ) {
			$name    = get_option( 'wld_from_name',    get_bloginfo( 'name' ) );
			$address = get_option( 'wld_from_address', get_option( 'admin_email' ) );
			$headers[] = 'From: ' . $name . ' <' . $address . '>';
		}

		return $headers;
	}

	/**
	 * Wrap content in a simple branded HTML email shell.
	 *
	 * @param string $body  Inner HTML.
	 * @return string
	 */
	private static function wrap( $body ) {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url  = esc_url( home_url() );

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
		<div style="max-width:520px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
			<div style="background:#0073aa;padding:20px 32px;">
				<p style="margin:0;color:#fff;font-size:18px;font-weight:bold;">' . $site_name . '</p>
			</div>
			<div style="padding:32px;">' . $body . '</div>
			<div style="background:#f0f0f0;padding:14px 32px;text-align:center;font-size:12px;color:#888;">
				<a href="' . $site_url . '" style="color:#0073aa;text-decoration:none;">' . $site_name . '</a>
			</div>
		</div></body></html>';
	}

	/**
	 * Send the OTP email to the user.
	 *
	 * @param string $email
	 * @param string $full_name
	 * @param string $otp_code
	 * @return bool
	 */
	public static function send_otp_email( $email, $full_name, $otp_code ) {
		$expiry  = absint( get_option( 'wld_otp_expiry', 10 ) );
		/* translators: %s: site name */
		$subject = sprintf( __( 'Your Verification Code — %s', 'wp-lead-download' ), get_bloginfo( 'name' ) );

		$body = '<p style="font-size:16px;color:#333;">Hi <strong>' . esc_html( $full_name ) . '</strong>,</p>
		<p style="color:#555;font-size:14px;line-height:1.6;">
			Use the code below to verify your email and complete your download.
		</p>
		<div style="text-align:center;margin:28px 0;">
			<div style="display:inline-block;border:2px dashed #0073aa;border-radius:8px;padding:16px 32px;">
				<span style="font-size:36px;font-weight:bold;letter-spacing:12px;color:#0073aa;">' . esc_html( $otp_code ) . '</span>
			</div>
		</div>
		<p style="color:#888;font-size:13px;text-align:center;">
			' . sprintf(
				/* translators: %d: minutes */
				esc_html__( 'Valid for %d minutes.', 'wp-lead-download' ),
				$expiry
			) . '
		</p>
		<p style="color:#aaa;font-size:12px;text-align:center;margin-top:24px;">
			' . esc_html__( "If you didn't request this, please ignore this email.", 'wp-lead-download' ) . '
		</p>';

		$sent = wp_mail( $email, $subject, self::wrap( $body ), self::get_headers() );

		if ( ! $sent ) {
			update_option( 'wld_mail_last_error', [
				'time'  => current_time( 'mysql' ),
				'to'    => $email,
				'error' => error_get_last(),
			] );
		}

		return $sent;
	}

	/**
	 * Send an admin notification when a new lead is captured.
	 *
	 * @param array   $lead_data     Keys: full_name, email, phone, ip_address.
	 * @param WP_Post $download_post
	 * @return bool
	 */
	public static function send_admin_email( $lead_data, $download_post ) {
		$to = get_option( 'wld_notify_email', get_option( 'admin_email' ) );
		if ( empty( $to ) ) return false;

		$file_url = get_post_meta( $download_post->ID, '_wld_file_url', true );

		$subject = sprintf(
			/* translators: 1: lead name, 2: download title */
			__( 'New Lead: %1$s — %2$s', 'wp-lead-download' ),
			$lead_data['full_name'],
			$download_post->post_title
		);

		$rows = [
			__( 'Download',  'wp-lead-download' ) => esc_html( $download_post->post_title ),
			__( 'Full Name', 'wp-lead-download' ) => esc_html( $lead_data['full_name'] ),
			__( 'Email',     'wp-lead-download' ) => esc_html( $lead_data['email'] ),
			__( 'Phone',     'wp-lead-download' ) => esc_html( $lead_data['phone'] ),
			__( 'File URL',  'wp-lead-download' ) => $file_url
				? '<a href="' . esc_url( $file_url ) . '">' . esc_url( $file_url ) . '</a>'
				: '—',
			__( 'Date',      'wp-lead-download' ) => esc_html( current_time( 'mysql' ) ),
			__( 'IP',        'wp-lead-download' ) => esc_html( $lead_data['ip_address'] ),
		];

		$table_rows = '';
		foreach ( $rows as $label => $value ) {
			$table_rows .= '<tr>
				<td style="padding:8px 12px;font-weight:bold;background:#f9f9f9;border:1px solid #e5e5e5;white-space:nowrap;">'
					. esc_html( $label ) .
				'</td>
				<td style="padding:8px 12px;border:1px solid #e5e5e5;">' . $value . '</td>
			</tr>';
		}

		$body = '<p style="font-size:15px;color:#333;font-weight:bold;">'
			. esc_html__( 'A new lead has been captured:', 'wp-lead-download' )
			. '</p>
			<table style="border-collapse:collapse;width:100%;font-size:14px;">' . $table_rows . '</table>
			<p style="margin-top:20px;">
				<a href="' . esc_url( admin_url( 'admin.php?page=wld-leads' ) ) . '"
				   style="background:#0073aa;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-size:14px;">
					' . esc_html__( 'View All Leads', 'wp-lead-download' ) . '
				</a>
			</p>';

		return wp_mail( $to, $subject, self::wrap( $body ), self::get_headers() );
	}

	/**
	 * Send a confirmation email to the user after successful download.
	 *
	 * @param array $lead_data  Keys: full_name, email.
	 * @return bool
	 */
	public static function send_user_email( $lead_data ) {
		$subject = get_option( 'wld_user_email_subject', __( 'Thank you for downloading!', 'wp-lead-download' ) );
		$raw     = get_option( 'wld_user_email_body', '' );
		$content = str_replace( '{name}', esc_html( $lead_data['full_name'] ), wp_kses_post( $raw ) );

		$body = '<p style="font-size:16px;color:#333;">Hi <strong>' . esc_html( $lead_data['full_name'] ) . '</strong>,</p>
		<div style="font-size:14px;color:#555;line-height:1.7;">' . $content . '</div>';

		return wp_mail( $lead_data['email'], $subject, self::wrap( $body ), self::get_headers() );
	}
}
