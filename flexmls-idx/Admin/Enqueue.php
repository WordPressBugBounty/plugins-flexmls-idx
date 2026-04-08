<?php
namespace FlexMLS\Admin;

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

class Enqueue {

	/**
	 * Script handles for our plugin's own minified assets (add data-no-minify="1" to avoid double minification).
	 *
	 * @var string[]
	 */
	private static $no_minify_script_handles = array(
		'flexmls_admin_script',
		'fmc_connect',
		'fmc_portal',
		'fmc_flexmls_map',
		'chart-umd-js',
		'chartjs-adapter-date-fns-bundle',
		'chartkick-js',
	);

	/**
	 * Style handles for our plugin's own minified assets (add data-no-minify="1" to avoid double minification).
	 *
	 * @var string[]
	 */
	private static $no_minify_style_handles = array(
		'fmc_connect',
		'fmc_connect_frontend',
	);

	static function admin_enqueue_scripts( $hook ){
        $options = get_option( 'fmc_settings' );
		$hooked_pages = array(
			'settings_page_flexmls_connect', // Remove with old options page
			'flexmls-idx_page_fmc_admin_neighborhood',
			'flexmls-idx_page_fmc_admin_settings',
			'post.php',
			'post-new.php',
			'toplevel_page_fmc_admin_intro',
			'widgets.php'
		);
		if( !in_array( $hook, $hooked_pages ) ){
			//return;
		}
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'fmc_jquery_ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.3/themes/ui-lightness/jquery-ui.min.css' );
        if(!isset( $options['select2_turn_off']))
            $options['select2_turn_off'] = 0;

		if($options['select2_turn_off'] !== "admin" && $options['select2_turn_off'] !== "all") {
            wp_enqueue_script('select2-4.0.5', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js');
            wp_enqueue_style('select2-4.0.5', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css');
        }

		$version = ( defined( 'FMC_DEV' ) && FMC_DEV ) ? false : FMC_PLUGIN_VERSION;

		/* Fix error wp-color-picker for WP 5.5
		https://github.com/kallookoo/wp-color-picker-alpha/issues/35#issuecomment-670711991
		*/

		wp_register_script( 'flexmls_admin_script', plugins_url( 'assets/js/admin.js', dirname( __FILE__ ) ),
			array( 'jquery', 'wp-color-picker' ), $version );

		$color_picker_strings = array(
			'clear'            => __( 'Clear', 'flexmls-idx' ),
			'clearAriaLabel'   => __( 'Clear color', 'flexmls-idx' ),
			'defaultString'    => __( 'Default', 'flexmls-idx' ),
			'defaultAriaLabel' => __( 'Select default color', 'flexmls-idx' ),
			'pick'             => __( 'Select Color', 'flexmls-idx' ),
			'defaultLabel'     => __( 'Color value', 'flexmls-idx' ),
		);
		wp_localize_script( 'flexmls_admin_script', 'wpColorPickerL10n', $color_picker_strings );

		wp_enqueue_script('flexmls_admin_script');

		/*---------*/

		wp_enqueue_style( 'fmc_connect', plugins_url( 'assets/css/style_admin.css', dirname( __FILE__ ) ), array(), $version );

		// WordPress about.css sets .about-wrap .notice { display: none } — Credentials lives inside .about-wrap.
		if ( 'toplevel_page_fmc_admin_intro' === $hook ) {
			wp_add_inline_style(
				'fmc_connect',
				'.about-flexmls .flexmls-idx-admin-notice{box-sizing:border-box;margin:12px 0 15px;padding:1px 12px;background:#fff;border:1px solid #c3c4c7;border-left-width:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);}'
				. '.about-flexmls .flexmls-idx-admin-notice p{margin:.65em 0;padding:2px;line-height:1.5;}'
				. '.about-flexmls .flexmls-idx-admin-notice--warning{border-left-color:#dba617;}'
				. '.about-flexmls .flexmls-idx-admin-notice--error{border-left-color:#d63638;}'
				. '.about-flexmls .flexmls-idx-admin-notice--success{border-left-color:#00a32a;}'
			);
		}

		wp_enqueue_style( 'fmc_connect_frontend', plugins_url( 'assets/css/style.css', dirname( __FILE__ ) ), array(), $version );

		wp_localize_script( 'fmc_connect', 'fmcAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'pluginurl' => plugins_url( '', dirname( __FILE__ ) ),
			'nonce' => wp_create_nonce( 'fmc_ajax' ),
		) );

		add_thickbox();
	}

	/* Print the wpColorPickerL10n variable in the footer, to be sure it isn't overwritten by WordPress */
	static function admin_print_footer_scripts() {
		?>
		<script type="text/javascript">
			var wpColorPickerL10n = {
				"clear": <?php echo json_encode( __( 'Clear', 'flexmls-idx' ) ); ?>,
				"clearAriaLabel": <?php echo json_encode( __( 'Clear color', 'flexmls-idx' ) ); ?>,
				"defaultString": <?php echo json_encode( __( 'Default', 'flexmls-idx' ) ); ?>,
				"defaultAriaLabel": <?php echo json_encode( __( 'Select default color', 'flexmls-idx' ) ); ?>,
				"pick": <?php echo json_encode( __( 'Select Color', 'flexmls-idx' ) ); ?>,
				"defaultLabel": <?php echo json_encode( __( 'Color value', 'flexmls-idx' ) ); ?>
			};
		</script>
		<?php
	}

	static function wp_enqueue_scripts(){
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		$options = get_option( 'fmc_settings' );

        $chartkickTurnOff = isset($options['chartkick_turn_off']) ? $options['chartkick_turn_off'] : false;

        if( ! $chartkickTurnOff ) {

            wp_enqueue_script(
                'chart-umd-js',
                plugins_url('assets/js/chart.umd.js', dirname(__FILE__)),
                array(),
                FMC_PLUGIN_VERSION,
                false
            );

            wp_enqueue_script(
                'chartjs-adapter-date-fns-bundle',
                plugins_url('assets/js/chartjs-adapter-date-fns.bundle.js', dirname(__FILE__)),
                array('chart-umd-js'),
                FMC_PLUGIN_VERSION,
                false
            );

            wp_enqueue_script(
                'chartkick-js',
                plugins_url('assets/js/chartkick.js', dirname(__FILE__)),
                array('chart-umd-js', 'chartjs-adapter-date-fns-bundle'),
                FMC_PLUGIN_VERSION,
                false
            );
        }

		// Google Maps: only enqueue when a map is shown on load (listing detail or search with default_view=map).
		// Search results with default_view=list (map closed) load the API when user clicks "Open Map" to avoid billing every page load.
		$google_maps_no_enqueue = 0;
		if( isset( $options[ 'google_maps_no_enqueue' ] ) && 1 == $options[ 'google_maps_no_enqueue' ] ){
			$google_maps_no_enqueue = 1;
		}
		$has_maps_key = isset( $options[ 'google_maps_api_key' ] ) && ! empty( $options[ 'google_maps_api_key' ] ) && 0 === $google_maps_no_enqueue;
		global $fmc_special_page_caught;
		$is_listing_detail = ! empty( $fmc_special_page_caught['type'] ) && $fmc_special_page_caught['type'] === 'listing-details';
		$fmc_connect_deps = array( 'jquery' );
		if ( $has_maps_key && $is_listing_detail ) {
			self::enqueue_google_maps( $options );
			$fmc_connect_deps[] = 'fmc-google-maps-bootstrap';
		}

    if(!isset( $options[ 'select2_turn_off' ]))
        $options[ 'select2_turn_off' ] = 0;
    if($options['select2_turn_off'] !== "user" && $options['select2_turn_off'] !== "all")  {
        wp_enqueue_script('select2-4.0.5', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js');
        wp_enqueue_style('select2-4.0.5', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css');
    }

		wp_enqueue_script( 'fmc_connect', plugins_url( 'assets/js/main.js', dirname( __FILE__ ) ), $fmc_connect_deps, FMC_PLUGIN_VERSION );
		wp_enqueue_script( 'fmc_portal', plugins_url( 'assets/js/portal.js', dirname( __FILE__ ) ), array( 'jquery', 'fmc_connect' ), FMC_PLUGIN_VERSION );

		wp_enqueue_script( 'fmc_connect_flot_resize', '//cdnjs.cloudflare.com/ajax/libs/flot/4.2.2/jquery.flot.resize.min.js', array( 'jquery' ), FMC_PLUGIN_VERSION, true );

		wp_localize_script( 'fmc_connect', 'fmcAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'pluginurl' => plugins_url( '', dirname( __FILE__ ) ),
			'nonce' => wp_create_nonce( 'fmc_ajax' ),
		) );

		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_style( 'fmc_connect', plugins_url( 'assets/css/style.css', dirname( __FILE__ ) ), FMC_PLUGIN_VERSION );

	}

	/**
	 * Enqueue Google Maps API and bootstrap. Call when a map is shown on page load (listing detail or search with default_view=map).
	 *
	 * @param array|null $options Optional. FMC settings. Defaults to get_option( 'fmc_settings' ).
	 */
	static function enqueue_google_maps( $options = null ) {
		if ( $options === null ) {
			$options = get_option( 'fmc_settings' );
		}
		if ( empty( $options['google_maps_api_key'] ) ) {
			return;
		}
		if ( ! empty( $options['google_maps_no_enqueue'] ) ) {
			return;
		}
		$bootstrap = 'window.fmcGmapsQueue=[];window.fmcGmapsWhenReady=function(f){if(window.fmcGmapsLoaded)f();else window.fmcGmapsQueue.push(f);};window.fmcGmapsReady=function(){window.fmcGmapsLoaded=true;window.fmcGmapsQueue.forEach(function(f){f();});window.fmcGmapsQueue=[];};';
		wp_register_script( 'fmc-google-maps-bootstrap', false, array(), null, false );
		wp_enqueue_script( 'fmc-google-maps-bootstrap' );
		wp_add_inline_script( 'fmc-google-maps-bootstrap', $bootstrap, 'before' );
		$maps_url = 'https://maps.googleapis.com/maps/api/js?key=' . $options['google_maps_api_key'] . '&libraries=marker&loading=async&callback=fmcGmapsReady';
		wp_enqueue_script( 'google-maps', $maps_url, array( 'fmc-google-maps-bootstrap' ), null, false );
		wp_script_add_data( 'google-maps', 'async', true );
		if ( ! has_filter( 'script_loader_tag', array( __CLASS__, 'google_maps_script_loader_tag' ) ) ) {
			add_filter( 'script_loader_tag', array( __CLASS__, 'google_maps_script_loader_tag' ), 10, 3 );
		}
	}

	/**
	 * Add async attribute to Google Maps script tag (required for loading=async best practice).
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script src.
	 * @return string
	 */
	static function google_maps_script_loader_tag( $tag, $handle, $src ) {
		if ( 'google-maps' !== $handle ) {
			return $tag;
		}
		if ( strpos( $tag, ' async' ) === false ) {
			$tag = str_replace( ' src', ' async src', $tag );
		}
		return $tag;
	}

	/**
	 * Add data-no-minify="1" to our plugin's script tags so optimization plugins (e.g. WP Rocket) skip minifying them.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script src.
	 * @return string
	 */
	static function script_loader_tag_no_minify( $tag, $handle, $src ) {
		if ( ! in_array( $handle, self::$no_minify_script_handles, true ) ) {
			return $tag;
		}
		if ( strpos( $tag, ' data-no-minify=' ) !== false ) {
			return $tag;
		}
		return str_replace( ' src=', ' data-no-minify="1" src=', $tag );
	}

	/**
	 * Add data-no-minify="1" to our plugin's style tags so optimization plugins (e.g. WP Rocket) skip minifying them.
	 *
	 * @param string $tag    The link tag.
	 * @param string $handle The style handle.
	 * @param string $href   The stylesheet href.
	 * @return string
	 */
	static function style_loader_tag_no_minify( $tag, $handle, $href ) {
		if ( ! in_array( $handle, self::$no_minify_style_handles, true ) ) {
			return $tag;
		}
		if ( strpos( $tag, ' data-no-minify=' ) !== false ) {
			return $tag;
		}
		return str_replace( ' href=', ' data-no-minify="1" href=', $tag );
	}

}
