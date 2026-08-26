<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

$fmc_settings = get_option( 'fmc_settings' );
if ( ! is_array( $fmc_settings ) ) {
	$fmc_settings = array();
}

$fmc_settings[ 'multiple_summaries' ] = ( isset( $fmc_settings[ 'multiple_summaries' ] ) && 1 == $fmc_settings[ 'multiple_summaries' ] ) ? 1 : 0;
$fmc_settings[ 'allow_sold_searching' ] = ( isset( $fmc_settings[ 'allow_sold_searching' ] ) && 1 == $fmc_settings[ 'allow_sold_searching' ] ) ? 1 : 0;
$fmc_settings[ 'search_listing_card_native_lazy_load' ] = isset( $fmc_settings['search_listing_card_native_lazy_load'] ) ? (int) $fmc_settings['search_listing_card_native_lazy_load'] : 1;
$fmc_settings[ 'search_results_display_open_house_datetime' ] = ( isset( $fmc_settings[ 'search_results_display_open_house_datetime' ] ) && 1 == $fmc_settings[ 'search_results_display_open_house_datetime' ] ) ? 1 : 0;

?>
<form action="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=search-results' ); ?>" method="post">
	<h3>Search Results Settings</h3>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="multiple_summaries_y">Listing Summary/Search Result pages blank?</label>
				</th>
				<td>
					<p>
						<label for="multiple_summaries_y"><input type="radio" name="fmc_settings[multiple_summaries]" id="multiple_summaries_y" value="1" <?php checked( $fmc_settings[ 'multiple_summaries' ], 1 ); ?>> Yes, turn on compatibility mode</label><br />
						<label for="multiple_summaries_n"><input type="radio" name="fmc_settings[multiple_summaries]" id="multiple_summaries_n" value="0" <?php checked( $fmc_settings[ 'multiple_summaries' ], 0 ); ?>> No, keep normal behavior (recommended)</label>
					</p>
					<p class="description">If Listing Summary or Search Results pages appear blank, turn this on and save. This enables a compatibility mode that can resolve theme/plugin conflicts causing blank output.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="allow_sold_searching_y">Sold &amp; Pending Listings Search</label>
				</th>
				<td>
					<p>
						<label for="allow_sold_searching_y"><input type="radio" name="fmc_settings[allow_sold_searching]" id="allow_sold_searching_y" value="1" <?php checked( $fmc_settings[ 'allow_sold_searching' ], 1 ); ?>> Yes, allow visitors to search for sold &amp; pending listings</label><br />
						<label for="allow_sold_searching_n"><input type="radio" name="fmc_settings[allow_sold_searching]" id="allow_sold_searching_n" value="0" <?php checked( $fmc_settings[ 'allow_sold_searching' ], 0 ); ?>> No, do not allow searches for sold &amp; pending listings</label>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<h3>Search Results Page (Version 2 Template Only)</h3>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="search-listing-card-native-lazy-load">Listing thumbnail loading</label>
				</th>
				<td>
					<input type="hidden" name="fmc_settings[search_listing_card_native_lazy_load]" value="0" />
					<p>
						<label>
							<input type="checkbox" name="fmc_settings[search_listing_card_native_lazy_load]" id="search-listing-card-native-lazy-load" value="1" <?php checked( $fmc_settings[ 'search_listing_card_native_lazy_load' ], 1 ); ?> />
							Use the browser&rsquo;s native lazy loading for listing photos on search results (recommended).
						</label>
					</p>
					<p class="description">Turn this off if another plugin or your theme lazy-loads images and thumbnails look wrong or fail to load.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="search-results-display-open-house-datetime">Display Open House Date/Time</label>
				</th>
				<td>
					<input type="hidden" name="fmc_settings[search_results_display_open_house_datetime]" value="0" />
					<p>
						<label>
							<input type="checkbox" name="fmc_settings[search_results_display_open_house_datetime]" id="search-results-display-open-house-datetime" value="1" <?php checked( $fmc_settings[ 'search_results_display_open_house_datetime' ], 1 ); ?> />
							Show the next open house date and time on Version 2 listing summary and search results cards.
						</label>
					</p>
					<p class="description">When enabled, listings with an upcoming open house display the date and start time (for example, <code>Open House: 08/22/2026 - 1:00 PM</code>).</p>
				</td>
			</tr>
		</tbody>
	</table>

	<h3>Search Results Page (Version 1 Template Only)</h3>
	<p>Customize which fields are shown on the search results page. Drag the fields to change their order.</p>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label>Search Results Fields</label>
				</th>
				<td>
					<?php
						$SparkFields = new \SparkAPI\StandardFields();
						$property_fields = $SparkFields->get_standard_fields();

						$json_fields = json_encode( isset( $fmc_settings[ 'search_results_fields' ] ) && is_array( $fmc_settings[ 'search_results_fields' ] ) ? $fmc_settings[ 'search_results_fields' ] : array() );

						// Template that will be populated with $jsonFields data through js
						$json_template  = '<div id="flexmls_connect__field_{{field_id}}" class="flexmls_connect__admin_srf_row">';
						$json_template .= '<span class="flexmls_connect__admin_srf_field_col">{{field_id}}</span>';
						$json_template .= '<input class="flexmls_connect__admin_srf_display_col" type="text" name="fmc_settings[search_results_fields][{{field_id}}]" value="{{display_name}}">';
						$json_template .= '<a class="flexmls_connect__admin_srf_delete" href="#">Delete</a>';
						$json_template .= '</div>';
					?>
					<div id="flexmls_connect__admin_srf_table" class="flexmls_connect__admin_srf_table" data-fields='<?php echo $json_fields; ?>' data-template='<?php echo $json_template; ?>'>
						<div class="flexmls_connect__admin_srf_labels">
							<div class="flexmls_connect__admin_srf_label flexmls_connect__admin_srf_field_col">Field ID</div>
							<div class="flexmls_connect__admin_srf_label flexmls_connect__admin_srf_display_col">Display Name</div>
						</div>
					</div>
					<br />
					<select data-placeholder="Add a new field..." class="chosen-select flexmls_connect__admin_srf_add_new" style="width:350px;" tabindex="4">
						<option value=""></option>
						<?php if( is_array( $property_fields ) ): ?>
							<?php foreach( $property_fields[ 0 ] as $property_key => $property_val ): ?>
								<option value="<?php echo $property_key; ?>"><?php echo $property_val[ 'Label' ]; ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</td>
			</tr>
		</tbody>
	</table>

	<?php \FlexMLS\Admin\Settings::floating_save_button( 'update_fmc_search_results_action', 'update_fmc_search_results_nonce', 'Save Search Results Settings' ); ?>
</form>
