<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WLD_Admin_Menu {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_post_wld_export_csv', [ 'WLD_Leads_Table', 'handle_export_csv' ] );
		WLD_Settings::init();
	}

	public static function register_menus() {
		// Top-level menu — goes to the All Leads page
		add_menu_page(
			__( 'Lead Downloads', 'wp-lead-download' ),
			__( 'Lead Downloads', 'wp-lead-download' ),
			'manage_options',
			'wld-dashboard',
			[ __CLASS__, 'render_leads_page' ],
			'dashicons-download',
			30
		);

		// All Downloads — points directly to the CPT list screen
		add_submenu_page(
			'wld-dashboard',
			__( 'All Downloads', 'wp-lead-download' ),
			__( 'All Downloads', 'wp-lead-download' ),
			'manage_options',
			'edit.php?post_type=wld_download'
		);

		// Add New Download
		add_submenu_page(
			'wld-dashboard',
			__( 'Add New', 'wp-lead-download' ),
			__( 'Add New', 'wp-lead-download' ),
			'manage_options',
			'post-new.php?post_type=wld_download'
		);

		// All Leads — with unread count badge
		$new_leads_count = WLD_DB_Setup::get_new_leads_count();
		$leads_menu_label = __( 'All Leads', 'wp-lead-download' );
		if ( $new_leads_count > 0 ) {
			$leads_menu_label .= ' <span class="update-plugins count-' . absint( $new_leads_count ) . '">'
				. '<span class="plugin-count">' . absint( $new_leads_count ) . '</span></span>';
		}

		add_submenu_page(
			'wld-dashboard',
			__( 'All Leads', 'wp-lead-download' ),
			$leads_menu_label,
			'manage_options',
			'wld-leads',
			[ __CLASS__, 'render_leads_page' ]
		);

		// Settings
		add_submenu_page(
			'wld-dashboard',
			__( 'Settings', 'wp-lead-download' ),
			__( 'Settings', 'wp-lead-download' ),
			'manage_options',
			'wld-settings',
			[ 'WLD_Settings', 'render_page' ]
		);

	}

	public static function render_shortcode_builder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wp-lead-download' ) );
		}

		$downloads = get_posts( [
			'post_type'      => 'wld_download',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		// Build JS data array — ID, label, color per download
		$js_data = [];
		foreach ( $downloads as $dl ) {
			$js_data[] = [
				'id'    => absint( $dl->ID ),
				'label' => esc_js( get_post_meta( $dl->ID, '_wld_button_label', true ) ?: __( 'Download Now', 'wp-lead-download' ) ),
				'color' => esc_js( get_post_meta( $dl->ID, '_wld_button_color', true ) ?: '#0073aa' ),
			];
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shortcode Builder', 'wp-lead-download' ); ?></h1>

			<?php if ( empty( $downloads ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: link to Add New */
							esc_html__( 'No published downloads found. %s first.', 'wp-lead-download' ),
							'<a href="' . esc_url( admin_url( 'post-new.php?post_type=wld_download' ) ) . '">'
								. esc_html__( 'Add a download', 'wp-lead-download' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<table class="form-table" style="max-width:600px;">
					<tr>
						<th scope="row">
							<label for="wld-sb-select"><?php esc_html_e( 'Select Download', 'wp-lead-download' ); ?></label>
						</th>
						<td>
							<select id="wld-sb-select" style="min-width:240px;">
								<option value=""><?php esc_html_e( '— Choose a download —', 'wp-lead-download' ); ?></option>
								<?php foreach ( $downloads as $dl ) : ?>
									<option value="<?php echo esc_attr( $dl->ID ); ?>">
										<?php echo esc_html( $dl->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<div id="wld-sb-preview" style="margin-top:24px;display:none;">
					<h3><?php esc_html_e( 'Preview', 'wp-lead-download' ); ?></h3>

					<button type="button" id="wld-preview-btn"
					        style="color:#fff;border:none;padding:12px 24px;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;">
					</button>

					<p style="margin-top:20px;">
						<strong><?php esc_html_e( 'Shortcode:', 'wp-lead-download' ); ?></strong>
					</p>
					<code id="wld-preview-shortcode"
					      style="font-size:15px;padding:8px 14px;background:#f0f0f0;border-radius:4px;display:inline-block;user-select:all;">
					</code>
					<button type="button" id="wld-sb-copy" class="button" style="margin-left:10px;">
						<?php esc_html_e( 'Copy Shortcode', 'wp-lead-download' ); ?>
					</button>
					<span id="wld-sb-copied" style="margin-left:8px;color:#0a8a30;display:none;">
						<?php esc_html_e( 'Copied!', 'wp-lead-download' ); ?>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<script>
		(function(){
			var downloads = <?php echo wp_json_encode( $js_data ); ?>;
			var select    = document.getElementById('wld-sb-select');
			var preview   = document.getElementById('wld-sb-preview');
			var btnEl     = document.getElementById('wld-preview-btn');
			var codeEl    = document.getElementById('wld-preview-shortcode');
			var copyBtn   = document.getElementById('wld-sb-copy');
			var copiedEl  = document.getElementById('wld-sb-copied');

			if (!select) return;

			select.addEventListener('change', function(){
				var id = this.value;
				if (!id) { preview.style.display = 'none'; return; }

				var dl = null;
				for (var i = 0; i < downloads.length; i++) {
					if (String(downloads[i].id) === String(id)) { dl = downloads[i]; break; }
				}
				if (!dl) return;

				btnEl.textContent              = dl.label;
				btnEl.style.backgroundColor    = dl.color;
				codeEl.textContent             = '[lead_download id="' + dl.id + '"]';
				preview.style.display          = '';
			});

			if (copyBtn) {
				copyBtn.addEventListener('click', function(){
					var text = codeEl.textContent.trim();
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(function(){
							copiedEl.style.display = 'inline';
							setTimeout(function(){ copiedEl.style.display = 'none'; }, 2000);
						});
					} else {
						var ta = document.createElement('textarea');
						ta.value = text;
						document.body.appendChild(ta);
						ta.select();
						document.execCommand('copy');
						document.body.removeChild(ta);
						copiedEl.style.display = 'inline';
						setTimeout(function(){ copiedEl.style.display = 'none'; }, 2000);
					}
				});
			}
		})();
		</script>
		<?php
	}

	public static function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wp-lead-download' ) );
		}

		$table = new WLD_Leads_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'All Leads', 'wp-lead-download' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wld_download' ) ); ?>"
			   class="page-title-action">
				<?php esc_html_e( 'Add Download', 'wp-lead-download' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Lead(s) deleted successfully.', 'wp-lead-download' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="wld-leads" />
				<?php
				$table->search_box( __( 'Search Leads', 'wp-lead-download' ), 'wld-search' );
				$table->display();
				update_option( 'wld_last_viewed_leads', current_time( 'mysql' ) );
				?>
			</form>
		</div>
		<?php
	}
}
