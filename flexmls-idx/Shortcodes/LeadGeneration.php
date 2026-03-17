<?php
namespace FlexMLS\Shortcodes;

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

class LeadGeneration {

	public static function tinymce_form(){
		$widget = new \FlexMLS\Widgets\LeadGeneration();

		$response = array(
			'title' => 'Lead Generation / Contact Form',
			'body' => $widget->get_form( array(
				'is_tinymce' => 1
			) )
		);
		exit( json_encode( $response ) );
	}

	public static function integration_view_vars(){
		$vars = array();

		$vars['title'] = 'Lead Generation';        
        $vars['blurb'] = '';
        $vars['success_message'] = 'Thank you for your request';
        $vars['buttontext'] = "Submit";
        $vars["use_captcha"] = 0;
    
        return $vars;
	}

	public static function shortcode( $atts, $content = null ){
		$vars = self::integration_view_vars();

		$atts = shortcode_atts( array(
			'title' => $vars['title'],
			'blurb' => $vars['blurb'],
			'success' => $vars['success_message'],
			'buttontext' => $vars['buttontext'],
			'use_captcha' => $vars["use_captcha"],
			'is_widget' => 0,
			'before_title' => '<h3>',
			'after_title' => '</h3>',
			'widget_id' => ''
		), $atts, 'lead_generation' );

		global $wp;
		$fmc_settings = get_option( 'fmc_settings' );
		
		// Check if API credentials are available
		if( empty( $fmc_settings[ 'api_key' ] ) || empty( $fmc_settings[ 'api_secret' ] ) ){
			$html = '';
			if( !empty( $atts[ 'title' ] ) ){
				$html .= $atts[ 'before_title' ] . apply_filters( 'widget_title', $atts[ 'title' ] ) . $atts[ 'after_title' ];
			}
			$html .= '<p>This widget is temporarily unavailable. Please refresh the page or try again later.</p>';
			return $html;
		}
		
		if( empty( $atts[ 'title' ] ) && 1 == $fmc_settings[ 'default_titles' ] ){
			$atts[ 'title' ] = 'Lead Generation';
		}

		global $fmc_api;

		// Check if API is actually working by trying to get preferences
		// This will capture error codes like 1010 (Plugin Key Disabled)
		$preferences = $fmc_api->GetPreferences();
		
		// If preferences is false, API is not available - check for error codes
		if( $preferences === false || !is_array( $preferences ) ){
			$html = '';
			if( !empty( $atts[ 'title' ] ) ){
				$html .= $atts[ 'before_title' ] . apply_filters( 'widget_title', $atts[ 'title' ] ) . $atts[ 'after_title' ];
			}
			
			// Use widget_not_available for consistent error messaging with error codes
			// Error code should be set in $fmc_api->last_error_code from the GetPreferences() call
			$error_message = flexmlsConnect::widget_not_available( $fmc_api, false, array(), array( 'title' => !empty( $atts[ 'title' ] ) ? $atts[ 'title' ] : '' ) );
			// Extract just the message part (without widget wrapper since this is a shortcode)
			if ( preg_match( '/<h3>.*?<\/h3>(.*)/s', $error_message, $matches ) ) {
				$html .= $matches[1];
			} elseif ( preg_match( '/(This widget.*?\.)/', $error_message, $matches ) ) {
				$html .= '<p>' . $matches[1] . '</p>';
			} else {
				$html .= '<p>This widget is temporarily unavailable. Please refresh the page or try again later.</p>';
			}
			return $html;
		}

		$page = new \flexmlsConnectPageCore( $fmc_api );
		$page->render_template_styles();

		$flexmls_button_background_color = ( isset( $fmc_settings['search_listing_template_primary_color'] ) ) ? 'flexmls-primary-color-background' : 'flexmls_connect__button' ;

		$random_string = uniqid( 'j' );

		$html  = '';
		if( !empty( $atts[ 'title' ] ) ){
			$html .= $atts[ 'before_title' ] . apply_filters( 'widget_title', $atts[ 'title' ] ) . $atts[ 'after_title' ];
		}
		if( !empty( $atts[ 'blurb' ] ) ){
			$html .= wpautop( $atts[ 'blurb' ] );
		}
		$label_id_prefix = !empty( $atts[ 'widget_id' ] ) ? $atts[ 'widget_id' ] . '-' : '';
		$html .= '<form id="' . $label_id_prefix . $random_string . '">';
			$html .= '	<div class="flexmls_connect__form_row">
							<input class="flexmls_connect__form_input" type="text" name="name" id="' . $label_id_prefix . 'name" placeholder="Your Name" aria-label="Your Name" required>
						</div>';
			$html .= '	<div class="flexmls_connect__form_row">
							<input class="flexmls_connect__form_input" type="email" name="email" id="' . $label_id_prefix . 'email" placeholder="Email Address" aria-label="Email Address" required>
						</div>';

			// Check if RequiredFields exists and is an array before using in_array()
			$required_fields = ( is_array( $preferences ) && isset( $preferences[ 'RequiredFields' ] ) && is_array( $preferences[ 'RequiredFields' ] ) ) ? $preferences[ 'RequiredFields' ] : array();

			if( in_array( 'address', $required_fields ) ){
				$html .= '	<div class="flexmls_connect__form_row">
								<input class="flexmls_connect__form_input" type="text" name="address" id="' . $label_id_prefix . 'address" placeholder="Home Address" aria-label="Home Address" required>
							</div>
							<div class="flexmls_connect__form_row">
								<input class="flexmls_connect__form_input" type="text" name="city" id="' . $label_id_prefix . 'city" placeholder="City" aria-label="City" required>
							</div>
							<div class="flexmls_connect__form_row">
								<input class="flexmls_connect__form_input" type="text" name="state" id="' . $label_id_prefix . 'state" placeholder="State" aria-label="State" required>
							</div>
							<div class="flexmls_connect__form_row">
								<input class="flexmls_connect__form_input" type="text" name="zip" id="' . $label_id_prefix . 'zip" placeholder="ZIP Code" aria-label="ZIP Code" required>
							</div>';
			}
			if( in_array( 'phone', $required_fields ) ){
				$html .= '	<div class="flexmls_connect__form_row">
								<input class="flexmls_connect__form_input" type="tel" name="phone" id="' . $label_id_prefix . 'phone" placeholder="Phone Number" aria-label="Phone Number" required>
							</div>';
			}
			$html .= '	<div class="flexmls_connect__form_row">
							<textarea class="flexmls_connect__form_textarea" name="message_body" id="' . $label_id_prefix . 'message" rows="5" placeholder="Your Message" aria-label="Your Message"></textarea>
						</div>';
			$FlexmlsConnectBase = new \flexmlsConnect();
			$html .= (isset($fmc_settings['contact_disclaimer'])) ? "<small class='flexmls-text-small'>" . $FlexmlsConnectBase::get_contact_disclaimer() . "</small>" : '';
			$html .= '	<div class="flexmls_connect__form_row flexmls_connect__form_row_color">
							<label for="' . $random_string . '">' . $random_string . '</label>
							<input type="text" name="color" id="' . $random_string . '" tabindex="-1">
						</div>';
			$html .= '	<input type="hidden" name="source" value="' . ( $wp->did_permalink ? home_url( add_query_arg( array(), $wp->request ) ) : home_url( '?' . $wp->query_string ) ) . '">';
			$html .= '	<input type="hidden" name="success" value="' . esc_attr( $atts[ 'success' ] ) . '">';
			$html .= '	<div class="flexmls_connect__form_footer">
							<span class="flexmls_loading_svg" style="display: none;">' . \FlexMLS\Admin\Utilities::get_loading_svg() . '</span>
							<button class="flexmls_connect__form_submit flexmls_leadgen_button ' .  $flexmls_button_background_color .'" type="button" data-form="#' . $label_id_prefix . $random_string . '">' . $atts[ 'buttontext' ] . '</button>
						</div>';
		$html .= '</form>';

		return $html;
	}

	public static function submit_lead(){
		flexmls_verify_ajax_nonce();
		$result = array(
			'message' => '',
			'success' => 1
		);
		if( !empty( $_POST[ 'honeypot' ] ) ){
			// This is spam. Send a success message and call it quits.
			exit( json_encode( array( 'success' => 1 ) ) );
		}
		$data = array(
			'DisplayName' => sanitize_text_field( $_POST[ 'name' ] ),
			'PrimaryEmail' => is_email( $_POST[ 'email' ] ),
			'SourceURL' => filter_var( $_POST[ 'source' ], FILTER_VALIDATE_URL )
		);
		if( array_key_exists( 'address', $_POST ) ){
			$data[ 'HomeStreetAddress' ] = sanitize_text_field( $_POST[ 'address' ] );
			$data[ 'HomeLocality' ] = sanitize_text_field( $_POST[ 'city' ] );
			$data[ 'HomeRegion' ] = sanitize_text_field( $_POST[ 'state' ] );
			$data[ 'HomePostalCode' ] = sanitize_text_field( $_POST[ 'zip' ] );
		}
		if( array_key_exists( 'phone', $_POST ) ){
			$data[ 'PrimaryPhoneNumber' ] = sanitize_text_field( $_POST[ 'phone' ] );
		}

		foreach( $data as $d ){
			if( empty( $d ) || !$d ){
				$result[ 'message' ] = 'All fields are required';
				$result[ 'success' ] = 0;
				exit( json_encode( $result ) );
			}
		}

		$Contacts = new \SparkAPI\Contacts();
		$contact = $Contacts->add_contact( $data );

		$message_body = strip_tags( $_POST[ 'message_body' ] );

		$subject = $data[ 'DisplayName' ] . ' would like you to contact them.';

		$body  = 'Message: ' . stripslashes( $message_body ) . PHP_EOL . PHP_EOL;
		$body .= 'Email: ' . $data[ 'PrimaryEmail' ] . PHP_EOL;
		if( array_key_exists( 'PrimaryPhoneNumber', $data ) ){
			$body .= 'Phone: ' . $data[ 'PrimaryPhoneNumber' ] . PHP_EOL;
		}
		$body .= PHP_EOL;
		$body .= '(This message was generated by your WordPress FlexMLS(r) Contact Me Form on ' . $data[ 'SourceURL' ] . ')';

		$message_me = $Contacts->message_me( $subject, $body, $data[ 'PrimaryEmail' ] );
		exit( json_encode( $result ) );
	}
}
