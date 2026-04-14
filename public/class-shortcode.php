<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WLD_Shortcode {

	private static $assets_enqueued = false;
	private static $modal_rendered  = false;

	public static function init() {
		add_shortcode( 'lead_download',       [ __CLASS__, 'render' ] );
		add_action( 'wp_enqueue_scripts',     [ __CLASS__, 'register_assets' ] );
		add_action( 'wp_footer',              [ __CLASS__, 'maybe_render_modal' ] );
	}

	/**
	 * Register (not enqueue) front-end assets so they are ready on demand.
	 */
	public static function register_assets() {
		wp_register_style(
			'wld-public-style',
			WLD_PLUGIN_URL . 'public/style.css',
			[],
			WLD_VERSION
		);

		wp_register_script(
			'wld-public-script',
			WLD_PLUGIN_URL . 'public/script.js',
			[],          // no jQuery dependency — pure JS
			WLD_VERSION,
			true         // footer
		);

		wp_localize_script( 'wld-public-script', 'wld_vars', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => '',  // fetched fresh on first use to survive full-page cache
		] );
	}

	/**
	 * Render the [lead_download id="X"] shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'lead_download' );
		$id   = absint( $atts['id'] );

		if ( ! $id ) return '';

		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'wld_download' || $post->post_status !== 'publish' ) return '';
		if ( get_post_meta( $id, '_wld_active', true ) !== '1' )  return '';

		$file_url = get_post_meta( $id, '_wld_file_url', true );
		if ( empty( $file_url ) ) return '';

		$btn_label   = get_post_meta( $id, '_wld_button_label',  true ) ?: __( 'Download Now',             'wp-lead-download' );
		$btn_color   = get_post_meta( $id, '_wld_button_color',  true ) ?: '#0073aa';
		$form_title  = get_post_meta( $id, '_wld_form_title',    true ) ?: __( 'Fill details to download', 'wp-lead-download' );
		$thank_you   = get_post_meta( $id, '_wld_thank_you_msg', true ) ?: '';

		// Enqueue assets once per page
		if ( ! self::$assets_enqueued ) {
			wp_enqueue_style( 'wld-public-style' );
			wp_enqueue_script( 'wld-public-script' );
			self::$assets_enqueued = true;
		}

		ob_start();
		?>
		<div class="wld-download-wrap">
			<button class="wld-trigger-btn"
			        data-download-id="<?php echo esc_attr( $id ); ?>"
			        data-form-title="<?php echo esc_attr( $form_title ); ?>"
			        data-thankyou="<?php echo esc_attr( $thank_you ); ?>"
			        data-btn-color="<?php echo esc_attr( $btn_color ); ?>"
			        style="background-color:<?php echo esc_attr( $btn_color ); ?>;">
				<span class="wld-btn-label">
					<?php echo esc_html( $btn_label ); ?>
				</span>
				<span class="wld-btn-icon" aria-hidden="true">
					<!-- PDF file icon -->
					<svg xmlns="http://www.w3.org/2000/svg" width="28" height="34" viewBox="0 0 28 34" fill="none">
						<!-- Page body -->
						<path d="M2 0h17l9 9v23a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2z" fill="white" opacity="0.95"/>
						<!-- Fold triangle -->
						<path d="M19 0l9 9h-7a2 2 0 0 1-2-2V0z" fill="white" opacity="0.5"/>
						<!-- "PDF" text on the icon -->
						<text x="14" y="24" text-anchor="middle" font-family="Arial,sans-serif" font-size="8" font-weight="800" fill="<?php echo esc_attr( $btn_color ); ?>" letter-spacing="0.5">PDF</text>
					</svg>
				</span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output the modal HTML in wp_footer — only once, only when needed.
	 */
	public static function maybe_render_modal() {
		if ( ! self::$assets_enqueued )  return;
		if ( self::$modal_rendered )     return;
		self::$modal_rendered = true;
		include WLD_PLUGIN_DIR . 'public/modal.php';
	}
}
