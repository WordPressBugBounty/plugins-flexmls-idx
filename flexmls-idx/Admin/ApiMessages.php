<?php
/**
 * Shared API error copy for wp-admin and front-end widgets.
 *
 * @package Flexmls\Admin
 */

namespace FlexMLS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Spark API D.Message display for known codes + generic connection errors.
 */
class ApiMessages {

	public const SPARK_RATE_LIMIT_DOC = 'https://sparkplatform.com/docs/api_services/read_first';

	/**
	 * Wrapper classes for admin alerts. WordPress core hides `.about-wrap .notice` (about.css); use flexmls-* inside Credentials/intro.
	 *
	 * @param string $variant error|warning|success.
	 * @param bool   $for_about_wrap True on fmc_admin_intro (inside .about-wrap).
	 * @param bool   $dismissible Add WP / flexmls dismissible hooks (retry flash, Google Maps nudge, etc.).
	 */
	public static function admin_alert_class( $variant, $for_about_wrap, $dismissible = false ) {
		$variant = sanitize_key( $variant );
		if ( ! $for_about_wrap ) {
			$map = array(
				'error'   => 'notice notice-error',
				'warning' => 'notice notice-warning',
				'success' => 'notice notice-success',
			);
			$cls = isset( $map[ $variant ] ) ? $map[ $variant ] : 'notice notice-warning';
			if ( $dismissible ) {
				$cls .= ' is-dismissible';
			}
			return $cls;
		}
		$base = 'flexmls-idx-admin-notice flexmls-idx-admin-notice--' . $variant;
		if ( $dismissible ) {
			$base .= ' flexmls-idx-admin-notice--dismissible';
		}
		return $base;
	}

	/**
	 * Whether Spark D.Message is the standard “API key is disabled” payload (IDX Consultant copy applies).
	 *
	 * @param string|null $raw_message Trimmed API message before UI fallback.
	 */
	public static function is_1010_api_key_disabled_payload( $raw_message ) {
		if ( ! is_string( $raw_message ) ) {
			return false;
		}
		$t = trim( $raw_message );
		if ( '' === $t ) {
			return false;
		}
		$t = rtrim( $t, '.' );
		$t = preg_replace( '/\s+/', ' ', $t );
		return 0 === strcasecmp( $t, 'API key is disabled' );
	}

	/**
	 * User-facing 1010 message (Spark may return “API key is disabled”; show “Plugin key is disabled.” instead).
	 *
	 * @param string $raw_message Trimmed D.Message or empty.
	 */
	public static function format_1010_display_message( $raw_message ) {
		$raw = is_string( $raw_message ) ? trim( $raw_message ) : '';
		if ( self::is_1010_api_key_disabled_payload( $raw ) ) {
			return __( 'Plugin key is disabled.', 'fmcdomain' );
		}
		return '' !== $raw ? $raw : __( 'Plugin key is disabled.', 'fmcdomain' );
	}

	/**
	 * Echo wp-admin notice for Spark connection errors (credentials / session).
	 *
	 * @param int|null    $error_code Spark D.Code or null.
	 * @param string|null $error_message Spark D.Message or null.
	 * @param bool        $include_wrapper Print outer notice div when true.
	 * @param bool        $for_about_wrap Use non-.notice classes inside .about-wrap screens.
	 */
	public static function echo_admin_api_error_notice( $error_code, $error_message, $include_wrapper = true, $for_about_wrap = false ) {
		$code = null !== $error_code ? (int) $error_code : null;
		$msg  = is_string( $error_message ) ? trim( $error_message ) : '';

		if ( $include_wrapper ) {
			echo '<div class="' . esc_attr( self::admin_alert_class( 'error', $for_about_wrap ) ) . '">';
		}

		if ( 1010 === $code ) {
			$raw     = $msg;
			$display = self::format_1010_display_message( $raw );
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Plugin Error Message:', 'fmcdomain' ),
				esc_html( $display )
			);
			if ( self::is_1010_api_key_disabled_payload( $raw ) ) {
				echo '<p>' . esc_html__( 'This may be due to an expired or unpaid plugin subscription.', 'fmcdomain' ) . '</p>';
				echo '<p>' . self::idx_consultant_contact_html() . '</p>';
			}
		} elseif ( 1015 === $code ) {
			echo '<p>' . esc_html__( 'Connection paused: The linked Flexmls account has been deactivated, likely by your MLS. Please contact them to restore access and then click Retry connection.', 'fmcdomain' ) . '</p>';
			$retry_url = wp_nonce_url( admin_url( 'admin-post.php?action=fmc_retry_connection' ), 'fmc_retry_connection' );
			echo '<p><a class="button button-primary" href="' . esc_url( $retry_url ) . '">' . esc_html__( 'Retry connection', 'fmcdomain' ) . '</a></p>';
		} else {
			$support = admin_url( 'admin.php?page=fmc_admin_settings&tab=support' );
			printf(
				'<p>%s <a href="%s">%s</a>.</p>',
				esc_html__( 'There was an error connecting to Flexmls® IDX. Please check your credentials and try again. If your credentials are correct and you continue to see this error message,', 'fmcdomain' ),
				esc_url( $support ),
				esc_html__( 'contact support', 'fmcdomain' )
			);
		}

		if ( $include_wrapper ) {
			echo '</div>';
		}
	}

	/**
	 * IDX Consultant contact line (HTML). Shown for Spark 1010 when D.Message matches the disabled plugin key payload.
	 */
	public static function idx_consultant_contact_html() {
		return wp_kses_post(
			sprintf(
				/* translators: 1: phone link, 2: email address (linked) */
				__( 'If you need help resolving this, contact the IDX Consultant team at FBS at %1$s or %2$s. Include your Flexmls username and plugin key so we can look up your account.', 'fmcdomain' ),
				'<a href="tel:+18663209977">' . esc_html__( '(866) 320-9977', 'fmcdomain' ) . '</a>',
				'<a href="mailto:idx@flexmls.com">idx@flexmls.com</a>'
			)
		);
	}

	/**
	 * Block IDX plugin API calls when the key lacks WordPressIdx, except endpoints needed for session health and re-checking system info.
	 *
	 * @param string $service Spark service path (e.g. system, my/account, listings).
	 * @param string $method  HTTP method.
	 */
	public static function is_spark_api_blocked_missing_wordpress_idx_subscription( $service, $method ) {
		global $fmc_api;
		if ( ! isset( $fmc_api ) || ! is_object( $fmc_api ) || true !== $fmc_api->wordpress_idx_entitlement_blocked ) {
			return false;
		}
		$method_u = strtoupper( (string) $method );
		$svc        = (string) $service;
		if ( 'GET' === $method_u && ( 'system' === $svc || 'my/account' === $svc ) ) {
			return false;
		}
		return true;
	}

	/**
	 * After a successful GetMyAccount(), set $fmc_api->wordpress_idx_entitlement_blocked from GET /system (cached).
	 *
	 * @param object $fmc_api flexmlsConnectUser.
	 */
	public static function sync_wordpress_idx_entitlement_on_api( $fmc_api ) {
		if ( ! is_object( $fmc_api ) || ! method_exists( $fmc_api, 'GetSystemInfo' ) ) {
			return;
		}
		$err = isset( $fmc_api->last_error_code ) ? (int) $fmc_api->last_error_code : 0;
		if ( 1010 === $err || 1015 === $err ) {
			$fmc_api->wordpress_idx_entitlement_blocked = null;
			return;
		}
		$info = $fmc_api->GetSystemInfo();
		$ent  = self::system_info_wordpress_idx_entitlement( $info );
		if ( true === $ent ) {
			$fmc_api->wordpress_idx_entitlement_blocked = false;
		} elseif ( false === $ent ) {
			$fmc_api->wordpress_idx_entitlement_blocked = true;
		} else {
			$fmc_api->wordpress_idx_entitlement_blocked = null;
		}
	}

	/**
	 * Whether GetSystemInfo includes WordPressIdx in Configuration.*.FlexmlsProducts.
	 *
	 * @param array<string, mixed>|mixed $system_info First element from GET /system.
	 * @return bool|null True if entitled; false if the payload clearly lists products but not WordPressIdx; null if inconclusive (missing or unusable Configuration).
	 */
	public static function system_info_wordpress_idx_entitlement( $system_info ) {
		if ( ! is_array( $system_info ) ) {
			return null;
		}
		$configs = isset( $system_info['Configuration'] ) ? $system_info['Configuration'] : null;
		if ( ! is_array( $configs ) || array() === $configs ) {
			return null;
		}
		$saw_products = false;
		foreach ( $configs as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! array_key_exists( 'FlexmlsProducts', $row ) || ! is_array( $row['FlexmlsProducts'] ) ) {
				continue;
			}
			$saw_products = true;
			if ( in_array( 'WordPressIdx', $row['FlexmlsProducts'], true ) ) {
				return true;
			}
		}
		return $saw_products ? false : null;
	}

	/**
	 * After Spark auth succeeds, warn when system info shows the account is not entitled to the WordPress IDX product.
	 *
	 * @param object|null $fmc_api flexmlsConnectUser (or any object with GetSystemInfo()).
	 * @param bool        $for_about_wrap Use non-.notice classes inside .about-wrap screens.
	 */
	public static function maybe_echo_wordpress_idx_entitlement_notice( $fmc_api, $for_about_wrap = false ) {
		if ( ! is_object( $fmc_api ) || ! method_exists( $fmc_api, 'GetSystemInfo' ) ) {
			return;
		}
		if ( false === $fmc_api->wordpress_idx_entitlement_blocked ) {
			return;
		}
		if ( true !== $fmc_api->wordpress_idx_entitlement_blocked ) {
			$info = $fmc_api->GetSystemInfo();
			if ( false !== self::system_info_wordpress_idx_entitlement( $info ) ) {
				return;
			}
		}
		echo '<div class="' . esc_attr( self::admin_alert_class( 'warning', $for_about_wrap, ! $for_about_wrap ) ) . '">';
		echo '<p><strong>' . esc_html__( 'Flexmls IDX:', 'fmcdomain' ) . '</strong> ';
		echo esc_html__( 'This plugin key is not subscribed to the Flexmls® IDX for WordPress product.', 'fmcdomain' );
		echo '</p>';
		echo '<p>' . self::idx_consultant_contact_html() . '</p>';
		echo '</div>';
	}

	/**
	 * Generic public-site copy when the key lacks the WordPress IDX product (no subscription or billing details).
	 * Full guidance with IDX Consultant contact is for wp-admin only (not the public site).
	 */
	public static function widget_wordpress_idx_subscription_blocked_public_message() {
		return esc_html__( 'Listing content is temporarily unavailable.', 'fmcdomain' );
	}

	/**
	 * Full subscription + IDX Consultant copy for wp-admin contexts (widget settings, Elementor, etc.).
	 */
	public static function widget_wordpress_idx_entitlement_admin_message() {
		return esc_html__( 'This plugin key is not subscribed to the Flexmls® IDX for WordPress product.', 'fmcdomain' ) . ' ' . self::idx_consultant_contact_html();
	}

	/**
	 * Front-end widget message for 1010 / 1015 (may contain HTML links).
	 *
	 * @param int|null    $error_code Spark D.Code.
	 * @param string|null $error_message Spark D.Message.
	 */
	public static function widget_unavailable_message( $error_code, $error_message ) {
		$code = null !== $error_code ? (int) $error_code : null;
		$msg  = is_string( $error_message ) ? trim( $error_message ) : '';

		if ( 1010 === $code ) {
			$raw     = $msg;
			$display = self::format_1010_display_message( $raw );
			$labeled = sprintf(
				/* translators: %s: plugin error message text */
				esc_html__( 'Plugin Error Message: %s', 'fmcdomain' ),
				esc_html( $display )
			);
			if ( self::is_1010_api_key_disabled_payload( $raw ) ) {
				$sub = esc_html__( 'This may be due to an expired or unpaid plugin subscription.', 'fmcdomain' );
				return $labeled . ' ' . $sub . ' ' . self::idx_consultant_contact_html();
			}
			return $labeled;
		}
		if ( 1015 === $code ) {
			return esc_html__( 'The linked Flexmls account has been deactivated, likely by your MLS. Please contact them to restore access.', 'fmcdomain' );
		}
		return '';
	}

	/**
	 * Connection paused notice (cooldown) + Retry form.
	 *
	 * @param array<string, mixed> $state From ConnectionPause::get_state().
	 * @param bool                 $for_about_wrap Use non-.notice classes inside .about-wrap screens.
	 */
	public static function echo_connection_paused_notice( $state, $for_about_wrap = false ) {
		$http = isset( $state['http_status'] ) ? (int) $state['http_status'] : 0;
		$api  = isset( $state['api_code'] ) ? $state['api_code'] : null;
		$api_msg_raw = isset( $state['api_message'] ) ? trim( (string) $state['api_message'] ) : '';
		$next        = isset( $state['next_auto_attempt_at'] ) ? (int) $state['next_auto_attempt_at'] : 0;
		$is_1015     = ( null !== $api && '' !== (string) $api && 1015 === (int) $api );

		$retry_url = wp_nonce_url( admin_url( 'admin-post.php?action=fmc_retry_connection' ), 'fmc_retry_connection' );

		echo '<div class="' . esc_attr( self::admin_alert_class( 'warning', $for_about_wrap ) ) . '"><p><strong>' . esc_html__( 'Flexmls IDX: Connection paused', 'fmcdomain' ) . '</strong></p>';

		if ( $is_1015 ) {
			echo '<p>' . esc_html__( 'Connection paused: The linked Flexmls account has been deactivated, likely by your MLS. Please contact them to restore access and then click Retry connection.', 'fmcdomain' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Automatic connection attempts are paused after several failed license checks to avoid locking out your site. Use “Retry connection” after you have confirmed billing or credentials.', 'fmcdomain' ) . '</p>';
		}

		$printed_plugin_error_code = false;

		// 1015: main copy above; omit redundant plugin error message line.
		if ( ! $is_1015 && '' !== $api_msg_raw ) {
			$display_msg = self::format_1010_display_message( $api_msg_raw );
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Plugin Error Message:', 'fmcdomain' ),
				esc_html( $display_msg )
			);
			if ( null !== $api && '' !== (string) $api ) {
				printf(
					'<p><strong>%s</strong> %s</p>',
					esc_html__( 'Plugin Error Code:', 'fmcdomain' ),
					esc_html( (string) $api )
				);
				$printed_plugin_error_code = true;
			}
			if ( self::is_1010_api_key_disabled_payload( $api_msg_raw ) ) {
				echo '<p>' . esc_html__( 'This may be due to an expired or unpaid plugin subscription.', 'fmcdomain' ) . '</p>';
				echo '<p>' . self::idx_consultant_contact_html() . '</p>';
			}
		} elseif ( ! empty( $state['wp_error'] ) ) {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Details:', 'fmcdomain' ),
				esc_html( (string) $state['wp_error'] )
			);
		}

		if ( ! $printed_plugin_error_code && null !== $api && '' !== (string) $api ) {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Plugin Error Code:', 'fmcdomain' ),
				esc_html( (string) $api )
			);
		}

		if ( 429 === $http ) {
			$rate_doc = '<a href="' . esc_url( self::SPARK_RATE_LIMIT_DOC ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Flexmls IDX rate limit overview', 'fmcdomain' ) . '</a>';
			echo '<p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to rate limit documentation */
					__( 'Requests may be rate limited for this plugin key. See %s for details. Automatic retries stay paused until the cool-down expires or you retry manually.', 'fmcdomain' ),
					$rate_doc
				)
			) . '</p>';
		}

		if ( $next > time() ) {
			echo '<p>' . sprintf(
				/* translators: %s: localized time */
				esc_html__( 'Automatic retries may resume after: %s', 'fmcdomain' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) )
			) . '</p>';
		}

		echo '<p><a class="button button-primary" href="' . esc_url( $retry_url ) . '">' . esc_html__( 'Retry connection', 'fmcdomain' ) . '</a></p>';
		echo '</div>';
	}
}
