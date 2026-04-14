<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WLD_Downloads_CPT {

	/**
	 * Register all CPT hooks. Static guard prevents double-registration.
	 */
	public static function register() {
		static $done = false;
		if ( $done ) return;
		$done = true;

		add_action( 'init',                   [ __CLASS__, 'register_cpt' ] );
		add_action( 'add_meta_boxes',         [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post',              [ __CLASS__, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_admin_scripts' ] );
	}

	public static function register_cpt() {
		$labels = [
			'name'               => __( 'Downloads',              'wp-lead-download' ),
			'singular_name'      => __( 'Download',               'wp-lead-download' ),
			'add_new'            => __( 'Add New',                'wp-lead-download' ),
			'add_new_item'       => __( 'Add New Download',       'wp-lead-download' ),
			'edit_item'          => __( 'Edit Download',          'wp-lead-download' ),
			'new_item'           => __( 'New Download',           'wp-lead-download' ),
			'search_items'       => __( 'Search Downloads',       'wp-lead-download' ),
			'not_found'          => __( 'No downloads found.',    'wp-lead-download' ),
			'not_found_in_trash' => __( 'No downloads in trash.', 'wp-lead-download' ),
			'menu_name'          => __( 'WLD Downloads',          'wp-lead-download' ),
		];

		register_post_type( 'wld_download', [
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false,
			'supports'        => [ 'title' ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'has_archive'     => false,
			'rewrite'         => false,
		] );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'wld_download_settings',
			__( 'Download Settings', 'wp-lead-download' ),
			[ __CLASS__, 'render_meta_box' ],
			'wld_download',
			'normal',
			'high'
		);

		add_meta_box(
			'wld_download_stats',
			__( 'Download Statistics', 'wp-lead-download' ),
			[ __CLASS__, 'render_stats_meta_box' ],
			'wld_download',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'wld_meta_box_nonce', 'wld_meta_box_nonce' );

		$file_url    = get_post_meta( $post->ID, '_wld_file_url',     true );
		$btn_label   = get_post_meta( $post->ID, '_wld_button_label', true );
		$btn_color   = get_post_meta( $post->ID, '_wld_button_color', true );
		$form_title  = get_post_meta( $post->ID, '_wld_form_title',   true );
		$thankyou    = get_post_meta( $post->ID, '_wld_thank_you_msg', true );
		$active      = get_post_meta( $post->ID, '_wld_active',       true );

		// Defaults
		if ( $btn_label  === '' ) $btn_label  = __( 'Download Now',              'wp-lead-download' );
		if ( $btn_color  === '' ) $btn_color  = '#0073aa';
		if ( $form_title === '' ) $form_title = __( 'Fill details to download',  'wp-lead-download' );
		if ( $active     === '' ) $active     = '1'; // checked by default for new posts
		?>

		<table class="form-table" style="margin-bottom:0;">

			<tr>
				<th scope="row"><label for="wld_file_url"><?php esc_html_e( 'File URL', 'wp-lead-download' ); ?> <span style="color:#c00;">*</span></label></th>
				<td>
					<input type="url" id="wld_file_url" name="wld_file_url"
					       value="<?php echo esc_attr( $file_url ); ?>"
					       class="regular-text" placeholder="https://" />
					<p class="description"><?php esc_html_e( 'Direct link to the file users will receive after OTP verification.', 'wp-lead-download' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="wld_button_label"><?php esc_html_e( 'Button Label', 'wp-lead-download' ); ?></label></th>
				<td>
					<input type="text" id="wld_button_label" name="wld_button_label"
					       value="<?php echo esc_attr( $btn_label ); ?>"
					       class="regular-text" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="wld_button_color"><?php esc_html_e( 'Button Color', 'wp-lead-download' ); ?></label></th>
				<td>
					<input type="text" id="wld_button_color" name="wld_button_color"
					       value="<?php echo esc_attr( $btn_color ); ?>"
					       class="wld-color-picker" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="wld_form_title"><?php esc_html_e( 'Form Title', 'wp-lead-download' ); ?></label></th>
				<td>
					<input type="text" id="wld_form_title" name="wld_form_title"
					       value="<?php echo esc_attr( $form_title ); ?>"
					       class="regular-text" />
					<p class="description"><?php esc_html_e( 'Heading shown at the top of the lead capture form.', 'wp-lead-download' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="wld_thank_you_msg"><?php esc_html_e( 'Thank You Message', 'wp-lead-download' ); ?></label></th>
				<td>
					<textarea id="wld_thank_you_msg" name="wld_thank_you_msg"
					          rows="3" class="large-text"><?php echo esc_textarea( $thankyou ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown on the success screen after the download starts.', 'wp-lead-download' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Active', 'wp-lead-download' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wld_active" value="1"
						       <?php checked( '1', $active ); ?> />
						<?php esc_html_e( 'Enable this download (uncheck to temporarily disable the gate).', 'wp-lead-download' ); ?>
					</label>
				</td>
			</tr>

		</table>

		<?php if ( $post->post_status === 'publish' ) : ?>
		<div style="margin-top:15px;padding:12px;background:#f0f0f0;border-left:4px solid #0073aa;">
			<strong><?php esc_html_e( 'Your Shortcode:', 'wp-lead-download' ); ?></strong><br>
			<code style="font-size:14px;user-select:all;">[lead_download id="<?php echo absint( $post->ID ); ?>"]</code>
		</div>
		<?php endif; ?>

		<?php
	}

	public static function render_stats_meta_box( $post ) {
		$total  = WLD_DB_Setup::get_lead_count_by_download( $post->ID );
		$recent = WLD_DB_Setup::get_recent_leads( $post->ID, 5 );

		$leads_url = add_query_arg(
			[ 'page' => 'wld-leads', 'filter_download_id' => absint( $post->ID ) ],
			admin_url( 'admin.php' )
		);
		?>
		<p style="margin:0 0 8px;">
			<strong><?php esc_html_e( 'Total Downloads:', 'wp-lead-download' ); ?></strong>
			<?php echo absint( $total ); ?>
		</p>

		<?php if ( $recent ) : ?>
			<table style="width:100%;border-collapse:collapse;font-size:12px;">
				<thead>
					<tr>
						<th style="text-align:left;padding:4px 6px;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Name', 'wp-lead-download' ); ?></th>
						<th style="text-align:left;padding:4px 6px;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Email', 'wp-lead-download' ); ?></th>
						<th style="text-align:left;padding:4px 6px;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Date', 'wp-lead-download' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $lead ) : ?>
						<tr>
							<td style="padding:4px 6px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $lead->full_name ); ?></td>
							<td style="padding:4px 6px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $lead->email ); ?></td>
							<td style="padding:4px 6px;border-bottom:1px solid #f0f0f0;">
								<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $lead->downloaded_at ) ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p style="color:#888;font-size:13px;"><?php esc_html_e( 'No leads yet.', 'wp-lead-download' ); ?></p>
		<?php endif; ?>

		<p style="margin:12px 0 0;">
			<a href="<?php echo esc_url( $leads_url ); ?>">
				<?php esc_html_e( 'View all leads &rarr;', 'wp-lead-download' ); ?>
			</a>
		</p>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['wld_meta_box_nonce'] ) ||
		     ! wp_verify_nonce( $_POST['wld_meta_box_nonce'], 'wld_meta_box_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( $post->post_type !== 'wld_download' ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$fields = [
			'wld_file_url'     => '_wld_file_url',
			'wld_button_label' => '_wld_button_label',
			'wld_button_color' => '_wld_button_color',
			'wld_form_title'   => '_wld_form_title',
			'wld_thank_you_msg'=> '_wld_thank_you_msg',
		];

		foreach ( $fields as $post_key => $meta_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				if ( $post_key === 'wld_file_url' ) {
					update_post_meta( $post_id, $meta_key, esc_url_raw( wp_unslash( $_POST[ $post_key ] ) ) );
				} elseif ( $post_key === 'wld_thank_you_msg' ) {
					update_post_meta( $post_id, $meta_key, sanitize_textarea_field( wp_unslash( $_POST[ $post_key ] ) ) );
				} elseif ( $post_key === 'wld_button_color' ) {
					// Accept only valid hex colours.
					$color = sanitize_hex_color( wp_unslash( $_POST[ $post_key ] ) );
					update_post_meta( $post_id, $meta_key, $color ?: '#0073aa' );
				} else {
					update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
				}
			}
		}

		// Checkbox — explicitly save 0 when unchecked.
		$active = isset( $_POST['wld_active'] ) ? '1' : '0';
		update_post_meta( $post_id, '_wld_active', $active );
	}

	/**
	 * Enqueue the WP color picker on the CPT edit screen only.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'wld_download' ) return;

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_add_inline_script( 'wp-color-picker', '
			jQuery(document).ready(function($){
				$(".wld-color-picker").wpColorPicker();
			});
		' );
	}
}
