<?php
/**
 * Core functionalities for the SparkAPI.
 *
 * This file provides the main class responsible for handling various operations
 * related to the Spark API within the WordPress context.
 *
 * @package    SparkAPI
 * @subpackage Core
 * @category   API
 */

namespace SparkAPI;

defined( 'ABSPATH' ) || die( 'This plugin requires WordPress' );

/**
 * Core class
 *
 * This class provides functionalities for making API calls to the FlexMLS IDX API, generating authentication tokens,
 * handling cache, and processing API responses.
 */
#[\AllowDynamicProperties]
class Core {

	/**
	 * The base URL of the FlexMLS IDX API.
	 *
	 * @var string $api_base
	 */
	protected $api_base;

	/**
	 * The headers to be included in API requests.
	 *
	 * @var array $api_headers
	 */
	protected $api_headers;

	/**
	 * The version of the FlexMLS IDX API.
	 *
	 * @var string $api_version
	 */
	protected $api_version;

	/**
	 * The version of the FlexMLS WordPress Plugin.
	 *
	 * @var string $plugin_version
	 */
	protected $plugin_version;

	/**
	 * User IP address for API 
	 *
	 * @var string $user_ip
	 */
	protected $user_ip;

	/**
	 * User-Agent for API 
	 *
	 * @var string $user_agent
	 */
	protected $user_agent;

	/** Lock TTL (seconds) for auth token generation. */
	private const AUTH_LOCK_TTL = 30;

	/** Time (ms) to wait after claiming lock before re-checking. */
	private const AUTH_LOCK_WAIT_MS = 200;

	/** Max age (seconds) of lock timestamp to consider we still hold it. */
	private const AUTH_LOCK_MAX_AGE = 25;

	/** How long (seconds) to wait for another request to set the auth token. */
	private const AUTH_POLL_MAX_WAIT = 25;

	/** Lock TTL (seconds) for query cache single-flight. */
	private const QUERY_LOCK_TTL = 60;

	/** Time (ms) to wait after claiming query lock before re-checking. */
	private const QUERY_LOCK_WAIT_MS = 300;

	/** Max age (seconds) of query lock timestamp to consider we still hold it. */
	private const QUERY_LOCK_MAX_AGE = 55;

	/** How long (seconds) to wait for another request to fill query cache. */
	private const QUERY_POLL_MAX_WAIT = 20;

	/**
	 * Try to acquire a transient-based lock (single-flight across requests).
	 *
	 * @param string $lock_key    Transient key for the lock.
	 * @param int    $ttl_seconds Lock TTL in seconds.
	 * @param int    $wait_ms     Milliseconds to wait after setting lock before re-reading.
	 * @param int    $max_age     Max age in seconds of lock timestamp to consider we hold it.
	 * @return bool True if this request holds the lock.
	 */
	private function try_acquire_lock( $lock_key, $ttl_seconds, $wait_ms, $max_age ) {
		$lock_id = wp_generate_uuid4();
		set_transient( $lock_key, array( 'id' => $lock_id, 'ts' => time() ), $ttl_seconds );
		usleep( $wait_ms * 1000 );
		$lock = get_transient( $lock_key );
		return is_array( $lock )
			&& isset( $lock['id'], $lock['ts'] )
			&& $lock['id'] === $lock_id
			&& ( time() - (int) $lock['ts'] ) < $max_age;
	}

	/**
	 * Release a transient-based lock.
	 *
	 * @param string $lock_key Transient key for the lock.
	 */
	private function release_lock( $lock_key ) {
		delete_transient( $lock_key );
	}

	/**
	 * Poll for a transient to be set by another request.
	 *
	 * @param string $transient_name Transient key.
	 * @param int    $max_wait_sec   Max seconds to wait.
	 * @param int    $interval_ms    Milliseconds between checks.
	 * @return mixed Transient value when found, or false.
	 */
	private function wait_for_transient( $transient_name, $max_wait_sec, $interval_ms = 500 ) {
		$deadline = time() + $max_wait_sec;
		while ( time() < $deadline ) {
			$value = get_transient( $transient_name );
			if ( false !== $value && '' !== $value ) {
				return $value;
			}
			usleep( $interval_ms * 1000 );
		}
		return false;
	}

	/**
	 * Constructor for the Core class.
	 *
	 * This initializes the Core class with the necessary API base URL, API version, plugin version, and API headers.
	 *
	 * Example usage:
	 * $core = new Core();
	 */
	public function __construct() {
		$this->api_base       = FMC_API_BASE;
		$this->api_version    = FMC_API_VERSION;
		$this->plugin_version = FMC_PLUGIN_VERSION;
		$this->user_agent 	  = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? $_SERVER['HTTP_USER_AGENT'] : null;
		$this->user_ip		  = ( isset( $_SERVER['REMOTE_ADDR'] ) ) ? $_SERVER['REMOTE_ADDR'] : null;

		$this->last_error_code = null;
		$this->last_error_mess = null;

		$this->api_headers    = array(
			'Accept-Encoding'       => 'gzip,deflate',
			'Content-Type'          => 'application/json',
			'User-Agent'            => 'Flexmls WordPress Plugin/' . $this->api_version,
			'X-SparkApi-User-Agent' => 'flexmls-WordPress-Plugin/' . $this->plugin_version,
			'X-WP-User-Agent' 		=> $this->user_agent,
			'X-User-IP-Address'		=> $this->user_ip
		);
	}

	/**
	 * Display an admin notice for API connection error.
	 *
	 * This function displays an error notice in the WordPress admin area when there is an error connecting to the FlexMLS IDX API.
	 * It provides a link to the support page for further assistance.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->admin_notices_api_connection_error();
	 */
	public function admin_notices_api_connection_error() {
		// Check for specific error code 1010 (Plugin Key Disabled)
		if ( $this->last_error_code == 1010 ) {
			printf(
				'<div class="notice notice-error">
					<p>Your Flexmls&reg; IDX Plugin Key has been disabled.
					<ul style="list-style-type: square; padding-left: 25px;">
					<li>Please check your credentials and try again. If your credentials are correct and you continue to see this error message, 
					please <a href="%s">contact support</a>
					<p>or</p></li>
					<li> You may need to renew your plugin subscription. Please contact the Flexmls IDX Consultant Team: <a href="tel:8663209977">(866)320-9977</a> or <a href="mailto:idxsales@fbsdata.com">Email</a></li>	
					</ul>
					</p>
				</div>',
				admin_url( 'admin.php?page=fmc_admin_intro&tab=support' )
			);
		} else {
			$support_link    = admin_url( 'admin.php?page=fmc_admin_settings&tab=support' );
			$error_message   = __( 'There was an error connecting to the FlexMLS® IDX API. Please check your credentials and try again. If your credentials are correct and you continue to see this error message,', 'fmcdomain' );
			$contact_support = __( 'contact support', 'fmcdomain' );

			printf(
				'<div class="notice notice-error">
				<p>%s <a href="%s">%s</a>.</p>
			</div>',
				esc_textarea( $error_message ),
				esc_url( $support_link ),
				esc_textarea( $contact_support )
			);
		}
	}

	/**
	 * Clear cache.
	 *
	 * This function clears the cache by deleting transient options related to FlexMLS IDX API.
	 * It uses WordPress Transients API for compatibility with object caching systems (Memcached/Redis).
	 * It also generates a new authentication token and stores it in the cache.
	 *
	 * @param bool $force Whether to force clear the cache or not.
	 *
	 * @return bool True on successful cache clearance, false otherwise.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->clear_cache();  // Clears the cache
	 * $core->clear_cache(true);  // Clears the cache and forces cache clearance
	 */
	public function clear_cache( $force = false ) {
		// Clear FMC widget cache transients using the existing tracker
		$cache_tracker = get_transient( 'fmc_cache_tracker' );
		if ( is_array( $cache_tracker ) ) {
			foreach ( $cache_tracker as $cache_item_name => $value ) {
				delete_transient( 'fmc_cache_' . $cache_item_name );
			}
			// Clear the tracker itself
			delete_transient( 'fmc_cache_tracker' );
		}
		// Also use pattern-based deletion as fallback
		$this->delete_transients_by_pattern( 'fmc_cache_' );

		if ( $force ) {
			// Force clear all flexmls_query transients
			$this->delete_transients_by_pattern( 'flexmls_query_' );
			delete_option( 'fmc_db_cache_key' );
		} else {
			// Only clear expired flexmls_query transients
			// WordPress handles expiration automatically, but we can clean up tracked ones
			$this->cleanup_expired_transients( 'flexmls_query_' );
		}

		// Periodically clean up the tracking array to remove orphaned entries
		// This helps prevent the tracking array from growing indefinitely
		$this->cleanup_tracking_array();

		delete_transient( 'flexmls_auth_token' );
		$this->generate_auth_token();
		return true;
	}

	/**
	 * Delete transients by pattern using WordPress Transients API.
	 *
	 * This method is compatible with object caching systems (Memcached/Redis).
	 * It uses a tracking system to store transient names for bulk operations.
	 *
	 * @param string $pattern The transient name pattern to match (without 'transient_' prefix).
	 * @return int Number of transients deleted.
	 */
	private function delete_transients_by_pattern( $pattern ) {
		$tracked_transients = get_option( 'fmc_tracked_transients', array() );
		$deleted_count     = 0;

		if ( ! is_array( $tracked_transients ) ) {
			$tracked_transients = array();
		}

		// Delete tracked transients matching the pattern
		foreach ( $tracked_transients as $transient_name => $transient_pattern ) {
			if ( strpos( $transient_name, $pattern ) === 0 ) {
				delete_transient( $transient_name );
				unset( $tracked_transients[ $transient_name ] );
				$deleted_count++;
			}
		}

		// Update the tracking option
		if ( $deleted_count > 0 ) {
			update_option( 'fmc_tracked_transients', $tracked_transients );
		}

		// Fallback: If we're not using object cache, we can still query the database
		// This is a safety net for sites not using persistent object caching
		if ( ! wp_using_ext_object_cache() ) {
			global $wpdb;
			$transient_query = "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s LIMIT 250";
			$prepared_query  = $wpdb->prepare( $transient_query, '_transient_' . $wpdb->esc_like( $pattern ) . '%', '_transient_timeout_' . $wpdb->esc_like( $pattern ) . '%' );
			$wpdb->query( $prepared_query );
		}

		return $deleted_count;
	}

	/**
	 * Clean up expired transients.
	 *
	 * WordPress automatically handles transient expiration, but this method
	 * helps clean up tracked transients that may have expired.
	 *
	 * @param string $pattern The transient name pattern to match.
	 * @return int Number of expired transients cleaned up.
	 */
	private function cleanup_expired_transients( $pattern ) {
		$tracked_transients = get_option( 'fmc_tracked_transients', array() );
		$cleaned_count      = 0;

		if ( ! is_array( $tracked_transients ) ) {
			return 0;
		}

		// Check each tracked transient and remove if expired
		foreach ( $tracked_transients as $transient_name => $transient_pattern ) {
			if ( strpos( $transient_name, $pattern ) === 0 ) {
				// Try to get the transient - if it returns false, it's expired or doesn't exist
				$value = get_transient( $transient_name );
				if ( false === $value ) {
					// Transient is expired or doesn't exist, remove from tracking
					unset( $tracked_transients[ $transient_name ] );
					$cleaned_count++;
				}
			}
		}

		// Update the tracking option if we cleaned anything
		if ( $cleaned_count > 0 ) {
			update_option( 'fmc_tracked_transients', $tracked_transients );
		}

		// Fallback for non-object-cache environments: clean up expired transients from database
		if ( ! wp_using_ext_object_cache() ) {
			global $wpdb;
			$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
                WHERE a.option_name LIKE %s
                AND a.option_name NOT LIKE %s
                AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
                AND b.option_value < %d";
			$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_transient_' . $pattern ) . '%', $wpdb->esc_like( '_transient_timeout_' . $pattern ) . '%', time() ) );
		}

		return $cleaned_count;
	}

	/**
	 * Track a transient name for later bulk operations.
	 *
	 * This helps us manage transients when using object caching systems
	 * where we can't query the database directly.
	 *
	 * @param string $transient_name The full transient name (without 'transient_' prefix).
	 * @param string $pattern The pattern this transient belongs to (for grouping).
	 * @return void
	 */
	public function track_transient( $transient_name, $pattern = 'flexmls_query_' ) {
		$tracked_transients = get_option( 'fmc_tracked_transients', array() );

		if ( ! is_array( $tracked_transients ) ) {
			$tracked_transients = array();
		}

		// Store the transient name with its pattern for easy lookup
		$tracked_transients[ $transient_name ] = $pattern;

		// Limit the size of the tracking array to prevent it from growing too large
		// Keep only the most recent 1000 transients
		if ( count( $tracked_transients ) > 1000 ) {
			// Remove oldest entries (simple FIFO approach)
			$tracked_transients = array_slice( $tracked_transients, -1000, null, true );
		}

		update_option( 'fmc_tracked_transients', $tracked_transients );
	}

	/**
	 * Clean up the tracking array by removing entries for transients that no longer exist.
	 *
	 * This helps prevent the tracking array from growing indefinitely and
	 * removes orphaned entries that reference expired or deleted transients.
	 *
	 * @return int Number of orphaned entries removed.
	 */
	private function cleanup_tracking_array() {
		$tracked_transients = get_option( 'fmc_tracked_transients', array() );
		$cleaned_count      = 0;

		if ( ! is_array( $tracked_transients ) || empty( $tracked_transients ) ) {
			return 0;
		}

		// Sample a subset of tracked transients to check (to avoid performance issues)
		// Check up to 100 entries per cleanup cycle
		$sample_size = min( 100, count( $tracked_transients ) );
		$sample_keys = array_slice( array_keys( $tracked_transients ), 0, $sample_size, true );

		foreach ( $sample_keys as $transient_name ) {
			// Check if the transient still exists
			$value = get_transient( $transient_name );
			if ( false === $value ) {
				// Transient doesn't exist or is expired, remove from tracking
				unset( $tracked_transients[ $transient_name ] );
				$cleaned_count++;
			}
		}

		// Update the tracking option if we cleaned anything
		if ( $cleaned_count > 0 ) {
			update_option( 'fmc_tracked_transients', $tracked_transients );
		}

		return $cleaned_count;
	}

	/**
	 * Generate authentication token.
	 *
	 * This function generates an authentication token for making API requests.
	 * It retrieves the cached authentication token if it exists and is valid.
	 * Otherwise, it retrieves the API credentials, generates security parameters, and makes a request to the API to get a new authentication token.
	 * The generated authentication token is stored in the cache for future use.
	 *
	 * @param bool $retry Whether to retry generating the authentication token or not.
	 *
	 * @return array|bool The generated authentication token on success, false on failure.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->generate_auth_token();  // Generates and retrieves the authentication token
	 * $core->generate_auth_token(false);  // Generates and retrieves the authentication token without retrying
	 */
	public function generate_auth_token( $retry = true ) {
		// Get the cached auth token and failure timestamps.
		$auth_token          = get_transient( 'flexmls_auth_token' );
		$failures_timestamps = get_transient( 'flexmls_auth_failures_timestamps' ) ?: array();

		// Check if there have been more than 2 failures in the last 15 minutes.
		$recent_failures = array_filter(
			$failures_timestamps,
			function ( $timestamp ) {
				return ( time() - $timestamp ) <= ( 15 * MINUTE_IN_SECONDS );
			}
		);

		// If there are more than 2 recent failures, return the bad token without making an API request.
		if ( count( $recent_failures ) > 2 ) {
			return $auth_token;
		}

		// If we have a valid token and haven't failed too much, return the token.
		if ( $auth_token ) {
			return $auth_token;
		}

		$options = get_option( 'fmc_settings' );
		if ( ! $this->valid_api_credentials( $options ) ) {
			return false;
		}

		// Single-flight: only one request should call the session API; others wait for the token.
		$lock_key = 'flexmls_auth_token_lock';
		$have_lock = $this->try_acquire_lock(
			$lock_key,
			self::AUTH_LOCK_TTL,
			self::AUTH_LOCK_WAIT_MS,
			self::AUTH_LOCK_MAX_AGE
		);

		if ( ! $have_lock ) {
			$auth_token = $this->wait_for_transient( 'flexmls_auth_token', self::AUTH_POLL_MAX_WAIT );
			return $auth_token ? $auth_token : $this->generate_auth_token( $retry );
		}

		try {
			$params           = $this->generate_security_params( $options );
			$url              = sprintf( 'https://%s/%s/session?%s', $this->api_base, $this->api_version, build_query( $params ) );
			$response         = wp_remote_post( $url, array( 'headers' => $this->api_headers ) );
			$decoded_response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $this->is_valid_response( $decoded_response ) ) {
				$auth_token = $decoded_response;
				set_transient( 'flexmls_auth_token', $auth_token, 15 * MINUTE_IN_SECONDS );
				$this->release_lock( $lock_key );
				return $auth_token;
			}

			// Preserve error code and message from API response before returning false.
			if ( is_array( $decoded_response ) && isset( $decoded_response['D'] ) ) {
				if ( isset( $decoded_response['D']['Code'] ) ) {
					$this->last_error_code = $decoded_response['D']['Code'];
				}
				if ( isset( $decoded_response['D']['Message'] ) ) {
					$this->last_error_mess = $decoded_response['D']['Message'];
				}
			}

			// Only the lock-holder records failure (avoids race on failure_timestamps).
			$failures_timestamps[] = time();
			set_transient( 'flexmls_auth_failures_timestamps', $failures_timestamps, 15 * MINUTE_IN_SECONDS );
		} finally {
			$this->release_lock( $lock_key );
		}

		$this->handle_failed_auth( $retry );
		return false;
	}

	/**
	 * Check if API credentials are valid.
	 *
	 * This function checks if the API credentials are valid by verifying the presence and non-emptiness of the API key and API secret.
	 *
	 * @param array $options The API credentials options.
	 *
	 * @return bool True if the API credentials are valid, false otherwise.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $options = get_option( 'fmc_settings' );
	 * $core->valid_api_credentials( $options );  // Returns true or false
	 */
	private function valid_api_credentials( $options ) {
		return isset( $options['api_key'], $options['api_secret'] ) && ! empty( $options['api_key'] ) && ! empty( $options['api_secret'] );
	}

	/**
	 * Generate security parameters.
	 *
	 * This function generates the security parameters required for API requests.
	 * It generates the security string by concatenating the API secret, 'ApiKey', and API key.
	 * It returns an array of security parameters including the API key and the security signature.
	 *
	 * @param array $options The API credentials options.
	 *
	 * @return array The generated security parameters.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $options = get_option( 'fmc_settings' );
	 * $core->generate_security_params( $options );  // Returns an array of security parameters
	 */
	private function generate_security_params( $options ) {
		$security_string = md5( $options['api_secret'] . 'ApiKey' . $options['api_key'] );
		return array(
			'ApiKey' => $options['api_key'],
			'ApiSig' => $security_string,
		);
	}

	/**
	 * Check if API response is valid.
	 *
	 * This function checks if the API response is valid by verifying the presence of the 'D' key in the response array
	 * and the 'Success' key in the 'D' array with a value of true.
	 *
	 * @param array $response The API response array.
	 *
	 * @return bool True if the API response is valid, false otherwise.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $response = json_decode( wp_remote_retrieve_body( $response ), true );
	 * $core->is_valid_response( $response );  // Returns true or false
	 */
	private function is_valid_response( $response ) {
		return is_array( $response ) && isset( $response['D']['Success'] ) && true === wp_validate_boolean( $response['D']['Success'] );
	}

	/**
	 * Handle failed authentication.
	 *
	 * This function handles failed authentication by incrementing the authentication token failures count.
	 * If retry is set to true, it generates a new authentication token.
	 * If retry is set to false, it adds an admin notice for API connection error.
	 *
	 * @param bool $retry Whether to retry generating the authentication token or not.
	 *
	 * @return void
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->handle_failed_auth();  // Handles failed authentication and retries generating the authentication token
	 * $core->handle_failed_auth(false);  // Handles failed authentication and adds an admin notice for API connection error
	 */
	private function handle_failed_auth( $retry ) {
		if ( ! $retry ) {
			add_action( 'admin_notices', array( $this, 'admin_notices_api_connection_error' ) );
			return;
		}

		$this->generate_auth_token( false );
	}

	/**
	 * Get the first result from the API response.
	 *
	 * This function retrieves the first result from the API response.
	 * It checks if the response is successful and if there are any results.
	 * If the response is successful and there are results, it returns the first result.
	 * If the response is not successful or there are no results, it returns null or false accordingly.
	 *
	 * @param array $response The API response array.
	 *
	 * @return mixed|null|false The first result from the API response, null if there are no results, false if the response is not successful.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $response = json_decode( wp_remote_retrieve_body( $response ), true );
	 * $core->get_first_result( $response );  // Returns the first result, null, or false
	 */
	public function get_first_result( $response ) {
		if ( ! isset( $response['success'] ) || ! $response['success'] ) {
			return false;
		}

		if ( empty( $response['results'] ) ) {
			return null;
		}

		return $response['results'][0];
	}

	/**
	 * Get all results from the API response.
	 *
	 * This function retrieves all results from the API response.
	 * It checks if the response is successful and if there are any results.
	 * If the response is successful and there are results, it returns all the results.
	 * If the response is not successful or there are no results, it returns false.
	 *
	 * @param array $response The API response array.
	 *
	 * @return array|false All results from the API response, false if the response is not successful.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $response = json_decode( wp_remote_retrieve_body( $response ), true );
	 * $core->get_all_results( $response );  // Returns all results or false
	 */
	public function get_all_results( $response = array() ) {
		if ( ! isset( $response['success'] ) || ! $response['success'] ) {
			return false;
		}

		return $response['results'];
	}

	/**
	 * Get data from API and process the response.
	 *
	 * This function makes an API call to the FlexMLS IDX API, retrieves the response, and processes it.
	 * It takes the HTTP method, service, cache time, parameters, post data, and retry flag as input.
	 * It returns an array containing the success status and the results from the API response.
	 *
	 * @param string $method      The HTTP method for the API call.
	 * @param string $service     The service endpoint for the API call.
	 * @param int    $cache_time  The cache time for the API response.
	 * @param array  $params      The parameters for the API call.
	 * @param mixed  $post_data   The post data for the API call.
	 * @param bool   $a_retry     Whether to retry the API call or not.
	 *
	 * @return array The API response containing the success status and the results.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->get_from_api( 'GET', 'service', 3600, array( 'param1' => 'value1', 'param2' => 'value2' ), null, false );
	 */
	public function get_from_api( $method, $service, $cache_time = 0, $params = array(), $post_data = null, $a_retry = false ) {
		$json = $this->make_api_call( $method, $service, $cache_time, $params, $post_data, $a_retry );

		// Initialize the return data.
		$response_data = array(
			'success' => false,
		);

		// Check the existence of 'D' key in the response.
		if ( isset( $json['D'] ) ) {
			$data = $json['D'];

			// Process error details.
			$this->process_error_details( $data, $response_data );

			// Process pagination details.
			$this->process_pagination_details( $data );

			// Process success data.
			if ( true === wp_validate_boolean( $data['Success'] ) ) {
				$response_data['success'] = true;
				$response_data['results'] = $data['Results'];
			}
		}

		return $response_data;
	}

	/**
	 * Process the error details from the API response.
	 *
	 * This function processes the error details from the API response and stores them in the return array.
	 * It retrieves the error code and message from the response and stores them in the return array.
	 *
	 * @param array $data   The API response data.
	 * @param array $response_data The return array to store the error details.
	 *
	 * @return void
	 *
	 * Example usage:
	 * $core = new Core();
	 * $response = json_decode( wp_remote_retrieve_body( $response ), true );
	 * $core->process_error_details( $response['D'], $response_data );
	 */
	protected function process_error_details( $data, &$response_data ) {
		if ( isset( $data['Code'] ) ) {
			$this->last_error_code     = $data['Code'];
			$response_data['api_code'] = $data['Code'];
		}

		if ( isset( $data['Message'] ) ) {
			$this->last_error_mess        = $data['Message'];
			$response_data['api_message'] = $data['Message'];
		}
	}

	/**
	 * Process the pagination details from the API response.
	 *
	 * This function processes the pagination details from the API response and updates the corresponding class properties.
	 * It retrieves the pagination details such as total rows, page size, total pages, and current page from the response.
	 *
	 * @param array $data The API response data.
	 *
	 * @return void
	 *
	 * Example usage:
	 * $core = new Core();
	 * $response = json_decode( wp_remote_retrieve_body( $response ), true );
	 * $core->process_pagination_details( $response['D'] );
	 */
	protected function process_pagination_details( $data ) {
		if ( isset( $data['Pagination'] ) ) {
			$pagination = $data['Pagination'];

			$this->last_count   = $pagination['TotalRows'] ?? null;
			$this->page_size    = $pagination['PageSize'] ?? null;
			$this->total_pages  = $pagination['TotalPages'] ?? null;
			$this->current_page = $pagination['CurrentPage'] ?? null;
		} else {
			$this->last_count   = null;
			$this->page_size    = null;
			$this->total_pages  = null;
			$this->current_page = null;
		}
	}

	/**
	 * Check if a value is not blank or restricted.
	 *
	 * This function checks if a value is not blank or restricted.
	 * It is a placeholder function and should be replaced with the actual implementation.
	 *
	 * @param mixed $val The value to check.
	 *
	 * @return bool The result of the check.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->is_not_blank_or_restricted( $value );  // Returns true or false
	 */
	public function is_not_blank_or_restricted( $val ) {
		// Need to find back references and delete them.
		// This temporary placeholder is so nothing breaks in the interim.
		// It should be removed and references should be to the Formatter class.
		return \FlexMLS\Admin\Formatter::is_not_blank_or_restricted( $val );
	}

	/**
	 * Make an API call.
	 *
	 * This function makes an API call to the FlexMLS IDX API.
	 * It prepares the request object, signs the request, and executes the request.
	 * It returns the API response in JSON format.
	 *
	 * @param string $method      The HTTP method for the API call.
	 * @param string $service     The service endpoint for the API call.
	 * @param int    $cache_time  The cache time for the API response.
	 * @param array  $params      The parameters for the API call.
	 * @param mixed  $post_data   The post data for the API call.
	 * @param bool   $a_retry     Whether to retry the API call or not.
	 *
	 * @return array The API response in JSON format.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->make_api_call( 'GET', 'service', 3600, array( 'param1' => 'value1', 'param2' => 'value2' ), null, false );
	 */
	public function make_api_call( $method, $service, $cache_time = 0, $params = array(), $post_data = null, $a_retry = false ) {
		// Try generating auth token only once.
		$auth_token_generated = $this->generate_auth_token();

		if ( ! $auth_token_generated ) {
			return array( 'D' => array( 'Success' => false ) );
		}

		// Create the request object.
		$request = $this->prepare_request( $method, $service, $cache_time, $params, $post_data );
		if ( 'session' === $request['service'] ) {
			return $auth_token_generated;  // Return result from previously generated auth token.
		}

		// Attempt to retrieve cached result (single-flight: only one request fetches per key).
		$transient_name = 'flexmls_query_' . $request['transient_name'];
		$json           = get_transient( $transient_name );
		if ( false === wp_validate_boolean( $json ) ) {
			$query_lock_key = 'flexmls_query_lock_' . $request['transient_name'];
			$have_lock      = $this->try_acquire_lock(
				$query_lock_key,
				self::QUERY_LOCK_TTL,
				self::QUERY_LOCK_WAIT_MS,
				self::QUERY_LOCK_MAX_AGE
			);

			if ( ! $have_lock ) {
				$json = $this->wait_for_transient( $transient_name, self::QUERY_POLL_MAX_WAIT );
				if ( false !== $json && '' !== $json ) {
					return $json;
				}
				// Lock holder may have failed; fetch ourselves.
				$json = $this->execute_request( $request, $post_data );
			} else {
				try {
					$json = $this->execute_request( $request, $post_data );
				} finally {
					$this->release_lock( $query_lock_key );
				}
			}

			if ( isset( $json['D']['Code'] ) && 1020 === intval( $json['D']['Code'] ) && ! $a_retry ) {
				delete_transient( 'flexmls_auth_token' );
				if ( $this->generate_auth_token() ) {
					$json = $this->make_api_call( $method, $service, 0, $params, $post_data, true );
				}
			}
		}

		return $json;
	}

	/**
	 * Prepare the request object.
	 *
	 * This function prepares the request object for making an API call.
	 * It sets the cache duration, method, parameters, post data, and service in the request object.
	 * It also signs the request.
	 *
	 * @param string $method      The HTTP method for the API call.
	 * @param string $service     The service endpoint for the API call.
	 * @param int    $cache_time  The cache time for the API response.
	 * @param array  $params      The parameters for the API call.
	 * @param mixed  $post_data   The post data for the API call.
	 *
	 * @return array The prepared request object.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->prepare_request( 'GET', 'service', 3600, array( 'param1' => 'value1', 'param2' => 'value2' ), null );
	 */
	protected function prepare_request( $method, $service, $cache_time, $params, $post_data ) {
		$seconds_to_cache = $this->parse_cache_time( $cache_time );
		$method           = sanitize_text_field( $method );

		$request = array(
			'cache_duration' => $seconds_to_cache,
			'method'         => $method,
			'params'         => $params,
			'post_data'      => $post_data,
			'service'        => $service,
		);

		return $this->sign_request( $request );
	}

	/**
	 * Execute the request.
	 *
	 * This function executes the API request.
	 * It makes the HTTP request to the API endpoint and retrieves the response.
	 * It handles request errors and invalid JSON responses.
	 * It also caches the API response if the response is successful and the request is a GET request.
	 *
	 * @param array $request    The prepared request object.
	 * @param mixed $post_data  The post data for the API call.
	 *
	 * @return array The API response.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->execute_request( $request, $post_data );
	 */
	protected function execute_request( $request, $post_data ) {
		$url = sprintf(
			'https://%s/%s/%s?%s',
			$this->api_base,
			$this->api_version,
			$request['service'],
			$request['query_string']
		);

		$args = array(
			'method'  => $request['method'],
			'headers' => $this->api_headers,
			'body'    => $post_data,
		);

		$response = wp_remote_request( $url, $args );

		// Handle request errors.
		if ( is_wp_error( $response ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices_api_connection_error' ) );
			return array( 'http_code' => wp_remote_retrieve_response_code( $response ) );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		// Handle invalid JSON.
		if ( ! is_array( $json ) ) {
			return array(
				'http_code' => wp_remote_retrieve_response_code( $response ),
				'body'      => $json,
			);
		}

		// Handle valid JSON.
		if ( isset( $json['D']['Success'] ) && $json['D']['Success'] && 'GET' === strtoupper( $request['method'] ) ) {
			$transient_name = 'flexmls_query_' . $request['transient_name'];
			set_transient( $transient_name, $json, $request['cache_duration'] );
			// Track this transient for later bulk operations (compatible with object caching)
			$this->track_transient( $transient_name, 'flexmls_query_' );
		}

		return $json;
	}

	/**
	 * Make the request body sendable.
	 *
	 * This function prepares the request body to be sent in the API call.
	 * It encodes the request data in JSON format.
	 *
	 * @param mixed $data The request data.
	 *
	 * @return string The sendable request body.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->make_sendable_body( $data );  // Returns the sendable request body
	 */
	public function make_sendable_body( $data ) {
		return wp_json_encode( array( 'D' => $data ) );
	}

	/**
	 * Parse cache time.
	 *
	 * This function parses the cache time value and returns the corresponding number of seconds.
	 * It is a placeholder function and should be replaced with the actual implementation.
	 *
	 * @param int $time_value The cache time value.
	 *
	 * @return int The number of seconds for the cache time.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->parse_cache_time( $time_value );  // Returns the number of seconds for the cache time
	 */
	public function parse_cache_time( $time_value = 0 ) {
		// Need to find back references and delete them.
		// This temporary placeholder is so nothing breaks in the interim.
		// It should be removed and references should be to the Formatter class.
		return \FlexMLS\Admin\Formatter::parse_cache_time( $time_value );
	}

	/**
	 * Parse location search string.
	 *
	 * This function parses the location search string and returns the parsed location.
	 * It is a placeholder function and should be replaced with the actual implementation.
	 *
	 * @param string $location The location search string.
	 *
	 * @return mixed The parsed location.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->parse_location_search_string( $location );  // Returns the parsed location
	 */
	public function parse_location_search_string( $location ) {
		// Need to find back references and delete them.
		// This temporary placeholder is so nothing breaks in the interim.
		// It should be removed and references should be to the Formatter class.
		return \FlexMLS\Admin\Formatter::parse_location_search_string( $location );
	}

	/**
	 * Return all results from the API response.
	 *
	 * This function returns all results from the API response.
	 * It is a helper function that calls the get_all_results() method.
	 *
	 * @param array $response The API response array.
	 *
	 * @return array|false All results from the API response, false if the response is not successful.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $response = json_decode( wp_remote_retrieve_body( $response ), true );
	 * $core->return_all_results( $response );  // Returns all results or false
	 */
	public function return_all_results( $response = array() ) {
		return $this->get_all_results( $response );
	}

	/**
	 * Sign the request.
	 *
	 * This function signs the request by generating the security signature and adding it to the request parameters.
	 * It also sets the transient name for caching the API response.
	 *
	 * @param array $request The prepared request object.
	 *
	 * @return array The signed request object.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->sign_request( $request );
	 */
	public function sign_request( $request ) {
		$options    = get_option( 'fmc_settings' );
		$auth_token = get_transient( 'flexmls_auth_token' );

		if ( ! is_array( $options ) ) {
			$options = array();
		}
		if ( ! is_array( $request ) ) {
			return $request;
		}

		$request['cacheable_query_string'] = build_query( $request['params'] );
		$params                            = $request['params'];

		$api_secret   = isset( $options['api_secret'] ) ? $options['api_secret'] : '';
		$api_key      = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$security_string = $api_secret . 'ApiKey' . $api_key;

		$post_body       = $this->get_post_body( $request );
		$is_auth_request = ( 'session' === $request['service'] );

		if ( $is_auth_request ) {
			$params['ApiKey'] = $api_key;
		} else {
			$params           = $this->prepare_params_for_non_auth_request( $params, $auth_token );
			$security_string .= 'ServicePath' . rawurldecode( '/' . $this->api_version . '/' . $request['service'] );
			$security_string  = $this->build_security_string_from_params( $params, $security_string );
		}

		if ( $post_body ) {
			$security_string .= $post_body;
		}

		$params['ApiSig']          = md5( $security_string );
		$request['query_string']   = build_query( $params );
		$request['transient_name'] = $this->get_transient_name( $params, $request );

		return $request;
	}

	/**
	 * Get the post body for the request.
	 *
	 * This function retrieves the post body for the request.
	 * It is a helper function that checks if the request method is POST and if there is post data.
	 *
	 * @param array $request The prepared request object.
	 *
	 * @return string The post body for the request.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->get_post_body( $request );  // Returns the post body for the request
	 */
	private function get_post_body( $request ) {
		if ( 'POST' === $request['method'] && ! empty( $request['post_data'] ) ) {
			return $request['post_data'];
		}
		return '';
	}

	/**
	 * Prepare parameters for non-auth request.
	 *
	 * This function prepares the parameters for a non-authenticated request by adding the authentication token to the parameters.
	 *
	 * @param array $params           The parameters for the API call.
	 * @param array $auth_token       The authentication token.
	 *
	 * @return array The updated parameters for the API call.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->prepare_params_for_non_auth_request( $params, $security_string, $auth_token, $request );
	 */
	private function prepare_params_for_non_auth_request( $params, $auth_token ) {
		$params['AuthToken'] = '';
		if ( is_array( $auth_token ) && isset( $auth_token['D']['Results'][0]['AuthToken'] ) ) {
			$params['AuthToken'] = $auth_token['D']['Results'][0]['AuthToken'];
		}
		return $params;
	}

	/**
	 * Build the security string from the parameters.
	 *
	 * This function builds the security string from the parameters by sorting the parameters and concatenating their keys and values.
	 *
	 * @param array  $params           The parameters for the API call.
	 * @param string $security_string The security string for the API call.
	 *
	 * @return string The updated security string.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->build_security_string_from_params( $params, $security_string );
	 */
	private function build_security_string_from_params( $params, $security_string ) {
		ksort( $params );
		foreach ( $params as $key => $value ) {
			$value = ( !is_null($value) ) ? $value : '';
			$security_string .= $key . $value;
			$params[ $key ]   = rawurlencode( $value );
		}
		return $security_string;
	}

	/**
	 * Get the transient name for caching the API response.
	 *
	 * This function gets the transient name for caching the API response by removing the AuthToken and ApiSig parameters from the request parameters.
	 * It then generates a SHA1 hash from the remaining parameters.
	 *
	 * @param array $params  The parameters for the API call.
	 * @param array $request The prepared request object.
	 *
	 * @return string The transient name for caching the API response.
	 *
	 * Example usage:
	 * $core = new Core();
	 * $core->get_transient_name( $params, $request );  // Returns the transient name for caching the API response
	 */
	private function get_transient_name( $params, $request ) {
		unset( $params['AuthToken'], $params['ApiSig'] );
		$params[ $request['method'] ] = $request['service'];
		return sha1( build_query( $params ) );
	}
}
