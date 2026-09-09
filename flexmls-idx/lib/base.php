<?php

class flexmlsConnect {

  /** Display label for StandardFields `BuyerOfficeName` when required in IDX Detail compliance. */
  const LISTING_DETAIL_SELLING_OFFICE_LABEL = 'Selling Office:';

  function __construct() {
  }


	static function initial_init(){
		// Code moved - eventual cleanup
	}

	static function in_dev_mode() {
		if( defined( 'FMC_DEV' ) && FMC_DEV ){
			return true;
		}
		return false;
	}


	static function widget_init(){
		// Load all of the widgets we need for the plugin.
		global $fmc_widgets;
		$SparkAPI = new \SparkAPI\Core();
		$auth_token = $SparkAPI->generate_auth_token();
		if( $auth_token && $fmc_widgets ){
			foreach( $fmc_widgets as $class => $wdg ){
				if( file_exists( FMC_PLUGIN_DIR . 'components/' . $wdg[ 'component' ] ) ){
					require_once( FMC_PLUGIN_DIR . 'components/' . $wdg[ 'component' ] );
					// All widgets require a "key" or auth token so this will be removed
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
		// register where the AJAX calls should be routed when they come in
		add_action('wp_ajax_fmcShortcodeContainer', array('flexmlsConnect', 'shortcode_container') );
	}

  static function wp_init() {
    // handle form submission actions from the plugin
    if ( array_key_exists('fmc_do', $_POST) ) {
      switch($_POST['fmc_do']) {
        case "fmc_search":
          $handle = new fmcSearch();
          $handle->submit_search();
          break;
      }
    }
  }


  static function br_trigger_error($message, $errno) {

    if(isset($_GET['action'])
      && $_GET['action'] == 'error_scrape') {
      echo '<strong>' . $message . '</strong>';
      exit;

    } else {

      trigger_error($message, $errno);

    }
  }


  static function filter_mce_button($buttons) {
    array_push( $buttons, '|', 'fmc_button');
    return $buttons;
  }

  static function filter_mce_location_button($buttons) {
    array_push( $buttons, '|', 'fmc_button_location', 'fmc_button_locations');
    return $buttons;
  }

  static function filter_mce_plugin($plugins) {
    global $fmc_plugin_url;
    $plugins['fmc'] = $fmc_plugin_url . '/assets/js/tinymce_plugin.js';
    return $plugins;
  }

  static function filter_mce_plugin_global_vars() {
    global $fmc_plugin_url;
    ?>
    <script type='text/javascript'>
      var fmcPluginUrl = '<?php echo $fmc_plugin_url; ?>';
      if ( typeof window.fmcAjax === 'undefined' ) { window.fmcAjax = { ajaxurl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', nonce: '<?php echo esc_js( wp_create_nonce( 'fmc_ajax' ) ); ?>' }; }
      if ( typeof window.flexmlsIdxLinksSelect === 'undefined' ) {
        window.flexmlsIdxLinksSelect = <?php echo wp_json_encode(
          array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'fmc_ajax' ),
            'action'  => 'flexmls_idx_links_select2',
            'enabled' => self::idx_links_select2_enabled() ? 1 : 0,
          )
        ); ?>;
      }
    </script>
    <?php
  }

  static function shortcode_container() {
  	/*
    global $fmc_widgets;
    global $fmc_api;

    $fmc_my_type = $fmc_api->GetMyAccount();
    $fmc_my_type = $fmc_my_type['UserType'];

    $return = '';

    $return .= "<div id='fmc_box_body'>";
    $return .= "<ul class='flexmls_connect__widget_menu'>\n";

    foreach ($fmc_widgets as $class => $widg) {
      if ($widg['shortcode'] == 'idx_agent_search' and $fmc_my_type == 'Member')
        continue;

      $short_title = str_replace("FlexMLS&reg;: ", "", $widg['title']);
      $return .= "<li class='flexmls_connect__widget_menu_item'><a class='fmc_which_shortcode' data-connect-shortcode='{$class}' style='cursor:pointer;'>{$short_title}</a></li>\n";
    }
    $return .= "</ul>\n";

    $return .= "<div id='fmc_shortcode_window_content'><p class='first'>please select a widget to the left</p></div>";

    $return .= "</div>";

    $response['title'] = "";
    $response['body'] = $return;
    echo flexmlsJSON::json_encode($response);
    exit;
    */
  }


  // called to put the start of the form on the shortcode generator page
  static function shortcode_header() {
  	return '';
    return "<div class='flexmls-shorcode-generator'><form fmc-shortcode-form='true'>";
  }

  // called to put the end of the form and submit button on the shortcode generator page
  static function shortcode_footer() {
  	return '';
    $return  = "<div class='flexmls-widget-settings-submit-row'>";
    $return .= "<input type='button' class='fmc_shortcode_submit button-primary' value='Insert Widget' />";
    $return .= "</div>";
    $return .= "</form>";
    $return .= "</div>";
    return $return;
  }


  static function clean_spaces_and_trim($value) {
    $value = trim($value);
    // keep looking for sequences of multiple spaces until they no longer exist
    while (preg_match('/\s\s+/', "{$value}")) {
      $value = preg_replace('/\s\s+/', ' ', "{$value}");
    }
    return trim($value);
  }


  static function strip_quotes($value) {
    $value = stripslashes($value);

    if (preg_match('/^\'(.*?)\'$/', $value)) {
      return substr($value, 1, -1);
    }
    else {
      return $value;
    }
  }


  static function widget_not_available(&$api, $detailed = false, $args = false, $settings = false) {
    $return = "";

    if (is_array($args)) {
      $return .= isset($args['before_widget']) ? $args['before_widget'] : '';
      $return .= isset($args['before_title']) ? $args['before_title'] : '';
      $return .= isset($settings['title']) ? $settings['title'] : '';
      $return .= isset($args['after_title']) ? $args['after_title'] : '';
    }

    if ( isset( $api->wordpress_idx_entitlement_blocked ) && true === $api->wordpress_idx_entitlement_blocked ) {
      $message = is_admin()
        ? \FlexMLS\Admin\ApiMessages::widget_wordpress_idx_entitlement_admin_message()
        : \FlexMLS\Admin\ApiMessages::widget_wordpress_idx_subscription_blocked_public_message();
      $return .= $message;
      if (is_array($args)) {
        $return .= isset($args['after_widget']) ? $args['after_widget'] : '';
      }
      return $return;
    }

    $last_error_code = isset($api->last_error_code) ? $api->last_error_code : null;
    if ($last_error_code == 1500) {
      $message = "This widget requires a subscription to Flexmls&reg; IDX in order to work.  <a href=''>Buy Now</a>.";
    }
    elseif ( 1010 === (int) $last_error_code || 1015 === (int) $last_error_code ) {
      $api_msg = isset( $api->last_error_mess ) ? $api->last_error_mess : '';
      $message = \FlexMLS\Admin\ApiMessages::widget_unavailable_message( (int) $last_error_code, $api_msg );
    }
    elseif ($detailed == true) {
      $message = "There was an issue communicating with the Flexmls&reg; IDX API services required to generate this widget.  Please refresh the page or try again later.  Error code: ".$last_error_code;
    }
    else {
      $message = "This widget is temporarily unavailable.  Please refresh the page or try again later.  Error code: ".$last_error_code;
    }

    $return .= $message;

    if (is_array($args)) {
      $return .= isset($args['after_widget']) ? $args['after_widget'] : '';
    }

    return $return;
  }


  static function widget_missing_requirements($widget, $reqs_missing) {

    if (is_user_logged_in()) {
      return "<span style='color:red;'>Flexmls&reg; IDX: {$reqs_missing} are required settings for the {$widget} widget.</span>";
    }
    else {
      return false;
    }

  }


  static function shortcode($attr = array()) {
    global $fmc_api;

    if(empty($fmc_api)){
      return false;
    }

    if (!is_array($attr)) {
      $attr = array();
    }

    if (!array_key_exists('width', $attr)) {
      $attr['width'] = 600;
    }
    if (!array_key_exists('height', $attr)) {
      $attr['height'] = 500;
    }

  /**
	 * Set default IDX link in plugin settings
	 *
	 * @var string $default_idx_link
	 */
  $default_idx_link = flexmlsConnect::get_default_idx_link_url();

  /**
	 * Grabs the URL query parameter
	 *
	 * @var string $query_url
	 */
    $query_url = flexmlsConnect::wp_input_get('url');

    
	 /**
	 * Return $query_url as an associative array
	 *
	 * @var array $query_url_parse
	 */
  $query_url_parse = ( isset($query_url) ) ? parse_url( $query_url ) : '';
   
  
  /**
	 * Verifies Flexmls as the host and sets default IDX link if false
	 *
	 * @var string $show_link
	 */
    
    $show_link = ( isset($query_url_parse['host']) && $query_url_parse['host'] == 'link.flexmls.com' ) ? $query_url : flexmlsConnect::get_default_idx_link_url();

    // links saved or cached before the search widget stopped emitting blank criteria can
    // still arrive malformed, so repair them rather than framing a page flexmls rejects
    $show_link = flexmlsConnect::clean_idx_link($show_link);

    if(strpos($show_link, 'StreetAddress')){
       $show_link = str_replace('StreetAddress', 'streetaddress', $show_link);
    }

    // Encode attributes before wp_kses(): a raw "<" in a query value such as
    // list_price=<500000 is treated as a new tag and the iframe is printed as text.
    $flexmls_iframe = sprintf(
      "<iframe src='%s' width='%s' height='%s' frameborder='0'></iframe>",
      esc_attr( $show_link ),
      esc_attr( $attr['width'] ),
      esc_attr( $attr['height'] )
    );

    $allowed_tags = array(
        'iframe' => array(
          'src'    => true, // Allow src attribute
          'width'  => true, // Allow width
          'height' => true, // Allow height
          'frameborder' => true
        ),
    );

    return wp_kses($flexmls_iframe, $allowed_tags);
    
  }


  static function is_mobile() {

    $mobile_enabled = false;



    // WPTouch: http://wordpress.org/extend/plugins/wptouch/
    global $wptouch_plugin;

    if (is_object($wptouch_plugin)) {
      if ($wptouch_plugin->applemobile == true) {
        $mobile_enabled = true;
      }
    }

    // @todo add more later as deemed necessary

    return $mobile_enabled;

  }


  static function has_api_saved() {
    $options = get_option('fmc_settings');

    if ( empty($options['api_key']) || empty($options['api_secret']) ) {
      return false;
    }
    else {
      return true;
    }

  }

  static function use_default_titles() {
    $options = get_option('fmc_settings');

    if ($options['default_titles'] == true) {
      return true;
    }
    else {
      return false;
    }
  }


  static function get_destination_link() {
    $options = get_option('fmc_settings');
    if ( ! is_array( $options ) || empty( $options['destlink'] ) ) {
      return false;
    }
    return get_permalink( $options['destlink'] );
  }


  static function make_destination_link($link, $as = 'url', $params = array()) {

    $extra_query_string = null;
    if ( count($params) > 0 ) {
      $extra_query_string = http_build_query($params);
    }

    $options = get_option('fmc_settings');

    if (flexmlsConnect::get_destination_pref() == "own") {
      if (empty($extra_query_string)) {
        return $link;
      }
      else {
        return $link . '?' . $extra_query_string;
      }
    }

    if (!empty($options['destlink'])) {

      $permalink = get_permalink($options['destlink']);

      if (empty($permalink)) {
        return $link;
      }

      $return = "";

      $link = urlencode($link);

      if (strpos($permalink, '?') !== false) {
        $return = $permalink . '&' . $as . '=' . $link;
      }
      else {
        $return = $permalink . '?' . $as . '=' . $link;
      }

      if (empty($extra_query_string)) {
        return $return;
      }
      else {
        return $return . '&' . $extra_query_string;
      }

    }
    else {
      if (empty($extra_query_string)) {
        return $link;
      }
      else {
        return $link . '?' . $extra_query_string;
      }
    }

  }


  static function get_destination_window_pref() {
    $fmc_settings = get_option( 'fmc_settings' );
    return ( is_array( $fmc_settings ) && array_key_exists( 'destwindow', $fmc_settings ) ) ? $fmc_settings['destwindow'] : '';
  }

  static function get_destination_pref() {
    $options = new Fmc_Settings;
    return $options->destpref();
  }

  static function get_no_listings_page_number(){
    $options = new Fmc_Settings;
    return $options->listlink();
  }

  static function get_no_listings_pref(){
    $options = new Fmc_Settings;
    return $options->listpref();
  }


  static function get_default_idx_link() {
    global $fmc_api;

    $options = get_option('fmc_settings');

    if (array_key_exists('default_link', $options) && !empty($options['default_link'])) {
      // This link isn't validated. Use the FMC_IDX_Links class instead.
      return $options['default_link'];
    }
    else {
      $api_links = flexmlsConnect::get_all_idx_links();
      return $api_links[0]['LinkId'];
    }

  }

  static function get_idx_link_details($my_link) {
    global $fmc_api;

    $IDXLinks = new \SparkAPI\IDXLinks();
    $api_links = $IDXLinks->get_all_idx_links();

    //$api_links = flexmlsConnect::get_all_idx_links();

    if (is_array($api_links)) {
      foreach ($api_links as $link) {
        if ($link['LinkId'] == $my_link) {
          return $link;
        }
      }
    }

    return false;

  }

  static function get_default_idx_link_url() {
    global $fmc_api;

    $default_link = flexmlsConnect::get_default_idx_link();
    $api_links = flexmlsConnect::get_all_idx_links();

    $valid_links = array();
    foreach ($api_links as $link) {
      $valid_links[$link['LinkId']] = array('Uri' => $link['Uri'], 'Name' => $link['Name']);
    }

    if (array_key_exists($default_link, $valid_links) && array_key_exists('Uri', $valid_links[$default_link])) {
      return $valid_links[$default_link]['Uri'];
    }
    else {
      return "";
    }

  }

  static function remove_starting_equals($val) {
    if (preg_match('/^\=/', $val)) {
      $val = substr($val, 1);
    }
    return $val;
  }


  /*
   * A submitted search value is blank when the visitor typed nothing: either an empty
   * string or nothing but the comparison operators the search widget prefixes to a range.
   */
  static function is_blank_search_value($value) {

    if ( !is_string($value) ) {
      return empty($value);
    }

    return (bool) preg_match('/^[\s<>=,]*$/', $value);

  }


  /*
   * Guarantee an IDX link handed to a SmartFrame is a well formed URL: the query string
   * has to start with "?" and cannot contain parameters without a value.  Without the "?"
   * flexmls reads the whole query string as a path, which is rejected outright once a
   * value contains an encoded space.
   */
  static function clean_idx_link($link) {

    if ( empty($link) || !is_string($link) ) {
      return $link;
    }

    $split_at = strpos($link, '?');
    if ($split_at === false) {
      $split_at = strpos($link, '&');
    }
    if ($split_at === false) {
      return $link;
    }

    $base = substr($link, 0, $split_at);
    $query_string = substr($link, $split_at + 1);

    $keep = array();
    foreach ( explode('&', $query_string) as $pair ) {
      if ($pair === '') {
        continue;
      }

      $parts = explode('=', $pair, 2);
      if ( count($parts) == 2 && flexmlsConnect::is_blank_search_value($parts[1]) ) {
        continue;
      }

      $keep[] = $pair;
    }

    if ( empty($keep) ) {
      return $base;
    }

    return $base . '?' . implode('&', $keep);

  }

  static function is_ie() {

    $this_ua = getenv('HTTP_USER_AGENT');

    if ($this_ua && (strpos($this_ua, 'MSIE') !== false) && (strpos($this_ua, 'Opera') === false) ) {
      return true;
    }
    else {
      return false;
    }

  }

  static function ie_version() {
    preg_match('/MSIE ([0-9]\.[0-9])/', $_SERVER['HTTP_USER_AGENT'], $reg);
    if(!isset($reg[1])) {
      return -1;
    } else {
      return floatval($reg[1]);
    }
  }


	static function clear_temp_cache(){
		$count = get_option( 'fmc_cache_version' );
		if( empty( $count ) ){
			$count = 0;
		}
		$count++;
		update_option( 'fmc_cache_version', $count );
		$spark = new \SparkAPI\Core();
		$spark->clear_cache();
		//flexmlsConnect::garbage_collect_bad_caches();
	}

  static function greatest_fitting_number($num, $slide, $max) {
    if (($num * ($slide + 1)) <= $max) {
      return flexmlsConnect::greatest_fitting_number($num, $slide + 1, $max);
    }
    else {
      return $num * $slide;
    }
  }

  /**
   *
   * Used to calculate the API limit needed to fill as many
   * slides as possible without having any partially filled.
   */
  static function generate_api_limit_value($hor, $ver) {

    // _limit number to shoot for if we can
    $kind_limit = 1;

    // maximum _limit is allowed to be
    $max_limit = 25;

    if (empty($hor)) {
      $hor = 1;
    }
    if (empty($ver)) {
      $ver = 1;
    }

    $total = (int)$hor * (int)$ver;

    if ($total < $kind_limit) {
      return flexmlsConnect::greatest_fitting_number($total, 1, $kind_limit);
    }
    elseif ($total >= $kind_limit && $total <= $max_limit) {
      return $total;
    }
    else {
      return $max_limit;
    }

  }


  static function generate_appropriate_dimensions($hor, $ver) {
    $new_horizontal = $hor;

    if ($new_horizontal > 25) {
      $new_horizontal = 25;
    }

    $initial_total = ($hor * $ver);

    if ($initial_total > 25) {
      $room_to_grow = true;
      $new_vertical = 1;
      while ($room_to_grow) {
        if (($new_horizontal * ($new_vertical + 1)) >= 25) {
          $room_to_grow = false;
        }
        else {
          $new_vertical++;
        }
      }
    }
    else {
      // the grid is fine as-is
      $new_vertical = $ver;
    }

    return array( $new_horizontal, $new_vertical );
  }


  static function parse_location_search_string($location) {
    $locations = array();
    if (!empty($location)) {
        if (preg_match('/\|/', $location)) {
            $locations = explode("|", $location);
        }
        else {
            $locations[] = $location;
        }
    }

    $return = array();

    foreach ($locations as $loc) {
        list($loc_name, $loc_value) = explode("=", $loc, 2);
        list($loc_value, $loc_display) = explode("&", $loc_value);
        $loc_value_nice = preg_replace('/^\'(.*)\'$/', "$1", $loc_value);
        // if there weren't any single quotes, just use the original value
  if (empty($loc_value_nice)) {
    $loc_value_nice = $loc_value;
  }
        $loc_value_nice = flexmlsConnect::remove_starting_equals($loc_value_nice);
        $return[] = array(
    'r' => $loc,
            'f' => $loc_name,
            'v' => $loc_value_nice,
            'l' => $loc_display
        );
    }

    return $return;
  }

  static function cache_turned_on() {
    return true;
  }


  static function get_neighborhood_template_content($page_id = false) {

    $neigh_template_page_id = flexmlsConnect::get_neighborhood_template_id($page_id);

    if (!$neigh_template_page_id) {
      return false;
    }

    $content = get_post($neigh_template_page_id);
    return $content->post_content;

  }


	static function get_neighborhood_template_id( $page_id = false ){
		if( !$page_id || 'default' == $page_id ){
			$options = get_option( 'fmc_settings' );
			$neigh_template_page_id = $options[ 'neigh_template' ];
		} else {
			$neigh_template_page_id = $page_id;
		}
		if( empty( $neigh_template_page_id ) ){
			return false;
		}
		return $neigh_template_page_id;
	}

  static function special_location_tag_text() {
    return "";
  }


  static function wp_input_get($key) {

    if (isset($_GET) && is_array($_GET) && array_key_exists($key, $_GET)) {
      return stripslashes($_GET[$key]);
    }
    else {
      // parse the query string manually.  some kind of internal redirect
      // or protection is keeping PHP from knowing what $_GET is

      $full_requested_url = (preg_match('/^HTTP\//', $_SERVER['SERVER_PROTOCOL'])) ? "http" : "https";
      $full_requested_url .= "://" . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');

      $query_string = parse_url($full_requested_url, PHP_URL_QUERY);
      $query_parts = explode("&", $query_string ?? '');
      $manual = array();
      foreach ($query_parts as $p) {
        if ( $p === '' || false === strpos( $p, '=' ) ) {
          continue;
        }
        list( $k, $v ) = explode( '=', $p, 2 );
        if ( array_key_exists( $k, $manual ) ) {
          $manual[ $k ] .= ',' . urldecode( $v );
        } else {
          $manual[ $k ] = urldecode( $v );
        }
      }
      if ( array_key_exists($key, $manual) ) {
        return $manual[$key];
      }
      else {
        return null;
      }
    }
  }


  static function wp_input_post($key) {
    if (isset($_POST) && is_array($_POST) && array_key_exists($key, $_POST)) {
      return stripslashes($_POST[$key]);
    }
    else {
      return null;
    }
  }


  static function wp_input_get_post($key) {
    $via_post = self::wp_input_post($key);
    if ($via_post !== null) {
      return $via_post;
    }

    $via_get = self::wp_input_get($key);
    if ($via_get !== null) {
      return $via_get;
    }

    return null;
  }

  static function send_notification() {

    $options = get_option('fmc_settings');

    if (!array_key_exists('contact_notifications', $options)) {
      return true;
    }
    elseif ($options['contact_notifications'] === true) {
      return true;
    }
    else {
      return false;
    }

  }


  static function format_listing_street_address($data) {
    if ( ! is_array( $data ) ) {
      return array( '', '', '' );
    }
    $listing = isset( $data['StandardFields'] ) && is_array( $data['StandardFields'] ) ? $data['StandardFields'] : array();
    $first_line_address = ( isset($listing['UnparsedFirstLineAddress']) && flexmlsConnect::is_not_blank_or_restricted($listing['UnparsedFirstLineAddress'])) ? $listing['UnparsedFirstLineAddress'] : "";
    $second_line_address = "";

    if ( isset($listing['City']) && flexmlsConnect::is_not_blank_or_restricted($listing['City']) ) {
      $second_line_address .= "{$listing['City']}, ";
    }

    if ( isset($listing['StateOrProvince']) && flexmlsConnect::is_not_blank_or_restricted($listing['StateOrProvince']) ) {
      $second_line_address .= "{$listing['StateOrProvince']} ";
    }

    if ( isset($listing['PostalCode']) && flexmlsConnect::is_not_blank_or_restricted($listing['PostalCode']) ) {
      $second_line_address .= "{$listing['PostalCode']}";
    }

    $second_line_address = str_replace("********", "", $second_line_address);
    $second_line_address = flexmlsConnect::clean_spaces_and_trim($second_line_address);

    $one_line_address = (!empty($first_line_address)) ? $first_line_address . ", " : "";
    $one_line_address .= "{$second_line_address}";
    $one_line_address = flexmlsConnect::clean_spaces_and_trim($one_line_address);

    return array($first_line_address, $second_line_address, $one_line_address);

  }

  static function is_not_blank_or_restricted($val) {
    if (!is_array($val)) {
      $val = trim((string) $val);
      return ( empty($val) or $val == "********") ? false : true;
    } else {
      $result = true;
      foreach ($val as $v) {
        if (!flexmlsConnect::is_not_blank_or_restricted($v)){
          $result = false;
        }
      }
      return $result;
    }
  }

  static function generate_nice_urls() {
    global $wp_rewrite;
    return $wp_rewrite->using_mod_rewrite_permalinks();
  }

  static function make_nice_tag_url($tag, $params = array(), $type='fmc_tag') {

    $query_string = null;
    if ( count($params) > 0 ) {
      $query_string .= '?'. http_build_query($params);
    }
    if (flexmlsConnect::generate_nice_urls()) {
      if ($type == 'fmc_tag'){
        $options = get_option('fmc_settings');
        return get_home_url() . '/' . $options['permabase'] . '/' . $tag . '/' . $query_string;
      }
      elseif ($type == 'fmc_vow_tag'){
        return get_home_url() . '/' . 'portal' . '/' . $tag . '/' . $query_string;
      }
    }
    else {
      return flexmlsConnect::make_destination_link($tag, $type, $params);
    }
  }

  static function make_nice_address_url($data, $params = array(), $type='fmc_tag') {
    if ( ! is_array( $data ) || ! isset( $data['StandardFields']['ListingId'] ) ) {
      return '';
    }
    $address = flexmlsConnect::format_listing_street_address($data);
    $return = ( ! empty( $address ) && isset( $address[0], $address[1] ) ) ? $address[0] . '-' . $address[1] . '-mls_' . $data['StandardFields']['ListingId'] : '';
    $return = preg_replace('/[^\w]/', '-', $return);

    while (preg_match('/\-\-/', $return)) {
      $return = preg_replace('/\-\-/', '-', $return);
    }

    $return = preg_replace('/^\-/', '', $return);
    $return = preg_replace('/\-$/', '', $return);

    if (flexmlsConnect::generate_nice_urls()) {
      $options = get_option('fmc_settings');

      if (count($params) > 0) {
        $return .= '?'. http_build_query($params);
      }

      if ($type == 'fmc_vow_tag'){
        return get_home_url() . '/' . 'portal' . '/' . $return;
      }
      else {
        return get_home_url() . '/' . $options['permabase'] . '/' . $return;
      }
    }
    else {
      return flexmlsConnect::make_destination_link($return, $type, $params);
    }
  }

  static function make_nice_address_title($data) {
    if ( ! is_array( $data ) || ! isset( $data['StandardFields']['ListingId'] ) ) {
      return '';
    }
    $address = flexmlsConnect::format_listing_street_address($data);
    $return = ( ! empty( $address ) && isset( $address[0], $address[1] ) ) ? $address[0] . ', ' . $address[1] . ' (MLS# ' . $data['StandardFields']['ListingId'] . ')' : '';
    $return = flexmlsConnect::clean_spaces_and_trim($return);

    return $return;
  }

  static function format_date ($format,$date){
    //Format Last Modified Date
    //search for "php date" for format specs
    $LastModifiedDate= "";
    if (flexmlsConnect::is_not_blank_or_restricted($date)){
      $Seconds = strtotime($date);
      $LastModifiedDate=date($format,$Seconds);
    }
    return $LastModifiedDate;
  }


  static function make_api_formatted_value($value, $type) {

    $formatted_value = null;

    if ($type == 'Character') {
      $formatted_value = (string) "'". addslashes( trim( trim($value) ,"'") ) ."'";
    }
    elseif ($type == 'Integer') {
      $formatted_value = (int) $value;
    }
    elseif ($type == 'Decimal') {
      $formatted_value = number_format($value, 2, '.', '');
    }
    elseif ($type == 'Date' || $type == 'Datetime') {
      $formatted_value = trim($value); // no single quotes
    }
    else { }

    return $formatted_value;

  }

  static function NAR_broker_attribution($sf) {
    global $fmc_api;
    $AttributionContact = null;

    $GetListingOfficeInfo = array();
    $GetListingAgentInfo = array();
    if ( isset( $sf['ListOfficeId'] ) ) {
      $office_info = $fmc_api->GetAccount( $sf['ListOfficeId'] );
      $GetListingOfficeInfo = is_array( $office_info ) ? $office_info : array();
    }
    if ( isset( $sf['ListAgentId'] ) ) {
      $agent_info = $fmc_api->GetAccount( $sf['ListAgentId'] );
      $GetListingAgentInfo = is_array( $agent_info ) ? $agent_info : array();
    }

    if ( isset( $sf['AttributionContact'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['AttributionContact'] ) ) {
      $AttributionContact = $sf['AttributionContact'];
    } 
    elseif ( isset( $GetListingAgentInfo['AttributionContact'] ) && flexmlsConnect::is_not_blank_or_restricted( $GetListingAgentInfo['AttributionContact'] ) ) {
      $AttributionContact = $GetListingAgentInfo['AttributionContact'];
    } 
    elseif ( isset( $GetListingOfficeInfo['AttributionContact'] ) && flexmlsConnect::is_not_blank_or_restricted( $GetListingOfficeInfo['AttributionContact'] ) ) {
      $AttributionContact = $GetListingOfficeInfo['AttributionContact'];
    }

    if( isset( $AttributionContact ) ){
      //$return = "<div class='listing-req'>Broker Attribute: " . $AttributionContact . "</div>\n <hr /> \n";
      $return = $AttributionContact;
    } 
    else {
      $return = false;
    }

    return $return;

  } 

  static function fbs_products_branding_link() {
    $branding_base_url = "https://fbsproducts.com/?";
    $branding_url_utm = urlencode("utm_source=wp_plugin&utm_medium=software&utm_campaign=powered_by&utm_content=powered_by");
    $branding_text = "Search powered by FBS Products";
    $return = '<a href="' . $branding_base_url . $branding_url_utm . '" target="_blank">' . $branding_text .  '</a>';

    return $return;
  }

  static function get_big_idx_disclosure_text() {
    global $fmc_api;

    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || empty( $api_system_info['Configuration'][0]['IdxDisclaimer'] ) ) {
      return '';
    }
    return trim( $api_system_info['Configuration'][0]['IdxDisclaimer'] );
  }

  static function add_contact($content){
        global $fmc_api;
     return ($fmc_api->AddContact($content, flexmlsConnect::send_notification()));
  }

  static function message_me($subject, $body, $from_email){
    global $fmc_api;
    $my_account = $fmc_api->GetMyAccount();
    if ( ! is_array( $my_account ) || ! isset( $my_account['Id'] ) ) {
      return false;
    }
    $sender = $fmc_api->GetContacts(null, array("_select" => "Id", "_filter" => "PrimaryEmail Eq '{$from_email}'"));
    if ( ! is_array( $sender ) || ! isset( $sender[0]['Id'] ) ) {
      return false;
    }
    return $fmc_api->AddMessage(array(
      'Type'       => 'General',
      'Subject'    => $subject,
      'Body'       => $body,
      'Recipients' => array($my_account['Id']),
      'SenderId'   => $sender[0]['Id']
    ));
  }

  /**
   * New York state listings use alternate courtesy wording and a different disclosure layout.
   */
  static function listing_detail_uses_ny_state_rules( $sf ) {
    return is_array( $sf ) && isset( $sf['StateOrProvince'] ) && $sf['StateOrProvince'] === 'NY';
  }

  /**
   * Sold or closed listing: used with Behavior "contact buttons on sold/closed" to hide lead CTAs.
   */
  static function listing_is_sold_or_closed( $sf ) {
    if ( ! is_array( $sf ) ) {
      return false;
    }
    $ms = isset( $sf['MlsStatus'] ) ? (string) $sf['MlsStatus'] : '';
    if ( 'Closed' === $ms || strcasecmp( $ms, 'Sold' ) === 0 ) {
      return true;
    }
    if ( isset( $sf['StandardStatus'] ) && 'Closed' === (string) $sf['StandardStatus'] ) {
      return true;
    }
    return false;
  }

  /**
   * Whether to show lead CTAs (contact, ask, schedule) for this listing per Behavior and status.
   *
   * @param array      $sf            Standard fields for the listing.
   * @param array|null $fmc_settings  fmc_settings option, or null to load from the database.
   */
  static function should_show_listing_lead_ctas( $sf, $fmc_settings = null ) {
    if ( $fmc_settings === null ) {
      $fmc_settings = get_option( 'fmc_settings', array() );
    }
    if ( ! is_array( $fmc_settings ) ) {
      $fmc_settings = array();
    }
    if ( ! isset( $fmc_settings['listing_detail_contact_on_closed'] ) || 1 === (int) $fmc_settings['listing_detail_contact_on_closed'] ) {
      return true;
    }
    return ! self::listing_is_sold_or_closed( $sf );
  }

  /**
   * Label for the listing office line (search summary and listing detail).
   */
  static function listing_detail_list_office_label( $sf ) {
    return self::listing_detail_uses_ny_state_rules( $sf ) ? 'Listing Courtesy of' : 'Listing Office:';
  }

  /**
   * Same as listing_detail_list_office_label with trailing space for legacy v1 markup concatenation.
   */
  static function listing_detail_list_office_label_for_v1_markup( $sf ) {
    return self::listing_detail_uses_ny_state_rules( $sf ) ? 'Listing Courtesy of ' : 'Listing Office: ';
  }

  static function mls_requires_office_name_in_search_results() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance']
      : array();
    return in_array( 'ListOfficeName', $compList );
  }

  static function mls_requires_agent_name_in_search_results() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance']
      : array();
    return in_array( 'ListAgentName', $compList );
  }

  static function mls_requires_agent_phone_in_search_results() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance']
      : array();

    return in_array( 'ListAgentPhone', $compList );
  }

  static function mls_requires_agent_email_in_search_results() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Summary']['DisplayCompliance']
      : array();

    return in_array( 'ListAgentEmail', $compList );
  }

  // Similar methods for Detail view
  static function mls_requires_agent_name_in_listing_details() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance']
      : array();

    return in_array( 'ListAgentName', $compList );
  }

  static function mls_requires_agent_phone_in_listing_details() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance']
      : array();

    // Check for both ListMemberPhone (detail view) and ListAgentPhone (fallback)
    return ( in_array( 'ListMemberPhone', $compList ) || in_array( 'ListAgentPhone', $compList ) );
  }

  static function mls_requires_agent_email_in_listing_details() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance']
      : array();

    return in_array( 'ListMemberEmail', $compList );
  }

  static function mls_requires_office_name_in_listing_details() {
    global $fmc_api;
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return false;
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View']['Detail']['DisplayCompliance']
      : array();

    return in_array( 'ListOfficeName', $compList );
  }

  /**
   * Get agent phone with fallback to preferred phone and office phone if agent phone is blank/restricted
   * Uses the same logic for both search results and listing details
   *
   * @param array $sf StandardFields array
   * @param string $context 'search' or 'detail' - for future use if needed
   * @return string Phone number or empty string
   */
  static function get_agent_phone_with_fallback( $sf, $context = 'search' ) {
    // Array of phone fields in priority order
    $phone_fields = array(
      'ListAgentPhone',
      'ListAgentPreferredPhone', 
      'ListOfficePhone'
    );
    
    // Loop through phone fields and return the first valid one
    foreach ( $phone_fields as $field ) {
      if ( isset( $sf[$field] ) && flexmlsConnect::is_not_blank_or_restricted( $sf[$field] ) ) {
        return $sf[$field];
      }
    }
    
    // Return empty string if none are available
    return '';
  }

  static function mls_required_fields_and_values($type, &$record){
    //$type   String   "Summary" | "Detail"
    //$record GetListings(params)[0]
    global $fmc_plugin_url;
    global $fmc_api;
    if ( ! is_array( $record ) || ! isset( $record['StandardFields'] ) || ! is_array( $record['StandardFields'] ) ) {
      return array();
    }
    $sf = $record['StandardFields'];
    $api_system_info = $fmc_api->GetSystemInfo();
    if ( ! is_array( $api_system_info ) || ! isset( $api_system_info['MlsId'] ) ) {
      return array();
    }
    $mlsId = $api_system_info['MlsId'];
    $compList = isset( $api_system_info['DisplayCompliance'][$mlsId]['View'][$type]['DisplayCompliance'] ) && is_array( $api_system_info['DisplayCompliance'][$mlsId]['View'][$type]['DisplayCompliance'] )
      ? $api_system_info['DisplayCompliance'][$mlsId]['View'][$type]['DisplayCompliance']
      : array();

    //Get Adresses
    //Since these fields take a considerable amount of time to get, check if they are required from the compliance list beforehand.
    $OfficeAddress = '';
    if ( in_array( 'ListOfficeAddress', $compList ) && isset( $sf['ListOfficeId'] ) ) {
      $OfficeInfo = $fmc_api->GetAccountsByOffice( $sf['ListOfficeId'] );
      $OfficeAddress = ( is_array( $OfficeInfo ) && isset( $OfficeInfo[0]['Addresses'][0]['Address'] ) ) ? $OfficeInfo[0]['Addresses'][0]['Address'] : '';
    }

    $AgentAddress = '';
    if ( in_array( 'ListMemberAddress', $compList ) && isset( $sf['ListAgentId'] ) ) {
      $AgentInfo = $fmc_api->GetAccount( $sf['ListAgentId'] );
      $AgentAddress = ( is_array( $AgentInfo ) && isset( $AgentInfo['Addresses'][0]['Address'] ) ) ? $AgentInfo['Addresses'][0]['Address'] : '';
    }

    $CoAgentAddress = '';
    if ( in_array( 'CoListAgentAddress', $compList ) && isset( $sf['CoListAgentId'] ) ) {
      $CoAgentInfo = $fmc_api->GetAccount( $sf['CoListAgentId'] );
      $CoAgentAddress = ( is_array( $CoAgentInfo ) && isset( $CoAgentInfo['Addresses'][0]['Address'] ) ) ? $CoAgentInfo['Addresses'][0]['Address'] : '';
    }

    //Names
    $AgentName = "";
    $CoAgentName = "";
    if (
      isset( $sf['ListAgentFirstName'], $sf['ListAgentLastName'] )
      && flexmlsConnect::is_not_blank_or_restricted( $sf['ListAgentFirstName'] )
      && flexmlsConnect::is_not_blank_or_restricted( $sf['ListAgentLastName'] )
    ) {
      $AgentName = "{$sf['ListAgentFirstName']} {$sf['ListAgentLastName']}";
    }

    if (
      isset( $sf['CoListAgentFirstName'], $sf['CoListAgentLastName'] )
      && flexmlsConnect::is_not_blank_or_restricted( $sf['CoListAgentFirstName'] )
      && flexmlsConnect::is_not_blank_or_restricted( $sf['CoListAgentLastName'] )
    ) {
      $CoAgentName = "{$sf['CoListAgentFirstName']} {$sf['CoListAgentLastName']}";
    }

    //Primary Phone Numbers and Extensions
    $ListOfficePhone = "";
    $ListAgentPhone = "";
    $CoListAgentPhone = "";
    if ( isset( $sf['ListOfficePhone'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['ListOfficePhone'] ) ) {
      $ListOfficePhone = $sf['ListOfficePhone'];
      if ( isset( $sf['ListOfficePhoneExt'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['ListOfficePhoneExt'] ) ) {
        $ListOfficePhone .= ' ext. ' . $sf['ListOfficePhoneExt'];
      }
    }

    if ( isset( $sf['ListAgentPreferredPhone'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['ListAgentPreferredPhone'] ) ) {
      $ListAgentPhone = $sf['ListAgentPreferredPhone'];
      if ( isset( $sf['ListAgentPreferredPhoneExt'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['ListAgentPreferredPhoneExt'] ) ) {
        $ListAgentPhone .= ' ext. ' . $sf['ListAgentPreferredPhoneExt'];
      }
    }

    if ( isset( $sf['CoListAgentPreferredPhone'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['CoListAgentPreferredPhone'] ) ) {
      $CoListAgentPhone = $sf['CoListAgentPreferredPhone'];
      if ( isset( $sf['CoListAgentPreferredPhoneExt'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['CoListAgentPreferredPhoneExt'] ) ) {
        $CoListAgentPhone .= ' ext. ' . $sf['CoListAgentPreferredPhoneExt'];
      }
    }


    //format last modified date
    $LastModifiedDate = flexmlsConnect::format_date( 'F - d - Y', $sf['ModificationTimestamp'] ?? '' );

    $logo="";
    // Only set logo if IDXLogo is required in the compliance settings
    if (in_array('IDXLogo', $compList)) {
      if ($type == 'Summary') {
        if (isset($api_system_info['Configuration'][0]['IdxLogoSmall']) && !empty($api_system_info['Configuration'][0]['IdxLogoSmall'])) {
          $logo = $api_system_info['Configuration'][0]['IdxLogoSmall'];
        } else {
          $logo = "IDX";
        }
      }
      elseif ($type == 'Detail') {
        if (isset($api_system_info['Configuration'][0]['IdxLogo']) && !empty($api_system_info['Configuration'][0]['IdxLogo'])) {
          $logo = $api_system_info['Configuration'][0]['IdxLogo'];
        } else {
          $logo = "IDX";
        }
      }
      else {
        $logo = "IDX";
      }
    }

    $listing_office_label = self::listing_detail_list_office_label( $sf );

    //These will be printed in this order.
    $possibleRequired = array(
      "ListOfficeName"  => array( $listing_office_label, $sf['ListOfficeName'] ?? '' ),
      "ListOfficePhone"   => array( "Office Phone:", $ListOfficePhone ),
      "ListOfficeEmail"   => array( "Office Email:", $sf['ListOfficeEmail'] ?? '' ),
      "ListOfficeURL"   => array( "Office Website:", $sf['ListOfficeURL'] ?? '' ),
      "ListOfficeAddress"   => array( "Office Address:", $OfficeAddress ),
      "ListAgentName"   => array( "Listing Agent:", $AgentName ),
      "ListMemberPhone"   => array( "Agent Phone:", $sf['ListAgentPreferredPhone'] ?? '' ),
      "ListMemberEmail"   => array( "Agent Email:", $sf['ListAgentEmail'] ?? '' ),
      "ListMemberURL"   => array( "Agent Website:", $sf['ListAgentURL'] ?? '' ),
      "ListMemberAddress"   => array( "Agent Address:", $AgentAddress ),
      "CoListOfficeName"  => array( "Co Office Name:", $sf['CoListOfficeName'] ?? '' ),
      "CoListOfficePhone" => array( "Co Office Phone:", $sf['CoListOfficePhone'] ?? '' ),
      "CoListOfficeEmail" => array( "Co Office Email:", $sf['CoListOfficeEmail'] ?? '' ),
      "CoListOfficeURL" => array( "Co Office Website:", $sf['CoListOfficeURL'] ?? '' ),
      "CoListOfficeAddress" => array( "Co Office Address:", $CoAgentAddress ),
      "CoListAgentName" => array( "Co Listing Agent:", $CoAgentName ),
      "CoListAgentPhone"  => array( "Co Agent Phone:", $CoListAgentPhone ),
      "CoListAgentEmail"  => array( "Co Agent Email:", $sf['CoListAgentEmail'] ?? '' ),
      "CoListAgentURL"  => array( "Co Agent Webpage:", $sf['CoListAgentURL'] ?? '' ),
      "CoListAgentAddress"  => array("Co Agent Address:",$CoAgentAddress),
      "BuyerOfficeName"     => array( self::LISTING_DETAIL_SELLING_OFFICE_LABEL, isset( $sf['BuyerOfficeName'] ) ? $sf['BuyerOfficeName'] : '' ),
      "ListingUpdateTimestamp"=> array("Last Updated:",$LastModifiedDate),
      "IDXLogo"               => array("LOGO",$logo),//Todo -- Print Logo?
    );
    //var_dump($logo);
    $values= array();

    /*foreach ($compList as $test){
        array_push($values,array($possibleRequired[$test][0],$possibleRequired[$test][1]));
    } */

    foreach ($possibleRequired as $key => $value){
      if (in_array($key, $compList))
        array_push($values, array($value[0], $value[1]));
    }
    return $values;
  }

  static function is_odd($val) {
    return ($val % 2) ? true : false;
  }


  /*
   * Take a value and clean it so it can be used as a parameter value in what's sent to the API.
   *
   * @param string $var Regular string of text to be cleaned
   * @return string Cleaned string
   */
  static function clean_comma_list($var) {
    $var = ( $var !== null && $var !== '' ) ? (string) $var : '';
    $return = "";
    if ( $var !== '' && strpos( $var, ',' ) !== false ) {
      // $var contains a comma so break it apart into a list...
      $list = explode(",", $var);
      // trim the extra spaces and weird characters from the beginning and end of each item in the list...
      $list = array_map('trim', $list);
      // and put it back together as a comma-separated string to be returned
      $return = implode(",", $list);
    }
    else {
      // trim the extra spaces and weird characters from the beginning and end of the string to be returned
      $return = trim($var);
    }
    return $return;
  }

  static function page_slug_tag() {
    global $wp_query;
    return $wp_query->get('fmc_tag');
  }

  static function get_all_idx_links($only_saved_search = false) {
    global $fmc_api;

    $return = array();

    $current_page = 0;
    $total_pages = 1;

    while ($current_page < $total_pages) {

      $current_page++;

      $params = array(
          '_pagination' => 1,
          '_page' => $current_page
      );

      $result = $fmc_api->GetIDXLinks($params);

      if ( is_array($result) ) {
        foreach ($result as $r) {
          if ( ! is_array( $r ) ) {
            continue;
          }
          if ($only_saved_search and !array_key_exists('SearchId', $r) ) {
            // we're only wanting saved search links and this isn't one
            continue;
          }
          $return[] = $r;
        }
      }

      if ( $fmc_api->total_pages == null ) {
        break;
      }
      else {
        $current_page = $fmc_api->current_page;
        $total_pages = $fmc_api->total_pages;
      }
    }

    return $return;
  }

  /**
   * Whether Select2 is allowed in admin (lazy IDX link selects use it).
   */
  static function idx_links_select2_enabled() {
    $options = get_option( 'fmc_settings', array() );
    $v = isset( $options['select2_turn_off'] ) ? $options['select2_turn_off'] : 0;
    return ( 'admin' !== $v && 'all' !== $v );
  }

  /**
   * Load one IDX link row from the API. Widget/shortcode values may be a full numeric LinkId or a base-36 tiny code;
   * only the former works with GetIDXLink() directly — tiny codes must use GetIDXLinkFromTinyId().
   *
   * @param string|int $link_id Raw link identifier from saved settings.
   * @return array<string,mixed>|null
   */
  static function idx_links_resolve_api_row( $link_id ) {
    global $fmc_api;
    if ( ! $fmc_api ) {
      return null;
    }
    $link_id = trim( (string) $link_id );
    if ( '' === $link_id || 'default' === $link_id ) {
      return null;
    }
    // Spark "expanded" LinkIds used in GET idxlinks/{id} are long all-digit strings (see translate_tiny_code()).
    if ( strlen( $link_id ) >= 20 && ctype_digit( $link_id ) ) {
      $row = $fmc_api->GetIDXLink( $link_id );
      return ( is_array( $row ) && ! empty( $row['LinkId'] ) ) ? $row : null;
    }
    // Non-numeric (or short) ids are treated as tiny/base-36 codes. Do not fall back to GetIDXLink( $link_id )
    // — the API returns 400 for tiny strings on idxlinks/{id}, and only adds noise after FromTinyId fails.
    $row = $fmc_api->GetIDXLinkFromTinyId( $link_id );
    return ( is_array( $row ) && ! empty( $row['LinkId'] ) ) ? $row : null;
  }

  /**
   * One option row for a pre-selected IDX link (edit mode) without loading the full list.
   *
   * @param string|int|null $link_id LinkId or magic values default / empty.
   * @return array{id:string,text:string}|null
   */
  static function idx_links_select2_prefetch_option( $link_id ) {
    if ( null === $link_id || '' === $link_id || 'default' === $link_id ) {
      return null;
    }
    $raw = trim( (string) $link_id );
    $row = self::idx_links_resolve_api_row( $raw );
    if ( ! is_array( $row ) || empty( $row['LinkId'] ) ) {
      return null;
    }
    // Keep option value as stored (e.g. tiny code) so selected= matches get_field_value(); label from API.
    return array(
      'id'   => $raw,
      'text' => isset( $row['Name'] ) ? (string) $row['Name'] : (string) $row['LinkId'],
    );
  }

  /**
   * Escape a string for use inside a SparkQL character literal (single-quoted), e.g. contains('...').
   *
   * @param string $value Raw user or app string.
   * @return string
   */
  private static function sparkql_escape_char_literal( $value ) {
    $value = str_replace( '\\', '\\\\', $value );
    $value = str_replace( "'", "\\'", $value );
    return $value;
  }

  /**
   * SparkQL _filter for IDX link name substring search (GET idxlinks).
   *
   * @param string $escaped_term Already escaped for single-quoted Spark literal inside contains().
   * @param bool   $only_saved_search Whether to restrict to SavedSearch link type.
   * @return string
   */
  private static function idx_links_select2_build_name_filter( $escaped_term, $only_saved_search ) {
    $name_part = "Name Eq contains('" . $escaped_term . "')";
    if ( $only_saved_search ) {
      return "LinkType Eq 'SavedSearch' And " . $name_part;
    }
    return $name_part;
  }

  /**
   * @param bool $only_saved_search Same semantics as get_all_idx_links( true ): saved search IDX links only.
   * @param string $search_term Non-empty: server-side Spark _filter on Name; empty: normal API pagination.
   * @return array{results:array<int,array{id:string,text:string}>,more:bool}
   */
  static function idx_links_select2_query( $page, $per_page, $only_saved_search, $search_term = '' ) {
    global $fmc_api;
    if ( ! $fmc_api ) {
      return array( 'results' => array(), 'more' => false );
    }
    $page = max( 1, (int) $page );
    $per_page = (int) $per_page;
    if ( $per_page < 5 ) {
      $per_page = 20;
    }
    if ( $per_page > 50 ) {
      $per_page = 50;
    }
    $search_term = is_string( $search_term ) ? trim( $search_term ) : '';
    if ( '' !== $search_term ) {
      if ( function_exists( 'mb_substr' ) ) {
        $search_term = mb_substr( $search_term, 0, 200, 'UTF-8' );
      } else {
        $search_term = substr( $search_term, 0, 200 );
      }
      return self::idx_links_select2_query_search( $page, $per_page, $only_saved_search, $search_term );
    }
    $params = array(
      '_pagination' => 1,
      '_page'       => $page,
      '_limit'      => $per_page,
    );
    if ( $only_saved_search ) {
      $params['_filter'] = "LinkType Eq 'SavedSearch'";
    }
    $raw = $fmc_api->GetIDXLinks( $params );
    if ( false === $raw || ! is_array( $raw ) ) {
      unset( $params['_filter'] );
      $raw = $fmc_api->GetIDXLinks( $params );
    }
    $results = array();
    if ( is_array( $raw ) ) {
      foreach ( $raw as $r ) {
        if ( ! is_array( $r ) ) {
          continue;
        }
        if ( $only_saved_search && ! self::idx_link_row_is_saved_search( $r ) ) {
          continue;
        }
        $opt = self::idx_links_select2_row( $r );
        if ( $opt ) {
          $results[] = $opt;
        }
      }
    }
    $more = false;
    if ( null !== $fmc_api->total_pages && null !== $fmc_api->current_page ) {
      $more = (int) $fmc_api->current_page < (int) $fmc_api->total_pages;
    }
    return array( 'results' => $results, 'more' => $more );
  }

  /**
   * Search IDX links: prefer Spark `_filter` + pagination; fall back to scanning API pages in PHP if the API rejects the filter.
   *
   * @return array{results:array<int,array{id:string,text:string}>,more:bool}
   */
  private static function idx_links_select2_query_search( $page, $per_page, $only_saved_search, $search_term ) {
    global $fmc_api;
    $escaped = self::sparkql_escape_char_literal( $search_term );
    $filter  = self::idx_links_select2_build_name_filter( $escaped, $only_saved_search );
    $params  = array(
      '_pagination' => 1,
      '_page'       => $page,
      '_limit'      => $per_page,
      '_filter'     => $filter,
    );
    $raw = $fmc_api->GetIDXLinks( $params );
    if ( false !== $raw && is_array( $raw ) ) {
      $results = array();
      foreach ( $raw as $r ) {
        if ( ! is_array( $r ) ) {
          continue;
        }
        if ( $only_saved_search && ! self::idx_link_row_is_saved_search( $r ) ) {
          continue;
        }
        $opt = self::idx_links_select2_row( $r );
        if ( $opt ) {
          $results[] = $opt;
        }
      }
      $more = false;
      if ( null !== $fmc_api->total_pages && null !== $fmc_api->current_page ) {
        $more = (int) $fmc_api->current_page < (int) $fmc_api->total_pages;
      }
      return array( 'results' => $results, 'more' => $more );
    }

    if ( $only_saved_search ) {
      $params_name_only = array(
        '_pagination' => 1,
        '_page'       => $page,
        '_limit'      => $per_page,
        '_filter'     => "Name Eq contains('" . $escaped . "')",
      );
      $raw_retry = $fmc_api->GetIDXLinks( $params_name_only );
      if ( false !== $raw_retry && is_array( $raw_retry ) ) {
        $results = array();
        foreach ( $raw_retry as $r ) {
          if ( ! is_array( $r ) || ! self::idx_link_row_is_saved_search( $r ) ) {
            continue;
          }
          $opt = self::idx_links_select2_row( $r );
          if ( $opt ) {
            $results[] = $opt;
          }
        }
        $more = false;
        if ( null !== $fmc_api->total_pages && null !== $fmc_api->current_page ) {
          $more = (int) $fmc_api->current_page < (int) $fmc_api->total_pages;
        }
        return array( 'results' => $results, 'more' => $more );
      }
    }

    return self::idx_links_select2_query_search_client_scan( $page, $per_page, $only_saved_search, $search_term );
  }

  /**
   * Legacy search: pull pages without _filter and match Name in PHP (capped pages). Used when Spark rejects _filter.
   *
   * @return array{results:array<int,array{id:string,text:string}>,more:bool}
   */
  private static function idx_links_select2_query_search_client_scan( $page, $per_page, $only_saved_search, $search_term ) {
    global $fmc_api;
    $skip              = max( 0, ( $page - 1 ) * $per_page );
    $matched           = array();
    $api_page          = 1;
    $max_api_pages     = 40;
    $total_pages       = 1;
    $last_batch_size   = 0;
    while ( $api_page <= $max_api_pages && count( $matched ) < $per_page ) {
      $raw = $fmc_api->GetIDXLinks(
        array(
          '_pagination' => 1,
          '_page'       => $api_page,
          '_limit'      => 50,
        )
      );
      if ( false === $raw || ! is_array( $raw ) ) {
        break;
      }
      $last_batch_size = count( $raw );
      if ( null !== $fmc_api->total_pages ) {
        $total_pages = max( 1, (int) $fmc_api->total_pages );
      }
      foreach ( $raw as $r ) {
        if ( ! is_array( $r ) ) {
          continue;
        }
        if ( $only_saved_search && ! self::idx_link_row_is_saved_search( $r ) ) {
          continue;
        }
        $name = isset( $r['Name'] ) ? (string) $r['Name'] : '';
        if ( function_exists( 'mb_stripos' ) ) {
          if ( mb_stripos( $name, $search_term, 0, 'UTF-8' ) === false ) {
            continue;
          }
        } elseif ( stripos( $name, $search_term ) === false ) {
          continue;
        }
        $opt = self::idx_links_select2_row( $r );
        if ( ! $opt ) {
          continue;
        }
        if ( $skip > 0 ) {
          $skip--;
          continue;
        }
        if ( count( $matched ) < $per_page ) {
          $matched[] = $opt;
        }
      }
      if ( count( $matched ) >= $per_page ) {
        break;
      }
      if ( $api_page >= $total_pages ) {
        break;
      }
      $api_page++;
    }
    $more = ( count( $matched ) === $per_page ) && ( $api_page < $total_pages || $last_batch_size >= 50 );
    return array( 'results' => $matched, 'more' => $more );
  }

  /**
   * @param array<string,mixed> $r
   */
  private static function idx_link_row_is_saved_search( $r ) {
    if ( ! is_array( $r ) ) {
      return false;
    }
    if ( isset( $r['LinkType'] ) && 'SavedSearch' === $r['LinkType'] ) {
      return true;
    }
    return array_key_exists( 'SearchId', $r );
  }

  /**
   * @param array<string,mixed> $r
   * @return array{id:string,text:string}|null
   */
  private static function idx_links_select2_row( $r ) {
    if ( empty( $r['LinkId'] ) ) {
      return null;
    }
    return array(
      'id'   => (string) $r['LinkId'],
      'text' => isset( $r['Name'] ) ? (string) $r['Name'] : (string) $r['LinkId'],
    );
  }

  /**
   * Lazy office-agent Select2 uses the same admin Select2 on/off setting as IDX links.
   */
  static function office_agents_select2_enabled() {
    return self::idx_links_select2_enabled();
  }

  /**
   * Full office roster via Spark pagination (for integrations and plain &lt;select&gt; fallback).
   *
   * @param string $office_id From my/account OfficeId.
   * @return array<int,array<string,mixed>>
   */
  static function get_accounts_by_office_all_pages( $office_id ) {
    global $fmc_api;
    if ( ! $fmc_api || '' === (string) $office_id ) {
      return array();
    }
    $out         = array();
    $page        = 1;
    $total_pages = 1;
    while ( $page <= $total_pages ) {
      $raw = $fmc_api->GetAccountsByOffice(
        $office_id,
        array(
          '_pagination' => 1,
          '_page'       => $page,
          '_limit'      => 25,
        )
      );
      if ( false === $raw || ! is_array( $raw ) ) {
        break;
      }
      foreach ( $raw as $row ) {
        if ( is_array( $row ) ) {
          $out[] = $row;
        }
      }
      if ( null === $fmc_api->total_pages ) {
        break;
      }
      $total_pages = max( 1, (int) $fmc_api->total_pages );
      ++$page;
    }
    return $out;
  }

  /**
   * @param string|int|null $account_id
   * @return array{id:string,text:string}|null
   */
  static function office_agents_select2_prefetch_option( $account_id ) {
    if ( null === $account_id || '' === trim( (string) $account_id ) ) {
      return null;
    }
    global $fmc_api;
    if ( ! $fmc_api ) {
      return null;
    }
    $raw = trim( (string) $account_id );
    $row = $fmc_api->GetAccount( $raw );
    if ( ! is_array( $row ) || empty( $row['Id'] ) ) {
      return null;
    }
    return array(
      'id'   => (string) $row['Id'],
      'text' => isset( $row['Name'] ) ? (string) $row['Name'] : (string) $row['Id'],
    );
  }

  /**
   * @return array{id:string,text:string}|null
   */
  private static function office_agents_select2_row( $r ) {
    if ( ! is_array( $r ) || empty( $r['Id'] ) ) {
      return null;
    }
    return array(
      'id'   => (string) $r['Id'],
      'text' => isset( $r['Name'] ) ? (string) $r['Name'] : (string) $r['Id'],
    );
  }

  /**
   * Paged office roster for Select2 (server uses my/account OfficeId).
   *
   * @return array{results:array<int,array{id:string,text:string}>,more:bool}
   */
  static function office_agents_select2_query( $page, $per_page, $search_term = '' ) {
    global $fmc_api;
    if ( ! $fmc_api || ! self::is_office() ) {
      return array( 'results' => array(), 'more' => false );
    }
    $acct = $fmc_api->GetMyAccount();
    if ( ! is_array( $acct ) || empty( $acct['OfficeId'] ) ) {
      return array( 'results' => array(), 'more' => false );
    }
    $office_id = $acct['OfficeId'];
    $page      = max( 1, (int) $page );
    $per_page  = (int) $per_page;
    if ( $per_page < 5 ) {
      $per_page = 20;
    }
    if ( $per_page > 50 ) {
      $per_page = 50;
    }
    $api_limit   = min( $per_page, 25 );
    $search_term = is_string( $search_term ) ? trim( $search_term ) : '';
    if ( function_exists( 'mb_substr' ) ) {
      $search_term = mb_substr( $search_term, 0, 200, 'UTF-8' );
    } else {
      $search_term = substr( $search_term, 0, 200 );
    }
    if ( '' !== $search_term ) {
      return self::office_agents_select2_query_search( $office_id, $page, $api_limit, $search_term );
    }
    $params = array(
      '_pagination' => 1,
      '_page'       => $page,
      '_limit'      => $api_limit,
    );
    $raw     = $fmc_api->GetAccountsByOffice( $office_id, $params );
    $results = array();
    if ( is_array( $raw ) ) {
      foreach ( $raw as $r ) {
        $opt = self::office_agents_select2_row( $r );
        if ( $opt ) {
          $results[] = $opt;
        }
      }
    }
    $more = false;
    if ( null !== $fmc_api->total_pages && null !== $fmc_api->current_page ) {
      $more = (int) $fmc_api->current_page < (int) $fmc_api->total_pages;
    }
    return array( 'results' => $results, 'more' => $more );
  }

  /**
   * @return array{results:array<int,array{id:string,text:string}>,more:bool}
   */
  private static function office_agents_select2_query_search( $office_id, $page, $api_limit, $search_term ) {
    global $fmc_api;
    $escaped = self::sparkql_escape_char_literal( $search_term );
    $filter  = "Name Eq contains('" . $escaped . "')";
    $params  = array(
      '_pagination' => 1,
      '_page'       => $page,
      '_limit'      => $api_limit,
      '_filter'     => $filter,
    );
    $raw = $fmc_api->GetAccountsByOffice( $office_id, $params );
    if ( false !== $raw && is_array( $raw ) ) {
      $results = array();
      foreach ( $raw as $r ) {
        $opt = self::office_agents_select2_row( $r );
        if ( $opt ) {
          $results[] = $opt;
        }
      }
      $more = false;
      if ( null !== $fmc_api->total_pages && null !== $fmc_api->current_page ) {
        $more = (int) $fmc_api->current_page < (int) $fmc_api->total_pages;
      }
      return array( 'results' => $results, 'more' => $more );
    }
    return self::office_agents_select2_query_search_client_scan( $office_id, $page, $api_limit, $search_term );
  }

  /**
   * @return array{results:array<int,array{id:string,text:string}>,more:bool}
   */
  private static function office_agents_select2_query_search_client_scan( $office_id, $page, $api_limit, $search_term ) {
    global $fmc_api;
    $skip            = max( 0, ( $page - 1 ) * $api_limit );
    $matched         = array();
    $api_page        = 1;
    $max_api_pages   = 40;
    $total_pages     = 1;
    $last_batch_size = 0;
    while ( $api_page <= $max_api_pages && count( $matched ) < $api_limit ) {
      $raw = $fmc_api->GetAccountsByOffice(
        $office_id,
        array(
          '_pagination' => 1,
          '_page'       => $api_page,
          '_limit'      => 25,
        )
      );
      if ( false === $raw || ! is_array( $raw ) ) {
        break;
      }
      $last_batch_size = count( $raw );
      if ( null !== $fmc_api->total_pages ) {
        $total_pages = max( 1, (int) $fmc_api->total_pages );
      }
      foreach ( $raw as $r ) {
        if ( ! is_array( $r ) ) {
          continue;
        }
        $name = isset( $r['Name'] ) ? (string) $r['Name'] : '';
        if ( function_exists( 'mb_stripos' ) ) {
          if ( mb_stripos( $name, $search_term, 0, 'UTF-8' ) === false ) {
            continue;
          }
        } elseif ( stripos( $name, $search_term ) === false ) {
          continue;
        }
        $opt = self::office_agents_select2_row( $r );
        if ( ! $opt ) {
          continue;
        }
        if ( $skip > 0 ) {
          --$skip;
          continue;
        }
        if ( count( $matched ) < $api_limit ) {
          $matched[] = $opt;
        }
      }
      if ( count( $matched ) >= $api_limit ) {
        break;
      }
      if ( $api_page >= $total_pages ) {
        break;
      }
      ++$api_page;
    }
    $more = ( count( $matched ) === $api_limit ) && ( $api_page < $total_pages || $last_batch_size >= 25 );
    return array( 'results' => $matched, 'more' => $more );
  }

  static function possible_destinations() {
    return array('local' => 'my search results', 'remote' => 'a flexmls IDX frame');
  }

  static function is_agent() {
    $type = get_option('fmc_my_type');
    return ($type == 'Member') ? true : false;
  }

  static function is_office() {
    $type = get_option('fmc_my_type');
    return ($type == 'Office') ? true : false;
  }

  static function is_company() {
    $type = get_option('fmc_my_type');
    return ($type == 'Company') ? true : false;
  }

  static function get_office_id() {
    return get_option('fmc_my_office');
  }

  static function get_company_id() {
    return get_option('fmc_my_company');
  }

  static function possible_fonts() {
    return array(
      'Arial' => 'Arial',
      'Lucida Sans Unicode' => 'Lucida Sans Unicode',
      'Tahoma' => 'Tahoma',
      'Verdana' => 'Verdana'
    );
  }

  static function hexLighter($hex, $factor = 20) {
    $hex = str_replace('#', '', $hex);
    $new_hex = '';

    $base['R'] = hexdec($hex[0].$hex[1]);
    $base['G'] = hexdec($hex[2].$hex[3]);
    $base['B'] = hexdec($hex[4].$hex[5]);

    foreach ($base as $k => $v) {
      $amount = 255 - $v;
      $amount = $amount / 100;
      $amount = round($amount * $factor);
      $new_decimal = $v + $amount;

      $new_hex_component = dechex($new_decimal);
      if (strlen($new_hex_component) < 2) {
        $new_hex_component = "0".$new_hex_component;
      }
      $new_hex .= $new_hex_component;
    }

    return '#' . $new_hex;
  }

  static function hexDarker($color, $dif=20){
    $color = str_replace('#', '', $color);
    if (strlen($color) != 6){ return '#000000'; }
    $rgb = '';

    for ($x=0;$x<3;$x++){
        $c = hexdec(substr($color,(2*$x),2)) - $dif;
        $c = ($c < 0) ? 0 : dechex($c);
        $rgb .= (strlen($c) < 2) ? '0'.$c : $c;
    }

    return '#' . $rgb;
  }

  public static function allowMultipleLists() {
    $options = get_option('fmc_settings');

    if (array_key_exists('multiple_summaries', $options)) {
      if ($options['multiple_summaries']) {
          return true;
      }
    }
    return false;
  }


  static function nice_property_type_label($abbrev) {
    global $fmc_api;
    $options = get_option('fmc_settings');

    if ( array_key_exists("property_type_label_{$abbrev}", $options) and !empty($options["property_type_label_{$abbrev}"]) ) {
      return $options["property_type_label_{$abbrev}"];
    }
    else {
      $api_property_types = $fmc_api->GetPropertyTypes();
      if ( ! is_array( $api_property_types ) || ! array_key_exists( $abbrev, $api_property_types ) ) {
        return $abbrev;
      }
      return $api_property_types[ $abbrev ];
    }
  }

  //todo: check if this works as expected
  public static function gentle_price_rounding($val) {
    // check if the value has decimal places and if those aren't just zeros

    if ( !flexmlsConnect::is_not_blank_or_restricted($val) )
    return "";

      if ( strpos($val, '.') !== false ) {
        // has a decimal
        $places = explode(".", $val);
        if ($places[1] != "00") {
          return number_format($val, 2);
        }
      }

      return number_format($val, 0);
  }

  /**
   * Display price from listing StandardFields (slideshow, list cards, detail blocks).
   *
   * Order: CurrentPricePublic (typical IDX public price), then ClosePrice when closed if public
   * is unavailable, then ListPrice, then ListPriceHigh–ListPriceLow when both are set (high shown
   * first, spaced dash, then low).
   *
   * @param array $sf StandardFields from an API listing.
   * @return string Price with a leading "$", or empty string when no displayable price.
   */
  public static function format_listing_standard_price_display( $sf ) {
    if ( ! is_array( $sf ) ) {
      return '';
    }
    $current_public = $sf['CurrentPricePublic'] ?? '';
    if ( flexmlsConnect::is_not_blank_or_restricted( $current_public ) ) {
      return '$' . flexmlsConnect::gentle_price_rounding( $current_public );
    }
    $list_price = $sf['ListPrice'] ?? '';
    if ( flexmlsConnect::is_not_blank_or_restricted( $list_price ) ) {
      return '$' . flexmlsConnect::gentle_price_rounding( $list_price );
    }
    $close_price = $sf['ClosePrice'] ?? '';
    $mls_status  = $sf['MlsStatus'] ?? '';
    if ( flexmlsConnect::is_not_blank_or_restricted( $close_price ) && $mls_status === 'Closed' ) {
      return '$' . flexmlsConnect::gentle_price_rounding( $close_price );
    }

    $list_price_low  = $sf['ListPriceLow'] ?? '';
    $list_price_high = $sf['ListPriceHigh'] ?? '';
    if ( flexmlsConnect::is_not_blank_or_restricted( $list_price_low ) && flexmlsConnect::is_not_blank_or_restricted( $list_price_high ) ) {
      return '$' . flexmlsConnect::gentle_price_rounding( $list_price_high )
        . ' - $' . flexmlsConnect::gentle_price_rounding( $list_price_low );
    }
    return '';
  }

	public static function garbage_collect_bad_caches(){
		$SparkAPI = new \SparkAPI\Core();
		$SparkAPI->clear_cache();
		return true;
	}

  static function translate_tiny_code($tiny_id){
    $tiny_id = trim( (string) $tiny_id );
    $base36  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $valid   = ( $tiny_id !== '' && strlen( $tiny_id ) <= 64 );
    if ( $valid ) {
      for ( $i = 0; $i < strlen( $tiny_id ); $i++ ) {
        if ( strpos( $base36, $tiny_id[ $i ] ) === false ) {
          $valid = false;
          break;
        }
      }
    }
    if ( ! $valid ) {
      return '20000000';
    }
    $t_id = (string) flexmlsConnect::bc_base_convert( $tiny_id, 36, 10 );
    $prefix = "20";
    if ( $t_id[0]=='9' && strlen($t_id) == 18){
      $prefix="19";
    }
    while ((strlen($prefix . $t_id)) < 20){
      $prefix .= "0";
    }
    return  $prefix . $t_id . "000000";
  }

  static function bc_base_convert($value,$quellformat,$zielformat){
    $vorrat = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if(max($quellformat,$zielformat) > strlen($vorrat))
      trigger_error('Bad Format max: '.strlen($vorrat),E_USER_ERROR);
    if(min($quellformat,$zielformat) < 2)
      trigger_error('Bad Format min: 2',E_USER_ERROR);
    $dezi   = '0';
    $level  = 0;
    $result = '';
    $value  = trim((string)$value,"\r\n\t +");
    $vorzeichen = '-' === $value[0]?'-':'';
    $value  = ltrim($value,"-0");
    $len    = strlen($value);
    for($i=0;$i<$len;$i++)
    {
      $wert = strpos($vorrat,$value[$len-1-$i]);
      if(FALSE === $wert) trigger_error('Bad Char in input 1',E_USER_ERROR);
      if($wert >= $quellformat) trigger_error('Bad Char in input 2',E_USER_ERROR);
      $dezi = bcadd($dezi,bcmul(bcpow($quellformat,$i),$wert));
    }
    if(10 == $zielformat) return $vorzeichen.$dezi; // abkürzung
    while(1 !== bccomp(bcpow($zielformat,$level++),$dezi));
    for($i=$level-2;$i>=0;$i--)
    {
      $factor  = bcpow($zielformat,$i);
      $zahl    = bcdiv($dezi,$factor,0);
      $dezi    = bcmod($dezi,$factor);
      $result .= $vorrat[$zahl];
    }
    $result = empty($result)?'0':$result;
    return $vorzeichen.$result ;
  }

  static function show_error($error = array()){
    if ( ! is_array( $error ) ) {
      $error = array();
    }
    $return = '<div class="fmc-error"><b>Error:</b> ';
    if (array_key_exists("title", $error)) {
      $return .= $error["title"] . "<br>";
    }
    if (array_key_exists("message", $error)) {
      $return .= $error["message"];
    }
    $return .= "</div>";
    return $return;
  }

  static public function is_portal_on()  {
    global $fmc_api;

    $portal = $fmc_api->GetPortal();
    if ( ! is_array( $portal ) || ! array_key_exists( 0, $portal ) ) {
      return false;
    }
    $row = $portal[0];
    if ( ! is_array( $row ) ) {
      return false;
    }

    return ! empty( $row['Enabled'] );
  }

  /**
   * @param array<int, mixed>|mixed $portal Raw GetPortal() result.
   * @return string|null
   */
  private static function portal_row_display_name( $portal ) {
    if ( ! is_array( $portal ) || ! array_key_exists( 0, $portal ) || ! is_array( $portal[0] ) ) {
      return null;
    }
    return isset( $portal[0]['DisplayName'] ) ? $portal[0]['DisplayName'] : null;
  }

  static function get_portal_slug() {
    global $fmc_api;

    $portal_slug = null;
    $portal = $fmc_api->GetPortal();
    $portal_on = flexmlsConnect::is_portal_on();

    if ( $portal_on ) {
      $portal_slug = self::portal_row_display_name( $portal );
    } elseif ( is_array( $portal ) && array_key_exists( 0, $portal ) && is_array( $portal[0] ) ) {
      $fmc_api->SetPortal( array(), array( 'AutoName' => true ) );
      $fmc_api->DeleteCache( 'portal' );
      $portal = $fmc_api->GetPortal();
      $portal_slug = self::portal_row_display_name( $portal );
    }

    return $portal_slug;
  }

  public static function get_contact_disclaimer() {

    $options = get_option('fmc_settings');
      
    return $options['contact_disclaimer'];

  }

}
