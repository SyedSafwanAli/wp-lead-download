<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enables auto-update checking against a GitHub Releases API endpoint.
 * Attach by instantiating in the main plugin file (admin context only).
 */
class WLD_Updater {

	/** @var string Absolute path to the main plugin file. */
	private $plugin_file;

	/** @var string plugin_basename() of the main file. */
	private $plugin_basename;

	/** @var string GitHub username / organisation. */
	private $github_user;

	/** @var string GitHub repository slug. */
	private $github_repo;

	/** @var array|null Cached result of get_plugin_data(). */
	private $plugin_data = null;

	/**
	 * @param string $plugin_file   Absolute path — pass WLD_PLUGIN_FILE.
	 * @param string $github_user   GitHub username.
	 * @param string $github_repo   Repository name (must match plugin folder).
	 */
	public function __construct( $plugin_file, $github_user, $github_repo ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->github_user     = $github_user;
		$this->github_repo     = $github_repo;

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api',                           [ $this, 'get_plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete',             [ $this, 'flush_cache' ],     10, 2 );
	}

	/* ------------------------------------------------------------------
	   GitHub API helpers
	------------------------------------------------------------------ */

	/**
	 * Fetch the latest release from GitHub, cached for 12 hours.
	 *
	 * @return object|false stdClass from GitHub, or false on error.
	 */
	private function get_release_data() {
		$cached = get_transient( 'wld_update_check' );
		if ( $cached !== false ) return $cached;

		$response = wp_remote_get(
			"https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest",
			[
				'headers' => [
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
					'Accept'     => 'application/vnd.github.v3+json',
				],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( 'wld_update_check', false, HOUR_IN_SECONDS );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		set_transient( 'wld_update_check', $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Get the download URL for the plugin zip.
	 * Prefers a release asset named wp-lead-download.zip (correct folder structure),
	 * falls back to GitHub's auto-generated zipball (may have wrong folder name).
	 *
	 * @param  object $release GitHub release object.
	 * @return string
	 */
	private function get_download_url( $release ) {
		if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( isset( $asset->name ) && $asset->name === $this->github_repo . '.zip' ) {
					return $asset->browser_download_url;
				}
			}
		}
		return ! empty( $release->zipball_url ) ? $release->zipball_url : '';
	}

	/* ------------------------------------------------------------------
	   WordPress update pipeline hooks
	------------------------------------------------------------------ */

	/**
	 * Inject update data into the WP updates transient when a newer
	 * version exists on GitHub.
	 *
	 * @param  object $transient  The update_plugins site transient.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) return $transient;

		$release = $this->get_release_data();
		if ( ! $release || empty( $release->tag_name ) ) return $transient;

		$remote_version = ltrim( $release->tag_name, 'v' );

		if ( version_compare( $remote_version, WLD_VERSION, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) [
				'id'          => $this->plugin_basename,
				'slug'        => $this->github_repo,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_version,
				'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
				'package'     => $this->get_download_url( $release ),
			];
		}

		return $transient;
	}

	/**
	 * Return plugin information for the "View details" popup in WP admin.
	 *
	 * @param  false|object|array $result
	 * @param  string             $action
	 * @param  object             $args
	 * @return false|object
	 */
	public function get_plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) return $result;
		if ( ! isset( $args->slug ) || $args->slug !== $this->github_repo ) return $result;

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( null === $this->plugin_data ) {
			$this->plugin_data = get_plugin_data( $this->plugin_file );
		}

		$release = $this->get_release_data();
		$version = ( $release && ! empty( $release->tag_name ) )
			? ltrim( $release->tag_name, 'v' )
			: WLD_VERSION;

		$changelog = ( $release && ! empty( $release->body ) )
			? '<pre>' . esc_html( $release->body ) . '</pre>'
			: '<p>' . esc_html__( 'See GitHub releases for full changelog.', 'wp-lead-download' ) . '</p>';

		$info              = new stdClass();
		$info->name        = isset( $this->plugin_data['Name'] ) ? $this->plugin_data['Name'] : 'WP Lead Download';
		$info->slug        = $this->github_repo;
		$info->version     = $version;
		$info->author      = isset( $this->plugin_data['Author'] ) ? $this->plugin_data['Author'] : '';
		$info->homepage    = "https://github.com/{$this->github_user}/{$this->github_repo}";
		$info->download_link = $release ? $this->get_download_url( $release ) : '';
		$info->sections    = [
			'description' => isset( $this->plugin_data['Description'] ) ? $this->plugin_data['Description'] : '',
			'changelog'   => $changelog,
		];

		return $info;
	}

	/**
	 * Delete the cached release data immediately after a successful update
	 * so the next admin page load picks up the new version cleanly.
	 *
	 * @param  \WP_Upgrader $upgrader
	 * @param  array        $hook_extra
	 */
	public function flush_cache( $upgrader, $hook_extra ) {
		if ( isset( $hook_extra['action'], $hook_extra['type'] )
			&& $hook_extra['action'] === 'update'
			&& $hook_extra['type']   === 'plugin'
			&& isset( $hook_extra['plugins'] )
			&& in_array( $this->plugin_basename, (array) $hook_extra['plugins'], true )
		) {
			delete_transient( 'wld_update_check' );
		}
	}
}
