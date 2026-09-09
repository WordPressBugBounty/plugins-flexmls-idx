<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

$fmc_settings = get_option( 'fmc_settings' );
$google_maps_no_enqueue = 0;
if( isset( $fmc_settings[ 'google_maps_no_enqueue' ] ) && 1 == $fmc_settings[ 'google_maps_no_enqueue' ] ){
	$google_maps_no_enqueue = 1;
}
//https://developers.google.com/maps/documentation/javascript/get-api-key#console
?>
<h3>Google Maps Settings</h3>
<p>In order for maps to display on your website, you must include a Google Maps API Key. <a href="https://developers.google.com/maps/documentation/javascript/get-api-key#console" target="_blank" rel="noopener noreferrer">Here&#8217;s how to get a Google Maps API Key</a>.</p>
<div class="notice notice-info inline" style="margin: 1em 0;">
	<p><strong>Billing and usage.</strong> Maps use your Google Maps API key and are billed through your Google Cloud account, not FBS. Restrict your key to your website domain(s) and the Maps JavaScript API only. Set Listing Summary widgets to <strong>Default view: List</strong> so search maps load only when a visitor clicks Open Map. Listing detail maps load when the map area scrolls into view (Version 2) or when the Map tab is opened (Version 1).</p>
	<p><strong>Protect against unexpected charges.</strong> In Google Cloud, set a <a href="https://cloud.google.com/apis/docs/capping-api-usage" target="_blank" rel="noopener noreferrer">daily quota</a> on the Maps JavaScript API as a hard cap on usage, and add <a href="https://cloud.google.com/billing/docs/how-to/budgets" target="_blank" rel="noopener noreferrer">budget alerts</a> so you are emailed when spend crosses thresholds you choose. Budget alerts warn you; quotas stop further usage.</p>
	<p><strong>Bots and firewalls.</strong> Automated traffic to IDX pages can inflate map usage if JavaScript runs and a map initializes. Use your host&#8217;s WAF or Under Attack mode to limit abusive crawlers while still allowing verified search engines—see <a href="https://go.wearefbs.com/idx-help-center/waf-and-seo-allowlisting-crawlers-on-idx-pages" target="_blank" rel="noopener noreferrer">WAF and SEO: Allowlisting Crawlers on IDX Pages</a>.</p>
	<p>For billing questions, usage reports, or unexpected charges, contact <a href="https://cloud.google.com/maps-platform/support" target="_blank" rel="noopener noreferrer">Google Maps Platform support</a> or see <a href="https://developers.google.com/maps/billing-and-pricing/overview" target="_blank" rel="noopener noreferrer">Google&#8217;s Maps billing documentation</a>.</p>
</div>
<form action="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=gmaps' ); ?>" method="post">
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="google_maps_api_key">Google Maps API Key</label>
				</th>
				<td>
					<p>
						<input type="text" class="regular-text" name="fmc_settings[google_maps_api_key]" id="google_maps_api_key" value="<?php echo ( isset( $fmc_settings[ 'google_maps_api_key' ] ) ? $fmc_settings[ 'google_maps_api_key' ] : '' ); ?>">
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="map_height">Default Map Height</label>
				</th>
				<td>
					<p><input type="text" class="fmc-small-number" name="fmc_settings[map_height]" id="map_height" value="<?php echo ( isset( $fmc_settings[ 'map_height' ] ) ? $fmc_settings[ 'map_height' ] : '' ); ?>"></p>
					<p class="description">Enter a height value (in px).</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="google_maps_no_enqueue">Google Maps JavaScript</label>
				</th>
				<td>
					<p><label for="google_maps_no_enqueue"><input type="checkbox" name="fmc_settings[google_maps_no_enqueue]" id="google_maps_no_enqueue" value="1" <?php checked( $google_maps_no_enqueue, 1 ); ?>> Do not load the Google Maps API script</label></p>
					<p class="description">If checked, the Google Maps javascript will not be loaded by this plugin. Use this if your theme or other plugins already load the Google Maps script and your API Key.</p>
				</td>
			</tr>
		</tbody>
	</table>
	<?php \FlexMLS\Admin\Settings::floating_save_button( 'update_google_maps_action', 'update_google_maps_nonce', 'Save Settings' ); ?>
</form>
