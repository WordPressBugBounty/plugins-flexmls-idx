
<div class="flexmls-listings-list-wrapper flexmls-widthchange-wrapper">
	<?php if ( ! empty( $this->search_data ) ) : ?>
		<?php
			$mls_fields_to_suppress = [];
			global $wp;
			$current_url = home_url( add_query_arg( $_GET, $wp->request ) );

			$fmc_settings_listing_lazy = get_option( 'fmc_settings' );
			$fmc_v2_listing_native_lazy_on = ! is_array( $fmc_settings_listing_lazy )
				|| ! isset( $fmc_settings_listing_lazy['search_listing_card_native_lazy_load'] )
				|| (int) $fmc_settings_listing_lazy['search_listing_card_native_lazy_load'] === 1;
			$display_open_house_datetime = is_array( $fmc_settings_listing_lazy )
				&& ! empty( $fmc_settings_listing_lazy['search_results_display_open_house_datetime'] )
				&& (int) $fmc_settings_listing_lazy['search_results_display_open_house_datetime'] === 1;

			foreach ($this->search_data as $record) :
				// Establish some variables
				$listing_address = flexmlsConnect::format_listing_street_address($record);
				$first_line_address = htmlspecialchars($listing_address[0]);
				$second_line_address = htmlspecialchars($listing_address[1]);
				$one_line_address = htmlspecialchars($listing_address[2]);
				$one_line_without_zip_address = flexmlsSearchUtil::one_line_without_zip_address( $record );
				$link_to_details_criteria = $this->search_criteria;


				$sf = isset( $record['StandardFields'] ) && is_array( $record['StandardFields'] ) ? $record['StandardFields'] : array();

				if ( empty( $mls_fields_to_suppress ) ) {
					$mls_fields_to_suppress = flexmlsSearchUtil::mls_fields_to_suppress( $sf );
				}

				$link_to_details_criteria['m'] = $sf['MlsId'] ?? '';

				$link_to_details = flexmlsConnect::make_nice_address_url($record, $link_to_details_criteria);

				$link_to_details = add_query_arg( 'search_referral_url', urlencode( $current_url ), $link_to_details );

		?>
			<?php $main_photo = fmcSearchResults::main_photo_from_collection( $sf['Photos'] ?? array() ); ?>
			<?php
			?>
			<div
				class="flexmls-listing flexmls_connect__sr_result"
				title="<?php echo esc_attr( $one_line_address . ' - MLS# ' . $sf['ListingId'] ); ?>"
				link="<?php echo esc_url( $link_to_details ); ?>"
			>
				<?php
				// Accessible description for the listing photo (<img alt>).
				$image_aria_label = '';
				if ( ! empty( $main_photo['caption'] ) ) {
					$image_aria_label = $main_photo['caption'];
				} elseif ( ! empty( $one_line_address ) ) {
					$image_aria_label = 'Property photo for ' . $one_line_address;
				} else {
					$image_aria_label = 'Property photo for listing #' . $sf['ListingId'];
				}

				$srcset_parts = array(
					esc_url( $main_photo['Uri300'] ) . ' 300w',
					esc_url( $main_photo['Uri640'] ) . ' 640w',
				);
				if ( ! empty( $main_photo['Uri1280'] ) && $main_photo['Uri1280'] !== $main_photo['Uri640'] ) {
					$srcset_parts[] = esc_url( $main_photo['Uri1280'] ) . ' 1280w';
				}
				$photo_srcset = implode( ', ', $srcset_parts );
				// Card image width: ~380px in 3-col, ~48% in 2-col, up to 480px single column.
				$photo_sizes = '(min-width: 900px) 380px, (min-width: 600px) 48vw, min(480px, 100vw)';
				?>
				<div class="flexmls-image-wrapper">
					<a
						href="<?php echo esc_url( $link_to_details ); ?>"
						class="flexmls-listing-photo-link flexmls-listing-photo-action--detail"
					>
						<img
							class="flexmls-listing-card__photo"
							src="<?php echo esc_url( $main_photo['Uri640'] ); ?>"
							srcset="<?php echo esc_attr( $photo_srcset ); ?>"
							sizes="<?php echo esc_attr( $photo_sizes ); ?>"
							alt="<?php echo esc_attr( $image_aria_label ); ?>"
							<?php if ( $fmc_v2_listing_native_lazy_on ) : ?>loading="lazy" <?php endif; ?>
							decoding="async"
							width="640"
							height="400"
						/>
					</a>
				<?php if ( ! empty( $sf['OnMarketDate'] ?? '' ) ) : ?>	
					<?php if ( strtotime( $sf['OnMarketDate'] ) > strtotime( '-7 days' ) ) : ?>
						<span class="new-listing-tag">New Listing</span>
					<?php endif; ?>
					<?php if ( ! empty( $sf['OpenHousesCount'] ?? 0 ) ) : ?>
						<span class="new-listing-tag open-house">Open House</span>
					<?php endif; ?>
				<?php endif; ?>
					<?php $list_price = flexmlsConnect::format_listing_standard_price_display( $sf ); ?>
					<span class="flexmls-price"><?php echo esc_html( $list_price ); ?></span>
					<div class="flexmls-portal-links">
						<?php fmcAccount::write_carts( $record ); ?>
					</div>
				</div>
				<a href="<?php echo esc_url( $link_to_details ); ?>" class="flexmls-content-wrapper flexmls-listing-content-link">
					<div class="flexmls-address">
						<?php echo esc_html( $one_line_without_zip_address ); ?>
					</div>
					<div class="flexmls-quick-details">
						<?php $sf_status = $sf['MlsStatus'] ?? $sf['StandardStatus'] ?? ''; ?>
							<span class="flexmls-status flexmls-status-<?php echo sanitize_title( $sf_status ); ?>"><?php echo esc_html( $sf_status ); ?></span>
						<?php
							$is_beds_present = flexmlsConnect::is_not_blank_or_restricted( $sf['BedsTotal'] ?? '' );
							$is_baths_present = flexmlsConnect::is_not_blank_or_restricted( $sf['BathsTotal'] ?? '' );
							$is_sqft_present = flexmlsConnect::is_not_blank_or_restricted( $sf['BuildingAreaTotal'] ?? '' ) || flexmlsConnect::is_not_blank_or_restricted( $sf['LivingArea'] ?? '' );
						?>
						<?php if ( $is_beds_present || $is_baths_present || $is_sqft_present ) : ?>
							<div class="flexmls-details">
								<?php if ( $is_beds_present ) : ?>
									<span class="flexmls-detail"><?php echo esc_html( $sf['BedsTotal'] ); ?>BD</span>
								<?php endif; ?>

								<?php if ( $is_baths_present ) : ?>
									<span class="flexmls-detail"><?php echo esc_html( $sf['BathsTotal'] ); ?>BA</span>
								<?php endif; ?>

								<?php if ( $is_sqft_present ) : ?>
								<?php	$sf_sqft = ( flexmlsConnect::is_not_blank_or_restricted( $sf['BuildingAreaTotal'] ?? '' ) ) ? $sf['BuildingAreaTotal'] : ( $sf['LivingArea'] ?? '' ); ?>
									<span class="flexmls-detail"><?php echo esc_html( number_format($sf_sqft) ); ?>SF</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
					<?php
					$open_houses = ( isset( $sf['OpenHouses'] ) && is_array( $sf['OpenHouses'] ) ) ? $sf['OpenHouses'] : array();
					$next_open_house = ! empty( $open_houses[0] ) && is_array( $open_houses[0] ) ? $open_houses[0] : null;
					?>
					<?php if ( $display_open_house_datetime && $next_open_house && ( ! empty( $next_open_house['Date'] ) || ! empty( $next_open_house['StartTime'] ) || ! empty( $next_open_house['EndTime'] ) ) ) : ?>
						<?php
						$open_house_date  = isset( $next_open_house['Date'] ) ? $next_open_house['Date'] : '';
						$open_house_start = isset( $next_open_house['StartTime'] ) ? $next_open_house['StartTime'] : '';
						$open_house_end   = isset( $next_open_house['EndTime'] ) ? $next_open_house['EndTime'] : '';
						$open_house_time  = trim( $open_house_start . ( $open_house_start && $open_house_end ? ' - ' : '' ) . $open_house_end );
						$open_house_display = trim( $open_house_date . ( $open_house_date && $open_house_time ? ' - ' : '' ) . $open_house_time );
						?>
						<div class="flexmls-open-house-datetime-wrapper">
							<span class="flexmls-bold-label">Open House: </span><?php echo esc_html( $open_house_display ); ?>
						</div>
					<?php endif; ?>
					<div class="flexmls-last-modified-and-idx-wrapper">
						<?php if ( flexmlsConnect::is_not_blank_or_restricted( $sf['ModificationTimestamp'] ?? '' ) ) : ?>
							<div class="flexmls-last-modified-and-label-wrapper">
								<span class="flexmls-bold-label">Last Modified:</span> <?php echo esc_html( flexmlsConnect::format_date( "g:ia, F j, Y", $sf["ModificationTimestamp"] ) ); ?>
							</div>
						<?php endif; ?>
						<?php fmcSearchResults::compliance_label( $record ); ?>
					</div>

					<?php if ( flexmlsConnect::mls_requires_office_name_in_search_results() ) : ?>
						<?php $listing_office_label = flexmlsConnect::listing_detail_list_office_label( $sf ); ?>
						<span class="flexmls-office-name">
							<span class="flexmls-bold-label"><?php echo esc_html( $listing_office_label ) ; ?></span>
							<?php echo esc_html( $sf['ListOfficeName'] ?? '' ); ?>
						</span>
					<?php endif; ?>

					<?php if ( flexmlsConnect::mls_requires_agent_name_in_search_results() ) : ?>
						<div class="flexmls-agent-name-and-label-wrapper">
							<span class="flexmls-agent-name">
								<span class="flexmls-bold-label">Listing Agent: </span>
								<?php echo esc_html( $sf['ListAgentName'] ?? '' ); ?>

									<?php if ( flexmlsConnect::mls_requires_agent_phone_in_search_results() ) : ?>
										<?php 
										$phone_number = flexmlsConnect::get_agent_phone_with_fallback( $sf, 'search' );
										if ( ! empty( $phone_number ) ) {
											echo "<br/>" . esc_html( $phone_number );
										}
										?>
									<?php endif; ?>

									<?php if ( flexmlsConnect::mls_requires_agent_email_in_search_results() ) : ?>
										<?php echo " |  " . esc_html( $sf['ListAgentEmail'] ?? '' ); ?>
									<?php endif; ?>
								
							</span>
						</div>
					<?php endif; ?>
					
				</a>
			</div>

		<?php endforeach; ?>
	<?php endif; ?>
</div>
