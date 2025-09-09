<?php

class flexmlsConnectSettings {

	private $options;

	function __construct(){
		$this->options = new Fmc_Settings;
		add_action( 'admin_init', array( $this, 'settings_init' ) );
	}

	function settings_init(){
		global $wp_rewrite;
		global $fmc_api;
		global $fmc_version;

		$options = get_option( 'fmc_settings' );

		if ( current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) ) {
      if( isset( $options['integration'] ) && $options['integration']['divi'] ){
        add_filter( 'mce_buttons', array('flexmlsConnect', 'filter_mce_location_button' ) );
      } else {
        add_filter( 'mce_buttons', array('flexmlsConnect', 'filter_mce_button' ) );
      }
			add_filter( 'mce_external_plugins', array('flexmlsConnect', 'filter_mce_plugin' ) );

      add_action( "admin_head", array('flexmlsConnect', 'filter_mce_plugin_global_vars' ) );

			add_action( 'wp_ajax_fmc_update_sr_field_order', array('flexmlsConnectSettings', 'sr_fields_save_order') );
		}

    if ($this->options->search_results_fields() == '' || count($this->options->search_results_fields()) == 0 ) {
      flexmlsConnectSettings::set_default_search_results_fields();
    }

    if (empty($options['oauth_key']) && empty($options['oauth_secret']) && empty($options['oauth_failure'])){
      $data = array(
        "Type" => "WordPressIdx",
        "RedirectUri" => flexmlsConnectPortalUser::redirect_uri(),
        "ApplicationUrl" => site_url(),
      );

      $oauth_info = $fmc_api ? $fmc_api->CreateOauthKey($data) : false;
      if (!$oauth_info){
        $options['oauth_failure'] = true;
      }
      else {
        $options['oauth_key'] = $oauth_info[0]["ClientId"];
        $options['oauth_secret'] = $oauth_info[0]["ClientSecret"];
      }
      update_option('fmc_settings', $options);
      $wp_rewrite->flush_rules(true);
    }

    if (empty($options["portal_text"])){
      $options["portal_text"] =
      '<div style="margin:0 auto;">
        With a portal you are able to:
        <ul class="flexmls_dialog_list" style="text-align:center;margin:0 auto;list-style:none;">
          <li><em>Save your searches</em></li>
          <li><em>Get updates on listings</em></li>
          <li><em>Track listings</em></li>
          <li><em>Add notes and messages</em></li>
          <li><em>Personalize your dashboard</em></li>
        </ul>
      </div>';
      update_option('fmc_settings', $options);
    }

    // register our settings with WordPress so it can automatically handle saving them
    register_setting('fmc_settings_group', 'fmc_settings', array('flexmlsConnectSettings', 'settings_validate') );
  }

  static function set_default_search_results_fields() {
    $options = get_option('fmc_settings');

    $default_fields = array(
        'PropertyType' => 'Property Type',
        'BedsTotal' => '# of Bedrooms',
        'BathsTotal' => '# of Bathrooms',
        'BuildingAreaTotal' => 'Square Footage',
        'YearBuilt' => 'Year Built',
        'MLSAreaMinor' => 'Area',
        'SubdivisionName' => 'Subdivision',
        'PublicRemarks' => 'Description'
    );
    $options['search_results_fields'] = $default_fields;
    update_option('fmc_settings', $options);
  }

  static function validate_search_results_fields($input) {
    $valid_fields = array();
    if(count($input) > 0) {
      global $fmc_api;
      $api_property_fields = $fmc_api->GetStandardFields();

      foreach ($input as $field_id => $display_name) {
        if(in_array($field_id, array_keys($api_property_fields[0]))) {
          $valid_fields[$field_id] = sanitize_text_field($display_name);
        }
      }
    }
    return $valid_fields;
  }

  /**
   * Formats the entered height to ensure it is in pixels or %
   *
   * @param $height string The height value
   */
  static function format_map_height( $height ) {
	  // It doesn't have px or % attached if it's 1 character or less.
	  $strlen = strlen( $height );
	  if ( 1 > $strlen ) {
		  return false;
	  }
	  if ( '%' === substr( $height, -1 ) ) {
		return $height;
	  }
	  if ( 'px' === substr( $height, -2 ) ) {
		  return $height;
	  }

	  return $height . 'px';
  }

	static function settings_validate( $input ){
		global $wp_rewrite;
		global $fmc_api;

		$options = get_option( 'fmc_settings' );

		foreach( $input as $key => $value ){
			if( !is_array( $value ) ){
				$input[ $key ] = trim( $value );
			}
		}

		if( array_key_exists( 'tab', $input ) && 'settings' == $input[ 'tab' ] ){
			if( $options[ 'api_key' ] != $input[ 'api_key' ] || $options[ 'api_secret' ] != $input[ 'api_secret' ] ){
				$input[ 'clear_cache' ] = 'y';
			}
			$options[ 'api_key' ] = trim( $input[ 'api_key' ] );
			$options[ 'api_secret' ] = trim( $input[ 'api_secret' ] );

			if( array_key_exists( 'clear_cache', $input ) && 'y' == $input[ 'clear_cache' ] ){
				// since clear_cache is checked, wipe out the contents of the fmc_cache_* transient items
				// but don't do anything else since we aren't saving the state of this particular checkbox
				flexmlsConnect::clear_temp_cache();
				flexmlsAPI_WordPressCache::clearDB();
			}
		}
    elseif (array_key_exists('tab', $input) && $input['tab'] == "behavior") {

      if ($input['default_titles'] == "y") {
        $options['default_titles'] = true;
      }
      else {
        $options['default_titles'] = false;
      }

      $options['destpref'] = $input['destpref'];
      $options['destlink'] = $input['destlink'];
      $options['listpref'] = $input['listpref'];
      $options['listlink'] = $input['listlink'];
      $options['destwindow'] = array_key_exists('destwindow', $input) ? $input['destwindow'] : null;
      $options['default_link'] = $input['default_link'];
      $options['neigh_template'] = array_key_exists('neigh_template', $input) ? $input['neigh_template'] : null;
      $options['permabase'] = (!empty($input['permabase'])) ? $input['permabase'] : 'idx';

      if ($input['contact_notifications'] == "y") {
        $options['contact_notifications'] = true;
      }
      else {
        $options['contact_notifications'] = false;
      }

      if (array_key_exists('multiple_summaries', $input) && $input['multiple_summaries'] == "y") {
        $options['multiple_summaries'] = true;
      }
      else {
        $options['multiple_summaries'] = false;
      }

      $property_types = explode(",", $input['property_types']);
      foreach ($property_types as $pt) {
        $options['property_type_label_'.$pt] = $input['property_type_label_'.$pt];
      }

      $valid_fields = flexmlsConnectSettings::validate_search_results_fields($input['search_results_fields']);
      $options['search_results_fields'] = $valid_fields;

      if (array_key_exists('listing_office_disclosure', $input)){
        $options['listing_office_disclosure'] = $input['listing_office_disclosure'];
      } else {
        $options['listing_office_disclosure'] = null;
      }

      if (array_key_exists('listing_agent_disclosure', $input)){
        $options['listing_agent_disclosure'] = $input['listing_agent_disclosure'];
      } else {
        $options['listing_agent_disclosure'] = null;
      }

      $options['allow_sold_searching'] = array_key_exists('allow_sold_searching', $input) ? $input['allow_sold_searching'] : null;

      if (array_key_exists('use_captcha', $input) && $input['use_captcha'] == "true"){
        $options['use_captcha'] = true;
      } else {
        $options['use_captcha'] = false;
      }

	  if ( array_key_exists( 'maps_api_key', $input ) ) {
		  $options['google_maps_api_key'] = sanitize_text_field( $input['maps_api_key'] );
	  }

      if ( array_key_exists( 'map_height', $input ) ) {
	      if ( ! $input['map_height'] ) {
		      $options['map_height'] = '';
	      } else {
		      $height = flexmlsConnectSettings::format_map_height( $input['map_height'] );
		      $options['map_height'] = sanitize_text_field( $height );
	      }
      }

    }
    elseif (array_key_exists('tab', $input) && $input['tab'] == "portal") {

      $options['oauth_key'] = trim($input['oauth_key']);
      $options['oauth_secret'] = trim($input['oauth_secret']);

      $options['portal_search'] = (array_key_exists('portal_search', $input) && $input['portal_search']==true);
      $options['portal_carts'] = (array_key_exists('portal_carts', $input) && $input['portal_carts']==true);
      $options['portal_listing'] = (array_key_exists('portal_listing', $input) && $input['portal_listing']==true);
      $options['portal_force'] = (array_key_exists('portal_force', $input) && $input['portal_force']==true);

      //the following 4 fields are checked to be positive integers, if they are not then they are null
      $options['portal_mins'] = ((is_numeric($input['portal_mins']) and $input['portal_mins']>=0) ? intval($input['portal_mins']) : null);
      $detail_page = ((is_numeric($input['detail_page']) and $input['detail_page']>=0) ? intval($input['detail_page']) : null);
      $options['search_page'] = ((is_numeric($input['search_page']) and $input['search_page']>=0) ? $input['search_page'] : null);

      $options['portal_position_x'] = $input['portal_position_x'];
      $options['portal_position_y'] = $input['portal_position_y'];

      $options['portal_text'] = trim($input['portal_text']);
      }

    return $options;

  }
}
