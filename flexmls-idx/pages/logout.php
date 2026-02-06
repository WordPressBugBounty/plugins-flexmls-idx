<?php

class flexmlsConnectPageLogout {

	function pre_tasks( $tag ){
		if( !headers_sent() ){
			setcookie( 'spark_oauth', json_encode( array() ), array(
				'expires' => time() - DAY_IN_SECONDS,
				'path' => '/',
				'samesite' => 'Lax'
			) );
		}
		wp_redirect( home_url() );
		exit;
	}

	function generate_page(){
		return null;
	}

}