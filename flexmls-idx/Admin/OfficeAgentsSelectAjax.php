<?php
namespace FlexMLS\Admin;

defined( 'ABSPATH' ) || die( 'This plugin requires WordPress' );

/**
 * AJAX for Select2 lazy-loaded office agent roster (Listing Summary / office accounts).
 */
class OfficeAgentsSelectAjax {

	public static function register() {
		add_action( 'wp_ajax_flexmls_office_agents_select2', array( __CLASS__, 'ajax_select2' ) );
	}

	public static function ajax_select2() {
		\flexmls_verify_ajax_nonce();
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'flexmls-idx' ) ), 403 );
		}
		if ( ! \flexmlsConnect::is_office() ) {
			wp_send_json( array( 'results' => array(), 'pagination' => array( 'more' => false ) ) );
		}

		$page     = isset( $_REQUEST['page'] ) ? (int) wp_unslash( $_REQUEST['page'] ) : 1;
		$per_page = isset( $_REQUEST['per_page'] ) ? (int) wp_unslash( $_REQUEST['per_page'] ) : 20;
		$term     = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '';

		$data = \flexmlsConnect::office_agents_select2_query( $page, $per_page, $term );
		if ( ! is_array( $data ) ) {
			wp_send_json( array( 'results' => array(), 'pagination' => array( 'more' => false ) ) );
		}

		wp_send_json(
			array(
				'results'    => $data['results'],
				'pagination' => array( 'more' => ! empty( $data['more'] ) ),
			)
		);
	}
}
