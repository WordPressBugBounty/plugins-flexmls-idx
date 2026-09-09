<?php

class flexmlsConnectPageLogout {

	function pre_tasks( $tag ){
		\flexmlsConnectPortalUser::set_spark_oauth_cookie( json_encode( array() ), time() - DAY_IN_SECONDS );
		wp_redirect( home_url() );
		exit;
	}

	function generate_page(){
		return null;
	}

}