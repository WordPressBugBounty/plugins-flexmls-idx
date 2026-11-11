<?php

/**
 * Verify nonce for public IDX AJAX requests. Call at the start of wp_ajax_nopriv_ handlers
 * that are triggered from the front end. Dies with 403 or JSON error if the nonce is missing or invalid.
 */
function flexmls_verify_ajax_nonce() {
	if ( ! check_ajax_referer( 'fmc_ajax', 'nonce', false ) ) {
		if ( wp_doing_ajax() && ( isset( $_REQUEST['nonce'] ) || isset( $_POST['nonce'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token. Please refresh the page.', 'flexmls-idx' ) ) );
		}
		status_header( 403 );
		wp_die( esc_html__( 'Invalid security token. Please refresh the page.', 'flexmls-idx' ), '', array( 'response' => 403 ) );
	}
}

/**
 * add_shortcode() for plugin shortcodes, with the output rendered through flexmls_render_safely().
 */
function flexmls_add_shortcode( $tag, $callback ) {
	add_shortcode( $tag, function ( $atts, $content = null, $shortcode_tag = '' ) use ( $tag, $callback ) {
		return flexmls_render_safely( "[{$tag}] shortcode", function () use ( $callback, $atts, $content, $shortcode_tag ) {
			return call_user_func( $callback, $atts, $content, $shortcode_tag );
		} );
	} );
}

/**
 * Returns what $render returns. If it throws, the error is logged and the widget-unavailable
 * message is returned in place of the output, so a failed API call can't take down the page.
 *
 * @param string   $context Names what was being rendered, for the error log and browser console.
 * @param callable $render
 * @param string   $before  Markup to wrap the message in, e.g. the sidebar's before_widget.
 * @param string   $after
 * @return mixed
 */
function flexmls_render_safely( $context, $render, $before = '', $after = '' ) {
	$ob_level = ob_get_level();
	try {
		return call_user_func( $render );
	} catch ( \Throwable $e ) {
		$detail = flexmls_recover_from_error( $context, $e, $ob_level );
		// Same markup as flexmlsConnect::widget_not_available(); main.js logs data-flexmls-error to the console.
		return $before . '<div class="flexmls-widget-unavailable" data-flexmls-error="' . esc_attr( $detail ) . '">This widget is temporarily unavailable. Please refresh the page or try again later.</div>' . $after;
	}
}

/**
 * Discards output left in buffers opened after $ob_level (view templates buffer their output),
 * writes the error to the PHP error log, and returns a description of it for the browser console.
 * Only administrators get the error message and file in that description.
 *
 * @param string     $context
 * @param \Throwable $e
 * @param int        $ob_level ob_get_level() from before the code that threw.
 * @return string
 */
function flexmls_recover_from_error( $context, $e, $ob_level ) {
	while ( ob_get_level() > $ob_level ) {
		if ( ! ob_end_clean() ) {
			break;
		}
	}

	$error = sprintf( '%s: %s in %s:%d', get_class( $e ), $e->getMessage(), $e->getFile(), $e->getLine() );
	error_log( "Flexmls IDX: {$context} failed. {$error}" );

	if ( function_exists( 'wp_get_current_user' ) && current_user_can( 'manage_options' ) ) {
		return "{$context} failed. {$error}";
	}
	return "{$context} failed. The PHP error log has the details.";
}

/**
 * Shown in place of a widget or shortcode settings form that failed to build.
 *
 * @param string $detail From flexmls_recover_from_error().
 */
function flexmls_settings_form_error_html( $detail ) {
	return '<p><b>Flexmls&reg; IDX couldn\'t load these settings.</b> ' . esc_html( $detail ) . '</p>';
}

function flexmls_autoloader( $className, $dir = '' ){
    $className = ltrim($className, '\\');
    $fileName  = '';

    if ($lastNsPos = strrpos($className, '\\')) {
        $className = substr($className, $lastNsPos + 1);
    }

    $fileName .= $dir . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    if(!file_exists ($fileName)) {
        return false;
    }

    require_once $fileName;
}

function updateUserOptions($auth_token){
    global $fmc_api;
    if (flexmlsConnect::has_api_saved() && $auth_token) {
        $api_my_account = $fmc_api->GetMyAccount();
        if ( ! is_array( $api_my_account ) ) {
            return;
        }
        update_option('fmc_my_type', isset( $api_my_account['UserType'] ) ? $api_my_account['UserType'] : '' );
        update_option('fmc_my_id', isset( $api_my_account['Id'] ) ? $api_my_account['Id'] : '' );

        $my_office_id = "";
        if ( array_key_exists('OfficeId', $api_my_account) && !empty($api_my_account['OfficeId']) ) {
        $my_office_id = $api_my_account['OfficeId'];
        }
        update_option('fmc_my_office', $my_office_id);

        $my_company_id = "";
        if ( array_key_exists('CompanyId', $api_my_account) && !empty($api_my_account['CompanyId']) ) {
        $my_company_id = $api_my_account['CompanyId'];
        }
        update_option('fmc_my_company', $my_company_id);
    }
}