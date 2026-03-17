<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

global $wp_version;
$options = get_option( 'fmc_settings' );
$options = is_array( $options ) ? $options : array();

$active_theme = wp_get_theme();
$all_plugins = get_plugins();
$active_plugin_files = get_option( 'active_plugins', array() );

// Handle multisite network-activated plugins
if ( is_multisite() ) {
	$network_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
	if ( ! empty( $network_active_plugins ) ) {
		$active_plugin_files = array_merge( $active_plugin_files, array_keys( $network_active_plugins ) );
	}
}

// Separate plugins into active and deactivated
$active_plugins = array();
$deactivated_plugins = array();

foreach( $all_plugins as $plugin_file => $plugin_data ) {
	if ( in_array( $plugin_file, $active_plugin_files ) ) {
		$active_plugins[ $plugin_file ] = $plugin_data;
	} else {
		$deactivated_plugins[ $plugin_file ] = $plugin_data;
	}
}

$known_plugin_conflicts = array(
			'screencastcom-video-embedder/screencast.php', // Screencast Video Embedder, JS syntax errors in 0.4.4 breaks all pages
		);

$known_plugin_conflicts_tag = ' &ndash; <span class="flexmls-known-plugin-conflict-tag">Known issues</span>';

// Plugins that minify CSS/JS — show a notice that our plugin's assets should be excluded from their minification.
$minification_plugins = array(
	'autoptimize/autoptimize.php'       => 'Autoptimize',
	'wp-rocket/wp-rocket.php'           => 'WP Rocket',
	'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
	'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
	'wp-optimize/wp-optimize.php'       => 'WP-Optimize',
	'nitropack/nitropack.php'           => 'NitroPack',
	'perfmatters/perfmatters.php'       => 'Perfmatters',
	'fast-velocity-minify/fvm.php'      => 'Fast Velocity Minify',
);

$fmc_asset_paths_for_minify_exclude = array(
	'assets/js/admin.js',
	'assets/js/main.js',
	'assets/js/portal.js',
	'assets/js/map.js',
	'assets/js/integration.js',
	'assets/js/flex_gtb.js',
	'assets/css/style.css',
	'assets/css/style_admin.css',
);

// Check if an update is available and how many versions behind (for version display messaging).
$fmc_plugin_basename = plugin_basename( FMC_PLUGIN_DIR . 'flexmls_connect.php' );
$fmc_update_info     = null;
$fmc_versions_behind = null;
$latest              = null;

// Prefer WordPress update transient (used when plugin slug matches repo, e.g. flexmls-idx).
$update_plugins = get_site_transient( 'update_plugins' );
$canonical_slug = 'flexmls-idx/flexmls_connect.php';
foreach ( array( $fmc_plugin_basename, $canonical_slug ) as $slug ) {
	if ( ! empty( $update_plugins->response[ $slug ] ) && ! empty( $update_plugins->response[ $slug ]->new_version ) ) {
		$latest = $update_plugins->response[ $slug ]->new_version;
		break;
	}
}

// Fallback: when developing from a different folder (e.g. wordpress-idx-plugin), transient has no entry.
// Fetch latest version from WordPress.org API and cache it.
if ( $latest === null ) {
	$latest = get_transient( 'fmc_latest_version_from_api' );
	if ( $latest === false ) {
		$api_url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=flexmls-idx&request[fields][version]=1';
		$response = wp_remote_get( $api_url, array( 'timeout' => 5 ) );
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['version'] ) ) {
				$latest = $body['version'];
				set_transient( 'fmc_latest_version_from_api', $latest, 12 * HOUR_IN_SECONDS );
			}
		}
	}
}

if ( $latest !== null && version_compare( FMC_PLUGIN_VERSION, $latest, '<' ) ) {
	$fmc_update_info = $latest;
	// Parse semver (major.minor.patch) to approximate "versions behind" for messaging.
	$cur_parts    = array_map( 'intval', explode( '.', FMC_PLUGIN_VERSION . '.0' ) );
	$latest_parts = array_map( 'intval', explode( '.', $latest . '.0' ) );
	$cur_parts    = array_slice( $cur_parts, 0, 3 );
	$latest_parts = array_slice( $latest_parts, 0, 3 );
	$fmc_versions_behind = ( $latest_parts[0] - $cur_parts[0] ) * 10000
		+ ( $latest_parts[1] - $cur_parts[1] ) * 100
		+ ( isset( $latest_parts[2] ) && isset( $cur_parts[2] ) ? $latest_parts[2] - $cur_parts[2] : 0 );
	if ( $fmc_versions_behind < 0 ) {
		$fmc_versions_behind = 0;
	}
}

?>

<div class="support-content">
	<h3>FBS Products Support</h3>
	<table>
		<tr>
			<td>Email:</td>
			<td><a href="<?php echo antispambot( 'mailto:idxsupport@flexmls.com' ); ?>"><?php echo antispambot( 'idxsupport@flexmls.com' ); ?></td>
		</tr>
		<tr>
			<td>Online:</td>
			<td><a href="https://fbsidx.com/help" target="_blank">fbsidx.com/help</a></td>
		</tr>
		<tr>
			<td>Phone:</td>
			<td>888-525-4747 x.171</td>
		</tr>
		<tr>
			<td><strong>Hours of operation:</strong> 8am - 5pm Central Time</td>
		</tr>
	</table>

	<div class="getting-started">
		<h3 class="bg-blue-head">Getting Started with your WordPress Plugin</h3>
		<p>Visit our <a href="https://fbsidx.com/help/plugin" target="_blank">online help center here</a> for step by step instructions.</p>
	</div>

	<div class="installation-info">
		<h3 class="bg-blue-head">Installation Information <button type="button" class="button button-secondary" id="flexmls-copy-installation-info" style="background-color: #fff; color: var(--wp-admin-theme-color); margin-left: 10px; vertical-align: middle;">Copy to clipboard</button></h3>
		<div class="content" id="flexmls-installation-info-content">
			<p><strong>Website URL:</strong> <?php echo home_url(); ?></p>
			<p><strong>WordPress URL:</strong> <?php echo site_url(); ?></p>
			<p><strong>WordPress Version:</strong> <?php echo $wp_version; ?></p>
			<p><strong>Flexmls&reg; IDX Plugin Version:</strong> <?php
			if ( $fmc_update_info === null ) {
				// Current or update check not available — show version only.
				echo esc_html( FMC_PLUGIN_VERSION );
			} elseif ( $fmc_versions_behind >= 3 ) {
				// 3 or more versions behind — advise to update with link and latest version.
				echo esc_html( FMC_PLUGIN_VERSION );
				$plugins_url = admin_url( 'plugins.php' );
				?><br><span class="flexmls-version-update-advice flexmls-version-update-advice--urgent">Please update to the latest version (<?php echo esc_html( $fmc_update_info ); ?>) by going to the <a href="<?php echo esc_url( $plugins_url ); ?>">Plugins</a> page and updating to <?php echo esc_html( $fmc_update_info ); ?>.</span><?php
			} else {
				// 1–2 versions behind — gentle notice in orange.
				echo esc_html( FMC_PLUGIN_VERSION );
				?><br><span class="flexmls-version-update-advice flexmls-version-update-advice--minor">Newer versions are available (latest: <?php echo esc_html( $fmc_update_info ); ?>).</span><?php
			}
			?></p>
			<p><strong>Plugin Key:</strong> <?php echo isset( $options['api_key'] ) && $options['api_key'] !== '' ? esc_html( $options['api_key'] ) : '—'; ?></p>
			<p><strong>Web Server:</strong> <?php 
				$server_software = $_SERVER[ 'SERVER_SOFTWARE' ];
				// Check if nginx is detected and add link to nginx configuration guidance
				if ( \FlexMLS\Admin\NginxCompatibility::is_nginx() ) {
					printf( '%s - <a href="%s#nginx-configuration-guidance" title="View nginx configuration guidance">nginx configuration help</a>', 
						$server_software, 
						admin_url( 'admin.php?page=fmc_admin_settings' ) 
					);
				} else {
					echo $server_software;
				}
			?></p>
			<p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
			<p><strong>Theme:</strong> <?php
				if( $active_theme->get( 'ThemeURI' ) ){
					printf( "<a href=\"%s\" target=\"_blank\">%s</a> (Version %s)",
						$active_theme->get( 'ThemeURI' ),
						$active_theme->get( 'Name' ),
						$active_theme->get( 'Version' )
					);
				} else {
					printf( "%s (Version %s)",
						$active_theme->get( 'Name' ),
						$active_theme->get( 'Version' )
					);
				}
			?></p>
			<p><strong>Parent Theme:</strong> <?php
				if( is_child_theme() ){
					$parent_theme = $active_theme->get( 'Template' );
					$parent_theme = wp_get_theme( $parent_theme );
					if( $parent_theme->get( 'ThemeURI' ) ){
						printf( "<a href=\"%s\" target=\"_blank\">%s</a> (Version %s)",
							$parent_theme->get( 'ThemeURI' ),
							$parent_theme->get( 'Name' ),
							$parent_theme->get( 'Version' )
						);
					} else {
						printf( "%s (Version %s)",
							$parent_theme->get( 'Name' ),
							$parent_theme->get( 'Version' )
						);
					}
				} else {
					echo 'N/A';
				}
			?></p>
			<p><strong>PHP Memory Limit:</strong> <?php
				$memory_limit = ini_get( 'memory_limit' );
				echo esc_html( $memory_limit );
				$memory_bytes = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( $memory_limit ) : 0;
				if ( $memory_bytes > 0 && $memory_bytes < 128 * 1024 * 1024 ) {
					echo ' <span class="description">— If you experience errors or slowness, contact your hosting provider to increase the PHP memory limit.</span>';
				}
			?></p>
			<p><strong>PHP Max Execution Time:</strong> <?php
				$max_exec = ini_get( 'max_execution_time' );
				echo ( false === $max_exec || '' === $max_exec ) ? 'N/A (default)' : esc_html( $max_exec . ' seconds' );
			?></p>
			<p><strong>PHP SAPI:</strong> <?php echo esc_html( php_sapi_name() ); ?></p>
			<?php global $wpdb; ?>
			<p><strong>MySQL / MariaDB Version:</strong> <?php echo $wpdb->db_version() ? esc_html( $wpdb->db_version() ) : 'N/A'; ?></p>
			<p><strong>Object Cache (Redis/Memcached):</strong> <?php echo wp_using_ext_object_cache() ? 'Yes' : 'No'; ?></p>
			<?php
			// Test if the database user can delete transients (required for plugin cache cleanup).
			$fmc_cap_check_key   = 'fmc_cap_check_' . time();
			set_transient( $fmc_cap_check_key, '1', 60 );
			$fmc_cap_check_set   = ( get_transient( $fmc_cap_check_key ) === '1' );
			$fmc_cap_check_gone  = delete_transient( $fmc_cap_check_key );
			$fmc_db_can_delete   = $fmc_cap_check_set && $fmc_cap_check_gone;
			$fmc_db_delete_unknown = $fmc_cap_check_set === false;
			?>
			<p><strong>Database user can delete transients (cache cleanup):</strong> <?php
				if ( $fmc_db_can_delete ) {
					echo 'Yes';
				} elseif ( $fmc_db_delete_unknown ) {
					echo 'Could not determine';
				} else {
					echo 'No';
				}
			?></p>
			<?php if ( ! $fmc_db_can_delete ) : ?>
			<p style="color: #c00; font-size: 14px;">Your database user may not have permission to delete from the options table. The plugin's cache cleanup cannot remove old transients, which can lead to database bloat. Ask your hosting provider to grant the WordPress database user SELECT, INSERT, UPDATE, and DELETE on the database.</p>
			<?php endif; ?>
			<p><strong>Multisite:</strong> <?php echo is_multisite() ? 'Yes' : 'No'; ?></p>
			<p><strong>WP_DEBUG:</strong> <?php echo ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Yes' : 'No'; ?></p>
			<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
			<p><strong>WP_DEBUG_LOG:</strong> <?php echo ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? 'Yes' : 'No'; ?></p>
			<?php endif; ?>
			<p><strong>WP Cron Disabled:</strong> <?php echo ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'Yes' : 'No'; ?></p>
			<p><strong>API Credentials (Flexmls IDX):</strong> <?php
				$api_configured = ! empty( $options['api_key'] ) && ! empty( $options['api_secret'] );
				echo $api_configured ? 'Configured' : 'Not configured';
			?></p>
			<p><strong>IDX Permalink Base:</strong> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fmc_admin_settings&tab=behavior#fmc-setting-permalink-base' ) ); ?>"><?php echo esc_html( ! empty( $options['permabase'] ) ? $options['permabase'] : 'idx (default)' ); ?></a></p>
			<p><strong>IDX Search Results Page:</strong> <?php
				$destlink = isset( $options['destlink'] ) ? $options['destlink'] : '';
				if ( '' !== $destlink && is_numeric( $destlink ) ) {
					$dest_post = get_post( (int) $destlink );
					$edit_url = admin_url( 'post.php?post=' . (int) $destlink . '&action=edit' );
					if ( $dest_post ) {
						printf( '<a href="%s">%s</a> (ID: %d)', esc_url( $edit_url ), esc_html( $dest_post->post_title ), (int) $destlink );
					} else {
						printf( 'Page ID %d (missing or trashed)', (int) $destlink );
					}
				} else {
					echo 'Not set';
				}
			?></p>
			<p><strong>Cached API Responses (tracked):</strong> <?php
				$tracked = get_option( 'fmc_tracked_transients', array() );
				$tracked_count = is_array( $tracked ) ? count( $tracked ) : 0;
				echo esc_html( (string) $tracked_count );
			?></p>
			<p><strong>cURL Version:</strong> <?php $curl_version = curl_version(); echo $curl_version[ 'version' ]; ?></p>
			<p><strong>Permalinks:</strong> <?php echo ( get_option( 'permalink_structure' ) ? 'Yes' : 'No' ); ?></p>
			<p><strong>Active Plugins:</strong></p>
			<?php if ( ! empty( $active_plugins ) ): ?>
				<ul class="flexmls-list-active-plugins">
					<?php foreach( $active_plugins as $plugin_file => $active_plugin ): ?>
						<li>
							<?php
								printf(
									'<a href="%s" target="_blank">%s</a> (Version %s) by <a href="%s" target="_blank">%s</a>%s',
									esc_url( $active_plugin[ 'PluginURI' ] ),
									esc_html( $active_plugin[ 'Name' ] ),
									esc_html( $active_plugin[ 'Version' ] ),
									esc_url( $active_plugin[ 'AuthorURI' ] ),
									esc_html( $active_plugin[ 'Author' ] ),
									in_array( $plugin_file, $known_plugin_conflicts ) ? $known_plugin_conflicts_tag : ''
								);
							?>
							<?php if ( isset( $minification_plugins[ $plugin_file ] ) ) : ?>
								<br><span class="flexmls-minify-notice description" style="display: inline-block; margin-top: 0.35em;">You do not need to minify our CSS/JS in <?php echo esc_html( $minification_plugins[ $plugin_file ] ); ?>. Our plugin already minifies its own assets. Exclude our files from <?php echo esc_html( $minification_plugins[ $plugin_file ] ); ?>'s minification settings to avoid conflicts. Our asset paths: <code><?php echo esc_html( implode( ', ', $fmc_asset_paths_for_minify_exclude ) ); ?></code>.</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else: ?>
				<p><em>No active plugins.</em></p>
			<?php endif; ?>
			
			<?php if ( ! empty( $deactivated_plugins ) ): ?>
				<p><strong>Deactivated Plugins:</strong></p>
				<ul class="flexmls-list-active-plugins">
					<?php foreach( $deactivated_plugins as $plugin_file => $deactivated_plugin ): ?>
						<?php
							printf(
								'<li><a href="%s" target="_blank">%s</a> (Version %s) by <a href="%s" target="_blank">%s</a>%s</li>',
								$deactivated_plugin[ 'PluginURI' ],
								$deactivated_plugin[ 'Name' ],
								$deactivated_plugin[ 'Version' ],
								$deactivated_plugin[ 'AuthorURI' ],
								$deactivated_plugin[ 'Author' ],
								in_array( $plugin_file, $known_plugin_conflicts ) ? $known_plugin_conflicts_tag : ''
							);
						?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<script>
	(function() {
		var btn = document.getElementById('flexmls-copy-installation-info');
		var content = document.getElementById('flexmls-installation-info-content');
		if (!btn || !content) return;
		btn.addEventListener('click', function() {
			var text = content.innerText || content.textContent || '';
			if (!text) return;
			var done = function() {
				btn.textContent = 'Copied!';
				setTimeout(function() { btn.textContent = 'Copy to clipboard'; }, 2000);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done).catch(function() {
					fallbackCopy(text, done);
				});
			} else {
				fallbackCopy(text, done);
			}
		});
		function fallbackCopy(str, callback) {
			var ta = document.createElement('textarea');
			ta.value = str;
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			try {
				document.execCommand('copy');
				if (callback) callback();
			} catch (e) {}
			document.body.removeChild(ta);
		}
	})();
	</script>

</div>
