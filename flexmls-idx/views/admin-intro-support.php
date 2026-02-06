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
		<h3 class="bg-blue-head">Installation Information <button type="button" class="button button-secondary" id="flexmls-copy-installation-info" style="margin-left: 10px; vertical-align: middle;">Copy to clipboard</button></h3>
		<div class="content" id="flexmls-installation-info-content">
			<p><strong>Website URL:</strong> <?php echo home_url(); ?></p>
			<p><strong>WordPress URL:</strong> <?php echo site_url(); ?></p>
			<p><strong>WordPress Version:</strong> <?php echo $wp_version; ?></p>
			<p><strong>Flexmls&reg; IDX Plugin Version:</strong> <?php echo FMC_PLUGIN_VERSION; ?></p>
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
						<?php
							printf(
								'<li><a href="%s" target="_blank">%s</a> (Version %s) by <a href="%s" target="_blank">%s</a>%s</li>',
								$active_plugin[ 'PluginURI' ],
								$active_plugin[ 'Name' ],
								$active_plugin[ 'Version' ],
								$active_plugin[ 'AuthorURI' ],
								$active_plugin[ 'Author' ],
								in_array( $plugin_file, $known_plugin_conflicts ) ? $known_plugin_conflicts_tag : ''
							);
						?>
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
