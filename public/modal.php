<?php
/**
 * Modal template — rendered once in wp_footer when a [lead_download] shortcode is present.
 * Included by WLD_Shortcode::maybe_render_modal().
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div id="wld-modal-overlay" class="wld-overlay" role="dialog" aria-modal="true" aria-labelledby="wld-modal-title">

	<div class="wld-modal-box">

		<button class="wld-close-btn" aria-label="<?php esc_attr_e( 'Close dialog', 'wp-lead-download' ); ?>">&times;</button>

		<!-- ============================================================
		     Screen 1: Lead Capture Form
		     ============================================================ -->
		<div id="wld-screen-1" class="wld-screen">

			<h3 id="wld-modal-title"></h3>

			<div class="wld-error" id="wld-s1-error" role="alert" aria-live="polite" style="display:none;"></div>

			<form id="wld-lead-form" novalidate>
				<input type="hidden" id="wld-download-id" name="download_id" value="">

				<div class="wld-field">
					<label for="wld-full-name"><?php esc_html_e( 'Full Name', 'wp-lead-download' ); ?> *</label>
					<input type="text"
					       name="full_name"
					       id="wld-full-name"
					       required
					       autocomplete="name"
					       placeholder="<?php esc_attr_e( 'John Doe', 'wp-lead-download' ); ?>">
				</div>

				<div class="wld-field">
					<label for="wld-email"><?php esc_html_e( 'Email Address', 'wp-lead-download' ); ?> *</label>
					<input type="email"
					       name="email"
					       id="wld-email"
					       required
					       autocomplete="email"
					       placeholder="<?php esc_attr_e( 'john@example.com', 'wp-lead-download' ); ?>">
				</div>

				<div class="wld-field">
					<label for="wld-phone"><?php esc_html_e( 'Phone Number', 'wp-lead-download' ); ?></label>
					<input type="tel"
					       name="phone"
					       id="wld-phone"
					       autocomplete="tel"
					       placeholder="<?php esc_attr_e( 'Optional', 'wp-lead-download' ); ?>">
				</div>

				<button type="submit" class="wld-btn" id="wld-s1-submit" aria-describedby="wld-s1-error">
					<span class="wld-btn-text"><?php esc_html_e( 'Send Verification Code', 'wp-lead-download' ); ?></span>
					<span class="wld-spinner" style="display:none;" aria-hidden="true"></span>
				</button>
			</form>

		</div><!-- /wld-screen-1 -->

		<!-- ============================================================
		     Screen 2: OTP Verification
		     ============================================================ -->
		<div id="wld-screen-2" class="wld-screen" style="display:none;">

			<h3><?php esc_html_e( 'Check Your Email', 'wp-lead-download' ); ?></h3>

			<p class="wld-otp-subtext">
				<?php esc_html_e( 'We sent a 6-digit code to', 'wp-lead-download' ); ?>
				<strong id="wld-otp-email-display"></strong>
			</p>

			<div id="wld-test-otp-box" style="display:none;margin-bottom:14px;padding:12px 16px;background:#fff8e1;border:2px dashed #f5a623;border-radius:6px;text-align:center;">
				<p style="margin:0 0 6px;font-size:12px;color:#8a6d00;font-weight:600;"><?php esc_html_e( 'TEST MODE — Your OTP Code:', 'wp-lead-download' ); ?></p>
				<span id="wld-test-otp-code" style="font-size:28px;font-weight:bold;letter-spacing:10px;color:#0073aa;"></span>
			</div>

			<div class="wld-error" id="wld-s2-error" role="alert" aria-live="polite" style="display:none;"></div>

			<form id="wld-otp-form" novalidate>
				<input type="hidden" id="wld-otp-download-id" name="download_id" value="">
				<input type="hidden" id="wld-otp-email"       name="email"       value="">

				<div class="wld-field">
					<input type="text"
					       name="otp_code"
					       id="wld-otp-input"
					       maxlength="6"
					       pattern="[0-9]{6}"
					       placeholder="000000"
					       autocomplete="one-time-code"
					       inputmode="numeric"
					       aria-label="<?php esc_attr_e( 'Enter 6-digit verification code', 'wp-lead-download' ); ?>"
					       aria-required="true">
				</div>

				<button type="submit" class="wld-btn" id="wld-s2-submit" aria-describedby="wld-s2-error">
					<span class="wld-btn-text"><?php esc_html_e( 'Verify &amp; Download', 'wp-lead-download' ); ?></span>
					<span class="wld-spinner" style="display:none;" aria-hidden="true"></span>
				</button>
			</form>

			<div class="wld-otp-actions">
				<a href="#" id="wld-resend-link" class="wld-resend-disabled">
					<?php esc_html_e( 'Resend in', 'wp-lead-download' ); ?>
					<span id="wld-countdown">60</span><?php esc_html_e( 's', 'wp-lead-download' ); ?>
				</a>
				<span class="wld-sep">|</span>
				<a href="#" id="wld-back-link">&#8592; <?php esc_html_e( 'Change email', 'wp-lead-download' ); ?></a>
			</div>

		</div><!-- /wld-screen-2 -->

		<!-- ============================================================
		     Screen 3: Success / Download
		     ============================================================ -->
		<div id="wld-screen-3" class="wld-screen" style="display:none;">

			<div class="wld-success-icon">
				<svg viewBox="0 0 52 52" width="64" height="64" aria-hidden="true">
					<circle cx="26" cy="26" r="25" fill="none" stroke="#4CAF50" stroke-width="2"/>
					<path d="M14 27 l8 8 l16-16" fill="none" stroke="#4CAF50" stroke-width="3"
					      stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</div>

			<h3 id="wld-thankyou-msg"></h3>

			<p class="wld-download-starting">
				<?php esc_html_e( 'Your download will start automatically…', 'wp-lead-download' ); ?>
			</p>

		</div><!-- /wld-screen-3 -->

	</div><!-- /wld-modal-box -->

</div><!-- /wld-modal-overlay -->
