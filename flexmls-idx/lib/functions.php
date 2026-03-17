<?php

/**
 * Verify nonce for public IDX AJAX requests. Call at the start of wp_ajax_nopriv_ handlers
 * that are triggered from the front end. Dies with 403 or JSON error if the nonce is missing or invalid.
 * Allows internal server-side block render (FlexMlsCallback) via one-time token so Gutenberg blocks work on the front end.
 */
function flexmls_verify_ajax_nonce() {
	// Internal server-side render: FlexMlsCallback sends a one-time token (cURL request has no cookies).
	$internal_token = isset( $_POST['fmc_render_token'] ) ? sanitize_text_field( wp_unslash( $_POST['fmc_render_token'] ) ) : '';
	if ( $internal_token && strlen( $internal_token ) === 32 && ctype_xdigit( $internal_token ) ) {
		$transient_key = 'fmc_render_' . $internal_token;
		if ( get_transient( $transient_key ) ) {
			delete_transient( $transient_key );
			return;
		}
	}

	if ( ! check_ajax_referer( 'fmc_ajax', 'nonce', false ) ) {
		if ( wp_doing_ajax() && ( isset( $_REQUEST['nonce'] ) || isset( $_POST['nonce'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token. Please refresh the page.', 'flexmls-idx' ) ) );
		}
		status_header( 403 );
		wp_die( esc_html__( 'Invalid security token. Please refresh the page.', 'flexmls-idx' ), '', array( 'response' => 403 ) );
	}
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