<?php

/*
 * Vamtam CRM Integration, used to check for updates and aiding support queries
 */

class Version_Checker {
	public $remote;
	public $interval;
	public $notice;

	private $update_api_prefix = 'https://updates.vamtam.com/0/envato/';

	private $update_api_url;
	private $validate_api_url;

	private static $instance;

	public static $VALID_LICENSE   = 'VALIDATED';
	public static $INVALID_LICENSE = 'INVALID';

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->remote   = 'https://api.vamtam.com/version';
		$this->interval = 2 * 3600;

		$this->update_api_url   = $this->update_api_prefix . 'check-theme';
		$this->validate_api_url = $this->update_api_prefix . 'validate-license';

		if ( ! isset( $_GET['import'] ) && ( ! isset( $_GET['step'] ) || (int) $_GET['step'] != 2 ) ) {
			add_action( 'admin_init', array( $this, 'check_version' ) );
		}

		add_action( 'wp_ajax_vamtam-check-license', array( $this, 'check_license' ) );
		add_action( 'vamtam_saved_options', array( $this, 'check_version' ) );

		if ( VamtamFramework::license() === false ) {
			$this->check_license( [ 'no_ajax' => true ] );
		}

		// set_site_transient('update_themes', null);

		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_update' ) );
	}

	public function check_update( $updates ) {
		// prevent conflicts with themes hosted on wp.org
		$theme_name = wp_get_theme()->get_template();
		if (
			isset( $updates->response ) &&
			isset( $updates->response[ $theme_name ] ) &&
			strpos( $updates->response[ $theme_name ]['package'], 'downloads.wordpress.org' ) !== false
		) {
			unset( $updates->response[ $theme_name ] );
		}

		$response = $this->update_api_request( $updates );

		if ( false === $response ) {
			// No update is available.
			$item = array(
				'theme'        => $theme_name,
				'new_version'  => VamtamFramework::get_version(),
				'url'          => '',
				'package'      => '',
				'requires'     => '',
				'requires_php' => '',
			);

			// Adding the "mock" item to the `no_update` property is required
			// for the enable/disable auto-updates links to correctly appear in UI.
			$updates->no_update[ $theme_name ] = $item;

			return $updates;
		}

		if ( ! isset( $updates->response ) ) {
			$updates->response = array();
		}

		$updates->response = array_merge( $updates->response, $response );

		// Small trick to ensure the updates get shown in the network admin
		if ( is_multisite() && ! is_main_site() ) {
			global $current_site;

			switch_to_blog( $current_site->blog_id );
			set_site_transient( 'update_themes', $updates );
			restore_current_blog();
		}

		return $updates;
	}

	private function update_api_request( $update_cache ) {
		global $wp_version;

		$theme_name = wp_get_theme()->get_template();

		$raw_response = wp_remote_post( $this->update_api_url, array(
			'body' => array(
				'version'      => isset( $update_cache->checked[ $theme_name ] ) ? $update_cache->checked[ $theme_name ] : VamtamFramework::get_version(),
				'purchase_key' => apply_filters( 'vamtam_purchase_code', '' ),
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
		) );

		if ( is_wp_error( $raw_response ) || 200 !== wp_remote_retrieve_response_code( $raw_response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

		return $response['themes'];
	}


	public function check_license( $args ) {
		$no_ajax = isset( $args['no_ajax'] ) ? $args['no_ajax'] : false;

		//if the func is called from the server we dont need to check_ajax_referer.
		if ( ! $no_ajax ) {
			check_ajax_referer( 'vamtam-check-license', 'nonce' );
		}

		global $wp_version;

		$should_unregister = isset( $_POST['unregister'] ) && vamtam_sanitize_bool( $_POST['unregister'] );

		if ( $should_unregister ) {
			delete_option( VamtamFramework::get_purchase_code_option_key() );
			VamtamFramework::license( self::$INVALID_LICENSE );
		}

		$key = $no_ajax && ! $should_unregister ? VamtamFramework::get_purchase_code() : $_POST['license-key'];

		if ( ! empty( $key ) ) {
			if ( VamtamFramework::license() === self::$VALID_LICENSE ) {
				// Already Validated.
				VamtamFramework::license_valid();
			} else {
				$raw_response = wp_remote_post( $this->validate_api_url, array(
					'body' => array(
						'purchase_key' => $key,
					),
					'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
				) );

				if ( ! is_wp_error( $raw_response ) ) {
					if ( $raw_response['response']['code'] >= 200 && $raw_response['response']['code'] < 300 ) {
						if ( ! $no_ajax ) {
							VamtamFramework::license_valid();
						}
						VamtamFramework::license( self::$VALID_LICENSE );
					} else {
						if ( ! $no_ajax ) {
							VamtamFramework::license_invalid();
						}
						VamtamFramework::license( self::$INVALID_LICENSE );
					}
				} else {
					if ( ! $no_ajax ) {
						VamtamFramework::license_failed();
					}
				}

				$this->check_version();
			}
		} else if ( $should_unregister ) {
			VamtamFramework::license_unregister();
		}

		if ( ! $no_ajax ) {
			die;
		}
	}

	public static function is_valid_purchase_code() {
		if ( apply_filters( 'vamtam_purchase_code_import_override', false ) ) {
			return true;
		}

		return VamtamFramework::license() === self::$VALID_LICENSE;
	}

	public function check_version() {
		$local_version = VamtamFramework::get_version();
		$key           = VAMTAM_THEME_SLUG . '_' . $local_version;

		$last_license_key    = get_option( VamtamFramework::get_purchase_code_option_key() . '-old' );
		$current_license_key = VamtamFramework::get_purchase_code();

		$system_status_opt_out_old = get_option( 'vamtam-system-status-opt-in-old' );
		$system_status_opt_out     = get_option( 'vamtam-system-status-opt-in' );

		if ( $last_license_key !== $current_license_key || $system_status_opt_out_old !== $system_status_opt_out || false === get_transient( $key ) ) {
			global $wp_version;

			$data = array(
				'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url() . '; ',
				'blocking'   => false,
				'body'       => array(
					'theme_version'  => $local_version,
					'php_version'    => phpversion(),
					'server'         => getenv('SERVER_SOFTWARE'),
					'theme_name'     => VAMTAM_THEME_NAME,
					'license_key'    => $current_license_key,
					'active_plugins' => self::active_plugins(),
					'system_status'  => self::system_status(),
				),
			);

			if ( $last_license_key !== $current_license_key ) {
				update_option( VamtamFramework::get_purchase_code_option_key() . '-old', $current_license_key );
			}

			if ( $system_status_opt_out_old !== $system_status_opt_out ) {
				update_option( 'vamtam-system-status-opt-in-old', $system_status_opt_out );
			}

			wp_remote_post( $this->remote, $data );

			set_transient( $key, true, $this->interval ); // cache
		}
	}

	public static function active_plugins() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

		return $active_plugins;
	}

	public static function system_status() {
		if ( ! get_option( 'vamtam-system-status-opt-in' ) ) {
			return array(
				'disabled' => true,
			);
		}

		$result = array(
			'disabled'         => false,
			'wp_debug'         => WP_DEBUG,
			'wp_debug_display' => WP_DEBUG_DISPLAY,
			'wp_debug_log'     => WP_DEBUG_LOG,
			'active_plugins'   => array(),
			'writable'         => array(),
			'ziparchive'       => class_exists( 'ZipArchive' ),
		);

		if ( function_exists( 'ini_get' ) ) {
			$result['post_max_size']      = ini_get( 'post_max_size' );
			$result['max_input_vars']     = ini_get( 'max_input_vars' );
			$result['max_execution_time'] = ini_get( 'max_execution_time' );
			$result['memory_limit']       = ini_get( 'memory_limit' );
		}

		$active_plugins = self::active_plugins();

		foreach ( $active_plugins as $plugin ) {
			$plugin_data = @get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );

			$result['active_plugins'][ $plugin ] = array(
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'author'  => $plugin_data['AuthorName'],
			);
		}

		$result['wp_remote_post'] = 'Irrelevant';

		return $result;
	}
}

Version_Checker::get_instance();

