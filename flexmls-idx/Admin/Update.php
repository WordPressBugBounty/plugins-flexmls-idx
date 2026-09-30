<?php
namespace FlexMLS\Admin;

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

class Update {

	/** One-time migration flag: fmc_tracked_transients must not autoload. */
	const TRACKED_TRANSIENTS_AUTOLOAD_MIGRATION = 'fmc_tracked_transients_noautoload_v1';

	public static function hourly_cache_cleanup(){
		$SparkAPI = new \SparkAPI\Core();
		// Non-force: expires query junk only; a still-valid AuthToken is kept (WP-1389).
		$SparkAPI->clear_cache();
		// After cache wipe, refresh account health / entitlement / ConnectionPause.
		\FlexMLS\Admin\ApiMessages::ensure_spark_account_bootstrap();
	}

	/**
	 * Stop autoloading fmc_tracked_transients on existing installs (WP-1381).
	 *
	 * New writes already pass autoload=false. This flips the DB flag for sites that
	 * already created the option with default autoload=yes, and drops the option on
	 * non-object-cache hosts where SQL pattern deletes make the tracker unnecessary.
	 *
	 * @return void
	 */
	public static function maybe_migrate_tracked_transients_autoload() {
		if ( get_option( self::TRACKED_TRANSIENTS_AUTOLOAD_MIGRATION ) ) {
			return;
		}

		$option_name = \SparkAPI\Core::TRACKED_TRANSIENTS_OPTION;

		if ( ! wp_using_ext_object_cache() ) {
			delete_option( $option_name );
		} elseif ( false !== get_option( $option_name, false ) ) {
			if ( function_exists( 'wp_set_option_autoload' ) ) {
				wp_set_option_autoload( $option_name, false );
			} else {
				global $wpdb;
				$wpdb->update(
					$wpdb->options,
					array( 'autoload' => 'no' ),
					array( 'option_name' => $option_name ),
					array( '%s' ),
					array( '%s' )
				);
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( $option_name, 'options' );
			}
		}

		update_option( self::TRACKED_TRANSIENTS_AUTOLOAD_MIGRATION, 1, false );
	}

	public static function set_minimum_options( $is_new_install = false ){
		$fmc_settings = get_option( 'fmc_settings' );

		if($fmc_settings === false) {
			$fmc_settings = array();
			add_option( 'fmc_settings', $fmc_settings );
		}

		if( $is_new_install ){
			$new_page_id = wp_insert_post( array(
				'post_title' => 'Search',
				'post_content' => '[idx_frame width="100%" height="600"]',
				'post_type' => 'page',
				'post_status' => 'publish'
			) );
			$fmc_settings[ 'autocreatedpage' ] = $new_page_id;
			$fmc_settings[ 'destlink' ] = $new_page_id;
			$fmc_settings[ 'search_listing_template_version' ] = 'v2';
			$fmc_settings['market_stat_version'] = 'v2';
		} else {
			$SparkAPI = new \SparkAPI\Core();
			$SparkAPI->clear_cache( true );
		}
		$defaults = array(
			'api_key' => '',
			'api_secret' => '',
			'allow_sold_searching' => 0,
			'contact_notifications' => 1,
			'default_titles' => 1,
			'destpref' => 'page',
			'detail_page' => '',
			'listpref' => 'page',
			'multiple_summaries' => 0,
			'oauth_key' => '',
			'oauth_secret' => '',
			'permabase' => 'idx',
			'portal_mins' => '',
			'portal_snooze_amount' => 7,
			'portal_snooze_unit' => 'days',
			'portal_position_x' => 'center',
			'portal_position_y' => 'center',
			'search_page' => ''
		);
		foreach( $defaults as $key => $val ){
			if( !isset( $fmc_settings[ $key ] ) || empty( $fmc_settings[ $key ] ) ){
				$fmc_settings[ $key ] = $val;
			}
		}
		update_option( 'fmc_settings', $fmc_settings );

		// Legacy caching option. Will be removed in future versions
		update_option( 'fmc_cache_version', 1 );

		self::maybe_migrate_tracked_transients_autoload();
	}
}
