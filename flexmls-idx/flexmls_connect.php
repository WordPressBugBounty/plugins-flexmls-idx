<?php
/*
Flexmls® IDX Plugin
Plugin Name: Flexmls® IDX
Plugin URI: https://fbsidx.com/help
Description: Provides Flexmls&reg; Customers with Flexmls&reg; IDX features on their WordPress websites. <strong>Tips:</strong> <a href="admin.php?page=fmc_admin_settings">Activate your Flexmls&reg; IDX plugin</a> on the settings page; <a href="widgets.php">add widgets to your sidebar</a> using the Widgets Admin under Appearance; and include widgets on your posts or pages using the Flexmls&reg; IDX Widget Short-Code Generator on the Visual page editor.
Author: FBS
Version: 3.18
Author URI:  https://www.flexmls.com
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
*/

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

const FMC_API_BASE = 'sparkapi.com';
const FMC_API_VERSION = 'v1';
const FMC_PLUGIN_VERSION = '3.18';

define( 'FMC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

global $auth_token_failures, $spark_oauth_global;
$auth_token_failures = 0;

$fmc_version = FMC_PLUGIN_VERSION;
$fmc_plugin_dir = dirname(realpath(__FILE__));
$fmc_plugin_url = plugins_url() .'/flexmls-idx';

if( defined( 'FMC_DEV' ) && FMC_DEV && WP_DEBUG ){
	ini_set( 'error_log', FMC_PLUGIN_DIR . '/debug.log' );
}


class FlexMLS_IDX {

	function __construct(){
        require_once( 'lib/functions.php' );
		require_once( 'Admin/autoloader.php' );
		require_once( 'Admin/NginxCompatibility.php' );
		require_once( 'Shortcodes/autoloader.php' );
		require_once( 'SparkAPI/autoloader.php' );
		require_once( 'Widgets/autoloader.php' );
		require_once( 'lib/base.php' );
		require_once( 'lib/flexmls-json.php' );
		require_once( 'lib/search-util.php' );
		require_once( 'lib/settings-page.php' );
		require_once( 'lib/flexmlsAPI/Core.php' );
		require_once( 'lib/flexmlsAPI/WordPressCache.php' );
		require_once( 'lib/oauth-api.php' );
		require_once( 'lib/apiauth-api.php' );
		require_once( 'lib/fmc_settings.php' );
		require_once( 'lib/fmcStandardStatus.php' );
		require_once( 'lib/account.php' );
		require_once( 'lib/idx-links.php' );
		require_once( 'pages/portal-popup.php' );
		require_once( 'components/widget.php' );
		require_once( 'components/photo_settings.php' );
		require_once( 'components/listing-map.php' );
		require_once( 'pages/core.php' );
		require_once( 'pages/full-page.php' );
		require_once( 'pages/listing-details.php' );
		require_once( 'pages/search-results.php' );
		require_once( 'pages/fmc-agents.php' );
		require_once( 'pages/next-listing.php' );
		require_once( 'pages/prev-listing.php' );
		require_once( 'pages/oauth-login.php' );
		require_once( 'components/LocationGenerator.php' );

		add_action( 'admin_enqueue_scripts', array( '\FlexMLS\Admin\Enqueue', 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( '\FlexMLS\Admin\Enqueue', 'admin_print_footer_scripts' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu_fix_submenu_labels' ), 99 );
		add_action( 'admin_init', array( $this, 'admin_init_redirect_clear_cache' ) );
		add_action( 'admin_post_fmc_retry_connection', array( $this, 'handle_fmc_retry_connection' ) );
		add_filter( 'submenu_file', array( $this, 'admin_submenu_file' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'flexmls_hourly_cache_cleanup', array( '\FlexMLS\Admin\Update', 'hourly_cache_cleanup' ) );
		add_action( 'init', array( $this, 'rewrite_rules' ) );
		add_action( 'parse_query', array( $this, 'parse_query' ) );
		add_action( 'plugins_loaded', array( '\FlexMLS\Admin\Settings', 'update_settings' ), 9 );
		add_action( 'plugins_loaded', array( $this, 'session_start' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_idx_redirects' ), 10, 2 );
		add_action( 'send_headers', array( $this, 'send_idx_page_header' ) );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		//add_action( 'wp_ajax_fmcShortcodeContainer', array( 'flexmlsConnect', 'shortcode_container' ) );
		add_action( 'wp_ajax_fmcShortcodeContainer', array( '\FlexMLS\Admin\TinyMCE', 'tinymce_shortcodes' ) );
		add_action('wp_ajax_fmcLocationGenerator', array('\FlexMLS\Admin\LocationGenerator', 'tinymce_form') );
		add_action( 'wp_ajax_tinymce_shortcodes_generate', array( '\FlexMLS\Admin\TinyMCE', 'tinymce_shortcodes_generate' ) );
        add_action( 'wp_ajax_nopriv_tinymce_shortcodes_generate', array( '\FlexMLS\Admin\TinyMCE', 'tinymce_shortcodes_generate' ) );
		add_action( 'wp_ajax_fmcleadgen_shortcode', array( '\FlexMLS\Shortcodes\LeadGeneration', 'tinymce_form' ) );
		add_action( 'wp_ajax_fmcleadgen_submit', array( '\FlexMLS\Shortcodes\LeadGeneration', 'submit_lead' ) );
		add_action( 'wp_ajax_nopriv_fmcleadgen_submit', array( '\FlexMLS\Shortcodes\LeadGeneration', 'submit_lead' ) );
		add_action( 'wp_enqueue_scripts', array( '\FlexMLS\Admin\Enqueue', 'wp_enqueue_scripts' ) );
		add_filter( 'script_loader_tag', array( '\FlexMLS\Admin\Enqueue', 'script_loader_tag_no_minify' ), 10, 3 );
		add_filter( 'style_loader_tag', array( '\FlexMLS\Admin\Enqueue', 'style_loader_tag_no_minify' ), 10, 3 );

		add_action( 'wp_ajax_flexmls_connect_save_search', array( 'flexmlsConnectPageSearchResults', 'save_user_search' ) );
		add_action( 'wp_ajax_nopriv_flexmls_connect_save_search', array( 'flexmlsConnectPageSearchResults', 'save_user_search' ) );
		
		// AJAX handler for nginx configuration rules
		add_action( 'wp_ajax_fmc_get_nginx_rules', array( '\FlexMLS\Admin\NginxCompatibility', 'ajax_get_nginx_rules' ) );

		add_shortcode( 'idx_frame', array( 'flexmlsConnect', 'shortcode' ) );
		add_shortcode( 'lead_generation', array( '\FlexMLS\Shortcodes\LeadGeneration', 'shortcode' ) );
		add_shortcode( 'neighborhood_page', array( 'FlexMLS\Shortcodes\NeighborhoodPage', 'shortcode' ) );
	}

	function admin_menu(){
		$options = get_option( 'fmc_settings', array() );
		$has_key = ! empty( $options['api_key'] ) && ! empty( $options['api_secret'] );

		add_menu_page( 'Flexmls&reg; IDX', 'Flexmls&reg; IDX', 'edit_posts', 'fmc_admin_intro', array( '\FlexMLS\Admin\Settings', 'admin_menu_cb_intro' ), 'dashicons-location', 77 );

		if ( $has_key ) {
			add_submenu_page( 'fmc_admin_intro', 'Flexmls&reg; IDX: Settings', 'Settings', 'manage_options', 'fmc_admin_settings', array( '\FlexMLS\Admin\Settings', 'admin_menu_cb_settings' ) );
			add_submenu_page( 'fmc_admin_intro', 'Clear Cache', 'Caching', 'manage_options', 'fmc_admin_cache', array( $this, 'admin_menu_cb_clear_cache' ) );
		}
	}

	function admin_menu_fix_submenu_labels(){
		global $submenu;
		if ( ! isset( $submenu['fmc_admin_intro'][0][0] ) ) {
			return;
		}
		$options = get_option( 'fmc_settings', array() );
		$has_key = ! empty( $options['api_key'] ) && ! empty( $options['api_secret'] );
		$submenu['fmc_admin_intro'][0][0] = $has_key ? 'Credentials' : 'Activate';
	}

	function admin_init_redirect_clear_cache(){
		if ( isset( $_GET['page'] ) && 'fmc_admin_cache' === $_GET['page'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=fmc_admin_settings&tab=cache' ) );
			exit;
		}
	}

	/**
	 * Manual "Retry connection" from Connection Paused notice (nonce + manage_options).
	 */
	function handle_fmc_retry_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fmcdomain' ) );
		}
		check_admin_referer( 'fmc_retry_connection' );
		\FlexMLS\Admin\ConnectionPause::clear_for_manual_retry();
		delete_transient( 'flexmls_auth_failures_timestamps' );
		delete_transient( 'flexmls_auth_token' );
		$spark = new \SparkAPI\Core();
		$ok    = (bool) $spark->generate_auth_token( 'manual' );
		$arg   = $ok ? 'success' : 'fail';
		wp_safe_redirect( admin_url( 'admin.php?page=fmc_admin_intro&fmc_connection_retry=' . rawurlencode( $arg ) ) );
		exit;
	}

	function admin_menu_cb_clear_cache(){
		wp_safe_redirect( admin_url( 'admin.php?page=fmc_admin_settings&tab=cache' ) );
		exit;
	}

	function admin_submenu_file( $submenu_file, $parent_file ){
		if ( 'fmc_admin_intro' === $parent_file && isset( $_GET['page'] ) && 'fmc_admin_settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'cache' === $_GET['tab'] ) {
			return 'fmc_admin_cache';
		}
		return $submenu_file;
	}

	/**
	 * True on Flexmls IDX intro (Credentials / Support / Features). WordPress hides many admin_notices there (about-wrap).
	 */
	public static function is_fmc_admin_intro_screen() {
		return isset( $_GET['page'] ) && 'fmc_admin_intro' === $_GET['page'];
	}

	/**
	 * Connection paused, retry flash, API errors, Google Maps nudge — shown via admin_notices except on intro (see Settings::admin_menu_cb_intro).
	 *
	 * @param bool $for_about_wrap True inside Credentials `.about-wrap` (WP core hides `.notice` there).
	 */
	public function render_fmc_api_connection_notices( $for_about_wrap = false ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options = get_option( 'fmc_settings' );
		if ( empty( $options['api_key'] ) || empty( $options['api_secret'] ) ) {
			$cls = esc_attr( \FlexMLS\Admin\ApiMessages::admin_alert_class( 'warning', $for_about_wrap ) );
			printf(
				'<div class="%3$s">
						<p>You must enter your Flexmls&reg; API Credentials. <a href="%1$s">Click here</a> to enter your API credentials, or <a href="%2$s">contact Flexmls&reg; support</a>.</p>
					</div>',
				admin_url( 'admin.php?page=fmc_admin_intro' ),
				admin_url( 'admin.php?page=fmc_admin_intro&tab=support' ),
				$cls
			);
			return;
		}
		if ( isset( $_GET['fmc_connection_retry'] ) && is_string( $_GET['fmc_connection_retry'] ) ) {
			$retry_flag = sanitize_key( wp_unslash( $_GET['fmc_connection_retry'] ) );
			if ( 'success' === $retry_flag ) {
				$cls = esc_attr( \FlexMLS\Admin\ApiMessages::admin_alert_class( 'success', $for_about_wrap, ! $for_about_wrap ) );
				echo '<div class="' . $cls . '"><p>' . esc_html__( 'Flexmls IDX: Connection retry succeeded.', 'fmcdomain' ) . '</p></div>';
			} elseif ( 'fail' === $retry_flag ) {
				$cls = esc_attr( \FlexMLS\Admin\ApiMessages::admin_alert_class( 'error', $for_about_wrap, ! $for_about_wrap ) );
				echo '<div class="' . $cls . '"><p>' . esc_html__( 'Flexmls IDX: Connection retry failed. Check the message below or try again later.', 'fmcdomain' ) . '</p></div>';
			}
		}

		// GetMyAccount() at plugin load sets last_error on $fmc_api; generate_auth_token() uses a separate Core instance
		// and may still return a cached session token — so 1010/1015 from my/account (etc.) never reached the notice path.
		global $fmc_api;
		$fmc_err_code = ( isset( $fmc_api ) && isset( $fmc_api->last_error_code ) ) ? (int) $fmc_api->last_error_code : 0;
		$fmc_err_mess = ( isset( $fmc_api ) && isset( $fmc_api->last_error_mess ) ) ? (string) $fmc_api->last_error_mess : '';
		\FlexMLS\Admin\ConnectionPause::ensure_pause_from_bootstrap_1015( $fmc_err_code, $fmc_err_mess );

		$cooling_down   = \FlexMLS\Admin\ConnectionPause::should_block_auto_session( 'auto' );
		$pause_state    = \FlexMLS\Admin\ConnectionPause::get_state();
		$pause_is_1015  = $cooling_down && isset( $pause_state['api_code'] ) && 1015 === (int) $pause_state['api_code'];
		if ( $cooling_down ) {
			\FlexMLS\Admin\ApiMessages::echo_connection_paused_notice( $pause_state, $for_about_wrap );
		}

		if ( 1010 === $fmc_err_code || 1015 === $fmc_err_code ) {
			if ( 1015 === $fmc_err_code && $pause_is_1015 ) {
				return;
			}
			\FlexMLS\Admin\ApiMessages::echo_admin_api_error_notice( $fmc_err_code, $fmc_err_mess, true, $for_about_wrap );
			return;
		}

		$SparkAPI   = new \SparkAPI\Core();
		$auth_token = $SparkAPI->generate_auth_token();
		if ( false === $auth_token && ! $cooling_down ) {
			$last_error_code = isset( $SparkAPI->last_error_code ) ? $SparkAPI->last_error_code : null;
			$last_error_mess = isset( $SparkAPI->last_error_mess ) ? $SparkAPI->last_error_mess : null;
			\FlexMLS\Admin\ApiMessages::echo_admin_api_error_notice( $last_error_code, $last_error_mess, true, $for_about_wrap );
		} elseif ( $auth_token ) {
			if ( isset( $fmc_api ) && is_object( $fmc_api ) ) {
				\FlexMLS\Admin\ApiMessages::maybe_echo_wordpress_idx_entitlement_notice( $fmc_api, $for_about_wrap );
			}
			if ( ! isset( $options['google_maps_api_key'] ) || empty( $options['google_maps_api_key'] ) ) {
				$cls = esc_attr( \FlexMLS\Admin\ApiMessages::admin_alert_class( 'warning', $for_about_wrap, ! $for_about_wrap ) );
				printf(
					'<div class="%3$s">
								<p>You have not entered a Google Maps API Key. It&#8217;s not required for the Flexmls&reg; IDX plugin, but maps will not show on your site without a Google Maps API key. <a href="%1$s">Click here</a> to enter your Google Map API Key, or <a href="%2$s" target="_blank">generate a Google Map API Key here</a>.</p>
							</div>',
					admin_url( 'admin.php?page=fmc_admin_settings&tab=gmaps' ),
					'https://developers.google.com/maps/documentation/javascript/get-api-key#get-an-api-key',
					$cls
				);
			}
		}
	}

	function admin_notices(){
		if( current_user_can( 'manage_options' ) ){
			$required_php_extensions = array();
			if( !extension_loaded( 'curl' ) ){
				$required_php_extensions[] = 'cURL';
			}
			if( !extension_loaded( 'bcmath' ) ){
				$required_php_extensions[] = 'BC Math';
			}
			if( count( $required_php_extensions ) ){
				printf(
					'<div class="notice notice-error"><p>Your website&#8217;s server does not have <em>' . implode( '</em> or <em>', $required_php_extensions ) . '</em> enabled which %1$s required for the Flexmls&reg; IDX plugin. Please contact your webmaster and have %2$s enabled on your website hosting plan.</p></div>',
					_n( 'is', 'are', count( $required_php_extensions ) ),
					_n( 'this extension', 'these extensions', count( $required_php_extensions ) )
				);
			}
			if ( self::is_fmc_admin_intro_screen() ) {
				return;
			}
			$this->render_fmc_api_connection_notices();
		}
	}

	function parse_query(){
		global $wp_query;
		if( isset( $wp_query->query_vars[ 'oauth_tag' ] ) ){
			if( 'oauth-login' == $wp_query->query_vars[ 'oauth_tag' ] ){
				$fmc_settings = get_option( 'fmc_settings' );
				$fmc_api_portal = new flexmlsConnectPortalUser( $fmc_settings[ 'oauth_key' ], $fmc_settings[ 'oauth_secret' ] );
				//$OAuth = new \SparkAPI\OAuth();
				//$OAuth->do_login();
			}
			if( 'oauth-logout' == $wp_query->query_vars[ 'oauth_tag' ] ){
				\SparkAPI\OAuth::log_out();
				
				$redirect_url = home_url();
				if( isset( $_GET[ 'redirect' ] ) ){
					$requested_redirect = esc_url_raw( $_GET[ 'redirect' ] );
					
					if( !empty( $requested_redirect ) ){

						if( strpos( $requested_redirect, '/' ) === 0 && strpos( $requested_redirect, '//' ) !== 0 ){

							$redirect_url = home_url( $requested_redirect );
						} else {

							$validated_url = wp_validate_redirect( $requested_redirect, home_url() );
							if( $validated_url !== false ){
								$redirect_url = $validated_url;
							}
						}
					}
				}
				
				// Use WordPress safe redirect function
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Send X-Flexmls-IDX: idx header on IDX page responses so WAFs can allowlist verified search engine bots for IDX paths.
	 * See docs/search-engine-bot-whitelisting.md for customer guidance.
	 */
	function send_idx_page_header() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( $request_uri === '' ) {
			return;
		}
		$fmc_settings = get_option( 'fmc_settings' );
		$permabase = is_array( $fmc_settings ) && isset( $fmc_settings['permabase'] ) ? $fmc_settings['permabase'] : 'idx';
		$permabase = preg_quote( $permabase, '/' );
		if ( preg_match( '/\/' . $permabase . '\//', $request_uri ) || preg_match( '/\/portal\//', $request_uri ) ) {
			header( 'X-Flexmls-IDX: idx' );
		}
	}

	/**
	 * Prevent WordPress from redirecting IDX URLs
	 * This prevents WordPress from redirecting /idx/search/ to /search-2/ etc.
	 */
	function prevent_idx_redirects( $redirect_url, $requested_url ) {
		$fmc_settings = get_option( 'fmc_settings' );
		$permabase = isset( $fmc_settings['permabase'] ) ? $fmc_settings['permabase'] : 'idx';
		
		// Check if the requested URL contains our permabase
		if ( strpos( $requested_url, '/' . $permabase . '/' ) !== false ) {
			// Don't redirect IDX URLs - let them be handled by our rewrite rules
			return false;
		}
		
		return $redirect_url;
	}

	public static function plugin_activate(){
		// Prevent any output during activation from triggering "unexpected output" on the plugins screen.
		ob_start();

		$is_fresh_install = false;
		if( false === get_option( 'fmc_settings' ) ){
			$is_fresh_install = true;
		}
		\FlexMLS\Admin\Update::set_minimum_options( $is_fresh_install );

		// Use nginx-compatible rewrite rule handling
		if( \FlexMLS\Admin\NginxCompatibility::is_nginx() ) {
			// For nginx, we don't flush rewrite rules on activation
			// The rules need to be configured in nginx config file
		} else {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}

		if( false === get_option( 'fmc_plugin_version' ) ){
			add_option( 'fmc_plugin_version', FMC_PLUGIN_VERSION, null, 'no' );
		}

		ob_end_clean();
	}

	public static function plugin_deactivate(){
		$SparkAPI = new \SparkAPI\Core();
		$SparkAPI->clear_cache( true );
		
		// Use nginx-compatible rewrite rule handling
		\FlexMLS\Admin\NginxCompatibility::handle_rewrite_rules();
	}

	public static function plugin_uninstall(){
		$timestamp = wp_next_scheduled( 'flexmls_hourly_cache_cleanup' );
		if( $timestamp ){
			wp_unschedule_event( $timestamp, 'flexmls_hourly_cache_cleanup' );
		}
		$SparkAPI = new \SparkAPI\Core();
		$SparkAPI->clear_cache( true );
		delete_option( 'fmc_cache_version' );
		delete_option( 'fmc_plugin_version' );
		delete_option( 'fmc_settings' );
		
		// Use nginx-compatible rewrite rule handling
		\FlexMLS\Admin\NginxCompatibility::handle_rewrite_rules();
	}

	function rewrite_rules(){
    $fmc_settings = get_option( 'fmc_settings' );
		add_rewrite_rule( 'oauth/callback/?', 'index.php?plugin=flexmls-idx&oauth_tag=oauth-login', 'top' );
		add_rewrite_rule( 'oauth/spark-logout/?', 'index.php?plugin=flexmls-idx&oauth_tag=oauth-logout', 'top' );
		add_rewrite_tag( '%oauth_tag%', '([^&]+)' );

		if ( is_array( $fmc_settings ) && isset( $fmc_settings['permabase'] ) && isset( $fmc_settings['destlink'] ) ) {
			add_rewrite_rule( $fmc_settings['permabase'] . '/([^/]+)?' , 'index.php?plugin=flexmls-idx&fmc_tag=$matches[1]&page_id=' . $fmc_settings['destlink'], 'top' );
			add_rewrite_rule( 'portal/([^/]+)?', 'index.php?plugin=flexmls-idx&fmc_vow_tag=$matches[1]&page_id=' . $fmc_settings['destlink'], 'top' );
		}
		add_rewrite_tag( '%fmc_tag%', '([^&]+)' );
		add_rewrite_tag( '%fmc_vow_tag%', '([^&]+)' );
		
		// Use nginx-compatible rewrite rule handling
		\FlexMLS\Admin\NginxCompatibility::handle_rewrite_rules();
	}

	function session_start(){
		//self::write_log( json_decode( $_COOKIE[ 'spark_oauth' ] ) );
		/*
		if( !session_id() ){
			session_start();
		}
		*/
		$SparkAPI = new \SparkAPI\Core();
		$fmc_plugin_version = get_option( 'fmc_plugin_version' );
		if( false === $fmc_plugin_version || version_compare( $fmc_plugin_version, FMC_PLUGIN_VERSION, '<' ) ){
			\FlexMLS\Admin\Update::set_minimum_options();
			$did_clear_cache = $SparkAPI->clear_cache( true );
			update_option( 'fmc_plugin_version', FMC_PLUGIN_VERSION, 'no' );
		}
		if( !wp_next_scheduled( 'flexmls_hourly_cache_cleanup' ) ){
			wp_schedule_event( time(), 'hourly', 'flexmls_hourly_cache_cleanup');
		}
		$auth_token = $SparkAPI->generate_auth_token();

		global $listings_per_page;
		if( isset( $_GET[ 'Limit' ] ) ){
			$listings_per_page = intval( $_GET[ 'Limit' ] );
			if( !headers_sent() ){
				setcookie( 'spark_listings_per_page', (string) $listings_per_page, array(
					'expires' => time() + 30 * DAY_IN_SECONDS,
					'path' => '/',
					'samesite' => 'Lax'
				) );
			}
		} elseif( isset( $_COOKIE[ 'spark_listings_per_page' ] ) ){
			$listings_per_page = intval( $_COOKIE[ 'spark_listings_per_page' ] );
		} else {
			$listings_per_page = 10;
		}

		global $listings_orderby;
		if( isset( $_GET[ 'OrderBy' ] ) ){
			$listings_orderby = sanitize_text_field( $_GET[ 'OrderBy' ] );
			if( !headers_sent() ){
				setcookie( 'spark_listings_orderby', $listings_orderby, array(
					'expires' => time() + 30 * DAY_IN_SECONDS,
					'path' => '/',
					'samesite' => 'Lax'
				) );
			}
		} elseif( isset( $_COOKIE[ 'spark_listings_orderby' ] ) ){
			$listings_orderby = $_COOKIE[ 'spark_listings_orderby' ];
		} else {
			$listings_orderby = '-ListPrice';
		}
	}

	function widgets_init(){
		global $fmc_widgets;
		$SparkAPI = new \SparkAPI\Core();
		$auth_token = $SparkAPI->generate_auth_token();

		if( $auth_token ){
			register_widget( '\\FlexMLS\\Widgets\\LeadGeneration' );
		}

		// This will come out soon once all of the widgets have been
		// rebuilt as native WordPress widgets and called using
		// register_widget above.
		if( $auth_token && $fmc_widgets ){
			foreach( $fmc_widgets as $class => $wdg ){
				if( file_exists( FMC_PLUGIN_DIR . 'components/' . $wdg[ 'component' ] ) ){
					require_once( FMC_PLUGIN_DIR . 'components/' . $wdg[ 'component' ] );
					// All widgets require a "key" or auth token so this can be removed
					/*
					$meets_key_reqs = false;
					if ($wdg['requires_key'] == false || ($wdg['requires_key'] == true && flexmlsConnect::has_api_saved())) {
						$meets_key_reqs = true;
					}
					*/
					if( class_exists( $class, false ) && true == $wdg[ 'widget' ] ){
						register_widget( $class );
					}
					if( false == $wdg[ 'widget' ] ){
						new $class();
					}
				}
			}
		}
	}

	static function write_log( $log, $title = 'Flexmls Log Item' ){
		error_log( '---------- ' . $title . ' ----------' );
		if( is_array( $log ) || is_object( $log ) ){
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}

}
$FlexMLS_IDX = new FlexMLS_IDX();

register_activation_hook( __FILE__, array( 'FlexMLS_IDX', 'plugin_activate' ) );
register_deactivation_hook( __FILE__, array( 'FlexMLS_IDX', 'plugin_deactivate' ) );
register_uninstall_hook( __FILE__, array( 'FlexMLS_IDX', 'plugin_uninstall' ));

/*
* Define widget information
*/

global $fmc_widgets;
$fmc_widgets = array(
    'fmcMarketStats' => array(
        'component' => 'market-statistics.php',
        'title' => "Flexmls&reg;: Market Statistics",
        'description' => "Show market statistics on your blog",
        'requires_key' => true,
        'shortcode' => 'market_stats',
        'max_cache_time' => 0,
        'widget' => true
        ),
    'fmcPhotos' => array(
        'component' => 'photos.php',
        'title' => "Flexmls&reg;: IDX Slideshow",
        'description' => "Show photos of selected listings",
        'requires_key' => true,
        'shortcode' => 'idx_slideshow',
        'max_cache_time' => 0,
        'widget' => true
        ),
    'fmcSearch' => array(
        'component' => 'v2/search.php',
        'title' => "Flexmls&reg;: IDX Search",
        'description' => "Allow users to search for listings",
        'requires_key' => true,
        'shortcode' => 'idx_search',
        'max_cache_time' => 0,
        'widget' => true
        ),
    'fmcLocationLinks' => array(
        'component' => 'location-links.php',
        'title' => "Flexmls&reg;: 1-Click Location Searches",
        'description' => "Allow users to view listings from a custom search narrowed to a specific area",
        'requires_key' => true,
        'shortcode' => 'idx_location_links',
        'max_cache_time' => 0,
        'widget' => true
        ),
    'fmcIDXLinksWidget' => array(
        'component' => 'idx-links.php',
        'title' => "Flexmls&reg;: 1-Click Custom Searches",
        'description' => "Share popular searches with your users",
        'requires_key' => true,
        'shortcode' => 'idx_custom_links',
        'max_cache_time' => 0,
        'widget' => true
        ),
    /*
    'fmcLeadGen' => array(
        'component' => 'lead-generation.php',
        'title' => "Flexmls&reg;: Contact Me Form",
        'description' => "Allow users to share information with you",
        'requires_key' => true,
        'shortcode' => 'lead_generation',
        'max_cache_time' => 0,
        'widget' => true
        ),
       */
    /*
    'fmcNeighborhoods' => array(
        'component' => 'neighborhoods.php',
        'title' => "Flexmls&reg;: Neighborhood Page",
        'description' => "Create a neighborhood page from a template",
        'requires_key' => true,
        'shortcode' => 'neighborhood_page-hold',
        'max_cache_time' => 0,
        'widget' => false
        ),
    */
    'fmcListingDetails' => array(
        'component' => 'listing-details.php',
        'title' => "Flexmls&reg;: IDX Listing Details",
        'description' => "Insert listing details into a page or post",
        'requires_key' => true,
        'shortcode' => 'idx_listing_details',
        'max_cache_time' => 0,
        'widget' => false
        ),
    'fmcSearchResults' => array(
        'component' => 'v2/search-results.php',
        'title' => "Flexmls&reg;: IDX Listing Summary",
        'description' => "Insert a summary of listings into a page or post",
        'requires_key' => true,
        'shortcode' => 'idx_listing_summary',
        'max_cache_time' => 0,
        'widget' => false
        ),
    /*The agent search widget is only available to Offices and Mls's (not of usertype member)*/
    'fmcAgents' => array(
        'component' => 'fmc-agents.php',
        'title' => "Flexmls&reg;: IDX Agent List",
        'description' => "Insert agent information into a page or post",
        'requires_key' => true,
        'shortcode' => 'idx_agent_search',
        'max_cache_time' => 0,
        'widget' => false
        ),
    'fmcAccount' => array(
        'component' => 'my-account.php',
        'title' => "Flexmls&reg;: Log in",
        'description' => "Portal Login/Registration",
        'requires_key' => true,
        'shortcode' => 'idx_portal_login',
        'max_cache_time' => 0,
        'widget' => true
        ),
    );

global $fmc_widgets_integration;
$fmc_widgets_integration = $fmc_widgets;

$fmc_widgets_integration['fmcLeadGen'] = array(
	'component' => 'lead-generation.php',
	'title' => "Flexmls&reg;: Contact Me Form",
	'description' => "Allow users to share information with you",
	'requires_key' => true,
	'shortcode' => 'lead_generation',
	'max_cache_time' => 0,
	'widget' => true
);
$fmc_widgets_integration['fmcNeighborhoods'] = array(
	'component' => 'neighborhoods.php',
	'title' => "Flexmls&reg;: Neighborhood Page",
	'description' => "Create a neighborhood page from a template",
	'requires_key' => true,
	'shortcode' => 'neighborhood_page-hold',
	'max_cache_time' => 0,
	'widget' => false
);


$fmc_special_page_caught = array(
    'type' => null
);

$options = get_option('fmc_settings');
$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
$api_secret = isset( $options['api_secret'] ) ? $options['api_secret'] : '';
$fmc_api = new flexmlsConnectUser($api_key,$api_secret);

if($options && array_key_exists('oauth_key', $options) && array_key_exists('oauth_secret', $options)) {
  $fmc_api_portal = new flexmlsConnectPortalUser($options['oauth_key'], $options['oauth_secret']);
}

$api_ini_file = $fmc_plugin_dir . '/lib/api.ini';

if (file_exists($api_ini_file)) {
  $local_settings = parse_ini_file($api_ini_file);
  if (array_key_exists('api_base', $local_settings)) {
    $fmc_api->api_base = trim($local_settings['api_base']);
    $fmc_api_portal->api_base = trim($local_settings['api_base']);
  }
}


$fmc_instance_cache = array();


/*
* register the init functions with the appropriate WP hooks
*/
//add_action('widgets_init', array('flexmlsConnect', 'widget_init') );

$active_account_check = $fmc_api->GetMyAccount();

if ( ! empty( $api_key ) && ! empty( $api_secret ) ) {
	\FlexMLS\Admin\ConnectionPause::ensure_pause_from_bootstrap_1015(
		isset( $fmc_api->last_error_code ) ? (int) $fmc_api->last_error_code : 0,
		isset( $fmc_api->last_error_mess ) ? (string) $fmc_api->last_error_mess : ''
	);
}

if ( ! empty( $api_key ) && ! empty( $api_secret ) && ! empty( $active_account_check ) ) {
	\FlexMLS\Admin\ApiMessages::sync_wordpress_idx_entitlement_on_api( $fmc_api );
}

if(!empty($api_key) && !empty($api_secret) && !empty($active_account_check) ){

	$options_int = get_option('fmc_settings');
	$options_integration = [
		'divi' => false,
		'elementor' => false,
		'wpbakery' => false
	];

	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	//------------------------------------
	//Elementor
	if (is_plugin_active('elementor/elementor.php')) {
		$options_integration['elementor'] = true;
		add_action( 'elementor/init', function() {
			require_once plugin_dir_path( __FILE__ ) . 'integration/elementor/index.php';

			\Elementor\Plugin::$instance->elements_manager->add_category('flexmls',
			[
				'title' => 'Flexmls&reg;',
				'icon' => 'fa fa-plug',
			]);

		});
	}
	//WPBackery
	if (is_plugin_active('js_composer/js_composer.php')) {
		$options_integration['wpbakery'] = true;
		require_once plugin_dir_path( __FILE__ ) . 'integration/wpbakery/index.php';
	}

	//-------------------

	$options_int['integration'] = $options_integration;
	update_option('fmc_settings', $options_int);
}

$fmc_admin = new flexmlsConnectSettings;

add_action('init', array('flexmlsConnect', 'initial_init') );


add_action('wp', array('flexmlsConnectPage', 'catch_special_request') );
add_action('wp', array('flexmlsConnect', 'wp_init') );

$fmc_search_results_loaded = false;
require_once plugin_dir_path( __FILE__ ) . 'lib/gutenberg.php';
