<?php
namespace FlexMLS\Admin;

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

class Settings {

	/**
	 * Capability required to read or write plugin settings, including API credentials.
	 */
	const SETTINGS_CAPABILITY = 'manage_options';

	/**
	 * Whether the current request is an authorized save for the given nonce field.
	 *
	 * A nonce proves the request was intentional, not that the sender is allowed to
	 * make it, so capability is checked alongside it. The credentials form lives on
	 * the fmc_admin_intro page, which is reachable with `edit_posts`, so every save
	 * branch must gate on SETTINGS_CAPABILITY independently.
	 *
	 * @param string $nonce_name   Name of the nonce field in $_POST.
	 * @param string $nonce_action Action the nonce was created for.
	 * @return bool
	 */
	private static function is_authorized_save( $nonce_name, $nonce_action ){
		if( empty( $_POST ) || ! isset( $_POST[ $nonce_name ] ) ){
			return false;
		}
		if( ! current_user_can( self::SETTINGS_CAPABILITY ) ){
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) );
		return (bool) wp_verify_nonce( $nonce, $nonce_action );
	}

	/**
	 * Outputs the nonce fields and submit button for a settings form.
	 *
	 * The button is pinned to the corner of the viewport by
	 * `.fmc-floating-save` so it stays reachable on long settings screens. It
	 * must be rendered inside its form so that it submits natively, which keeps
	 * browser validation and the TinyMCE/color picker submit hooks working.
	 *
	 * @param string $nonce_action Action passed to wp_nonce_field().
	 * @param string $nonce_name   Field name passed to wp_nonce_field().
	 * @param string $label        Visible button text.
	 */
	public static function floating_save_button( $nonce_action, $nonce_name, $label ){
		echo '<div class="fmc-floating-save">';
		wp_nonce_field( $nonce_action, $nonce_name );
		echo '<button type="submit" class="button button-primary fmc-floating-save-button">' . esc_html( $label ) . '</button>';
		echo '</div>';
	}

	public static function admin_menu_cb_intro(){
		$tab = isset( $_GET[ 'tab' ] ) ? sanitize_title( $_GET[ 'tab' ] ) : 'api';
		$fmc_plugin_dir = FMC_PLUGIN_DIR;

		?>
			<div class="wrap about-wrap about-flexmls">
			<?php
			// Core admin_notices are hidden/suppressed on about-wrap screens; output API/connection notices here.
			global $FlexMLS_IDX;
			if ( $FlexMLS_IDX instanceof \FlexMLS_IDX ) {
				$FlexMLS_IDX->render_fmc_api_connection_notices( true );
			}
			?>
			<?php
			// nginx warning removed from admin intro page - now only shown on behavior settings page
			?>

				<div class="intro-banner">
					<img src="<?php echo plugin_dir_url( dirname( __FILE__ ) ) . 'assets/images/dashboard-banner.png'; ?>">
				</div>

				<!-- <div class="wp-badge">Version <?php // echo FMC_PLUGIN_VERSION; ?></div> -->
				<?php
				$fmc_can_manage = current_user_can( self::SETTINGS_CAPABILITY );
				$fmc_intro_tab_label = $fmc_can_manage ? 'Credentials' : 'Get Started';
				?>
				<h2 class="nav-tab-wrapper wp-clearfix">
					<a href="<?php echo admin_url( 'admin.php?page=fmc_admin_intro&tab=api' ); ?>" class="nav-tab<?php echo ( 'api' == $tab ? ' nav-tab-active' : '' ); ?>"><?php echo esc_html( $fmc_intro_tab_label ); ?></a>

					<a href="<?php echo admin_url( 'admin.php?page=fmc_admin_intro&tab=support' ); ?>" class="nav-tab<?php echo ( 'support' == $tab ? ' nav-tab-active' : '' ); ?>">Support</a>

					<a href="<?php echo admin_url( 'admin.php?page=fmc_admin_intro&tab=features' ); ?>" class="nav-tab<?php echo ( 'features' == $tab ? ' nav-tab-active' : '' ); ?>">Features</a>
				</h2>
				<div class="intro-wrap-content">
				<?php switch ($tab) {

				case 'api':
					include_once( $fmc_plugin_dir . 'views/admin-intro-api.php' ); 
				break;

				case 'support':
					include_once( $fmc_plugin_dir . 'views/admin-intro-support.php' ); 
				break;

				case 'features':
					include_once( $fmc_plugin_dir . 'views/admin-intro-features.php' ); 
				break;

				default:
					include_once( $fmc_plugin_dir . 'views/admin-intro-404.php' ); 

				}
?>
				</div>
			</div>
		<?php
	}

	public static function admin_menu_cb_neighborhood(){
		if( ! current_user_can( self::SETTINGS_CAPABILITY ) ){
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fmcdomain' ) );
		}

		$fmc_settings = get_option( 'fmc_settings' );
		if( ! is_array( $fmc_settings ) ){
			$fmc_settings = array();
		}
		$system = new \SparkAPI\System();

		if( self::is_authorized_save( 'create_neighborhood_draft_nonce', 'create_neighborhood_draft_action' ) ){
			$new_template_id = wp_insert_post( array(
				'post_title' => 'Neighborhood Template Draft',
				'post_type' => 'page'
			) );
			if( $new_template_id ){
				printf(
					'<div class="notice notice-success">
						<p>Your new page <em>%s</em> has been created. <a href="%s">Click here to edit this new page</a> or continue creating your new neighborhood below.</p>
					</div>',
					'Neighborhood Template Draft',
					admin_url( 'post.php?post=' . $new_template_id . '&action=edit' )
				);
			}
		}
		if( self::is_authorized_save( 'add_neighborhood_nonce', 'add_neighborhood_action' ) ){
			$loc = $system->parse_location_search_string( stripcslashes( isset( $_POST[ 'location' ] ) ? $_POST[ 'location' ] : '' ) );
			if( empty( $loc ) ){
				echo '	<div class="notice notice-error">
							<p>Your new page was not created because you did not select a location. Please try again.</p>
						</div>';
			} else {
				$loc_title = $loc[ 0 ][ 'l' ];
				$loc_raw = $loc[ 0 ][ 'r' ];
				$shortcode = '[neighborhood_page title="' . $loc_title . '" location="' . $loc_raw . '" template="' . sanitize_text_field( $_POST[ 'template' ] ) . '"]';
				$new_page_id = wp_insert_post( array(
					'post_title' => $loc_title,
					'post_content' => $shortcode,
					'post_type' => 'page',
					'post_status' => 'publish',
					'post_parent' => intval( sanitize_text_field( $_POST[ 'parent' ] ) )
				) );
				$template_id = intval( sanitize_text_field( $_POST[ 'template' ] ) );
				if( !isset( $fmc_settings[ 'neigh_template' ] ) || empty( $fmc_settings[ 'neigh_template' ] ) ){
					$fmc_settings[ 'neigh_template' ] = $template_id;
					update_option( 'fmc_settings', $fmc_settings );
				}
				$template_page_template = get_post_meta( $template_id, '_wp_page_template', true );
				update_post_meta( $new_page_id, '_wp_page_template', $template_page_template);
				printf(
					'<div class="notice notice-success">
						<p>Your neighborhood has been created! You can <a href="%s">click here to edit this new page</a>, or add another neighborhood below.</p>
					</div>',
					admin_url( 'post.php?post=' . $new_page_id . '&action=edit' )
				);
			}
		}
		$can_create_neighborhood = true;
		$templates = get_posts( array(
			'order' => 'ASC',
			'orderby' => 'menu_order name',
			'nopaging' => true,
			'post_status' => 'draft',
			'post_type' => 'page'
		) );
		if( !$templates ){
			$can_create_neighborhood = false;
		}
		?>
		<div class="notice notice-warning">
		    <p>We will be deprecating the Neighborhood widget in a future update. We recommend using the <a href="https://fbsidx.com/help/plugin/slideshow">IDX Slideshow</a> and/or <a href="https://fbsidx.com/help/plugin/listing-summary">IDX Listing Summary</a> widgets to display listings.
		</div>
			<div class="wrap">
				<h1><?php echo get_admin_page_title(); ?></h1>
				<p>To create a new neighborhood page automatically, select your location and template below. You can create additional templates by adding additional <em>Pages</em> and setting them to <em>Draft</em> status. <a href="<?php echo admin_url( 'post-new.php?post_type=page' ); ?>">Click here to create a new page</a>.</p>
				<form action="<?php echo admin_url( 'admin.php?page=fmc_admin_neighborhood' ); ?>" method="post" autocomplete="off">
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="fmc_template">Neighborhood Template</label></th>
								<td>
									<?php if( !$can_create_neighborhood ): ?>
										<?php wp_nonce_field( 'create_neighborhood_draft_action', 'create_neighborhood_draft_nonce' ); ?>
										<button type="submit" class="button-secondary">Create A Template For Me</button>
										<p class="description">You do not have any draft pages set up for your Neighborhood template. Click the button above to automatically create a draft page you can use for your Neighborhood template.</p>
									<?php else: ?>
										<select name="template" id="fmc_template" class="regular-text">
											<?php foreach( $templates as $template ): ?>
												<option value="<?php echo $template->ID; ?>" <?php selected( $template->ID, $fmc_settings[ 'neigh_template' ] ); ?>><?php
													echo $template->post_title;
													if( $fmc_settings[ 'neigh_template' ] == $template->ID ){
														echo ' (Saved Default)';
													}
												?></option>
											<?php endforeach; ?>
										</select>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="fmc_parent">Parent Page</label></th>
								<td>
									<?php if( !$can_create_neighborhood ): ?>
										<p class="description">Create a draft first to make this selection available.</p>
									<?php else: ?>
										<?php wp_dropdown_pages( array(
											'class' => 'regular-text',
											'id' => 'fmc_parent',
											'name' => 'parent',
											'option_none_value' => 0,
											'show_option_none' => '(No Parent)'
										) ); ?>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="fmc_location">Location</label></th>
								<td>
									<?php if( !$can_create_neighborhood ): ?>
										<p class="description">Create a draft first to make this selection available.</p>
									<?php else: ?>

										<div class="flexmls_connect__location">

										  <select class="flexmlsAdminLocationSearch regular-text"
										    id="fmc_shortcode_field_location" name="fmc_shortcode_field_location"
										    data-portal-slug="<?= \flexmlsConnect::get_portal_slug()  ?>">
										  </select>

										  <input fmc-field="location" fmc-type='text' type='hidden' name="location"
										    class='flexmls_connect__location_fields' />

											<select style="display:none;" fmc-field="property_type" class="flexmls_connect__property_type"
												fmc-type="select" id="property_type" name="property_type">
												<option value="A" selected="selected"></option>
											</select>
        						</div>

									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
					<?php if( $can_create_neighborhood ): ?>
						<p><?php wp_nonce_field( 'add_neighborhood_action', 'add_neighborhood_nonce' ); ?><button type="submit" class="button-primary">Add Neighborhood</button></p>
					<?php endif; ?>
				</form>
			</div>
		<?php
	}

	public static function admin_menu_cb_settings(){
		$tab = isset( $_GET[ 'tab' ] ) ? sanitize_title( $_GET[ 'tab' ] ) : 'behavior';
		$fmc_plugin_dir = FMC_PLUGIN_DIR;
		
		$SparkAPI = new \SparkAPI\Core();
		$auth_token = $SparkAPI->generate_auth_token();
		?>
			<div class="wrap">
				<h1><?php echo get_admin_page_title(); ?></h1>
				<h2 class="nav-tab-wrapper wp-clearfix">
					<?php if( $auth_token ): ?><a href="<?php echo admin_url( 'admin.php?page=fmc_admin_settings' ); ?>" class="nav-tab<?php echo ( 'behavior' == $tab ? ' nav-tab-active' : '' ); ?>">Behavior</a><?php endif; ?>
					<?php if( $auth_token ): ?><a href="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=search-results' ); ?>" class="nav-tab<?php echo ( 'search-results' == $tab ? ' nav-tab-active' : '' ); ?>">Search Results</a><?php endif; ?>
					<?php if( $auth_token ): ?><a href="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=listing-detail' ); ?>" class="nav-tab<?php echo ( 'listing-detail' == $tab ? ' nav-tab-active' : '' ); ?>">Listing Detail</a><?php endif; ?>
					<a href="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=style' ); ?>" class="nav-tab<?php echo ( 'style' == $tab ? ' nav-tab-active' : '' ); ?>">Style</a>
					<a href="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=portal' ); ?>" class="nav-tab<?php echo ( 'portal' == $tab ? ' nav-tab-active' : '' ); ?>">Portal</a>
					<?php if( $auth_token ): ?><a href="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=gmaps' ); ?>" class="nav-tab<?php echo ( 'gmaps' == $tab ? ' nav-tab-active' : '' ); ?>">Google Maps</a><?php endif; ?>
					<a href="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=cache' ); ?>" class="nav-tab<?php echo ( 'cache' == $tab ? ' nav-tab-active' : '' ); ?>">Clear Cache</a>
				</h2>
			</div>
			<?php
				switch ($tab) {

					case 'behavior':
						include_once( $fmc_plugin_dir . 'views/admin-settings-behavior.php' ); 
					break;

					case 'search-results':
						include_once( $fmc_plugin_dir . 'views/admin-settings-search-results.php' );
					break;

					case 'listing-detail':
						include_once( $fmc_plugin_dir . 'views/admin-settings-listing-detail.php' );
					break;

					case 'cache':
						include_once( $fmc_plugin_dir . 'views/admin-settings-cache.php' ); 
					break;

					case 'gmaps':
						include_once( $fmc_plugin_dir . 'views/admin-settings-gmaps.php' ); 
					break;

					case 'portal':
						include_once( $fmc_plugin_dir . 'views/admin-settings-portal.php' ); 
					break;

					case 'style':
						include_once( $fmc_plugin_dir . 'views/admin-settings-style.php' ); 
					break;

					default:
						include_once( $fmc_plugin_dir . 'views/admin-settings-404.php' ); 

				}
		?>
		<?php
	}

	public static function did_clear_cache(){
		?>
			<div class="notice notice-success">
				<p>The cache has been cleared.</p>
			</div>
		<?php
	}

	public static function did_update_settings(){
		?>
			<div class="notice notice-success">
				<p>Your settings have been saved!</p>
			</div>
		<?php
	}

	public static function update_settings(){
		// Hooked on plugins_loaded, which also fires for front-end and logged-out
		// requests. Nothing here should run outside wp-admin.
		if( ! is_admin() ){
			return;
		}

		$fmc_settings = get_option( 'fmc_settings' );
		if( ! is_array( $fmc_settings ) ){
			$fmc_settings = array();
		}
		$did_change = false;

		// Save API Credentials
		if( self::is_authorized_save( 'update_api_credentials_nonce', 'update_api_credentials_action' ) ){
			$old_api_key = isset( $fmc_settings[ 'api_key' ] ) ? $fmc_settings[ 'api_key' ] : '';
			$old_api_secret = isset( $fmc_settings[ 'api_secret' ] ) ? $fmc_settings[ 'api_secret' ] : '';

			$new_api_key = isset( $_POST[ 'fmc_settings' ][ 'api_key' ] ) ? sanitize_text_field( $_POST[ 'fmc_settings' ][ 'api_key' ] ) : '';
			$new_api_secret = isset( $_POST[ 'fmc_settings' ][ 'api_secret' ] ) ? sanitize_text_field( $_POST[ 'fmc_settings' ][ 'api_secret' ] ) : '';
			// When already connected, empty secret means "keep existing" (e.g. form locked or user left blank)
			$SparkAPI = new \SparkAPI\Core();
			$had_auth = (bool) $SparkAPI->generate_auth_token();
			if ( $had_auth && '' === $new_api_secret ) {
				$new_api_secret = $old_api_secret;
			}

			$fmc_settings[ 'api_key' ] = $new_api_key;
			$fmc_settings[ 'api_secret' ] = $new_api_secret;
			$did_change = true;
			// Persisted before clear_cache()/generate_auth_token() below, which read the option.
			update_option( 'fmc_settings', $fmc_settings );

			$SparkAPI = new \SparkAPI\Core();
			$SparkAPI->clear_cache( true );
			$auth_token = $SparkAPI->generate_auth_token( 'manual' );
			if( $auth_token ){
				add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_update_settings' ) );
			}
		}

		// User clears cache
		if( self::is_authorized_save( 'clear_api_cache_nonce', 'clear_api_cache_action' ) ){
			$SparkAPI = new \SparkAPI\Core();
			$SparkAPI->clear_cache( true );
			$auth_token = $SparkAPI->generate_auth_token( 'manual' );
			if( $auth_token ){
				add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_clear_cache' ) );
			}
		}

		// User saves Behavior settings
		if( self::is_authorized_save( 'update_fmc_behavior_nonce', 'update_fmc_behavior_action' ) ){
			$did_change = true;
			$do_flush_rewrites = false;
			$old_permabase = isset( $fmc_settings[ 'permabase' ] ) ? $fmc_settings[ 'permabase' ] : '';
			$old_destlink = isset( $fmc_settings[ 'destlink' ] ) ? $fmc_settings[ 'destlink' ] : '';
			$posted_settings = isset( $_POST[ 'fmc_settings' ] ) && is_array( $_POST[ 'fmc_settings' ] ) ? $_POST[ 'fmc_settings' ] : array();
			foreach( $posted_settings as $key => $val ){
				switch( $key ){
					case 'default_titles':
					case 'contact_notifications':
					case 'multiple_summaries':
					case 'allow_sold_searching':
					case 'listing_detail_expand_sections':
					case 'listing_detail_show_more_info':
					case 'listing_detail_contact_on_closed':
						// Simple 1 or 0 values
						$fmc_settings[ $key ] = ( 1 == $val ? 1 : 0 );
						break;
					case 'neigh_template':
					case 'listlink':
					case 'destlink':
						// Numeric values like post ids
						$fmc_settings[ $key ] = preg_replace( '/[^0-9]/', '', $val );
						break;
					case 'default_link':
					case 'destwindow':
                    case 'select2_turn_off':
					case 'chartkick_turn_off':
					case 'v2_listing_photo_click_action':
					case 'v2_listing_photo_modal_provider':
					case 'destpref':
					case 'listpref':
					case 'permabase':
						// Text input values
						$fmc_settings[ $key ] = sanitize_text_field( $val );
						break;
					case 'property_types':
						// Special Case: Property Types
						$val = sanitize_text_field( $val );
						$fmc_settings[ $key ] = $val;
						$types = explode( ',', $val );
						foreach( $types as $type ){
							$label_key = 'property_type_label_' . $type;
							if( isset( $posted_settings[ $label_key ] ) ){
								$fmc_settings[ $label_key ] = sanitize_text_field( $posted_settings[ $label_key ] );
							}
						}
						break;
					case 'search_results_fields':
						// Special Case: Search Results
						$clean_fields = array();
						foreach( $val as $sr_key => $sr_val ){
							$clean_fields[ sanitize_text_field( $sr_key ) ] = sanitize_text_field( $sr_val );
						}
						$fmc_settings[ 'search_results_fields' ] = $clean_fields;
				}
			}
			if( !isset( $_POST[ 'fmc_settings' ][ 'destlink' ] ) ){
				$fmc_settings[ 'destlink' ] = 0;
			}
            if( !isset( $_POST[ 'fmc_settings' ][ 'select2_turn_off' ] ) ){
                $fmc_settings[ 'select2_turn_off' ] = 0;
            }
			if( !isset( $_POST[ 'fmc_settings' ][ 'chartkick_turn_off' ] ) ){
                $fmc_settings[ 'chartkick_turn_off' ] = 0;
            }
			if( !isset( $_POST[ 'fmc_settings' ][ 'destwindow' ] ) ){
				$fmc_settings[ 'destwindow' ] = '';
			}
			if( empty( $fmc_settings[ 'permabase' ] ) ){
				$fmc_settings[ 'permabase' ] = 'idx';
			}
			// Check for changes that require nginx configuration updates
			$nginx_config_changed = false;
			
			if( $old_permabase != $fmc_settings[ 'permabase' ] ){
				// Set transient to indicate permalink base was recently changed
				set_transient( 'fmc_permabase_changed', time(), 300 ); // 5 minutes
				$nginx_config_changed = true;
			}
			
			if( $old_destlink != $fmc_settings[ 'destlink' ] ){
				// Set transient to indicate destination page was recently changed
				set_transient( 'fmc_destlink_changed', time(), 300 ); // 5 minutes
				$nginx_config_changed = true;
			}
			
			if( $nginx_config_changed ){
				// Use nginx-compatible rewrite rule handling
				if( \FlexMLS\Admin\NginxCompatibility::is_nginx() ) {
					// For nginx, we don't flush rewrite rules as they need to be configured in nginx config
					// The rules are still added to WordPress for URL generation
				} else {
					add_action( 'shutdown', 'flush_rewrite_rules' );
				}
			}
			add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_update_settings' ) );
		}

		// User saves Search Results settings
		if( self::is_authorized_save( 'update_fmc_search_results_nonce', 'update_fmc_search_results_action' ) ){
			$did_change = true;
			$posted_settings = isset( $_POST[ 'fmc_settings' ] ) && is_array( $_POST[ 'fmc_settings' ] ) ? $_POST[ 'fmc_settings' ] : array();
			foreach( $posted_settings as $key => $val ){
				switch( $key ){
					case 'multiple_summaries':
					case 'allow_sold_searching':
					case 'search_listing_card_native_lazy_load':
					case 'search_results_display_open_house_datetime':
						$fmc_settings[ $key ] = ( 1 == $val ? 1 : 0 );
						break;
					case 'v2_listing_photo_click_action':
					case 'v2_listing_photo_modal_provider':
						$fmc_settings[ $key ] = sanitize_text_field( $val );
						break;
					case 'search_results_fields':
						$clean_fields = array();
						foreach( $val as $sr_key => $sr_val ){
							$clean_fields[ sanitize_text_field( $sr_key ) ] = sanitize_text_field( $sr_val );
						}
						$fmc_settings[ 'search_results_fields' ] = $clean_fields;
						break;
				}
			}

			add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_update_settings' ) );
		}

		// User saves Listing Detail settings
		if( self::is_authorized_save( 'update_fmc_listing_detail_nonce', 'update_fmc_listing_detail_action' ) ){
			$did_change = true;
			$posted_settings = isset( $_POST[ 'fmc_settings' ] ) && is_array( $_POST[ 'fmc_settings' ] ) ? $_POST[ 'fmc_settings' ] : array();
			foreach( $posted_settings as $key => $val ){
				switch( $key ){
					case 'listing_detail_expand_sections':
					case 'listing_detail_show_more_info':
					case 'listing_detail_contact_on_closed':
						$fmc_settings[ $key ] = ( 1 == $val ? 1 : 0 );
						break;
					case 'v2_listing_photo_click_action':
					case 'v2_listing_photo_modal_provider':
						$fmc_settings[ $key ] = sanitize_text_field( $val );
						break;
					case 'listlink':
						$fmc_settings[ $key ] = preg_replace( '/[^0-9]/', '', $val );
						break;
					case 'listpref':
						$fmc_settings[ $key ] = sanitize_text_field( $val );
						break;
				}
			}
			if( !isset( $_POST[ 'fmc_settings' ][ 'v2_listing_photo_click_action' ] ) || ! in_array( $_POST[ 'fmc_settings' ][ 'v2_listing_photo_click_action' ], array( 'detail', 'modal' ), true ) ){
				$fmc_settings[ 'v2_listing_photo_click_action' ] = 'modal';
			}
			if( !isset( $_POST[ 'fmc_settings' ][ 'v2_listing_photo_modal_provider' ] ) || ! in_array( $_POST[ 'fmc_settings' ][ 'v2_listing_photo_modal_provider' ], array( 'auto', 'cbox', 'third_party', 'none' ), true ) ){
				$fmc_settings[ 'v2_listing_photo_modal_provider' ] = 'auto';
			}

			add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_update_settings' ) );
		}


		// User saves style settings
		if( self::is_authorized_save( 'update_fmc_style_nonce', 'update_fmc_style_action' ) ){
			$did_change = true;
			$posted_settings = isset( $_POST[ 'fmc_settings' ] ) && is_array( $_POST[ 'fmc_settings' ] ) ? $_POST[ 'fmc_settings' ] : array();
			foreach( $posted_settings as $key => $val ){
				switch( $key ){
					case 'search_listing_template_version':
					case 'market_stat_version':
					case 'search_listing_template_primary_color':
					case 'search_listing_template_heading_font':
					case 'search_listing_template_body_font':
						// Text input values
						$fmc_settings[ $key ] = sanitize_text_field( $val );
						break;
				}
			}
			add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_update_settings' ) );
		}

		// User saves Oauth/Portal settings
		if( self::is_authorized_save( 'update_fmc_portal_nonce', 'update_fmc_portal_action' ) ){
			$did_change = true;
			$posted_settings = isset( $_POST[ 'fmc_settings' ] ) && is_array( $_POST[ 'fmc_settings' ] ) ? $_POST[ 'fmc_settings' ] : array();

			$old_oauth_key = isset( $fmc_settings[ 'oauth_key' ] ) ? $fmc_settings[ 'oauth_key' ] : '';
			$old_oauth_secret = isset( $fmc_settings[ 'oauth_secret' ] ) ? $fmc_settings[ 'oauth_secret' ] : '';
			$had_oauth = ( '' !== (string) $old_oauth_key && '' !== (string) $old_oauth_secret );

			$SparkAPI = new \SparkAPI\Core();
			$auth_token = $SparkAPI->generate_auth_token();

			// Only accept OAuth credential updates when a working plugin key is connected.
			if ( $auth_token ) {
				if ( array_key_exists( 'oauth_key', $posted_settings ) ) {
					$new_oauth_key = sanitize_text_field( $posted_settings[ 'oauth_key' ] );
					$new_oauth_secret = isset( $posted_settings[ 'oauth_secret' ] ) ? sanitize_text_field( $posted_settings[ 'oauth_secret' ] ) : '';
					// When already configured, empty secret means "keep existing" (form locked or user left blank).
					if ( $had_oauth && '' === $new_oauth_secret ) {
						$new_oauth_secret = $old_oauth_secret;
					}
					$fmc_settings[ 'oauth_key' ] = $new_oauth_key;
					$fmc_settings[ 'oauth_secret' ] = $new_oauth_secret;
				}
			}

			foreach( $posted_settings as $key => $val ){
				switch( $key ){
					case 'portal_carts':
					case 'portal_saving_searches':
					case 'portal_search':
					case 'portal_listing':
					case 'portal_force':
						$fmc_settings[ $key ] = ( 1 == $val ? 1 : 0 );
						break;
					case 'portal_mins':
					case 'detail_page':
					case 'search_page':
						$fmc_settings[ $key ] = preg_replace( '/[^0-9]/', '', $val );
						break;
					case 'portal_snooze_amount':
						$amount = intval( preg_replace( '/[^0-9]/', '', $val ) );
						$fmc_settings[ $key ] = $amount > 0 ? $amount : 7;
						break;
					case 'portal_snooze_unit':
						$allowed_units = array( 'minutes', 'hours', 'days', 'weeks', 'months' );
						$unit = sanitize_text_field( $val );
						$fmc_settings[ $key ] = in_array( $unit, $allowed_units, true ) ? $unit : 'days';
						break;
					case 'portal_position_x':
					case 'portal_position_y':
						$fmc_settings[ $key ] = sanitize_text_field( $val );
						break;
					case 'portal_text':
						$fmc_settings[ $key ] = wp_kses_post( $val );
						break;
					case 'contact_disclaimer':
						$fmc_settings[ $key ] = wp_kses_post( $val );
						break;
				}
			}
			if( !isset( $posted_settings[ 'portal_carts' ] ) ){
				$fmc_settings[ 'portal_carts' ] = 0;
			}
			if( !isset( $posted_settings[ 'portal_saving_searches' ] ) ){
				$fmc_settings[ 'portal_saving_searches' ] = 0;
			}
			if( !isset( $posted_settings[ 'portal_search' ] ) ){
				$fmc_settings[ 'portal_search' ] = 0;
			}
			if( !isset( $posted_settings[ 'portal_listing' ] ) ){
				$fmc_settings[ 'portal_listing' ] = 0;
			}
			if( !isset( $posted_settings[ 'portal_force' ] ) ){
				$fmc_settings[ 'portal_force' ] = 0;
			}
			if ( ! isset( $fmc_settings[ 'portal_snooze_amount' ] ) || ! is_numeric( $fmc_settings[ 'portal_snooze_amount' ] ) || intval( $fmc_settings[ 'portal_snooze_amount' ] ) < 1 ) {
				if ( isset( $fmc_settings[ 'portal_snooze_days' ] ) && is_numeric( $fmc_settings[ 'portal_snooze_days' ] ) && intval( $fmc_settings[ 'portal_snooze_days' ] ) >= 1 ) {
					$fmc_settings[ 'portal_snooze_amount' ] = intval( $fmc_settings[ 'portal_snooze_days' ] );
				} else {
					$fmc_settings[ 'portal_snooze_amount' ] = 7;
				}
			}
			$allowed_snooze_units = array( 'minutes', 'hours', 'days', 'weeks', 'months' );
			if ( ! isset( $fmc_settings[ 'portal_snooze_unit' ] ) || ! in_array( $fmc_settings[ 'portal_snooze_unit' ], $allowed_snooze_units, true ) ) {
				$fmc_settings[ 'portal_snooze_unit' ] = 'days';
			}
			add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_update_settings' ) );
		}

		// User saves Google settings
		if( self::is_authorized_save( 'update_google_maps_nonce', 'update_google_maps_action' ) ){
			$did_change = true;
			$posted_settings = isset( $_POST[ 'fmc_settings' ] ) && is_array( $_POST[ 'fmc_settings' ] ) ? $_POST[ 'fmc_settings' ] : array();
			if( isset( $posted_settings[ 'google_maps_api_key' ] ) ){
				$fmc_settings[ 'google_maps_api_key' ] = sanitize_text_field( $posted_settings[ 'google_maps_api_key' ] );
			}
			if( isset( $posted_settings[ 'map_height' ] ) ){
				$fmc_settings[ 'map_height' ] = sanitize_text_field( $posted_settings[ 'map_height' ] );
			}
			$fmc_settings[ 'google_maps_no_enqueue' ] = ( isset( $posted_settings[ 'google_maps_no_enqueue' ] ) ? 1 : 0 );
			add_action( 'admin_notices', array( '\FlexMLS\Admin\Settings', 'did_update_settings' ) );
		}

		if( $did_change ){
			update_option( 'fmc_settings', $fmc_settings );
		}
	}

}
