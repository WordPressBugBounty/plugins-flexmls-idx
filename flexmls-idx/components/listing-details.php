<?php
class fmcListingDetails extends fmcWidget {

  function __construct() {
    global $fmc_widgets;

    $widget_info = $fmc_widgets[ get_class($this) ];

    $widget_ops = array( 'description' => $widget_info['description'] );
//    $this->WP_Widget( get_class($this) , $widget_info['title'], $widget_ops);

    // have WP replace instances of [first_argument] with the return from the second_argument function
    add_shortcode($widget_info['shortcode'], array(&$this, 'shortcode'));

    // register where the AJAX calls should be routed when they come in
    add_action('wp_ajax_'.get_class($this).'_shortcode', array(&$this, 'shortcode_form') );
    add_action('wp_ajax_'.get_class($this).'_shortcode_gen', array(&$this, 'shortcode_generate') );
    add_action('wp_ajax_nopriv_'.get_class($this).'_shortcode_gen', array(&$this, 'shortcode_generate') );
    add_action(get_class($this).'_shortcode_gen_gtb', array(&$this, 'shortcode_generate_gtb') );

    add_action('wp_ajax_'.get_class($this).'_schedule_showing', array(&$this, 'schedule_showing') );
    add_action('wp_ajax_nopriv_'.get_class($this).'_schedule_showing', array(&$this, 'schedule_showing') );

        add_action('wp_ajax_'.get_class($this).'_contact', array(&$this, 'contact') );
        add_action('wp_ajax_nopriv_'.get_class($this).'_contact', array(&$this, 'contact') );

    }

  /**
   * Resolve listing agent/office emails from Spark. Never trust POST recipients.
   *
   * @param string $listing_key Spark ListingKey.
   * @param string $listing_id  MLS ListingId.
   * @return array{found:bool,agent:string,office:string}
   */
  function resolve_mls_listing_emails( $listing_key = '', $listing_id = '' ) {
    global $fmc_api;

    $emails = array(
      'found'  => false,
      'agent'  => '',
      'office' => '',
    );

    $listing_key = sanitize_text_field( (string) $listing_key );
    $listing_id  = sanitize_text_field( (string) $listing_id );

    if ( '' === $listing_key && '' === $listing_id ) {
      return $emails;
    }

    $filter = '';
    if ( '' !== $listing_key ) {
      $filter = "ListingKey Eq '" . str_replace( "'", "''", $listing_key ) . "'";
    } else {
      $filter = "ListingId Eq '" . str_replace( "'", "''", $listing_id ) . "'";
    }

    $result = $fmc_api->GetListings(
      array(
        '_filter' => $filter,
        '_limit'  => 1,
        '_select' => 'ListAgentEmail,ListOfficeEmail,ListingKey,ListingId',
      )
    );

    if ( ! is_array( $result ) || empty( $result[0]['StandardFields'] ) || ! is_array( $result[0]['StandardFields'] ) ) {
      return $emails;
    }

    $emails['found'] = true;
    $sf              = $result[0]['StandardFields'];
    if ( ! empty( $sf['ListAgentEmail'] ) && is_string( $sf['ListAgentEmail'] ) ) {
      $emails['agent'] = $sf['ListAgentEmail'];
    }
    if ( ! empty( $sf['ListOfficeEmail'] ) && is_string( $sf['ListOfficeEmail'] ) ) {
      $emails['office'] = $sf['ListOfficeEmail'];
    }

    return $emails;
  }

  /**
   * Strip CR/LF (and encoded variants) from values used in mail headers.
   *
   * @param string $value Raw header value.
   * @return string
   */
  function sanitize_mail_header_value( $value ) {
    $value = (string) $value;
    $value = str_ireplace( array( '%0a', '%0d' ), '', $value );
    return str_replace( array( "\r", "\n" ), '', $value );
  }

  /**
   * Pick Mls recipient from Spark listing data (agent, then office).
   *
   * @param array $resolved From resolve_mls_listing_emails().
   * @return string Empty if neither is usable.
   */
  function mls_recipient_from_resolved( $resolved ) {
    if ( ! is_array( $resolved ) ) {
      return '';
    }
    if ( flexmlsConnect::is_not_blank_or_restricted( $resolved['agent'] ?? '' ) ) {
      $email = sanitize_email( $resolved['agent'] );
      if ( is_email( $email ) ) {
        return $email;
      }
    }
    if ( flexmlsConnect::is_not_blank_or_restricted( $resolved['office'] ?? '' ) ) {
      $email = sanitize_email( $resolved['office'] );
      if ( is_email( $email ) ) {
        return $email;
      }
    }
    return '';
  }

  function schedule_showing($attr = array()) {
    flexmls_verify_ajax_nonce();
    global $fmc_api;
    $api_my_account = $fmc_api->GetMyAccount();
    if ( ! is_array( $api_my_account ) ) {
      die( 'Unable to load account.' );
    }
    $send_email = isset( $api_my_account['Emails'][0]['Address'] ) ? $api_my_account['Emails'][0]['Address'] : '';

    //This is our bot blocker... if it is set, then pretend like everything went okay
    if (!empty($_POST['flexmls_connect__important'])){
      exit("SUCCESS");
    }

    $action = isset( $api_my_account['UserType'] ) ? $api_my_account['UserType'] : '';

    if ( $action === 'Mls' ) {
      $listing_key = flexmlsConnect::wp_input_get_post( 'flexmls_connect__listing_key' );
      $listing_id  = flexmlsConnect::wp_input_get_post( 'flexmls_connect__listing_id' );
      $resolved    = $this->resolve_mls_listing_emails( $listing_key, $listing_id );
      if ( empty( $resolved['found'] ) ) {
        die( 'There was an error sending the e-mail: Listing not found.' );
      }
      $send_email = $this->mls_recipient_from_resolved( $resolved );
      if ( '' === $send_email ) {
        // No listing agent/office email in Spark — fall back to Spark message_me for the Mls account.
        $action = 'SendToMls';
      }
    }

    try {
      $from_email = sanitize_email( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__from' ) );
      $from_name  = sanitize_text_field( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__from_name' ) );
      $subject    = $this->sanitize_mail_header_value( sanitize_text_field( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__subject' ) ) );
      $message_in = (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__message' );
      $page_lead  = esc_url_raw( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__page_lead' ) );
      $phone      = sanitize_text_field( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__phone' ) );
      $to_name    = sanitize_text_field( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__to_name' ) );

      if ( ! is_email( $from_email ) ) {
        throw new Exception( 'From e-mail is invalid' );
      }
      if ( $action === 'Mls' ) {
        if ( ! is_email( $send_email ) ) {
          throw new Exception( 'Unable to resolve listing agent e-mail.' );
        }
        $headers  = 'From: ' . $this->sanitize_mail_header_value( $from_email ) . "\r\n";
        $message  = $message_in . "\r\n\r\n" . $from_name . ' <' . $from_email . ">\r\n";
        $message .= 'Sent From Page: ' . $page_lead . "\r\n";
        wp_mail( $send_email, $subject, $message, $headers );
        die( 'SUCCESS' );
      } else {
          $body =  "This message has been auto-generated by your wordpress site.\n\n This person has scheduled a show:\n";
          $body .= "This message was sent from this page: " . $page_lead . "\n";
          $body .= "To Agent: " . $to_name . "\n";
          $body .= "Name: " . $from_name . "\n";
          $body .= "Email: " . $from_email . "\n\n";
          $body .= "Phone: " . $phone . "\n\n";
          $body .= "Message:\n";
          $body .= $message_in;

          $Contact = array();
          $Contact['DisplayName'] = $from_name;
          $Contact['PrimaryEmail'] = $from_email;
          $Contact['PrimaryPhoneNumber'] = $phone;
          flexmlsConnect::add_contact($Contact);
          if ( flexmlsConnect::message_me( $subject, $body, $from_email ) ) {
            die( 'SUCCESS' );
          }
          else {
            throw new Exception("An Error occured while attempting to contact the site.");
          }
      }
    } 
       catch(Exception $e) {
          die('There was an error sending the e-mail: ' .$e->getMessage());
        }
        return;
  }


  function contact($attr = array()) {
    flexmls_verify_ajax_nonce();
    global $fmc_api;
    $api_my_account = $fmc_api->GetMyAccount();
    if ( ! is_array( $api_my_account ) ) {
      die( 'Unable to load account.' );
    }
    $send_email = isset( $api_my_account['Emails'][0]['Address'] ) ? $api_my_account['Emails'][0]['Address'] : '';

    //This is our bot blocker... if it is set, then pretend like everything went okay
    if (!empty($_POST['flexmls_connect__important'])){
      exit("SUCCESS");
    }

    $action = isset( $api_my_account['UserType'] ) ? $api_my_account['UserType'] : '';

    if ( $action === 'Mls' ) {
      $listing_key = flexmlsConnect::wp_input_get_post( 'flexmls_connect__listing_key' );
      $listing_id  = flexmlsConnect::wp_input_get_post( 'flexmls_connect__listing_id' );
      // Legacy contact form posted ListingId as flexmls_connect__mytype.
      if ( empty( $listing_id ) ) {
        $listing_id = flexmlsConnect::wp_input_get_post( 'flexmls_connect__mytype' );
      }
      $resolved = $this->resolve_mls_listing_emails( $listing_key, $listing_id );
      if ( empty( $resolved['found'] ) ) {
        die( 'There was an error sending the e-mail: Listing not found.' );
      }
      $send_email = $this->mls_recipient_from_resolved( $resolved );
      if ( '' === $send_email ) {
        $action = 'SendToMls';
      }
    }

    try{
      $from_email = sanitize_email( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__from' ) );
      $from_name  = sanitize_text_field( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__from_name' ) );
      $subject    = $this->sanitize_mail_header_value( sanitize_text_field( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__subject' ) ) );
      $message_in = (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__message' );
      $page_lead  = esc_url_raw( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__page_lead' ) );
      $phone      = sanitize_text_field( (string) flexmlsConnect::wp_input_get_post( 'flexmls_connect__phone' ) );

      if ( ! is_email( $from_email ) ) {
          throw new Exception( 'From e-mail is invalid' );
      }
      if ( $action === 'Mls' ) {
        if ( ! is_email( $send_email ) ) {
          throw new Exception( 'Unable to resolve listing agent e-mail.' );
        }
        $headers = 'From: ' . $this->sanitize_mail_header_value( $from_email ) . "\r\n";
        $message = $message_in . "\r\n\r\n" . $from_name;
        wp_mail( $send_email, $subject, $message, $headers );
        die( 'SUCCESS' );
      }
      else{

        $body =  "This message has been auto-generated by your wordpress site." . PHP_EOL; 
        $body .= "The following person has attempted to contact you:" . PHP_EOL;
        $body .= "This message was sent from this page: " . $page_lead . "\n";
        $body .= "Name: " . $from_name . PHP_EOL;
        $body .= "Email: " . $from_email . PHP_EOL;
        $body .= "Phone: " . $phone . PHP_EOL;
        $body .= "Message:" . PHP_EOL;

        $body .= $message_in;
        $Contact = array();
        $Contact['DisplayName'] = $from_name;
        $Contact['PrimaryEmail'] = $from_email;
        $Contact['PrimaryPhoneNumber'] = $phone;

        flexmlsConnect::add_contact($Contact);
        if ( flexmlsConnect::message_me( $subject, $body, $from_email ) ) {
          die( 'SUCCESS' );
        }
        else {
          throw new Exception("An Error occured while attempting to contact the site.");
        }
      }
    } catch(Exception $e) {
      die('There was an error sending the e-mail: ' .$e->getMessage());
    }
    return;
  }



  function jelly($args, $settings, $type) {
    global $fmc_api;

    $listing_id = isset( $settings['listing'] ) ? trim( (string) $settings['listing'] ) : '';
    $custom_page = new flexmlsConnectPageListingDetails($fmc_api);
    $custom_page->pre_tasks( '-mls_' . $listing_id );
    /* if($settings['integration'] == 'elementor'){
      return $custom_page->generate_page(false);
    } else {
    } */
    return $custom_page->generate_page(true);

  }


  function widget($args, $instance) {
    echo $this->jelly($args, $instance, "widget");
  }


  function shortcode($attr = array()) {

    $args = array(
        'before_title' => '<h3>',
        'after_title' => '</h3>',
        'before_widget' => '',
        'after_widget' => ''
        );

    //return var_dump($attr);    

    return $this->jelly($args, $attr, "shortcode");

  }


  function settings_form($instance) {
    $listing = array_key_exists('listing', $instance) ? esc_attr($instance['listing']) : null;

    $return  = "<p>\n";
    $return .= "<label for='".$this->get_field_id('listing')."'>" . __('MLS#:') . "</label>\n";
    $return .= "<input fmc-field='listing' fmc-type='text' type='text' class='widefat' id='".$this->get_field_id('listing')."' name='".$this->get_field_name('listing')."' value='{$listing}'>\n";
    $return .= "</p>\n";

    $return .= "<input type='hidden' name='shortcode_fields_to_catch' value='listing' />\n";
    $return .= "<input type='hidden' name='widget' value='". get_class($this) ."' />\n";

    return $return;

  }

  function integration_view_vars(){
    $vars = array();
    $vars['title'] = 'MLS#';
    $vars['param'] = 'listing';
    $vars['value'] = '';
    return $vars;
  }


  function update($new_instance, $old_instance) {
    $instance = $old_instance;

    $instance['listing'] = strip_tags($new_instance['listing']);

    return $instance;
  }

}
