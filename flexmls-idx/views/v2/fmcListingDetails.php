<?php flexmlsPortalPopup::popup_portal('detail_page');
      $api_prefs = $fmc_api->GetPreferences();
      if (!is_array($api_prefs) || !isset($api_prefs['RequiredFields']) || !is_array($api_prefs['RequiredFields'])) {
        $api_prefs['RequiredFields'] = array();
      }
      $phone_req  = in_array('phone', $api_prefs['RequiredFields']);
?>
<?php $listing_display_price = flexmlsConnect::format_listing_standard_price_display( $sf ); ?>
<div class="flexmls-listing-details flexmls-v2-widget flexmls-widthchange-wrapper flexmls-body-font">
	<?php $has_search_return = ! empty( $_GET['search_referral_url'] ); ?>
	<div class="flexmls-actions-wrapper listing-section <?php echo $has_search_return ? 'has-return-button' : ''; ?>">
		<?php if ( $has_search_return ) : ?>
			<?php 
			// Sanitize and validate the search referral URL to prevent XSS attacks
			$search_referral_url = isset( $_GET['search_referral_url'] ) ? $_GET['search_referral_url'] : '';
			
			// Get the default search URL with permalink base (e.g., /idx/search/)
			$options = get_option('fmc_settings');
			$permabase = isset( $options['permabase'] ) ? $options['permabase'] : 'idx';
			$default_search_url = get_home_url() . '/' . $permabase . '/search/';
			
			$back_to_search_link = wp_validate_redirect( $search_referral_url, $default_search_url );
			?>
			<a class="back-to-search-link flexmls-primary-color-font" href="<?php echo esc_url( $back_to_search_link ); ?>">&larr; Back to search</a>
		<?php endif; ?>
		<button class="flexmls-btn flexmls-btn-primary flexmls-primary-color-background" onclick="flexmls_connect.contactForm({
			'title': 'Contact agent',
			'subject': '<?php echo $one_line_address_add_slashes; ?> - MLS# <?php echo addslashes($sf['ListingId'])?> ',
			'agentEmail': '<?php echo $this->contact_form_agent_email( $sf ); ?>',
			'officeEmail': '<?php echo $this->contact_form_office_email( $sf ); ?>',
			'phoneRequired': <?php echo $phone_req ? 'true' : 'false'; ?>,
			'id': '<?php echo addslashes( $sf['ListingId'] ); ?>'
		<?php if( isset($options['contact_disclaimer']) ) : ?>
			,'disclaimer': '<?php echo esc_js(flexmlsConnect::get_contact_disclaimer()); ?>'
		<?php endif; ?>
		});">
			Contact agent
		</button>
		<div class="flexmls_connect__success_message" id="flexmls_connect__success_message<?php echo esc_attr( $sf['ListingId'] ); ?>" role="status" aria-live="polite"></div>
	</div>
	<div class="top-info-wrapper listing-section">
		<div class="title-and-details-wrapper">
			<div class="title-and-status-wrapper">
				<h2 class="property-title flexmls-title-largest flexmls-primary-color-font flexmls-heading-font"><?php echo esc_html( $one_line_address ); ?></h2>

				<?php if ( $sf['OnMarketDate'] ) : ?>
					<?php if ( strtotime( $sf['OnMarketDate'] ) > strtotime( '-7 days' ) ) : ?>
						<span class="new-listing-tag">New Listing</span>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			
			<div class="price-and-actions-wrapper">
					<span class="flexmls-price flexmls-title-large"><?php echo esc_html( $listing_display_price ); ?></span>
				<div class="actions-wrapper">
					<?php fmcAccount::write_carts( $record ); ?>
				</div>
			</div>
		</div>
	</div>
	<?php if ( $count_photos > 0 ) : ?>
		<div class="slideshow-wrapper listing-section">
			<div id="listing-slideshow" class="owl-carousel">
				<?php foreach ( $sf['Photos'] as $index => $p ) : ?>
					<?php if ( $index == 1 ) : ?>
						<?php if ( $count_videos > 0 ) : ?>
							<?php foreach ( $sf['Videos'] as $video ) : ?>
								<?php if ( $video['Privacy'] == "Public" ) : ?>
									<div class="listing-image listing-video">
										<?php echo $this->iframe_from_html_or_url( $video['ObjectHtml'] ); ?>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
						<?php if ( $count_tours > 0 ) : ?>
							<?php foreach ( $sf['VirtualTours'] as $vtour ) : ?>
								<?php if ( $vtour['Privacy'] == "Public" ) : ?>
									<?php $vt_bg_photo = $index - 1; ?>
									<div class="listing-image listing-vtour" style="background-image: url('<?php echo $sf['Photos'][$vt_bg_photo]['UriLarge']; ?>');">
										<div class="listing-vtour-card">
											<h3>Virtual Tour</h3>
										<a href="<?php echo $vtour['Uri']; ?>" target="_blank">
											<div class="listing-vtour-card-link"><?php echo $vtour['Uri']; ?>
											</div>							
											<button class="flexmls-btn flexmls-btn-primary flexmls-primary-color-background">View Tour</button>
										</a>
										</div>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endif; ?>

					<?php
					// Set alt value for ADA compliance
					$img_alt_attr = '';
					if ( !empty( $p['Caption'] ) ) {
						$img_alt_attr = htmlspecialchars( $p['Caption'], ENT_QUOTES );
					} elseif ( !empty( $p['Name'] ) ) {
						$img_alt_attr = htmlspecialchars( $p['Name'], ENT_QUOTES );
					} elseif ( !empty( $one_line_address ) ) {
						$img_alt_attr = $one_line_address;
					} else {
						$img_alt_attr = "Photo for listing #" . $sf['ListingId'];
					}
					?>
					<img class="owl-lazy" data-src="<?php echo esc_url( $p['UriLarge'] ); ?>" alt="<?php echo esc_attr( $img_alt_attr ); ?>" />
				<?php endforeach; ?>
			</div>
		</div>
		<script type="text/javascript">
			jQuery( function () {
				jQuery('#listing-slideshow').owlCarousel(
					{
						lazyLoad: true,
						autoHeight: true,
						nav: true,
						dots: false,
						center: true,
						loop: true,
						navText: [ "&lsaquo;", "&rsaquo;" ],
						responsive: {
							0: {
								items: 1
							},
							600: {
								items: 1
							}
						}
					}
				);
			} );
		</script>
	<?php endif; ?>

	<div class="main-details-section listing-section">
		<div class="flexmls-details">
			<?php
				$main_details = [
					['field' => 'PropertyTypeLabel', 'label' => 'Property Type'],
					['field' => 'BedsTotal', 'label' => 'Bedrooms'],
					['field' => 'BathsTotal', 'label' => 'Baths'],
				];

				if ( flexmlsConnect::is_not_blank_or_restricted( $sf['BuildingAreaTotal'] ) ) {
					$main_details []= ['field' => 'BuildingAreaTotal', 'label' => 'Square Footage', 'value' => number_format( $sf['BuildingAreaTotal'] )]; 
				}

				elseif ( flexmlsConnect::is_not_blank_or_restricted( $sf['LivingArea'] ) ) {
					$main_details []= ['field' => 'LivingArea', 'label' => 'Square Footage', 'value' => number_format( $sf['LivingArea'] )]; 
				}

				if ( flexmlsConnect::is_not_blank_or_restricted( $sf['LotSizeSquareFeet'] ) ) {
					$main_details []= ['field' => 'LotSizeSquareFeet', 'label' => 'Lot Size (sq. ft.)', 'value' => number_format( $sf['LotSizeSquareFeet'] ) ];
				}

				if ( flexmlsConnect::is_not_blank_or_restricted( $sf['MlsStatus'] ) ) {
					$main_details []= ['field' => 'MlsStatus', 'label' => 'Status' ];
				}

			?>

			<?php foreach ( $main_details as $detail ) : ?>
				<?php if ( flexmlsConnect::is_not_blank_or_restricted( $sf[$detail['field']] ) ) : ?>
					<?php $value = array_key_exists( 'value', $detail ) ? $detail['value'] : $sf[$detail['field']]; ?>
					<span class="flexmls-detail">
						<span class="detail-label flexmls-heading-font"><?php echo esc_html( $detail['label'] ); ?>:</span>
						<span class="detail-value"><?php echo esc_html( $value ); ?></span>
					</span>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<div class="price-and-dates">
			<?php if ( $listing_display_price !== '' ) : ?>
				<span class="flexmls-detail flexmls-price">
					<span class="detail-label">Current Price:</span>
					<span class="detail-value"><?php echo esc_html( $listing_display_price ); ?></span>
				</span>
			<?php endif; ?>
			<?php if( flexmlsConnect::is_not_blank_or_restricted( $sf['OnMarketDate'] ) ) : ?>
				<span class="flexmls-detail">
					<span class="detail-label">List Date:</span>
					<span class="detail-value"><?php echo esc_html( date( 'n/d/Y', strtotime( $sf['OnMarketDate'] ) ) ); ?></span>
				</span>
			<?php endif; ?>
			<?php if( flexmlsConnect::is_not_blank_or_restricted( $sf['ListingUpdateTimestamp'] ) ) : ?>
				<span class="flexmls-detail">
					<span class="detail-label">Last Modified:</span>
					<span class="detail-value"><?php echo esc_html( date( 'n/d/Y', strtotime( $sf['ListingUpdateTimestamp'] ) ) ); ?></span>
				</span>
			<?php endif; ?>
		</div>
	</div>
	  
	<div class="overview-section listing-section">
		<h2 class="flexmls-title-larger flexmls-primary-color-font flexmls-heading-font">Overview</h2>
		<?php if ( flexmlsConnect::is_not_blank_or_restricted( $sf['PublicRemarks'] ) ) : ?>
			<h3 class="flexmls-title-large flexmls-heading-font overview-subhead">Description</h3>
			<?php
				$remarks_full = $sf['PublicRemarks'];
				$remarks_plain = wp_strip_all_tags( $remarks_full );
				$remarks_limit = 1000;
				$remarks_long = strlen( $remarks_plain ) > $remarks_limit;
				$remarks_teaser = $remarks_long ? substr( $remarks_plain, 0, $remarks_limit ) : '';
			?>
			<div class="flexmls-description">
				<span class="flexmls-description-teaser"><?php echo $remarks_long ? esc_html( $remarks_teaser ) : $remarks_full; ?></span>
				<?php if ( $remarks_long ) : ?>
					<span class="flexmls-description-ellipsis">...</span>
					<a href="#" class="flexmls-description-read-more" aria-expanded="false">(Read more)</a>
					<span class="flexmls-description-full" style="display:none;"><?php echo $remarks_full; ?></span>
				<?php endif; ?>
			</div>
			<?php if ( flexmlsConnect::is_not_blank_or_restricted( $sf['Supplement'] ) ) : ?>
				<?php
					$supplement_full = $sf['Supplement'];
					$supplement_plain = wp_strip_all_tags( $supplement_full );
					$supplement_limit = 350;
					$is_long = strlen( $supplement_plain ) > $supplement_limit;
					$supplement_teaser = $is_long ? substr( $supplement_plain, 0, $supplement_limit ) : '';
				?>
				<div class="flexmls-supplement-wrapper">
					<p class="flexmls-supplement">
						<strong>Supplements:</strong>
						<span class="flexmls-supplement-teaser"><?php echo $is_long ? esc_html( $supplement_teaser ) : $supplement_full; ?></span>
						<?php if ( $is_long ) : ?>
							<span class="flexmls-supplement-ellipsis">...</span>
							<a href="#" class="flexmls-supplement-read-more" aria-expanded="false">(Read more)</a>
							<span class="flexmls-supplement-full" style="display:none;"><?php echo $supplement_full; ?></span>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( isset( $sf['OpenHousesCount'] ) && $sf['OpenHousesCount'] > 0 && ! empty( $sf['OpenHouses'] ) ) : ?>
			<h3 class="flexmls-title-large flexmls-heading-font overview-subhead">Open Houses</h3>
			<ul class="open-houses-list-details">
				<?php foreach ( $sf['OpenHouses'] as $OpenHouse ) : ?>
					<?php
					$oh_date = isset( $OpenHouse['Date'] ) ? strtotime( $OpenHouse['Date'] ) : 0;
					$today_start = strtotime( date( 'Y-m-d' ) );
					$tomorrow_start = $today_start + 86400;
					if ( $oh_date >= $today_start && $oh_date < $tomorrow_start ) {
						$opening_day = 'Today';
					} elseif ( $oh_date >= $tomorrow_start && $oh_date < $tomorrow_start + 86400 ) {
						$opening_day = 'Tomorrow';
					} else {
						$opening_day = $oh_date ? date( 'l, F j', $oh_date ) : '';
					}
					$start_time = isset( $OpenHouse['StartTime'] ) ? $OpenHouse['StartTime'] : '';
					$end_time   = isset( $OpenHouse['EndTime'] ) ? $OpenHouse['EndTime'] : '';
					$time_part  = ( $start_time !== '' || $end_time !== '' ) ? ', ' . $start_time . ' - ' . $end_time : '';
					?>
					<li class="open-house-item"><?php echo esc_html( $opening_day . $time_part ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<?php
			$fmc_detail_options = get_option( 'fmc_settings' );
			$expand_listing_detail_sections = isset( $fmc_detail_options['listing_detail_expand_sections'] ) ? (int) $fmc_detail_options['listing_detail_expand_sections'] : 0;
			$show_more_info_section = isset( $fmc_detail_options['listing_detail_show_more_info'] ) ? (int) $fmc_detail_options['listing_detail_show_more_info'] : 1;
	?>
	<div class="listing-section more-information-toggle">
		<h2 class="flexmls-title-larger flexmls-primary-color-font flexmls-heading-font">Listing Details <span class="mls-id">MLS# <?php echo esc_html( $sf['ListingId'] ); ?></span></h2>
	</div>

	<div class="features-section listing-section listing-more-information" data-expand-sections="<?php echo $expand_listing_detail_sections ? '1' : '0'; ?>">
		<div class="property-details">
			<?php
			// Labels already shown in hero/main-details/price-and-dates — do not repeat in Listing Details (SmartFrame-style)
			$detail_labels_suppress = array(
				'property type', 'propertytype', 'bedrooms', 'beds total', 'baths', 'baths total', 'square footage',
				'lot size (sq. ft.)', 'lot size (sq. ft)', 'lot size', 'status', 'mls status', 'current price',
				'list price', 'sold price', 'list date', 'on market date', 'last modified', 'listing date',
				'geo lat', 'geo lon', 'selling member', 'selling member name',
			);

			$normalize_section = function( $name ) {
				$n = trim( strtolower( (string) $name ) );
				if ( in_array( $n, array( 'location, tax & legal', 'location tax legal', 'location, legal & taxes', 'location legal taxes' ), true ) ) {
					return 'Location, Tax & Legal';
				}
				return $name;
			};

			// Parse a detail line "<b>Label:</b> value" or "Label: value" into [ 'label' => x, 'value' => y ]
			$parse_detail_line = function( $line ) {
				if ( preg_match( '/<b>\s*([^<]+)\s*:<\/b>\s*(.*)/s', trim( $line ), $m ) ) {
					return array( 'label' => trim( $m[1] ), 'value' => trim( $m[2] ) );
				}
				if ( preg_match( '/^([^:]+):\s*(.*)$/s', trim( strip_tags( $line ) ), $m ) ) {
					return array( 'label' => trim( $m[1] ), 'value' => trim( $m[2] ) );
				}
				return null;
			};

			$is_suppressed = function( $label ) use ( $detail_labels_suppress ) {
				$key = trim( strtolower( (string) $label ) );
				return in_array( $key, $detail_labels_suppress, true );
			};

			// Normalize boolean-like values (1/0, true/false) for display. Default: true/1 → "Yes", false/0 → "No".
			// Filter flexmls_listing_detail_display_value( $display_value, $raw_value, $label, $section_name ) allows per-field override (e.g. "Yes", "1", "True").
			$format_detail_value = function( $value, $label, $section_name ) {
				$raw = $value;
				if ( $value === true || $value === 1 || $value === '1' || $value === 'true' ) {
					$value = 'Yes';
				} elseif ( $value === false || $value === 0 || $value === '0' || $value === 'false' ) {
					$value = 'No';
				} else {
					$value = (string) $value;
				}
				return apply_filters( 'flexmls_listing_detail_display_value', $value, $raw, $label, $section_name );
			};

			// Build section => list of [ 'label' => x, 'value' => y ], deduped by label, suppressed labels excluded
			$all_property_details = array();

			// 1) Add from property_detail_values (MLS field order) first
			if ( ! empty( $this->property_detail_values ) && is_array( $this->property_detail_values ) ) {
				foreach ( $this->property_detail_values as $section_name => $section_fields ) {
					$norm = $normalize_section( $section_name );
					if ( ! isset( $all_property_details[ $norm ] ) ) {
						$all_property_details[ $norm ] = array();
					}
					$seen_labels = array();
					foreach ( $section_fields as $field_line ) {
						$parsed = $parse_detail_line( $field_line );
						if ( ! $parsed || $is_suppressed( $parsed['label'] ) ) {
							continue;
						}
						$label_key = strtolower( $parsed['label'] );
						if ( isset( $seen_labels[ $label_key ] ) ) {
							continue;
						}
						$seen_labels[ $label_key ] = true;
						$parsed['value'] = $format_detail_value( $parsed['value'], $parsed['label'], $norm );
						$all_property_details[ $norm ][] = $parsed;
					}
				}
			}

			// 2) Add from custom_fields['Main'] only when label not already in section
			if ( isset( $custom_fields['Main'] ) && is_array( $custom_fields['Main'] ) ) {
				foreach ( $custom_fields['Main'] as $section_name => $section_fields ) {
					$norm = $normalize_section( $section_name );
					if ( ! isset( $all_property_details[ $norm ] ) ) {
						$all_property_details[ $norm ] = array();
					}
					$seen_labels = array();
					foreach ( $all_property_details[ $norm ] as $item ) {
						$seen_labels[ strtolower( $item['label'] ) ] = true;
					}
					foreach ( $section_fields as $field_name => $field_value ) {
						if ( $is_suppressed( $field_name ) ) {
							continue;
						}
						$label_key = strtolower( trim( $field_name ) );
						if ( isset( $seen_labels[ $label_key ] ) ) {
							continue;
						}
						if ( is_array( $field_value ) ) {
							$display_vals = array();
							foreach ( $field_value as $val ) {
								if ( $val === true || $val === 1 ) {
									$display_vals[] = $format_detail_value( $val, $field_name, $norm );
								} elseif ( $val !== false && $val !== 0 ) {
									$display_vals[] = $format_detail_value( $val, $field_name, $norm );
								}
							}
							if ( ! empty( $display_vals ) ) {
								$seen_labels[ $label_key ] = true;
								$all_property_details[ $norm ][] = array( 'label' => $field_name, 'value' => implode( ', ', $display_vals ) );
							}
						} else {
							if ( $field_value !== false && $field_value !== 0 && $field_value !== '' ) {
								$seen_labels[ $label_key ] = true;
								$display_val = $format_detail_value( $field_value, $field_name, $norm );
								$all_property_details[ $norm ][] = array( 'label' => $field_name, 'value' => $display_val );
							}
						}
					}
				}
			}

			// Order sections: priority first (Address, Location Tax & Legal, General Property Info), then Property Features, then rest
			$priority_section_order = array( 'Address Information', 'Location, Tax & Legal', 'General Property Information' );
			$ordered_section_names = array();
			foreach ( $priority_section_order as $pname ) {
				if ( isset( $all_property_details[ $pname ] ) && ! empty( $all_property_details[ $pname ] ) ) {
					$ordered_section_names[] = $pname;
				}
			}
			foreach ( array_keys( $all_property_details ) as $k ) {
				if ( ! in_array( $k, $priority_section_order, true ) ) {
					$ordered_section_names[] = $k;
				}
			}
			// Build full display order with Property Features after General Property Information. Use placeholder for Property Features.
			$full_display_order = array();
			foreach ( $ordered_section_names as $name ) {
				$full_display_order[] = $name;
				if ( $name === 'General Property Information' && ! empty( $property_features_values ) ) {
					$full_display_order[] = '_PropertyFeatures_';
				}
			}
			if ( ! empty( $property_features_values ) && ! in_array( '_PropertyFeatures_', $full_display_order, true ) ) {
				$full_display_order[] = '_PropertyFeatures_';
			}
			// Remarks + misc sections often have long prose (Directions, Public Remarks). Wider breakpoints use a
			// 2-across grid for listing-detail-rows; stack those sections so each field gets full width.
			$section_uses_stacked_detail_rows = function( $name ) {
				$n = trim( (string) $name );
				$stacked = (bool) preg_match( '/remark/i', $n ) && (bool) preg_match( '/misc/i', $n );
				return (bool) apply_filters( 'flexmls_listing_detail_use_stacked_detail_rows', $stacked, $n );
			};
			// First 6 = standalone expandable sections; rest = sub-sections under "More Information" (SmartFrame-style)
			$first_six = array_slice( $full_display_order, 0, 6 );
			$more_info_items = array_slice( $full_display_order, 6 );

			// Output first 6 as standalone expandable sections
			if ( ! empty( $all_property_details ) || ! empty( $property_features_values ) ) : ?>
				<?php foreach ( $first_six as $section_name ) : ?>
					<?php
					if ( $section_name === '_PropertyFeatures_' ) {
						?>
				<div class="details-section flexmls-detail-section-toggle" data-initially-expanded="<?php echo $expand_listing_detail_sections ? '1' : '0'; ?>">
					<h3 class="detail-section-header flexmls-title-large flexmls-heading-font flexmls-primary-color-font flexmls-detail-section-header">Property Features</h3>
					<div class="flexmls-detail-section-body">
					<div class="property-details-wrapper property-features-rows">
						<?php foreach ( $property_features_values as $k => $v ) : ?>
							<?php $value_str = implode( '; ', array_filter( $v ) ); ?>
							<span class="detail-label flexmls-heading-font"><?php echo esc_html( $k ); ?>:</span>
							<span class="detail-value"><?php echo esc_html( $value_str ); ?></span>
						<?php endforeach; ?>
					</div>
					</div>
				</div>
						<?php
						continue;
					}
					$section_rows = isset( $all_property_details[ $section_name ] ) ? $all_property_details[ $section_name ] : array();
					if ( empty( $section_rows ) ) {
						continue;
					}
					?>
						<div class="details-section flexmls-detail-section-toggle" data-initially-expanded="<?php echo $expand_listing_detail_sections ? '1' : '0'; ?>">
							<h3 class="detail-section-header flexmls-title-large flexmls-heading-font flexmls-primary-color-font flexmls-detail-section-header"><?php echo esc_html( $section_name ); ?></h3>
							<div class="flexmls-detail-section-body">
							<?php
							$listing_detail_dl_class = 'property-details-wrapper listing-detail-rows';
							if ( $section_uses_stacked_detail_rows( $section_name ) ) {
								$listing_detail_dl_class .= ' listing-detail-rows--stacked';
							}
							?>
							<dl class="<?php echo esc_attr( $listing_detail_dl_class ); ?>">
								<?php foreach ( $section_rows as $row ) : ?>
									<dt class="detail-label flexmls-heading-font"><?php echo esc_html( $row['label'] ); ?></dt>
									<dd class="detail-value"><?php echo esc_html( $row['value'] ); ?></dd>
								<?php endforeach; ?>
							</dl>
							</div>
						</div>
				<?php endforeach; ?>

			<?php
			// "More Information" section: remaining sections as sub-sections (SmartFrame-style). Only output when Behavior setting allows.
			if ( $show_more_info_section && ! empty( $more_info_items ) ) :
				$more_info_expanded = $expand_listing_detail_sections ? '1' : '0';
			?>
				<div class="details-section details-section-more-info flexmls-detail-section-toggle" data-initially-expanded="<?php echo $more_info_expanded; ?>">
					<h3 class="detail-section-header flexmls-title-large flexmls-heading-font flexmls-primary-color-font flexmls-detail-section-header">More Information</h3>
					<div class="flexmls-detail-section-body">
						<?php foreach ( $more_info_items as $section_name ) : ?>
							<?php if ( $section_name === '_PropertyFeatures_' ) : ?>
								<div class="details-subsection">
									<h4 class="detail-subsection-header flexmls-heading-font flexmls-primary-color-font">Property Features</h4>
									<div class="property-details-wrapper property-features-rows">
										<?php foreach ( $property_features_values as $k => $v ) : ?>
											<?php $value_str = implode( '; ', array_filter( $v ) ); ?>
											<span class="detail-label flexmls-heading-font"><?php echo esc_html( $k ); ?>:</span>
											<span class="detail-value"><?php echo esc_html( $value_str ); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php else :
								$section_rows = isset( $all_property_details[ $section_name ] ) ? $all_property_details[ $section_name ] : array();
								if ( empty( $section_rows ) ) {
									continue;
								}
							?>
								<div class="details-subsection">
									<h4 class="detail-subsection-header flexmls-heading-font flexmls-primary-color-font"><?php echo esc_html( $section_name ); ?></h4>
									<?php
									$listing_detail_dl_class = 'property-details-wrapper listing-detail-rows';
									if ( $section_uses_stacked_detail_rows( $section_name ) ) {
										$listing_detail_dl_class .= ' listing-detail-rows--stacked';
									}
									?>
									<dl class="<?php echo esc_attr( $listing_detail_dl_class ); ?>">
										<?php foreach ( $section_rows as $row ) : ?>
											<dt class="detail-label flexmls-heading-font"><?php echo esc_html( $row['label'] ); ?></dt>
											<dd class="detail-value"><?php echo esc_html( $row['value'] ); ?></dd>
										<?php endforeach; ?>
									</dl>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php endif; ?>

			<?php $room_count = isset($room_values[0]) ? count($room_values[0]) : false; ?>
			<?php if ( $room_count ) : ?>
				<div class="details-section rooms-section flexmls-detail-section-toggle" data-initially-expanded="<?php echo $expand_listing_detail_sections ? '1' : '0'; ?>">
					<h3 class="detail-section-header flexmls-title-large flexmls-heading-font flexmls-primary-color-font flexmls-detail-section-header">Room Information</h3>
					<div class="flexmls-detail-section-body">
					<div class="flexmls-room-information-table-wrap">
						<table class="flexmls-room-information-table" width="100%">
							<thead>
								<tr>
									<?php foreach ( $room_names as $room_header ) : ?>
										<th scope="col"><?php echo esc_html( $room_header ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php for ( $x = 0; $x < $room_count; $x++ ) : ?>
									<tr class="<?php echo ( 0 === $x % 2 ) ? 'flexmls-room-row-zebra' : ''; ?>">
										<?php for ( $i = 0; $i < count( $room_values ); $i++ ) : ?>
											<td><?php echo isset( $room_values[ $i ][ $x ] ) ? esc_html( $room_values[ $i ][ $x ] ) : ''; ?></td>
										<?php endfor; ?>
									</tr>
								<?php endfor; ?>
							</tbody>
						</table>
					</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $sf['DocumentsCount'] ) : ?>
		<div class="documents-section listing-section">
			<h2 class="flexmls-title-larger flexmls-primary-color-font flexmls-heading-font">Documents</h2>
			<div class="flexmls-documents-wrapper">
				<?php $fmc_colorbox_extensions = [ 'gif', 'png' ]; ?>
				<?php foreach ( $sf['Documents'] as $fmc_document ) : ?>
					<?php if ($fmc_document['Privacy']=='Public') : ?>
						<?php
							$fmc_extension = explode( '.', $fmc_document['Uri'] );
							$fmc_extension = ( $fmc_extension[ count( $fmc_extension ) - 1 ] );
							if ( $fmc_extension == 'pdf' ){
								$fmc_file_image = $fmc_plugin_url . '/assets/images/pdf-tiny.gif';
								$fmc_docs_class = "class='fmc_document fmc_document_pdf'";
							}
							elseif ( in_array( $fmc_extension, $fmc_colorbox_extensions ) ){
								$fmc_file_image = $fmc_plugin_url . '/assets/images/image_16.gif';
								$fmc_docs_class = "class='fmc_document fmc_document_colorbox'";
							}
							else{
								$fmc_file_image = $fmc_plugin_url . '/assets/images/docs_16.gif';
							}

							echo "<div><a $fmc_docs_class value={$fmc_document['Uri']}><img src='{$fmc_file_image}' align='absmiddle' alt='View Document' title='View Document' /> {$fmc_document['Name']} &rsaquo;</a></div>";
						?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="map-section listing-section">
		<?php if ( isset ( $options['google_maps_api_key'] ) && $options['google_maps_api_key'] && flexmlsConnect::is_not_blank_or_restricted($sf['Latitude']) && flexmlsConnect::is_not_blank_or_restricted($sf['Longitude']) ) : ?>
			<div id='flexmls_connect__map_canvas' latitude='<?php echo esc_attr( $sf['Latitude'] ); ?>' longitude='<?php echo esc_attr( $sf['Longitude'] ); ?>'></div>
		<?php endif; ?>
	</div>

	<div class="compliance-section listing-section">
		<?php fmcSearchResults::compliance_label( $record, "Detail" ); ?>

		<?php if ( flexmlsConnect::mls_requires_office_name_in_listing_details() ) : ?>
			<?php $listing_office_label = flexmlsConnect::listing_detail_list_office_label( $sf ); ?>
			<div class="flexmls-office-name">
				<span class="flexmls-bold-label"><?php echo esc_html( $listing_office_label ) ; ?></span>
				<?php echo esc_html( $sf["ListOfficeName"] ); ?>
			</div>
		<?php endif; ?>

		<?php if ( flexmlsConnect::mls_requires_agent_name_in_listing_details() ) : ?>
			<div class="flexmls-agent-name-and-label-wrapper">
				<span class="flexmls-agent-name">
					<span class="flexmls-bold-label">Listing Agent: </span>
					<?php echo esc_html( $sf["ListAgentName"] ); ?>

					<?php if ( flexmlsConnect::mls_requires_agent_phone_in_listing_details() ) : ?>
						<?php
						$phone_number = flexmlsConnect::get_agent_phone_with_fallback( $sf, 'detail' );
						if ( ! empty( $phone_number ) ) {
							echo "<br/>" . esc_html( $phone_number );
						}
						?>
					<?php endif; ?>

					<?php if ( flexmlsConnect::mls_requires_agent_email_in_listing_details() ) : ?>
						<?php echo " |  " . esc_html( $sf["ListAgentEmail"] ); ?>
					<?php endif; ?>

				</span>
			</div>
		<?php endif; ?>

		<?php foreach ( $compList as $reqs ) : ?>
			<?php if ( isset( $reqs[0], $reqs[1] ) && $reqs[0] === flexmlsConnect::LISTING_DETAIL_SELLING_OFFICE_LABEL && flexmlsConnect::is_not_blank_or_restricted( $reqs[1] ) ) : ?>
				<div class="flexmls-selling-office-name">
					<span class="flexmls-bold-label"><?php echo esc_html( flexmlsConnect::LISTING_DETAIL_SELLING_OFFICE_LABEL ); ?></span>
					<?php echo esc_html( $reqs[1] ); ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php echo $this->render_source_mls_badge( $sf, 'source-mls-badge listing-section' ); ?>

	<div class="disclosure-section listing-section">
		<?php if ( ! flexmlsConnect::listing_detail_uses_ny_state_rules( $sf ) ) : ?>
			<?php foreach ( $compList as $reqs ) : ?>
				<?php if ( flexmlsConnect::is_not_blank_or_restricted( $reqs[1] ) ) : ?>
					<?php if ( $reqs[0] != 'Listing Office:' && $reqs[0] != 'Listing Courtesy of' && $reqs[0] != 'Listing Agent:' && $reqs[0] != flexmlsConnect::LISTING_DETAIL_SELLING_OFFICE_LABEL && $reqs[0] != 'LOGO' ) : ?>
						<div class="listing-req"><?php echo esc_html( $reqs[0] ); ?> <?php echo esc_html( $reqs[1] ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
		
		<?php if ( array_key_exists( 'CompensationDisclaimer', $sf ) ) : ?>
		    <?php if ( flexmlsConnect::is_not_blank_or_restricted( $sf['CompensationDisclaimer'] ) ) : ?>
			<hr />
			<div class="compensation-disclaimer">
		    <?php echo $sf['CompensationDisclaimer']; ?>
			</div>
			<hr />
		    <?php endif; ?>
		<?php endif; ?>

		<?php if( flexmlsConnect::NAR_broker_attribution( $sf ) ) : ?>
		<div class='listing-req'>Broker Attribution: 
			<?php echo flexmlsConnect::NAR_broker_attribution( $sf ); ?>
		</div>
		<hr />
		<?php endif; ?>

		<div class="disclosure-text">
			<?php echo flexmlsConnect::get_big_idx_disclosure_text(); ?>
		</div>
		<hr />
		
		<div class="fbs-branding" style="text-align: center;">
          <?php echo flexmlsConnect::fbs_products_branding_link(); ?>
      </div>
	</div>
</div>
<script>
(function() {
	var listingDetails = document.querySelector('.flexmls-listing-details.flexmls-v2-widget');
	if (!listingDetails) return;
	function bindExpandableText(linkSelector, containerSelector, teaserSel, ellipsisSel, fullSel) {
		listingDetails.querySelectorAll(linkSelector).forEach(function(link) {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				var wrapper = this.closest(containerSelector);
				if (!wrapper) return;
				var teaser = wrapper.querySelector(teaserSel);
				var ellipsis = wrapper.querySelector(ellipsisSel);
				var full = wrapper.querySelector(fullSel);
				var expanded = this.getAttribute('aria-expanded') === 'true';
				if (expanded) {
					if (teaser) teaser.style.display = '';
					if (ellipsis) ellipsis.style.display = '';
					if (full) full.style.display = 'none';
					this.textContent = '(Read more)';
					this.setAttribute('aria-expanded', 'false');
				} else {
					if (teaser) teaser.style.display = 'none';
					if (ellipsis) ellipsis.style.display = 'none';
					if (full) full.style.display = '';
					this.textContent = '(Read less)';
					this.setAttribute('aria-expanded', 'true');
				}
			});
		});
	}
	bindExpandableText('.flexmls-description-read-more', '.flexmls-description', '.flexmls-description-teaser', '.flexmls-description-ellipsis', '.flexmls-description-full');
	bindExpandableText('.flexmls-supplement-read-more', '.flexmls-supplement', '.flexmls-supplement-teaser', '.flexmls-supplement-ellipsis', '.flexmls-supplement-full');
	// Collapsible listing detail sections
	var expandByDefault = listingDetails.querySelector('.features-section') && listingDetails.querySelector('.features-section').getAttribute('data-expand-sections') === '1';
	listingDetails.querySelectorAll('.flexmls-detail-section-toggle').forEach(function(section) {
		var initiallyExpanded = section.getAttribute('data-initially-expanded') === '1';
		var header = section.querySelector('.flexmls-detail-section-header');
		var body = section.querySelector('.flexmls-detail-section-body');
		if (!header || !body) return;
		if (!initiallyExpanded) {
			section.classList.add('flexmls-detail-section-collapsed');
			body.style.display = 'none';
		}
		header.setAttribute('tabIndex', '0');
		header.setAttribute('role', 'button');
		header.setAttribute('aria-expanded', initiallyExpanded ? 'true' : 'false');
		header.addEventListener('click', function() {
			var collapsed = section.classList.contains('flexmls-detail-section-collapsed');
			section.classList.toggle('flexmls-detail-section-collapsed', !collapsed);
			body.style.display = collapsed ? '' : 'none';
			header.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
		});
		header.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				header.click();
			}
		});
	});
})();
</script>
