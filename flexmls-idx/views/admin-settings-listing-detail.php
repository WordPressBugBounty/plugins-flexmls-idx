<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

$fmc_settings = get_option( 'fmc_settings' );
if ( ! is_array( $fmc_settings ) ) {
	$fmc_settings = array();
}

$fmc_settings[ 'listlink' ] = isset( $fmc_settings[ 'listlink' ] ) ? $fmc_settings[ 'listlink' ] : '';
$fmc_settings[ 'listpref' ] = isset( $fmc_settings[ 'listpref' ] ) ? $fmc_settings[ 'listpref' ] : 'listpref';
$fmc_settings[ 'listing_detail_expand_sections' ] = isset( $fmc_settings[ 'listing_detail_expand_sections' ] ) ? (int) $fmc_settings[ 'listing_detail_expand_sections' ] : 0;
$fmc_settings[ 'listing_detail_show_more_info' ] = isset( $fmc_settings[ 'listing_detail_show_more_info' ] ) ? (int) $fmc_settings[ 'listing_detail_show_more_info' ] : 1;
$fmc_settings[ 'listing_detail_contact_on_closed' ] = isset( $fmc_settings[ 'listing_detail_contact_on_closed' ] ) ? (int) $fmc_settings[ 'listing_detail_contact_on_closed' ] : 1;
$fmc_settings[ 'v2_listing_photo_click_action' ] = ( isset( $fmc_settings[ 'v2_listing_photo_click_action' ] ) && in_array( $fmc_settings[ 'v2_listing_photo_click_action' ], array( 'detail', 'modal' ), true ) ) ? $fmc_settings[ 'v2_listing_photo_click_action' ] : 'modal';
$fmc_settings[ 'v2_listing_photo_modal_provider' ] = ( isset( $fmc_settings[ 'v2_listing_photo_modal_provider' ] ) && in_array( $fmc_settings[ 'v2_listing_photo_modal_provider' ], array( 'auto', 'cbox', 'third_party', 'none' ), true ) ) ? $fmc_settings[ 'v2_listing_photo_modal_provider' ] : 'auto';
$show_v2_photo_modal_provider = ( 'modal' === $fmc_settings['v2_listing_photo_click_action'] );

$all_public_pages = get_posts( array(
	'order' => 'ASC',
	'orderby' => 'menu_order name',
	'nopaging' => true,
	'post_type' => 'page'
) );
$all_public_pages = is_array( $all_public_pages ) ? $all_public_pages : array();

?>
<form action="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=listing-detail' ); ?>" method="post">
	<h3>Listing Detail Settings</h3>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="listpref_page">Listing Not Available Page</label>
				</th>
				<td>
					<p>
						<label for="listpref_default"><input type="radio" name="fmc_settings[listpref]" id="listpref_default" value="listpref" <?php checked( $fmc_settings[ 'listpref' ], 'listpref' ); ?>> Show Default Message: <em>This listing is no longer available</em></label><br />
						<label for="listpref_page"><input type="radio" name="fmc_settings[listpref]" id="listpref_page" value="page" <?php checked( $fmc_settings[ 'listpref' ], 'page' ); ?>> Mimic the contents of this page:</label> <select name="fmc_settings[listlink]"><?php
							foreach( $all_public_pages as $template ): ?>
								<option value="<?php echo $template->ID; ?>" <?php selected( $template->ID, $fmc_settings[ 'listlink' ] ); ?>><?php
									echo $template->post_title;
									if( $fmc_settings[ 'listlink' ] == $template->ID ){
										echo ' (Current Default)';
									}
								?></option>
							<?php endforeach; ?>
						?></select>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="listing_detail_expand_sections_y">Expand all listing detail page sections by default</label>
				</th>
				<td>
					<p>
						<label for="listing_detail_expand_sections_y"><input type="radio" name="fmc_settings[listing_detail_expand_sections]" id="listing_detail_expand_sections_y" value="1" <?php checked( $fmc_settings[ 'listing_detail_expand_sections' ], 1 ); ?>> Yes, show all sections expanded</label><br />
						<label for="listing_detail_expand_sections_n"><input type="radio" name="fmc_settings[listing_detail_expand_sections]" id="listing_detail_expand_sections_n" value="0" <?php checked( $fmc_settings[ 'listing_detail_expand_sections' ], 0 ); ?>> No, show other sections collapsed (users can expand each)</label>
					</p>
					<p class="description">Address Information, Location Tax &amp; Legal, General Property Information, and Property Features are always expanded. When set to No, remaining sections (e.g. Contract Information, Kitchen Features) start collapsed to reduce scrolling.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="listing_detail_show_more_info_y">Show &quot;More Information&quot; section on listing detail pages</label>
				</th>
				<td>
					<p>
						<label for="listing_detail_show_more_info_y"><input type="radio" name="fmc_settings[listing_detail_show_more_info]" id="listing_detail_show_more_info_y" value="1" <?php checked( $fmc_settings[ 'listing_detail_show_more_info' ], 1 ); ?>> Yes, show the More Information section</label><br />
						<label for="listing_detail_show_more_info_n"><input type="radio" name="fmc_settings[listing_detail_show_more_info]" id="listing_detail_show_more_info_n" value="0" <?php checked( $fmc_settings[ 'listing_detail_show_more_info' ], 0 ); ?>> No, hide the More Information section</label>
					</p>
					<p class="description">When No, the expandable &quot;More Information&quot; block is not displayed on listing detail pages.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="listing_detail_contact_on_closed_y">Contact on sold &amp; closed listings</label>
				</th>
				<td>
					<p>
						<label for="listing_detail_contact_on_closed_y"><input type="radio" name="fmc_settings[listing_detail_contact_on_closed]" id="listing_detail_contact_on_closed_y" value="1" <?php checked( $fmc_settings[ 'listing_detail_contact_on_closed' ], 1 ); ?>> Yes, show the Contact button on sold &amp; closed listings</label><br />
						<label for="listing_detail_contact_on_closed_n"><input type="radio" name="fmc_settings[listing_detail_contact_on_closed]" id="listing_detail_contact_on_closed_n" value="0" <?php checked( $fmc_settings[ 'listing_detail_contact_on_closed' ], 0 ); ?>> No, hide it on sold &amp; closed listings</label>
					</p>
				</td>
			</tr>
			<tr id="fmc-setting-v2-listing-detail-photo-action">
				<th scope="row">
					<label for="v2_listing_photo_click_action_detail">Version 2 Listing Detail Photo Action</label>
				</th>
				<td>
					<p>
						<label for="v2_listing_photo_click_action_detail"><input type="radio" name="fmc_settings[v2_listing_photo_click_action]" id="v2_listing_photo_click_action_detail" value="detail" <?php checked( $fmc_settings[ 'v2_listing_photo_click_action' ], 'detail' ); ?>> Do not open photo in modal window</label><br />
						<label for="v2_listing_photo_click_action_modal"><input type="radio" name="fmc_settings[v2_listing_photo_click_action]" id="v2_listing_photo_click_action_modal" value="modal" <?php checked( $fmc_settings[ 'v2_listing_photo_click_action' ], 'modal' ); ?>> Open large photo in modal window</label>
					</p>
					<p class="description">Applies only to Version 2 listing detail pages.</p>
				</td>
			</tr>
			<tr id="fmc-setting-v2-listing-detail-photo-modal-provider" <?php if ( ! $show_v2_photo_modal_provider ) : ?>style="display:none;"<?php endif; ?>>
				<th scope="row">
					<label for="v2_listing_photo_modal_provider_auto">Version 2 Photo Modal Provider</label>
				</th>
				<td>
					<p>
						<label for="v2_listing_photo_modal_provider_auto"><input type="radio" name="fmc_settings[v2_listing_photo_modal_provider]" id="v2_listing_photo_modal_provider_auto" value="auto" <?php checked( $fmc_settings[ 'v2_listing_photo_modal_provider' ], 'auto' ); ?>> Auto</label><br />
						<span class="description">Use your active theme/plugin lightbox when available; otherwise use the flexmls modal.</span><br />
						<label for="v2_listing_photo_modal_provider_cbox"><input type="radio" name="fmc_settings[v2_listing_photo_modal_provider]" id="v2_listing_photo_modal_provider_cbox" value="cbox" <?php checked( $fmc_settings[ 'v2_listing_photo_modal_provider' ], 'cbox' ); ?>> Force flexmls modal</label><br />
						<span class="description">Always use the built-in flexmls modal for listing photos.</span><br />
						<label for="v2_listing_photo_modal_provider_third_party"><input type="radio" name="fmc_settings[v2_listing_photo_modal_provider]" id="v2_listing_photo_modal_provider_third_party" value="third_party" <?php checked( $fmc_settings[ 'v2_listing_photo_modal_provider' ], 'third_party' ); ?>> Third-party only</label><br />
						<span class="description">Use only your active theme/plugin lightbox and disable the flexmls modal.</span><br />
						<label for="v2_listing_photo_modal_provider_none"><input type="radio" name="fmc_settings[v2_listing_photo_modal_provider]" id="v2_listing_photo_modal_provider_none" value="none" <?php checked( $fmc_settings[ 'v2_listing_photo_modal_provider' ], 'none' ); ?>> No modal</label><br />
						<span class="description">Keep photos non-modal when clicked.</span>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php \FlexMLS\Admin\Settings::floating_save_button( 'update_fmc_listing_detail_action', 'update_fmc_listing_detail_nonce', 'Save Listing Detail Settings' ); ?>
</form>
<script>
	jQuery( function( $ ) {
		var $providerRow = $( '#fmc-setting-v2-listing-detail-photo-modal-provider' );
		var $modalAction = $( '#v2_listing_photo_click_action_modal' );

		if ( ! $providerRow.length || ! $modalAction.length ) {
			return;
		}

		var toggleProviderRow = function() {
			if ( $modalAction.is( ':checked' ) ) {
				$providerRow.show();
			} else {
				$providerRow.hide();
			}
		};

		$( 'input[name="fmc_settings[v2_listing_photo_click_action]"]' ).on( 'change', toggleProviderRow );
		toggleProviderRow();
	} );
</script>
