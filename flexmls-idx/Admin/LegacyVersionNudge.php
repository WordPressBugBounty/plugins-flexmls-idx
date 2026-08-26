<?php
/**
 * Admin notice encouraging upgrade from legacy Listing Template / Market Stat V1 to V2.
 *
 * @package FlexMLS\Admin
 */

namespace FlexMLS\Admin;

defined( 'ABSPATH' ) || exit;

class LegacyVersionNudge {

	public const USER_META_KEY = 'fmc_dismiss_legacy_v1_nudge';

	public const AJAX_ACTION = 'fmc_dismiss_legacy_v1_nudge';

	public const NONCE_ACTION = 'fmc_dismiss_legacy_v1_nudge';

	/** @var bool */
	private static $did_render = false;

	public static function register() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_dismiss' ) );
	}

	public static function maybe_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( \FlexMLS_IDX::is_fmc_admin_intro_screen() ) {
			return;
		}
		if ( ! \flexmlsConnect::has_api_saved() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! self::should_show_for_user( $user_id ) ) {
			return;
		}
		self::render_notice();
		self::$did_render = true;
		add_action( 'admin_footer', array( __CLASS__, 'print_dismiss_script' ), 99 );
	}

	/**
	 * @param int $user_id Current user ID.
	 */
	private static function should_show_for_user( $user_id ) {
		if ( ! self::listing_is_v1() && ! self::market_stat_is_v1() ) {
			return false;
		}
		$dismissed_for = get_user_meta( $user_id, self::USER_META_KEY, true );
		return $dismissed_for !== FMC_PLUGIN_VERSION;
	}

	public static function listing_is_v1() {
		$options = get_option( 'fmc_settings' );
		if ( ! is_array( $options ) ) {
			return true;
		}
		return empty( $options['search_listing_template_version'] ) || $options['search_listing_template_version'] !== 'v2';
	}

	public static function market_stat_is_v1() {
		$options = get_option( 'fmc_settings' );
		if ( ! is_array( $options ) ) {
			return true;
		}
		return ! isset( $options['market_stat_version'] ) || $options['market_stat_version'] !== 'v2';
	}

	private static function render_notice() {
		$url  = admin_url( 'admin.php?page=fmc_admin_settings&tab=style' );
		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'your IDX settings', 'fmcdomain' )
		);

		$listing_v1 = self::listing_is_v1();
		$market_v1  = self::market_stat_is_v1();

		if ( $listing_v1 && $market_v1 ) {
			$message = sprintf(
				/* translators: %s: linked phrase "your IDX settings" */
				__( 'We noticed you are using legacy versions of Flexmls &reg; IDX Listing Template and Market Stat Widget. Consider upgrading both to Version 2 in %s for a better experience!', 'fmcdomain' ),
				$link
			);
		} elseif ( $listing_v1 ) {
			$message = sprintf(
				/* translators: %s: linked phrase "your IDX settings" */
				__( 'Your Flexmls &reg; IDX Listing Template is currently using an older version (V1). Upgrade to the new, improved Version 2 in %s.', 'fmcdomain' ),
				$link
			);
		} else {
			$message = sprintf(
				/* translators: %s: linked phrase "your IDX settings" */
				__( 'Your Flexmls &reg; IDX Market Stat Widget is currently using an older version (V1). Upgrade to the new, improved Version 2 in %s.', 'fmcdomain' ),
				$link
			);
		}

		echo '<div id="fmc-legacy-v1-nudge" class="notice notice-info is-dismissible"><p>';
		echo wp_kses_post( $message );
		echo '</p></div>';
	}

	public static function ajax_dismiss() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		update_user_meta( get_current_user_id(), self::USER_META_KEY, FMC_PLUGIN_VERSION );
		wp_send_json_success();
	}

	public static function print_dismiss_script() {
		if ( ! self::$did_render ) {
			return;
		}
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
<script>
jQuery(function($) {
	$(document).on('click', '#fmc-legacy-v1-nudge .notice-dismiss', function() {
		$.post(ajaxurl, {
			action: <?php echo wp_json_encode( self::AJAX_ACTION ); ?>,
			nonce: <?php echo wp_json_encode( $nonce ); ?>
		});
	});
});
</script>
		<?php
	}
}
