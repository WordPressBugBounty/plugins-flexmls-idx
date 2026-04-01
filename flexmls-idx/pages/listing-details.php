<?php

#[\AllowDynamicProperties]
class flexmlsConnectPageListingDetails extends flexmlsConnectPageCore {

  private $listing_data;
  protected $search_criteria;
  protected $type;
  protected $property_detail_values;

  function __construct( $api, $type = null ){

    parent::__construct($api);
    $this->type = is_null($type) ? 'fmc_tag' : $type;

    add_filter( 'wpseo_title', array( $this, 'wpseo_title' ), 0 );
    add_filter( 'wpseo_canonical', array( $this, 'wpseo_canonical' ), 0 );
    add_filter( 'wp_robots', array($this, 'wp_robots_noindex_listing'), 0 );
    
    // Handle other SEO plugins
    add_filter( 'rank_math/frontend/canonical', array( $this, 'rankmath_canonical' ), 0 );
    add_filter( 'aioseo_canonical_url', array( $this, 'aioseo_canonical' ), 0 );
    add_filter( 'the_seo_framework_meta_canonical', array( $this, 'seo_framework_canonical' ), 0 );
    add_filter( 'seopress_canonical', array( $this, 'seopress_canonical' ), 0 );
    
    add_action('wp_head', array($this, 'wp_meta_description_tag'), 0 );
    add_action('wp_head', array($this, 'open_graph_tags'), 0 );
    
    // Prevent SEO plugins from outputting OpenGraph tags on listing detail pages
    add_filter( 'wpseo_frontend_presenters', array( $this, 'filter_presenters' ), 10 );
    
    // Rank Math - Disable OpenGraph tags
    add_action( 'rank_math/head', array( $this, 'disable_rankmath_opengraph' ), 0 );
    
    // All in One SEO - Disable OpenGraph tags
    add_filter( 'aioseo_facebook_tags', array( $this, 'disable_aioseo_facebook_tags' ), 10 );
    add_filter( 'aioseo_twitter_tags', array( $this, 'disable_aioseo_twitter_tags' ), 10 );
    
    // SEOPress - Disable OpenGraph and Twitter tags
    $this->setup_seopress_disabling();
    
    // The SEO Framework - Disable OpenGraph tags
    add_filter( 'the_seo_framework_og_output', array( $this, 'disable_seo_framework_opengraph' ), 10 );
    
    // Jetpack - Disable OpenGraph tags
    add_filter( 'jetpack_enable_open_graph', array( $this, 'disable_jetpack_opengraph' ), 10 );

  }

  function wp_head(){

    if ( $this->uses_v2_template() ) {
      $this->render_template_styles();
    }
  }

  function pre_tasks($tag) {
    global $fmc_special_page_caught;
    global $fmc_api;

    add_action( 'wp_head', array( $this, 'wp_head' ) );


    
    list($params, $cleaned_raw_criteria, $context) = $this->parse_search_parameters_into_api_request();

    $this->search_criteria = $cleaned_raw_criteria;

    preg_match('/mls\_(.*?)$/', $tag, $matches);

    $id_found = (isset($matches[1])) ? $matches[1] : '';

    $filterstr = "ListingId Eq '{$id_found}'";

    if ( $mls_id = flexmlsConnect::wp_input_get('m') ) {
      $filterstr .= " and MlsId Eq '".$mls_id."'";
    }

    $params = array(
        '_filter' => $filterstr,
        '_limit' => 1,
        '_expand' => 'Photos,Videos,OpenHouses,VirtualTours,Documents,Rooms,CustomFields,Supplement'
    );
    $result = $this->api->GetListings($params);

    $listing = (is_countable($result) && count($result) > 0) ? $result[0] : null;

    $fmc_special_page_caught['type'] = "listing-details";

    $this->listing_data = $listing;

    if ($listing != null) {
      $fmc_special_page_caught['page-title'] = flexmlsConnect::make_nice_address_title($listing);
      $fmc_special_page_caught['post-title'] = flexmlsConnect::make_nice_address_title($listing);
      $fmc_special_page_caught['page-url'] = flexmlsConnect::make_nice_address_url($listing);
    }
    else {
      $page = flexmlsConnect::get_no_listings_page_number();
      $page_data = get_post($page);
      $fmc_special_page_caught['page-title'] = "Listing Not Available";
      $fmc_special_page_caught['post-title'] = $page_data->post_title;

    }

  }


  function generate_page($from_shortcode = false) {
    global $fmc_api;
    global $fmc_special_page_caught;
    global $fmc_plugin_url;
    global $fmc_api_portal;

    if ($this->type == 'fmc_vow_tag' && !$fmc_api_portal->is_logged_in()){
      return "Sorry, but you must <a href={$fmc_api_portal->get_portal_page()}>log in</a> to see this page.<br />";
    }

    if ($this->listing_data == null) {
      if (flexmlsConnect::get_no_listings_pref() == 'page'){
        $page = flexmlsConnect::get_no_listings_page_number();
        $page_data = get_post($page);
        remove_filter('the_content', array('flexmlsConnectPage', 'custom_post_content'));
        return apply_filters('the_content', $page_data->post_content);
      } else {
        return "This listing is no longer available.";
      }
    }

    $standard_fields_plus = $this->api->GetStandardFields();
    $standard_fields_plus = (is_array($standard_fields_plus) && isset($standard_fields_plus[0])) ? $standard_fields_plus[0] : array();
    // $custom_fields = $fmc_api->GetCustomFields();


    $options = get_option('fmc_settings');

    // set some variables
    $record =& $this->listing_data;
    if ( ! isset( $record['StandardFields'] ) || ! is_array( $record['StandardFields'] ) ) {
      return '<p>This listing is missing required data.</p>';
    }
    $sf =& $record['StandardFields'];
    $listing_address = flexmlsConnect::format_listing_street_address($record);
    $first_line_address = (isset($listing_address[0])) ? htmlspecialchars($listing_address[0]) : '';
    $second_line_address = (isset($listing_address[1])) ? htmlspecialchars($listing_address[1]) : '';
    $one_line_address = (isset($listing_address[2])) ? htmlspecialchars($listing_address[2]) : '';
    $one_line_address_add_slashes = (isset($listing_address[2])) ? addslashes($listing_address[2]) : '';
    $one_line_without_zip_address = flexmlsSearchUtil::one_line_without_zip_address( $record );
    $mls_fields_to_suppress = flexmlsSearchUtil::mls_fields_to_suppress( $sf );

    $compList = flexmlsConnect::mls_required_fields_and_values("Detail",$record);

    $custom_fields = array();
    if (isset($record["CustomFields"]) && is_array($record["CustomFields"]) && isset($record["CustomFields"][0]) && is_array($record["CustomFields"][0]["Main"])) {
      foreach ($record["CustomFields"][0]["Main"] as $data) {
        foreach ($data as $group_name => $fields) {
          foreach ($fields as $field) {
            foreach ($field as $field_name => $val) {
              // check if the field already exists
              if( array_key_exists("Main", $custom_fields) and
                  array_key_exists($group_name, $custom_fields["Main"]) and
                  array_key_exists($field_name, $custom_fields["Main"][$group_name]) ) {
                // if it is an array, add the value to the end
                if(is_array($custom_fields["Main"][$group_name][$field_name])) {
                  $custom_fields["Main"][$group_name][$field_name][] = $val;
                }
                // if it's not, move the value to an array, and add the new value
                else {
                  $current_val = $custom_fields["Main"][$group_name][$field_name];
                  $custom_fields["Main"][$group_name][$field_name] = array($current_val, $val);
                }
              }
              // if the field doesn't already exsist, jsut add it normally
              else {
                $custom_fields["Main"][$group_name][$field_name]= $val;
              }
            }
          }
        }
      }
    }

    if (isset($record["CustomFields"]) && is_array($record["CustomFields"]) && isset($record["CustomFields"][0]) && isset($record["CustomFields"][0]["Details"]) && is_array($record["CustomFields"][0]["Details"])) {
      foreach ($record["CustomFields"][0]["Details"] as $data) {
        foreach ($data as $group_name => $fields)
          foreach ($fields as $field)
            foreach ($field as $field_name => $val)
              $custom_fields["Details"][$group_name][$field_name]= $val;
      }
    }


    $MlsFieldOrder = $this->api->GetFieldOrder($sf["PropertyType"]);
    $property_features_values = array();
    if( is_array($MlsFieldOrder) && !empty($MlsFieldOrder) ){
      foreach ($MlsFieldOrder as $field){
        foreach ($field as $name => $key){
          foreach ($key as $property){

            if (in_array($property["Label"],$mls_fields_to_suppress)){
              continue;
            }

            $is_standard_Field = false;
            if (isset($property["Domain"]) and (isset($sf[$property["Field"]]))){
              /* Temporary hack to prevent warnings until Field Ordering gets rewritten */
              if (is_array($sf[$property["Field"]])){
                continue;
              }
              if ($property["Domain"] == "StandardFields" and
                  flexmlsConnect::is_not_blank_or_restricted($sf[$property["Field"]])){
                $is_standard_Field = true;
              }
            }


            $detail_custom_bool = false;
            $custom_custom_bool = false;
            // If a field has a boolean for a value, mark it in the features section
            if (isset($custom_fields["Details"][$name][$property["Label"]])) {
              $detail_custom_bool = $custom_fields["Details"][$name][$property["Label"]] === true;
            }
            if (isset($custom_fields["Main"][$name][$property["Label"]])) {
              $custom_custom_bool = $custom_fields["Main"][$name][$property["Label"]] === true;
            }

            // Check if for Custom field Details
            $custom_details = false;
            if (isset($property["Detail"]) and isset($custom_fields["Details"][$name][$property["Label"]])){
              $custom_details = $property["Detail"] and flexmlsConnect::is_not_blank_or_restricted($custom_fields["Details"][$name][$property["Label"]]);
            }

            $custom_main = false;
            if ( isset($custom_fields["Main"][$name][$property["Label"]]) ) {
              $custom_main = flexmlsConnect::is_not_blank_or_restricted(
                $custom_fields["Main"][$name][$property["Label"]]
              );
            }

            //Standard Fields
            if( $is_standard_Field ){
              if( 'PublicRemarks' == $property[ 'Field' ] ){
                continue; //WP-325
              }
              switch( $property[ 'Label' ] ){
                case 'List Price':
                case 'Current Price':
                case 'Sold Price':
                if ( flexmlsConnect::is_not_blank_or_restricted( $sf['ClosePrice']) && $sf['MlsStatus'] == 'Closed') : 
                  if( $property[ 'Label' ] == 'List Price'){
                    $property[ 'Label' ] = 'Sold Price';
                  }
                  $this->add_property_detail_value( '$' . flexmlsConnect::gentle_price_rounding( $sf['ClosePrice'] ), $property[ 'Label' ], $name );
                else:
                  $this->add_property_detail_value( '$' . flexmlsConnect::gentle_price_rounding( $sf[ $property[ 'Field' ] ] ), $property[ 'Label' ], $name );
                endif;
                break;
                default:
                $this->add_property_detail_value( $sf[ $property[ 'Field' ] ], $property[ 'Label' ], $name );
              }
            }

            //Custom Fields with value of true are placed in property feature section
            else if ($detail_custom_bool or $custom_custom_bool){
              $property_features_values[$name][]= $property["Label"];
            }
            //Custom Fields - DETAIL
            else if ($custom_details){
              $this->property_detail_values[$name][] = "<b>".$property["Label"].":</b> " .
                $custom_fields["Details"][$name][$property["Label"]];
            }

            //Custom Fields - MAIN
            else if ($custom_main){
              $this->add_property_detail_value( $custom_fields["Main"][$name][$property["Label"]],
                $property["Label"], $name );

            }
          }
        }
      }
     }
     $room_fields = $this->api->GetRoomFields($sf['MlsId']);
     $room_names = array();
     $room_values = array();
     // Column order must follow $room_fields keys; values are matched by field key ($rfk), not Label —
     // some MLSes define multiple room fields with the same Label (e.g. two "Length" columns). Matching
     // by label pushed the same value into every column with that label, duplicating data.
     $room_field_keys_ordered = array();

     if ( is_array( $room_fields ) ) {
       foreach ( $room_fields as $field_key => $mls_named_room ) {
         if ( ! is_array( $mls_named_room ) || ! isset( $mls_named_room['Label'] ) ) {
           continue;
         }
         array_push( $room_names, $mls_named_room['Label'] );
         array_push( $room_field_keys_ordered, $field_key );
         array_push( $room_values, array() );
       }
     }
     $room_information_values = array();

     if ( count($sf['Rooms']) > 0 ) {

       foreach ($sf['Rooms'] as $r) {

         foreach ($r['Fields'] as $rf) {
           foreach ($rf as $rfk => $rfv) {

             if ( is_array( $room_fields ) && array_key_exists( $rfk, $room_fields ) ) {
               $idx = array_search( $rfk, $room_field_keys_ordered, true );
               if ( $idx !== false ) {
                 array_push( $room_values[ $idx ], $rfv );
               }
             } else {
               // Field key not in MLS room metadata: match header text once (avoid duplicate Label columns).
               for ( $i = 0; $i < count( $room_names ); $i++ ) {
                 if ( (string) $rfk === (string) $room_names[ $i ] ) {
                   array_push( $room_values[ $i ], $rfv );
                   break;
                 }
               }
             }
             /*if     ($label == "Room") {
               $this_name = $rfv;
             }*/
           }
         }
       }

       //if all values in a field are zero append them to an array
       $toUnset = array();
       for ($i=0;$i<count($room_values);$i++){
         if (!array_filter($room_values[$i])) {
           array_push($toUnset,$i);
         }
       }
       //unset causes issues if attempt to do this in above for loop
       foreach ($toUnset as $index){
         unset($room_values[$index]);
         unset($room_names[$index]);
       }
       //reset the indexes to have order 0,1,2,...
       $room_values=array_values($room_values);
       $room_names= array_values($room_names);
     }


    // find the count for media stuff
    $count_photos = count($sf['Photos']);
    $count_videos = count($sf['Videos']);
    $count_tours = count($sf['VirtualTours']);
    $count_openhouses = count($sf['OpenHouses']);

    if ( $this->uses_v2_template() ) {
      ob_start();
  			global $fmc_plugin_dir;
  			require( $fmc_plugin_dir . "/views/v2/fmcListingDetails.php" );
  			$content = ob_get_contents();
  		ob_end_clean();
      return $content;
    }

    ob_start();
    flexmlsPortalPopup::popup_portal('detail_page');

    echo "<div class='flexmls_connect__prev_next'>";
    if ( $this->has_previous_listing() )
      echo "<button class='flexmls_connect__button left' href='". $this->browse_previous_url() ."'><img src='{$fmc_plugin_url}/assets/images/left.png' align='absmiddle' alt='Previous Listing' title='Previous Listing' /> Prev</button>";
    if ( $this->has_next_listing() )
      echo "<button class='flexmls_connect__button right' href='". $this->browse_next_url() ."'>Next <img src='{$fmc_plugin_url}/assets/images/right.png' align='absmiddle' alt='Next Listing' title='Next Listing' /></button>";
    echo "</div>";

    // begin
    echo "<div class='flexmls_connect__sr_detail' title='{$one_line_address} - MLS# {$sf['ListingId']}'>";



    echo "<hr class='flexmls_connect__sr_divider'>";
    echo "<div class='flexmls_connect__sr_address'>";

    // show price
    echo "<div class='flexmls_connect__ld_price'>";
    $ld_price = flexmlsConnect::format_listing_standard_price_display( $sf );
    if ( $ld_price !== '' ) {
      echo '<div>' . esc_html( $ld_price ) . '</div>';
    }
    echo "</div>";
    fmcAccount::write_carts($record);

    // show top address details
    if (!empty($first_line_address)) echo "{$first_line_address}<br />";
    if (!empty($second_line_address)) echo "{$second_line_address}<br />";
    echo "MLS# {$sf['ListingId']}<br />";

    $status_class = ($sf['MlsStatus'] == 'Closed') ? 'status_closed' : '';

    if (($sf['MlsStatus'] != 'Active') and !in_array( "MlsStatus", $mls_fields_to_suppress))
      echo "Status: <span class='flexmls_connect__ld_status {$status_class}'>{$sf['MlsStatus']}</span><br />";

    // show under address details (beds, baths, etc.)
    $under_address_details = array();

    if ( flexmlsConnect::is_not_blank_or_restricted($sf['BedsTotal']) )
      $under_address_details[] = $sf['BedsTotal'] .' beds';
    if ( flexmlsConnect::is_not_blank_or_restricted($sf['BathsTotal']) )
      $under_address_details[] = $sf['BathsTotal'] .' baths';
    if ( flexmlsConnect::is_not_blank_or_restricted($sf['BuildingAreaTotal']) )
      $under_address_details[] = $sf['BuildingAreaTotal'] .' sqft';

    echo implode(" &nbsp;|&nbsp; ", $under_address_details) . "<br />";

    echo "</div>";
    echo "<hr class='flexmls_connect__sr_divider'>";


    // display buttons
    echo "<div class='flexmls_connect__sr_details'>";

    // first, media buttons are on the right
    echo "<div class='flexmls_connect__right'>";
    if ($count_videos > 0) {
      echo "<button class='video_click' rel='v-{$sf['ListingKey']}'>Videos ({$count_videos})</button>";
      if ($count_tours > 0) {
        echo " &nbsp;|&nbsp; ";
      }
    }
    if ($count_tours > 0) {
      echo "<button class='tour_click' rel='t-{$sf['ListingKey']}'>Virtual Tours ({$count_tours})</button>";
    }
    echo "</div>";

    // Share and Print buttons
    echo "<div class='flexmls_connect__ld_button_group'>";
      echo "<button class='print_click' onclick='flexmls_connect.print(this);'><img src='{$fmc_plugin_url}/assets/images/print.png'align='absmiddle' alt='Print' title='Print' /> Print</button>";

      $api_my_account = $this->api->GetMyAccount();
      $api_prefs = $this->api->GetPreferences();
      if (!is_array($api_prefs) || !isset($api_prefs['RequiredFields']) || !is_array($api_prefs['RequiredFields'])) {
        $api_prefs['RequiredFields'] = array();
      }
      $phone_req = in_array('phone', $api_prefs['RequiredFields']);
      $address_req = in_array('address', $api_prefs['RequiredFields']);

      if (isset($api_my_account['Name']) && isset($api_my_account['Emails'][0]['Address'])) : ?>
        <button onclick="flexmls_connect.scheduleShowing({
          'id': '<?php addslashes($sf['ListingKey']) ?>',
          'title': 'Schedule a Showing',
          'subject': '<?php echo $one_line_address_add_slashes; ?> - MLS# <?php echo addslashes($sf['ListingId']); ?>',
          'agentName': '<?php echo addslashes($api_my_account['Name'])?>',
          'agentEmail': '<?php echo $this->contact_form_agent_email($sf); ?>',
          'officeEmail': '<?php echo $this->contact_form_office_email($sf); ?>',
          'phoneRequired': <?php echo $phone_req ? 'true' : 'false'; ?>,
          'addressRequired': <?php echo $address_req ? 'true' : 'false'; ?>
          <?php if( isset($options['contact_disclaimer']) ) : ?>
			  ,'disclaimer': '<?php echo esc_js(flexmlsConnect::get_contact_disclaimer()); ?>'
		      <?php endif; ?>
        })">
          <img src='<?php echo $fmc_plugin_url ?>/assets/images/showing.png' align='absmiddle' alt='Schedule a Showing' title='Schedule a Showing' /> Schedule a Showing
        </button>
      <?php endif ?>
      <button onclick="flexmls_connect.contactForm({
        'title': 'Ask a Question',
        'subject': '<?php echo $one_line_address_add_slashes; ?> - MLS# <?php echo addslashes($sf['ListingId'])?> ',
        'agentEmail': '<?php echo $this->contact_form_agent_email($sf); ?>',
        'officeEmail': '<?php echo $this->contact_form_office_email($sf); ?>',
        'id': '<?php echo addslashes($sf['ListingId']); ?>',
        'phoneRequired': <?php echo $phone_req ? 'true' : 'false'; ?>,
        'addressRequired': <?php echo $address_req ? 'true' : 'false'; ?>
        <?php if( isset($options['contact_disclaimer']) ) : ?>
			  ,'disclaimer': '<?php echo esc_js(flexmlsConnect::get_contact_disclaimer()); ?>'
		    <?php endif; ?>
      });">
        <img src='<?php echo $fmc_plugin_url ?>/assets/images/admin_16.png' align='absmiddle' alt='Ask a Question' title='Ask a Question' />
        Ask a Question
      </button>
    </div>
    <?php

    echo "<div class='flexmls_connect__success_message' id='flexmls_connect__success_message'></div>";

    echo "</div>";

    echo "<hr class='flexmls_connect__sr_divider'>";

    // hidden divs for tours and videos (colorboxes)
    echo "<div class='flexmls_connect__hidden2'></div>";
    echo "<div class='flexmls_connect__hidden3'></div>";

    // Photos
    if (isset($sf['Photos']) && is_array($sf['Photos']) && count($sf['Photos']) >= 1) {
    $main_photo_url = $sf['Photos'][0]['Uri640'];
    $main_photo_caption = htmlspecialchars($sf['Photos'][0]['Caption'], ENT_QUOTES);

      //set alt value
      if(!empty($main_photo_caption)){
        $main_photo_alt = $main_photo_caption;
      }
      elseif(!empty($sf['Photos'][0]['Name'])){
        $main_photo_alt = htmlspecialchars($sf['Photos'][0]['Name'], ENT_QUOTES);
      }
      elseif(!empty($one_line_address)){
        $main_photo_alt = $one_line_address;
      }
      else{
        $main_photo_alt = "Photo for listing #" . $sf['ListingId'];
      }

    //set title value
    $main_photo_title = "Photo for ";
    if(!empty($one_line_address)) {
      $main_photo_title .= $one_line_address . " - ";
    }
    $main_photo_title .= "Listing #" . $sf['ListingId'];

    echo "<div class='flexmls_connect__photos'>";
      echo "<div class='flexmls_connect__photo_container'>";
      echo "<img src='{$main_photo_url}' class='flexmls_connect__main_image' title='{$main_photo_title}' alt='{$main_photo_alt}' />";
      echo "</div>";

    // photo pager
    echo "<div class='flexmls_connect__photo_pager'>";

      echo "<div class='flexmls_connect__photo_switcher'>";
        echo "<button><img src='{$fmc_plugin_url}/assets/images/left.png' alt='Previous Photo' title='Previous Photo' /></button>";
        echo "&nbsp; <span>1</span> / {$count_photos} &nbsp;";
        echo "<button><img src='{$fmc_plugin_url}/assets/images/right.png' alt='Next Photo' title='Next Photo' /></button>";
      echo "</div>";

      // colobox photo popup
      echo "<button class='photo_click flexmls_connect__ld_larger_photos_link'>View Larger Photos ({$count_photos})</button>";

    echo "</div>";

    // filmstrip
    echo "<div class='flexmls_connect__filmstrip'>";
      if ($count_photos > 0) {
      $ind = 0;
        foreach ($sf['Photos'] as $p) {
          if(!empty($p['Caption'])){
            $img_alt_attr = htmlspecialchars($p['Caption'], ENT_QUOTES);
          }
          elseif(!empty($p['Name'])){
            $img_alt_attr = htmlspecialchars($p['Name'], ENT_QUOTES);
          }
          elseif(!empty($one_line_address)){
            $img_alt_attr = $one_line_address;
          }
          else{
            $img_alt_attr = "Photo for listing #" . $sf['ListingId'];
          }

          $img_title_attr = "Photo for ";
          if(!empty($one_line_address)){
            $img_title_attr .= $one_line_address . " - ";
          }
          $img_title_attr .= "Listing #" . $sf['ListingId'];

          echo "<img src='{$p['UriThumb']}' ind='{$ind}' fullsrc='{$p['UriLarge']}' alt='{$img_alt_attr}' title='{$img_title_attr}' width='65' /> ";

        $ind++;
        }
      }
    echo "</div>";
    echo "</div>";

    // hidden div for colorbox
    echo "<div class='flexmls_connect__hidden'>";
      if ($count_photos > 0) {
        foreach ($sf['Photos'] as $p) {
          echo "<a href='{$p['UriLarge']}' data-connect-ajax='true' rel='p-{$sf['ListingKey']}' title='".htmlspecialchars($p['Caption'], ENT_QUOTES)."'></a>";
        }
      }
      echo "</div>";
    }


    // Open Houses
    if ($count_openhouses > 0 && isset($sf['OpenHouses']) && is_array($sf['OpenHouses']) && isset($sf['OpenHouses'][0])) {
      $this_o = $sf['OpenHouses'][0];
      echo "<div class='flexmls_connect__sr_openhouse'><em>Open House</em> (". $this_o['Date'] ." - ". $this_o['StartTime'] ." - ". $this_o['EndTime'] .")</div>";
    }


    // Property Dscription
    if ( flexmlsConnect::is_not_blank_or_restricted($sf['PublicRemarks']) ) {
      echo "<br /><b>Property Description</b><br />";
      echo $sf['PublicRemarks'];
      echo "<br /><br />";
    }

    // Tabs
    echo "<div class='flexmls_connect__tab_div'>";
    echo "<div class='flexmls_connect__tab active' group='flexmls_connect__detail_group'>Details</div>";
   if ( isset ( $options['google_maps_api_key'] ) && $options['google_maps_api_key'] && flexmlsConnect::is_not_blank_or_restricted($sf['Latitude']) && flexmlsConnect::is_not_blank_or_restricted($sf['Longitude']) ){
        echo "<div class='flexmls_connect__tab' group='flexmls_connect__map_group'>Maps</div>";
    }
      if ($sf['DocumentsCount'])
        echo "<div class='flexmls_connect__tab' group='flexmls_connect__document_group'>Documents</div>";
    echo "</div>";


    // build the Details portion of the page
    echo "<div class='flexmls_connect__tab_group' id='flexmls_connect__detail_group'>";

    // render the results now
    // Create a merged array that includes all custom fields
    $all_property_details = array();
    
    // First, add all fields from $custom_fields
    if (isset($custom_fields['Main']) && is_array($custom_fields['Main'])) {
        foreach ($custom_fields['Main'] as $section_name => $section_fields) {
         // Handle the location/tax/legal section name variations
         $normalized_section_name = $section_name;
         if (in_array(strtolower($section_name), ['location, tax & legal', 'location tax legal', 'location, legal & taxes', 'location legal taxes'])) {
           $normalized_section_name = 'Location, Tax & Legal';
         }
        
        if (!isset($all_property_details[$normalized_section_name])) {
          $all_property_details[$normalized_section_name] = array();
        }
        
        foreach ($section_fields as $field_name => $field_value) {
          if (is_array($field_value)) {
            // Handle array values (like checkboxes)
            $display_values = array();
            foreach ($field_value as $val) {
              if ($val === true || $val === 1) {
                $display_values[] = $field_name;
              } elseif ($val !== false && $val !== 0) {
                $display_values[] = $val;
              }
            }
            if (!empty($display_values)) {
              $all_property_details[$normalized_section_name][] = "<b>{$field_name}:</b> " . implode(', ', $display_values);
            }
          } else {
            // Handle single values
            if ($field_value !== false && $field_value !== 0 && $field_value !== '') {
              $all_property_details[$normalized_section_name][] = "<b>{$field_name}:</b> {$field_value}";
            }
          }
        }
      }
    }
    
    // Then, add fields from $this->property_detail_values (but avoid duplicates)
    if ($this->property_detail_values && is_array($this->property_detail_values)) {
      foreach ($this->property_detail_values as $section_name => $section_fields) {
         // Handle the location/tax/legal section name variations
         $normalized_section_name = $section_name;
         if (in_array(strtolower($section_name), ['location, tax & legal', 'location tax legal', 'location, legal & taxes', 'location legal taxes'])) {
           $normalized_section_name = 'Location, Tax & Legal';
         }
        
        if (!isset($all_property_details[$normalized_section_name])) {
          $all_property_details[$normalized_section_name] = array();
        }
        
        foreach ($section_fields as $field_value) {
          // Check if this field is already in the array to avoid duplicates
          if (!in_array($field_value, $all_property_details[$normalized_section_name])) {
            $all_property_details[$normalized_section_name][] = $field_value;
          }
        }
      }
    }
    
    // Display all the merged property details
    if (!empty($all_property_details)) {
      foreach ($all_property_details as $section_name => $section_fields) {
        if (!empty($section_fields)) {
          echo "<div class='flexmls_connect__ld_detail_table'>";
          echo "<div class='flexmls_connect__detail_header'>{$section_name}</div>";
          echo "<div class='flexmls_connect__ld_property_detail_body columns2'>";

          $details_count = 0;

          foreach ($section_fields as $value) {
            $details_count++;

            if ($details_count === 1) {
              echo "<div class='flexmls_connect__ld_property_detail_row'>";
            }
            echo "<div class='flexmls_connect__ld_property_detail'>{$value}</div>";

            if ($details_count === 2) {
              echo "</div>"; // end row
              $details_count = 0;
            }
          }
          if ($details_count === 1) {
            // details ended earlier without closing the last row
            echo "</div>";
          }
          echo "</div>"; // end details body
          echo "</div>"; // end details table
        }
      }
    }

    echo "<div class='flexmls_connect__ld_detail_table'>";
      echo "<div class='flexmls_connect__detail_header'>Property Features</div>";
      echo "<div class='flexmls_connect__ld_property_detail_body'>";

        foreach ($property_features_values as $k => $v) {
          $value = "<b>".$k.": </b>";
          foreach($v as $x){
            $value .= $x."; ";
          }
          $value = trim($value,"; ");

          echo "<div class='flexmls_connect__ld_property_detail_row'>";
            echo "<div class='flexmls_connect__ld_property_detail'>{$value}</div>";
          echo "</div>";
        }
      echo "</div>";
    echo "</div>";

    if ( flexmlsConnect::is_not_blank_or_restricted( $sf["Supplement"] ) ) {
      echo "<div class='flexmls_connect__ld_detail_table'>";
        echo "<div class='flexmls_connect__detail_header'>Supplements</div>";
        echo "<div class='flexmls_connect__ld_property_detail_body'>";
          echo "<div class='flexmls_connect__ld_property_detail_row'>";
            echo "<div class='flexmls_connect__ld_property_detail'>{$sf["Supplement"]}</div>";
          echo "</div>";
        echo "</div>";
      echo "</div>";
    }

    // build the Room Information portion of the page

    if ( count($sf['Rooms']) > 0 ) {
      $room_count = isset($room_values[0]) ? count($room_values[0]) : false;
      if ($room_count) {
        echo "<div class='flexmls_connect__detail_header'>Room Information</div>";
        echo "<table width='100%'>";
        echo "  <tr>";
        foreach ($room_names as $room){
          echo "    <td><b>{$room}</b></td>";
        }
        echo "  </tr>";

        for ($x = 0; $x < $room_count; $x++)
        {
          echo "  <tr " . ($x % 2 == 0 ? "class='flexmls_connect__sr_zebra_on'" : "") . ">";
          for ($i = 0; $i < count($room_values); $i++){
            echo "<td>{$room_values[$i][$x]}</td>";
          }
          echo "</tr>";
        }
        echo "</table>";
      }

      echo "</div>";

      }

     echo "</div>";

      // map details, if present
      if ( isset ( $options['google_maps_api_key'] ) && $options['google_maps_api_key'] && flexmlsConnect::is_not_blank_or_restricted($sf['Latitude']) && flexmlsConnect::is_not_blank_or_restricted($sf['Longitude']) ){
      echo "<div class='flexmls_connect__tab_group' id='flexmls_connect__map_group'>
        <div id='flexmls_connect__map_canvas' latitude='{$sf['Latitude']}' longitude='{$sf['Longitude']}'></div>
        </div>";
      }


      //Documents tab
      if ($sf['DocumentsCount'])
      {

        echo "<div class='flexmls_connect__tab_group' id='flexmls_connect__document_group' style='display:none'>";
        echo "<div class='flexmls_connect__detail_header'>Listing Documents</div>";
        echo "<table>";

        //Image extensions to show colorbox for
        $fmc_colorbox_extensions = array('gif', 'png');

        foreach ($sf['Documents'] as $fmc_document){
          if ($fmc_document['Privacy']=='Public'){
            echo "<tr class=flexmls_connect__zebra><td>";
            $fmc_extension = explode('.',$fmc_document['Uri']);
            $fmc_extension = ($fmc_extension[count($fmc_extension)-1]);
            if ($fmc_extension == 'pdf'){
              $fmc_file_image = $fmc_plugin_url . '/assets/images/pdf-tiny.gif';
              $fmc_docs_class = "class='fmc_document_pdf'";
            }
            elseif (in_array($fmc_extension, $fmc_colorbox_extensions)){
              $fmc_file_image = $fmc_plugin_url . '/assets/images/image_16.gif';
              $fmc_docs_class = "class='fmc_document_colorbox'";
            }
            else{
              $fmc_file_image = $fmc_plugin_url . '/assets/images/docs_16.gif';
            }
            echo "<a $fmc_docs_class value={$fmc_document['Uri']}><img src='{$fmc_file_image}' align='absmiddle' alt='View Document' title='View Document' /> {$fmc_document['Name']} </a>";

            echo "</td></tr>";
          }

        }
        echo "</table>";
        echo "</div>";
      }


      echo "  <hr class='flexmls_connect__sr_divider'>";

      // Compliance information
      echo "<div class='flexmls_connect__ld_compliance'>";
      fmcSearchResults::compliance_label( $record, "Detail" );
      echo "</div>";

      // Office information
      if ( flexmlsConnect::mls_requires_office_name_in_listing_details() ) {
        $listing_office_label = ($sf['StateOrProvince'] == 'NY') ? 'Listing Courtesy of ' : 'Listing Office: ';
        echo "<div class='flexmls_connect__ld_office_name'>";
        echo "<span class='flexmls_connect__bold_label'>" . esc_html( $listing_office_label ) . "</span>";
        echo esc_html( $sf["ListOfficeName"] );
        echo "</div>";
      }

      // Agent information
      if ( flexmlsConnect::mls_requires_agent_name_in_listing_details() ) {
        echo "<div class='flexmls_connect__ld_agent_info'>";
        echo "<span class='flexmls_connect__bold_label'>Listing Agent: </span>";
        echo esc_html( $sf["ListAgentName"] );

        if ( flexmlsConnect::mls_requires_agent_phone_in_listing_details() ) {
          $phone_number = flexmlsConnect::get_agent_phone_with_fallback( $sf, 'detail' );
          if ( ! empty( $phone_number ) ) {
            echo "<br/>" . esc_html( $phone_number );
          }
        }

        if ( flexmlsConnect::mls_requires_agent_email_in_listing_details() ) {
          echo " | " . esc_html( $sf["ListAgentEmail"] );
        }
        echo "</div>";
      }

    // SourceMLS.org verification badge
    echo $this->render_source_mls_badge( $sf, 'flexmls_connect__source_mls_badge');

    // disclaimer
      echo "  <div class='flexmls_connect__idx_disclosure_text'>";

  if ($sf['StateOrProvince'] != 'NY'){
      foreach ($compList as $reqs){
          if (is_array($reqs) && isset($reqs[1]) && flexmlsConnect::is_not_blank_or_restricted($reqs[1])){
              if (isset($reqs[0]) && $reqs[0] == 'LOGO'){
                  // Skip logo display here - handled by compliance section
                  continue;
                }
              // Skip office and agent info if already handled by dedicated sections
              if (isset($reqs[0]) && $reqs[0] != 'Listing Office:' && $reqs[0] != 'Listing Courtesy of' && $reqs[0] != 'Listing Agent:') {
                echo "<p>{$reqs[0]} {$reqs[1]}</p>";
              }
          }
      }
  }
?>
  <?php if ( array_key_exists( 'CompensationDisclaimer', $sf ) ) : ?>
    <?php if ( flexmlsConnect::is_not_blank_or_restricted( $sf['CompensationDisclaimer'] ) ) : ?>
    <hr />
    <div class="compensation-disclaimer">
      <?php echo $sf['CompensationDisclaimer']; ?>
    </div>
    <hr />
    <?php endif; ?>
  <?php endif; ?>
<?php
      echo "<p>";
      echo flexmlsConnect::get_big_idx_disclosure_text();
      echo "</p><hr />";
?>

<?php if( flexmlsConnect::NAR_broker_attribution( $sf ) ) : ?>
      <div class='listing-req'>Broker Attribution: 
        <?php echo flexmlsConnect::NAR_broker_attribution( $sf ); ?>
      </div>
      <hr />
      <?php endif; ?>

    <div class="fbs-branding" style="text-align: center;">
    <?php echo flexmlsConnect::fbs_products_branding_link(); ?>
    </div>
<?php
      echo "</div>";

  // end
    $content = ob_get_contents();
    ob_end_clean();

    return $content;
  }

  function has_previous_listing() {
    return ( flexmlsConnect::wp_input_get('p') == 'y' ) ? true : false;
  }

  function has_next_listing() {
    return ( flexmlsConnect::wp_input_get('n') == 'y' ) ? true : false;
  }

  function browse_next_url() {
    if ( ! is_array( $this->listing_data ) || ! isset( $this->listing_data['StandardFields']['ListingId'] ) ) {
      return '';
    }
    $link_criteria = $this->search_criteria;
    $link_criteria['id'] = $this->listing_data['StandardFields']['ListingId'];
    return flexmlsConnect::make_nice_tag_url('next-listing', $link_criteria, $this->type);
  }

  function browse_previous_url() {
    if ( ! is_array( $this->listing_data ) || ! isset( $this->listing_data['StandardFields']['ListingId'] ) ) {
      return '';
    }
    $link_criteria = $this->search_criteria;
    $link_criteria['id'] = $this->listing_data['StandardFields']['ListingId'];
    return flexmlsConnect::make_nice_tag_url('prev-listing', $link_criteria,$this->type);
  }

  function wp_meta_description_tag() {
    $description = get_bloginfo('description');
    if ( is_array( $this->listing_data ) && isset( $this->listing_data['StandardFields']['PublicRemarks'] ) && flexmlsConnect::is_not_blank_or_restricted( $this->listing_data['StandardFields']['PublicRemarks'] ) ) {
      $description = $this->listing_data['StandardFields']['PublicRemarks'];
    }
    echo "<meta name='description' content='" . esc_attr( substr( $description, 0, 160 ) ) . "'>";
  }

  function wp_robots_noindex_listing($robots) {
    if ( ! isset( $this->listing_data ) || ! is_array( $this->listing_data ) ) {
      $robots['noindex'] = true;
      $robots['nofollow'] = true;
      return $robots;
    }
    $link_criteria_mls_status = isset( $this->listing_data['StandardFields']['StandardStatus'] ) ? $this->listing_data['StandardFields']['StandardStatus'] : '';
    if ( $link_criteria_mls_status == 'Closed' && get_option( 'blog_public' ) != 0 ) {
      $robots['noindex'] = true;
      $robots['nofollow'] = true;
      return $robots;
    }
    return $robots;
  }

  /**
   * Adds lines to $this->$property_detail_values. The line will only be added
   * if it doesn't already exist.
   *
   * @param $element The element that should be added. Can be array or string.
   * @param $label The label that should precede the element.
   * @param $field_group The group that this line should be added to.
   */
  private function add_property_detail_value($element, $label, $field_group) {

    if ( is_array($element) ){
      foreach ( $element as $value) {
        $this->add_property_detail_value($value, $label, $field_group);
      }
    } else {

      if(!is_array($this->property_detail_values)){
        $this->property_detail_values = Array();
      }

      $line = "<b>".$label.":</b> " . $element;
      if( !array_key_exists($field_group, $this->property_detail_values) or
          is_array($this->property_detail_values) && !in_array($line, $this->property_detail_values[$field_group]) ) {
        $this->property_detail_values[$field_group][] = $line;
      }
    }
  }

  /**
   * Renders the SourceMLS.org verification badge when SourceMLSURL is present.
   *
   * @param array $sf StandardFields from the listing record.
   * @param string|null $wrapper_class Optional CSS class(es) for a wrapping div (e.g. for v2 template).
   * @return string HTML for the badge, or empty string if not applicable.
   */
  function render_source_mls_badge( $sf, $wrapper_class = null ) {
    if ( ! isset( $sf['SourceMLSURL'] ) || ! flexmlsConnect::is_not_blank_or_restricted( $sf['SourceMLSURL'] ) ) {
      return '';
    }
    $img_url = esc_url( $sf['SourceMLSURL'] . '.png' );
    $beacon_url = esc_js( $sf['SourceMLSURL'] );
    $img = '<img src="' . $img_url . '" width="132" height="60" alt="Source MLS Verified" '
      . 'onload="navigator.sendBeacon(\'' . $beacon_url . '\')" '
      . 'onerror="this.style.display=\'none\'">';
    if ( $wrapper_class !== null && $wrapper_class !== '' ) {
      return '<div class="' . esc_attr( $wrapper_class ) . '">' . $img . '</div>';
    }
    return $img;
  }

  /**
   * Renders the Flexmls/FBS products branding link (e.g. for disclosure section).
   *
   * @return string HTML for the branding link.
   */
  function render_flexmls_branding() {
    return flexmlsConnect::fbs_products_branding_link();
  }

  function wpseo_title( $title ){
    $address = flexmlsConnect::format_listing_street_address( $this->listing_data );
    $title = array(
      'title' => $address[ 2 ],
      'site' => get_bloginfo( 'name' )
    );
    $sep = apply_filters( 'document_title_separator', '-' );
    $title = apply_filters( 'document_title_parts', $title );
    $title = implode( " $sep ", array_filter( $title ) );
    $title = capital_P_dangit( $title );
    return $title;
  }

  function wpseo_canonical() {
    // Disable the Yoast canonical tag. We add our own in flexmlsConnectPage::rel_canonical()
    return false;
  }

  function rankmath_canonical() {
    // Disable RankMath canonical tag. We add our own in flexmlsConnectPage::rel_canonical()
    return false;
  }

  function aioseo_canonical() {
    // Disable All in One SEO canonical tag. We add our own in flexmlsConnectPage::rel_canonical()
    return false;
  }

  function seo_framework_canonical() {
    // Disable The SEO Framework canonical tag. We add our own in flexmlsConnectPage::rel_canonical()
    return false;
  }

  function seopress_canonical() {
    // Disable SEOPress canonical tag. We add our own in flexmlsConnectPage::rel_canonical()
    return false;
  }

  function open_graph_tags() {
    $site_name = get_bloginfo('name');
    $title = flexmlsConnect::make_nice_address_title($this->listing_data);
    $sf = ( is_array($this->listing_data) && isset($this->listing_data['StandardFields']) && is_array($this->listing_data['StandardFields']) )
      ? $this->listing_data['StandardFields']
      : [];
    
    // Get the primary listing image
    $thumbnail = '';
    $thumbnail_alt = '';
    if ( isset($this->listing_data['StandardFields']['Photos']) && is_array($this->listing_data['StandardFields']['Photos']) && isset($this->listing_data['StandardFields']['Photos'][0]) ) {
      $thumbnail = $this->listing_data['StandardFields']['Photos'][0]['Uri1280'];
      // Use photo caption, name, or address as alt text
      if ( !empty($this->listing_data['StandardFields']['Photos'][0]['Caption']) ) {
        $thumbnail_alt = esc_attr($this->listing_data['StandardFields']['Photos'][0]['Caption']);
      } elseif ( !empty($this->listing_data['StandardFields']['Photos'][0]['Name']) ) {
        $thumbnail_alt = esc_attr($this->listing_data['StandardFields']['Photos'][0]['Name']);
      } else {
        $thumbnail_alt = esc_attr($title);
      }
    }
    
    $description = ( isset($sf['PublicRemarks']) ) ? substr($sf['PublicRemarks'], 0, 200) : '';
    $url = flexmlsConnect::make_nice_address_url($this->listing_data);
    
    // Get locale (default to en_US, but can be filtered)
    $locale = apply_filters( 'flexmls_opengraph_locale', get_locale() );
    // Ensure locale format is correct (e.g., en_US instead of en_US.UTF-8)
    if ( strpos( $locale, '.' ) !== false ) {
      $locale = substr( $locale, 0, strpos( $locale, '.' ) );
    }
    // Convert to format like en_US (underscore, not hyphen)
    $locale = str_replace( '-', '_', $locale );
    
    // Get listing price for OpenGraph price tags
    $price_amount = '';
    $price_currency = 'USD'; // Default to USD, can be filtered
    $price_currency = apply_filters( 'flexmls_opengraph_price_currency', $price_currency );
    
    if ( isset( $sf['CurrentPricePublic'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['CurrentPricePublic'] ) ) {
      $price_amount = preg_replace( '/[^0-9]/', '', $sf['CurrentPricePublic'] );
    } elseif ( isset( $sf['ListPrice'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['ListPrice'] ) ) {
      $price_amount = preg_replace( '/[^0-9]/', '', $sf['ListPrice'] );
    } elseif ( isset( $sf['ClosePrice'] ) && isset( $sf['MlsStatus'] ) && flexmlsConnect::is_not_blank_or_restricted( $sf['ClosePrice'] ) && $sf['MlsStatus'] == 'Closed' ) {
      $price_amount = preg_replace( '/[^0-9]/', '', $sf['ClosePrice'] );
    }

    echo "<!-- Flexmls® IDX WordPress Plugin - OpenGraph Tags for Listing Detail pages -->" . PHP_EOL;
    
    // Essential OpenGraph tags
    echo "<meta property='og:site_name' content='" . esc_attr($site_name) . "' />" . PHP_EOL;
    echo "<meta property='og:title' content='" . esc_attr($title) . "' />" . PHP_EOL;
    echo "<meta property='og:description' content=\"" . esc_attr($description) . "\" />" . PHP_EOL;
    echo "<meta property='og:url' content='" . esc_url($url) . "' />" . PHP_EOL;
    echo "<meta property='og:type' content='website' />" . PHP_EOL;
    echo "<meta property='og:locale' content='" . esc_attr($locale) . "' />" . PHP_EOL;
    
    // Image tags with enhanced metadata
    if ( !empty($thumbnail) ) {
      echo "<meta property='og:image' content='" . esc_url($thumbnail) . "' />" . PHP_EOL;
      echo "<meta property='og:image:secure_url' content='" . esc_url($thumbnail) . "' />" . PHP_EOL;
      if ( !empty($thumbnail_alt) ) {
        echo "<meta property='og:image:alt' content='" . $thumbnail_alt . "' />" . PHP_EOL;
      }
      // Recommended image dimensions for optimal display (1200x630 is ideal, but we'll use 1280 which is close)
      echo "<meta property='og:image:width' content='1280' />" . PHP_EOL;
      echo "<meta property='og:image:height' content='720' />" . PHP_EOL;
    }
    
    // Price information (very useful for real estate listings)
    if ( !empty($price_amount) ) {
      echo "<meta property='product:price:amount' content='" . esc_attr($price_amount) . "' />" . PHP_EOL;
      echo "<meta property='product:price:currency' content='" . esc_attr($price_currency) . "' />" . PHP_EOL;
    }
    
    // Twitter Card tags
    echo "<meta name='twitter:card' content='summary_large_image' />" . PHP_EOL;
    echo "<meta name='twitter:title' content='" . esc_attr($title) . "' />" . PHP_EOL;
    echo "<meta name='twitter:description' content=\"" . esc_attr($description) . "\" />" . PHP_EOL;
    if ( !empty($thumbnail) ) {
      echo "<meta name='twitter:image' content='" . esc_url($thumbnail) . "' />" . PHP_EOL;
      if ( !empty($thumbnail_alt) ) {
        echo "<meta name='twitter:image:alt' content='" . $thumbnail_alt . "' />" . PHP_EOL;
      }
    }
    
    // Optional Twitter tags (can be set via filters if needed)
    $twitter_site = apply_filters( 'flexmls_twitter_site', '' );
    if ( !empty($twitter_site) ) {
      echo "<meta name='twitter:site' content='" . esc_attr($twitter_site) . "' />" . PHP_EOL;
    }
    
    $twitter_creator = apply_filters( 'flexmls_twitter_creator', '' );
    if ( !empty($twitter_creator) ) {
      echo "<meta name='twitter:creator' content='" . esc_attr($twitter_creator) . "' />" . PHP_EOL;
    }
    
    echo "<!-- / Flexmls® IDX WordPress Plugin -->" . PHP_EOL;
  }

  function filter_presenters( $presenters ) {
    if ( ! $this->is_listing_detail_page() ) {
      return $presenters;
    }

    // Filter out all OpenGraph and Twitter Card presenters from Yoast
    if ( is_array( $presenters ) ) {
      foreach ( $presenters as $key => $presenter ) {
        // Check if presenter is an OpenGraph presenter
        if ( is_object( $presenter ) && (
          $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Title_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Description_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Image_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Url_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Type_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Site_Name_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Locale_Presenter ||
          strpos( get_class( $presenter ), 'Yoast\WP\SEO\Presenters\Open_Graph' ) !== false
        ) ) {
          unset( $presenters[ $key ] );
        }
        // Check if presenter is a Twitter Card presenter
        elseif ( is_object( $presenter ) && (
          $presenter instanceof \Yoast\WP\SEO\Presenters\Twitter\Card_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Twitter\Title_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Twitter\Description_Presenter ||
          $presenter instanceof \Yoast\WP\SEO\Presenters\Twitter\Image_Presenter ||
          strpos( get_class( $presenter ), 'Yoast\WP\SEO\Presenters\Twitter' ) !== false
        ) ) {
          unset( $presenters[ $key ] );
        }
      }
    }

    return $presenters;
  }

  /**
   * Check if we're on a listing detail page
   * 
   * @return bool True if on listing detail page, false otherwise
   */
  private function is_listing_detail_page() {
    global $fmc_special_page_caught;
    return ( isset( $fmc_special_page_caught['type'] ) && $fmc_special_page_caught['type'] === 'listing-details' ) ||
           isset( $this->listing_data );
  }

  /**
   * Setup SEOPress OpenGraph and Twitter tag disabling
   */
  private function setup_seopress_disabling() {
    // Try multiple hooks to ensure we catch SEOPress actions early enough
    $hooks = array(
      array( 'init', 999 ),
      array( 'template_redirect', 0 ),
      array( 'wp', 0 ),
    );
    foreach ( $hooks as $hook ) {
      add_action( $hook[0], array( $this, 'remove_seopress_social_actions' ), $hook[1] );
    }
    
    // Filter individual SEOPress social meta tag values
    $seopress_filters = array(
      'seopress_social_og_title',
      'seopress_social_og_desc',
      'seopress_social_og_image',
      'seopress_social_og_url',
      'seopress_social_og_site_name',
      'seopress_social_og_locale',
      'seopress_social_og_type',
      'seopress_social_og_author',
      'seopress_social_og_publisher',
      'seopress_social_fb_pages',
      'seopress_social_fb_admins',
      'seopress_social_fb_app_id',
      'seopress_social_twitter_card',
      'seopress_social_twitter_site',
      'seopress_social_twitter_creator',
      'seopress_social_twitter_title',
      'seopress_social_twitter_desc',
      'seopress_social_twitter_image',
    );
    foreach ( $seopress_filters as $filter ) {
      add_filter( $filter, array( $this, 'disable_seopress_opengraph' ), 999 );
    }
    
    // Additional filters to completely disable SEOPress social output
    $disable_filters = array(
      'seopress_social_twitter' => '__return_false',
      'seopress_social_og' => '__return_false',
      'seopress_disable_twitter' => '__return_true',
      'seopress_disable_og' => '__return_true',
    );
    foreach ( $disable_filters as $filter => $callback ) {
      add_filter( $filter, $callback, 999 );
    }
    
    // Hook into template output to remove SEOPress tags as last resort
    add_action( 'template_redirect', array( $this, 'start_seopress_output_buffer' ), 999 );
  }

  /**
   * Disable Rank Math OpenGraph tags on listing detail pages
   */
  function disable_rankmath_opengraph() {
    if ( ! $this->is_listing_detail_page() ) {
      return;
    }
    
    remove_all_actions( 'rank_math/opengraph/facebook' );
    remove_all_actions( 'rank_math/opengraph/twitter' );
  }

  /**
   * Disable All in One SEO Facebook OpenGraph tags on listing detail pages
   */
  function disable_aioseo_facebook_tags( $facebookMeta ) {
    return $this->is_listing_detail_page() ? array() : $facebookMeta;
  }

  /**
   * Disable All in One SEO Twitter Card tags on listing detail pages
   */
  function disable_aioseo_twitter_tags( $twitterMeta ) {
    return $this->is_listing_detail_page() ? array() : $twitterMeta;
  }

  /**
   * Remove SEOPress social meta tag actions on listing detail pages
   */
  function remove_seopress_social_actions() {
    if ( ! $this->is_listing_detail_page() ) {
      return;
    }
    
    // Remove SEOPress OpenGraph and Twitter Card actions with various priorities
    // SEOPress may use different priorities, so we try common ones
    $priorities = array( 1, 10, 99, 100 );
    foreach ( $priorities as $priority ) {
      remove_action( 'wp_head', 'seopress_social_og', $priority );
      remove_action( 'wp_head', 'seopress_social_twitter', $priority );
      remove_action( 'wp_head', 'seopress_social_facebook', $priority );
    }
    
    // Also try removing by class method if SEOPress uses object-oriented approach
    if ( class_exists( 'SEOPress\Actions\Social\TwitterCard' ) ) {
      remove_action( 'wp_head', array( 'SEOPress\Actions\Social\TwitterCard', 'twitter_card' ), 1 );
    }
    if ( class_exists( 'SEOPress\Actions\Social\OpenGraph' ) ) {
      remove_action( 'wp_head', array( 'SEOPress\Actions\Social\OpenGraph', 'opengraph' ), 1 );
    }
    
    // Remove all actions that might output Twitter or OpenGraph tags
    global $wp_filter;
    if ( isset( $wp_filter['wp_head'] ) ) {
      foreach ( $wp_filter['wp_head']->callbacks as $priority => $callbacks ) {
        foreach ( $callbacks as $callback ) {
          $function = isset( $callback['function'] ) ? $callback['function'] : null;
          if ( is_string( $function ) && (
            strpos( $function, 'seopress_social_twitter' ) !== false ||
            strpos( $function, 'seopress_social_og' ) !== false ||
            strpos( $function, 'seopress_social_facebook' ) !== false
          ) ) {
            remove_action( 'wp_head', $function, $priority );
          }
        }
      }
    }
  }

  /**
   * Start output buffering to remove SEOPress social tags from final HTML
   */
  function start_seopress_output_buffer() {
    if ( ! $this->is_listing_detail_page() ) {
      return;
    }
    
    ob_start( array( $this, 'remove_seopress_tags_from_output' ) );
  }

  /**
   * Remove SEOPress Twitter Card tags from output buffer
   */
  function remove_seopress_tags_from_output( $buffer ) {
    // Remove SEOPress Twitter Card meta tags that might have slipped through
    $patterns = array(
      '/<meta\s+name=["\']twitter:card["\'][^>]*\/?>/i',
      '/<meta\s+name=["\']twitter:site["\'][^>]*\/?>/i',
      '/<meta\s+name=["\']twitter:creator["\'][^>]*\/?>/i',
      '/<meta\s+name=["\']twitter:title["\'][^>]*\/?>/i',
    );
    
    foreach ( $patterns as $pattern ) {
      $buffer = preg_replace( $pattern, '', $buffer );
    }
    
    return $buffer;
  }

  /**
   * Disable SEOPress OpenGraph tags on listing detail pages
   */
  function disable_seopress_opengraph( $value ) {
    return $this->is_listing_detail_page() ? false : $value;
  }

  /**
   * Disable The SEO Framework OpenGraph tags on listing detail pages
   */
  function disable_seo_framework_opengraph( $output ) {
    return $this->is_listing_detail_page() ? '' : $output;
  }

  /**
   * Disable Jetpack OpenGraph tags on listing detail pages
   */
  function disable_jetpack_opengraph( $enabled ) {
    return $this->is_listing_detail_page() ? false : $enabled;
  }

	function iframe_from_html_or_url( $html_or_url ) {
		if ( strpos( $html_or_url, '<iframe' ) !== false ) {
			return $html_or_url;
		} else {
			return '<iframe src="' . esc_url( $html_or_url ) . '"></iframe>';
		}
	}
}
