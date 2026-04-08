<?php
/**
 * Connection pause / backoff state after repeated failed session (license) checks.
 *
 * @package Flexmls\Admin
 */

namespace FlexMLS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Persists next allowed automatic session attempt and last error context for admin display.
 */
class ConnectionPause {

	public const OPTION_KEY = 'fmc_connection_pause';

	/** Minimum failures in the rolling window before entering pause. */
	public const FAILURES_BEFORE_PAUSE = 3;

	/** Rolling window for counting session failures (seconds). */
	public const FAILURE_WINDOW = 900;

	/**
	 * @return array<string, mixed>
	 */
	public static function get_state() {
		$state = get_option( self::OPTION_KEY, array() );
		return is_array( $state ) ? $state : array();
	}

	public static function clear_state() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Whether automatic session requests should be skipped (cooldown active).
	 *
	 * @param string $context 'auto'|'manual' — manual retry always performs one POST.
	 */
	public static function should_block_auto_session( $context = 'auto' ) {
		if ( 'manual' === $context ) {
			return false;
		}
		$st = self::get_state();
		if ( empty( $st['next_auto_attempt_at'] ) ) {
			return false;
		}
		return time() < (int) $st['next_auto_attempt_at'];
	}

	/**
	 * Skip automatic token regeneration at end of clear_cache when cooldown active.
	 */
	public static function should_skip_clear_cache_token_refresh() {
		return self::should_block_auto_session( 'auto' );
	}

	/**
	 * Block 1020-driven token refresh retry when cooldown active.
	 */
	public static function should_block_auto_token_refresh() {
		return self::should_block_auto_session( 'auto' );
	}

	/**
	 * Copy stored pause context onto SparkAPI\Core for admin notices.
	 *
	 * @param \SparkAPI\Core $core Spark API core instance.
	 */
	public static function sync_last_error_to_core( $core ) {
		$st = self::get_state();
		if ( array_key_exists( 'api_code', $st ) && null !== $st['api_code'] && '' !== $st['api_code'] ) {
			$core->last_error_code = (int) $st['api_code'];
		}
		if ( ! empty( $st['api_message'] ) ) {
			$core->last_error_mess = $st['api_message'];
		} elseif ( ! empty( $st['wp_error'] ) ) {
			$core->last_error_mess = $st['wp_error'];
		} elseif ( ! empty( $st['http_status'] ) ) {
			$core->last_error_mess = sprintf(
				/* translators: %d: HTTP status code */
				__( 'HTTP %d', 'fmcdomain' ),
				(int) $st['http_status']
			);
		}
	}

	/**
	 * Called after a failed session POST: updates failure timestamps and may enter pause.
	 *
	 * @param int                             $http_status      HTTP status or 0 if transport error.
	 * @param array|null                      $decoded_response Parsed JSON body or null.
	 * @param string                          $wp_error_message Empty if none.
	 * @param array<int>                      $failures_timestamps Existing timestamps (modified in place).
	 * @param \WP_HTTP_Requests_Response|null $response         Raw HTTP response for Retry-After.
	 */
	public static function after_session_failure( $http_status, $decoded_response, $wp_error_message, array &$failures_timestamps, $response = null ) {
		$failures_timestamps[] = time();
		set_transient( 'flexmls_auth_failures_timestamps', $failures_timestamps, self::FAILURE_WINDOW );

		$recent = array_filter(
			$failures_timestamps,
			function ( $timestamp ) {
				return ( time() - (int) $timestamp ) <= self::FAILURE_WINDOW;
			}
		);

		$api_code = null;
		$api_msg  = '';
		if ( is_array( $decoded_response ) && isset( $decoded_response['D'] ) && is_array( $decoded_response['D'] ) ) {
			if ( isset( $decoded_response['D']['Code'] ) ) {
				$api_code = (int) $decoded_response['D']['Code'];
			}
			if ( isset( $decoded_response['D']['Message'] ) ) {
				$api_msg = (string) $decoded_response['D']['Message'];
			}
		}

		// Inactive / MLS access off: pause immediately (do not wait for 3 strikes).
		if ( 1015 === $api_code ) {
			self::enter_pause_for_1015( (int) $http_status, $api_msg, $wp_error_message, $response );
			return;
		}

		$retry_after_sec = null;
		if ( $response && ! is_wp_error( $response ) ) {
			$hdr = wp_remote_retrieve_header( $response, 'retry-after' );
			if ( is_string( $hdr ) && is_numeric( trim( $hdr ) ) ) {
				$retry_after_sec = min( (int) trim( $hdr ), DAY_IN_SECONDS );
			}
		}

		if ( count( $recent ) < self::FAILURES_BEFORE_PAUSE ) {
			return;
		}

		$next = self::compute_next_attempt_at( (int) $http_status, count( $recent ), $retry_after_sec );

		$state = array(
			'next_auto_attempt_at' => $next,
			'http_status'          => (int) $http_status,
			'api_code'             => $api_code,
			'api_message'          => $api_msg,
			'wp_error'             => $wp_error_message,
			'updated_at'           => time(),
		);
		update_option( self::OPTION_KEY, $state );

		$log = sprintf(
			'[Flexmls IDX] Connection paused: HTTP=%s API=%s %s',
			(string) $http_status,
			$api_code !== null ? (string) $api_code : '-',
			$api_msg ? $api_msg : ( $wp_error_message ? $wp_error_message : '' )
		);
		error_log( $log );
	}

	/**
	 * Enter cool-down for Spark 1015 (account inactive / MLS access off).
	 *
	 * @param int                             $http_status       HTTP status or 0.
	 * @param string                          $api_message       Spark D.Message.
	 * @param string                          $wp_error_message  Transport error text, if any.
	 * @param \WP_HTTP_Requests_Response|null $response          Raw response for Retry-After.
	 */
	public static function enter_pause_for_1015( $http_status, $api_message, $wp_error_message = '', $response = null ) {
		$retry_after_sec = null;
		if ( $response && ! is_wp_error( $response ) ) {
			$hdr = wp_remote_retrieve_header( $response, 'retry-after' );
			if ( is_string( $hdr ) && is_numeric( trim( $hdr ) ) ) {
				$retry_after_sec = min( (int) trim( $hdr ), DAY_IN_SECONDS );
			}
		}

		$now = time();
		if ( $retry_after_sec !== null && $retry_after_sec > 0 ) {
			$next = $now + $retry_after_sec;
		} elseif ( 429 === (int) $http_status ) {
			$next = $now + HOUR_IN_SECONDS;
		} else {
			$next = $now + HOUR_IN_SECONDS;
		}

		$state = array(
			'next_auto_attempt_at' => $next,
			'http_status'          => (int) $http_status,
			'api_code'             => 1015,
			'api_message'          => (string) $api_message,
			'wp_error'             => (string) $wp_error_message,
			'updated_at'           => $now,
		);
		update_option( self::OPTION_KEY, $state );

		error_log(
			sprintf(
				'[Flexmls IDX] Connection paused (1015 account inactive): HTTP=%s %s',
				(string) $http_status,
				$api_message ? $api_message : ( $wp_error_message ? $wp_error_message : '' )
			)
		);
	}

	/**
	 * If my/account (bootstrap) reports 1015, ensure session cool-down is active (avoids hammering the API).
	 *
	 * @param int    $api_code    Spark D.Code from flexmls API client.
	 * @param string $api_message Spark D.Message.
	 */
	public static function ensure_pause_from_bootstrap_1015( $api_code, $api_message ) {
		if ( 1015 !== (int) $api_code ) {
			return;
		}
		$st  = self::get_state();
		$now = time();
		if ( ! empty( $st['next_auto_attempt_at'] ) && (int) $st['next_auto_attempt_at'] > $now
			&& isset( $st['api_code'] ) && 1015 === (int) $st['api_code'] ) {
			return;
		}
		self::enter_pause_for_1015( 0, is_string( $api_message ) ? $api_message : '', '' );
	}

	/**
	 * @param int      $http_status       HTTP status code.
	 * @param int      $recent_fail_count Failures in rolling window.
	 * @param int|null $retry_after_sec   From Retry-After header.
	 */
	private static function compute_next_attempt_at( $http_status, $recent_fail_count, $retry_after_sec ) {
		$now = time();
		if ( $retry_after_sec !== null && $retry_after_sec > 0 ) {
			return $now + $retry_after_sec;
		}
		if ( 429 === (int) $http_status ) {
			return $now + HOUR_IN_SECONDS;
		}
		$steps = array( 5 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS );
		$idx   = min( max( 0, $recent_fail_count - self::FAILURES_BEFORE_PAUSE ), count( $steps ) - 1 );
		return $now + $steps[ $idx ];
	}

	public static function clear_for_manual_retry() {
		self::clear_state();
	}
}
