<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WLD_Leads_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => __( 'Lead',  'wp-lead-download' ),
			'plural'   => __( 'Leads', 'wp-lead-download' ),
			'ajax'     => false,
		] );
	}

	// -------------------------------------------------------------------------
	// Column definitions
	// -------------------------------------------------------------------------

	public function get_columns() {
		return [
			'cb'             => '<input type="checkbox" />',
			'id'             => __( 'ID',        'wp-lead-download' ),
			'download_title' => __( 'Download',  'wp-lead-download' ),
			'full_name'      => __( 'Full Name', 'wp-lead-download' ),
			'email'          => __( 'Email',     'wp-lead-download' ),
			'phone'          => __( 'Phone',     'wp-lead-download' ),
			'downloaded_at'  => __( 'Date',      'wp-lead-download' ),
		];
	}

	protected function get_sortable_columns() {
		return [
			'id'            => [ 'id',            true  ],
			'downloaded_at' => [ 'downloaded_at', true  ],
		];
	}

	protected function get_bulk_actions() {
		return [ 'delete' => __( 'Delete', 'wp-lead-download' ) ];
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lead_ids[]" value="%d" />', absint( $item->id ) );
	}

	protected function column_id( $item ) {
		return absint( $item->id );
	}

	protected function column_download_title( $item ) {
		$title = get_the_title( absint( $item->download_id ) );
		if ( ! $title ) return '&mdash;';
		$link = get_edit_post_link( absint( $item->download_id ) );
		return $link
			? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>'
			: esc_html( $title );
	}

	protected function column_full_name( $item ) {
		$delete_url = wp_nonce_url(
			add_query_arg( [
				'page'    => 'wld-leads',
				'action'  => 'wld_delete_lead',
				'lead_id' => absint( $item->id ),
			], admin_url( 'admin.php' ) ),
			'wld_delete_lead'
		);

		$actions = [
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this lead permanently?', 'wp-lead-download' ) ),
				esc_html__( 'Delete', 'wp-lead-download' )
			),
		];

		return esc_html( $item->full_name ) . $this->row_actions( $actions );
	}

	protected function column_email( $item ) {
		return '<a href="mailto:' . esc_attr( $item->email ) . '">' . esc_html( $item->email ) . '</a>';
	}

	protected function column_phone( $item ) {
		return esc_html( $item->phone ) ?: '&mdash;';
	}

	protected function column_downloaded_at( $item ) {
		return esc_html(
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->downloaded_at ) )
		);
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '&mdash;';
	}

	// -------------------------------------------------------------------------
	// Filter dropdown + export button in the table nav
	// -------------------------------------------------------------------------

	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) return;

		$selected   = isset( $_GET['filter_download_id'] ) ? absint( $_GET['filter_download_id'] ) : 0;
		$downloads  = get_posts( [ 'post_type' => 'wld_download', 'posts_per_page' => -1, 'post_status' => 'any' ] );
		$export_url = admin_url( 'admin-post.php?action=wld_export_csv&nonce=' . wp_create_nonce( 'wld_export_csv' ) );

		if ( $selected ) {
			$export_url = add_query_arg( 'filter_download_id', $selected, $export_url );
		}
		?>
		<div class="alignleft actions">
			<?php if ( $downloads ) : ?>
				<select name="filter_download_id">
					<option value="0"><?php esc_html_e( '— All Downloads —', 'wp-lead-download' ); ?></option>
					<?php foreach ( $downloads as $dl ) : ?>
						<option value="<?php echo esc_attr( $dl->ID ); ?>" <?php selected( $selected, $dl->ID ); ?>>
							<?php echo esc_html( $dl->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'wp-lead-download' ); ?>" />
			<?php endif; ?>

			<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Export CSV', 'wp-lead-download' ); ?>
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// prepare_items
	// -------------------------------------------------------------------------

	public function prepare_items() {
		// Handle single-row delete
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'wld_delete_lead' && isset( $_GET['lead_id'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
			check_admin_referer( 'wld_delete_lead' );
			WLD_DB_Setup::delete_lead( absint( $_GET['lead_id'] ) );
			wp_safe_redirect( add_query_arg( [ 'page' => 'wld-leads', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
			exit;
		}

		// Handle bulk delete
		if ( $this->current_action() === 'delete' && ! empty( $_POST['lead_ids'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
			check_admin_referer( 'bulk-' . $this->_args['plural'] );
			foreach ( (array) $_POST['lead_ids'] as $id ) {
				WLD_DB_Setup::delete_lead( absint( $id ) );
			}
			wp_safe_redirect( add_query_arg( [ 'page' => 'wld-leads', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$per_page     = $this->get_items_per_page( 'wld_leads_per_page', 20 );
		$current_page = $this->get_pagenum();
		$search       = isset( $_REQUEST['s'] )                ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) )         : '';
		$download_id  = isset( $_GET['filter_download_id'] )   ? absint( $_GET['filter_download_id'] )                       : 0;
		$orderby      = isset( $_REQUEST['orderby'] )          ? sanitize_key( $_REQUEST['orderby'] )                        : 'downloaded_at';
		$order        = isset( $_REQUEST['order'] )            ? sanitize_key( $_REQUEST['order'] )                          : 'DESC';

		$result = WLD_DB_Setup::get_leads( [
			'download_id' => $download_id,
			'search'      => $search,
			'per_page'    => $per_page,
			'paged'       => $current_page,
			'orderby'     => $orderby,
			'order'       => $order,
		] );

		$this->items = $result['items'];

		$this->set_pagination_args( [
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $result['total'] / $per_page ),
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	// -------------------------------------------------------------------------
	// CSV Export — registered via admin_post_wld_export_csv
	// -------------------------------------------------------------------------

	public static function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'wld_export_csv', 'nonce' );

		$download_id = isset( $_GET['filter_download_id'] ) ? absint( $_GET['filter_download_id'] ) : 0;

		$result = WLD_DB_Setup::get_leads( [
			'download_id' => $download_id,
			'per_page'    => 99999,
			'paged'       => 1,
		] );

		$filename = 'wld-leads-' . date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// BOM for Excel UTF-8 compatibility
		fputs( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, [ 'ID', 'Download', 'Full Name', 'Email', 'Phone', 'Date' ] );

		foreach ( $result['items'] as $lead ) {
			fputcsv( $output, [
				absint( $lead->id ),
				get_the_title( absint( $lead->download_id ) ),
				$lead->full_name,
				$lead->email,
				$lead->phone,
				$lead->downloaded_at,
			] );
		}

		fclose( $output );
		die();
	}
}
