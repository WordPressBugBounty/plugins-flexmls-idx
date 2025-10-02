<?php
namespace FlexMLS\Admin;

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

class NginxCompatibility {

	/**
	 * Check if the server is running nginx
	 * 
	 * @return bool True if nginx is detected, false otherwise
	 */
	public static function is_nginx() {
		// Check server software (most reliable method)
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$server_software = strtolower( $_SERVER['SERVER_SOFTWARE'] );
			if ( strpos( $server_software, 'nginx' ) !== false ) {
				return true;
			}
			// Explicitly check for other servers to avoid false positives
			$non_nginx_servers = array(
				'litespeed',
				'apache',
				'iis',
				'caddy',
				'lighttpd',
				'tomcat',
				'jetty',
				'h2o',
				'kestrel',
				'cowboy',
				'traffic server',
				'zeus',
				'cherokee',
				'resin',
				'tengine',
				'yaws',
				'monkey',
				'abyss',
				'openresty'
			);
			
			foreach ( $non_nginx_servers as $server ) {
				if ( strpos( $server_software, $server ) !== false ) {
					return false;
				}
			}
		}

		// Check for nginx-specific environment variables
		if ( isset( $_SERVER['NGINX_VERSION'] ) ) {
			return true;
		}

		// Check for nginx-specific headers (more specific than generic proxy headers)
		$nginx_indicators = array(
			'HTTP_X_NGINX_PROXY',
			'HTTP_X_NGINX_UPSTREAM',
			'HTTP_X_NGINX_CACHE'
		);

		foreach ( $nginx_indicators as $indicator ) {
			if ( isset( $_SERVER[ $indicator ] ) ) {
				return true;
			}
		}

		// Check for Apache modules - if they exist, we're likely on Apache
		if ( function_exists( 'apache_get_modules' ) ) {
			return false;
		}

		// Check for specific server indicators to avoid false positives
		$server_indicators = array(
			// LiteSpeed
			'LSWS_EDITION' => false,
			'HTTP_X_LITESPEED_CACHE' => false,
			// IIS
			'IIS' => false,
			'Microsoft-IIS' => false,
			// Apache Tomcat
			'TOMCAT_HOME' => false,
			'CATALINA_HOME' => false,
			// Jetty
			'JETTY_HOME' => false,
			// Caddy
			'CADDY_HOME' => false,
			// Lighttpd
			'LIGHTTPD_HOME' => false
		);

		foreach ( $server_indicators as $indicator => $is_nginx ) {
			if ( isset( $_SERVER[ $indicator ] ) ) {
				return $is_nginx;
			}
		}

		// Only return true if we have strong evidence of nginx
		// Don't use generic proxy headers as they can be set by any server behind a proxy
		return false;
	}

	/**
	 * Get nginx rewrite rules that need to be added to nginx configuration
	 * 
	 * @return array Array of nginx rewrite rules
	 */
	public static function get_nginx_rewrite_rules() {
		$fmc_settings = get_option( 'fmc_settings' );
		$permabase = isset( $fmc_settings['permabase'] ) ? $fmc_settings['permabase'] : 'idx';
		$destlink = isset( $fmc_settings['destlink'] ) ? $fmc_settings['destlink'] : '';
		
		// Detect if WordPress is in a subdirectory
		$wp_path = '';
		$site_url = site_url();
		$home_url = home_url();
		
		// Extract the path from the site URL
		$parsed_url = parse_url( $site_url );
		if ( isset( $parsed_url['path'] ) && $parsed_url['path'] !== '/' ) {
			$wp_path = rtrim( $parsed_url['path'], '/' );
		}
		
		$rules = array(
			'# Flexmls IDX Plugin Rewrite Rules for nginx',
			'# Add these rules to your nginx server block configuration',
			'# Place these rules BEFORE the main WordPress location block',
			'',
			'# OAuth callback rules',
			'location ~ ^' . $wp_path . '/oauth/callback/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&oauth_tag=oauth-login;',
			'}',
			'',
			'location ~ ^' . $wp_path . '/oauth/spark-logout/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&oauth_tag=oauth-logout;',
			'}',
			'',
			'# IDX permalink rules (matches WordPress: permabase/([^/]+)?)',
			'location ~ ^' . $wp_path . '/' . $permabase . '/([^/]+)/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&fmc_tag=$1' . ( $destlink ? '&page_id=' . $destlink : '' ) . ';',
			'}',
			'',
			'# Portal rules (matches WordPress: portal/([^/]+)?)',
			'location ~ ^' . $wp_path . '/portal/([^/]+)/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&fmc_vow_tag=$1' . ( $destlink ? '&page_id=' . $destlink : '' ) . ';',
			'}',
			'',
			'# End Flexmls IDX Plugin Rewrite Rules'
		);

		return $rules;
	}

	/**
	 * Display nginx warning message in admin
	 */
	public static function display_nginx_warning() {
		// Check if nginx is detected OR if we want to force display for testing
		$is_nginx = self::is_nginx();
		$force_display = isset( $_GET['force_nginx_warning'] );
		
		if ( ! $is_nginx && ! $force_display ) {
			return;
		}

		$rules = self::get_nginx_rewrite_rules();
		$rules_text = implode( "\n", $rules );
		
		?>
		<div class="notice notice-warning">
			<h3>⚠️ nginx Server Detected</h3>
			<p><strong>Your WordPress site is running on nginx.</strong> To ensure the Flexmls IDX plugin works correctly, you need to add the following rewrite rules to your nginx configuration file.</p>
			
			<div style="background: #f1f1f1; padding: 15px; margin: 10px 0; border-radius: 4px;">
				<h4>Required nginx Configuration:</h4>
				<p>Add these rules to your nginx server block configuration file (usually located at <code>/etc/nginx/sites-available/your-site</code> or similar):</p>
				<textarea readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px; background: #fff; border: 1px solid #ddd; padding: 10px;"><?php echo esc_textarea( $rules_text ); ?></textarea>
			</div>
			
			<div style="background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid #ffeaa7; border-radius: 4px;">
				<h4>⚠️ Important Steps:</h4>
				<ol>
					<li><strong>Add the rules above</strong> to your nginx configuration file</li>
					<li><strong>Test your nginx configuration</strong> with: <code>nginx -t</code></li>
					<li><strong>Reload nginx</strong> with: <code>systemctl reload nginx</code> or <code>service nginx reload</code></li>
					<li><strong>Clear any caching</strong> (if you use caching plugins)</li>
				</ol>
			</div>
			
			<div style="background: #d1ecf1; padding: 15px; margin: 10px 0; border: 1px solid #bee5eb; border-radius: 4px;">
				<h4>💡 Need Help?</h4>
				<p>If you're not comfortable editing nginx configuration files, please <strong>contact your website hosting provider or system administrator</strong> for assistance. They can help you add these rewrite rules to your nginx configuration.</p>
			</div>
			
		</div>
		<?php
	}

	/**
	 * Handle rewrite rules for nginx compatibility
	 * This method should be called instead of flush_rewrite_rules() when nginx is detected
	 */
	public static function handle_rewrite_rules() {
		if ( self::is_nginx() ) {
			// For nginx, we don't flush rewrite rules as they don't work the same way
			// Instead, we just add the rules to WordPress (they'll be used for URL generation)
			// The actual rewriting needs to be handled by nginx configuration
			return true;
		} else {
			// For Apache, use the standard WordPress flush_rewrite_rules
			flush_rewrite_rules();
			return true;
		}
	}

	/**
	 * Check if permalinks are working correctly
	 * 
	 * @return bool True if permalinks are working, false otherwise
	 */
	public static function check_permalink_compatibility() {
		if ( ! self::is_nginx() ) {
			// On Apache, assume permalinks work if they're enabled
			return get_option( 'permalink_structure' ) !== '';
		}

		// For nginx, we need to check if the rewrite rules are properly configured
		// This is a basic check - in reality, we can't fully verify nginx config from PHP
		$test_url = home_url( '/oauth/callback/' );
		
		// We can't easily test if nginx rules are working from PHP
		// So we'll assume they need to be configured if nginx is detected
		return false;
	}

	/**
	 * Test if a specific URL is accessible (for debugging nginx rules)
	 * 
	 * @param string $url The URL to test
	 * @return array Test results
	 */
	public static function test_url_accessibility( $url ) {
		$result = array(
			'url' => $url,
			'accessible' => false,
			'status_code' => null,
			'error' => null
		);

		// Only test if we're on nginx
		if ( ! self::is_nginx() ) {
			$result['error'] = 'Not on nginx server';
			return $result;
		}

		// Use wp_remote_get to test the URL
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'sslverify' => false,
			'user-agent' => 'Flexmls-IDX-Plugin/nginx-test'
		) );

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
		} else {
			$result['status_code'] = wp_remote_retrieve_response_code( $response );
			$result['accessible'] = ( $result['status_code'] >= 200 && $result['status_code'] < 400 );
		}

		return $result;
	}

	/**
	 * Get comprehensive server and plugin status information
	 * 
	 * @return array Detailed status information
	 */
	public static function get_comprehensive_status() {
		$fmc_settings = get_option( 'fmc_settings' );
		$server_info = self::get_server_info();
		
		$status = array(
			'server' => $server_info,
			'wordpress' => array(
				'version' => get_bloginfo( 'version' ),
				'permalink_structure' => get_option( 'permalink_structure' ),
				'permalinks_enabled' => get_option( 'permalink_structure' ) !== '',
				'home_url' => home_url(),
				'site_url' => site_url()
			),
			'plugin' => array(
				'version' => defined( 'FMC_PLUGIN_VERSION' ) ? FMC_PLUGIN_VERSION : 'Unknown',
				'api_configured' => !empty( $fmc_settings['api_key'] ) && !empty( $fmc_settings['api_secret'] ),
				'permabase' => isset( $fmc_settings['permabase'] ) ? $fmc_settings['permabase'] : 'idx',
				'destlink' => isset( $fmc_settings['destlink'] ) ? $fmc_settings['destlink'] : ''
			),
			'nginx_rules' => array(
				'generated' => self::get_nginx_rewrite_rules(),
				'permalink_compatible' => self::check_permalink_compatibility()
			)
		);

		// Test some URLs if we're on nginx
		if ( $server_info['is_nginx'] ) {
			$test_urls = array(
				home_url( '/oauth/callback/' ),
				home_url( '/' . $status['plugin']['permabase'] . '/test/' ),
				home_url( '/portal/test/' )
			);

			$status['url_tests'] = array();
			foreach ( $test_urls as $url ) {
				$status['url_tests'][] = self::test_url_accessibility( $url );
			}
		}

		return $status;
	}

	/**
	 * Display nginx guidance for permalink base changes
	 * This is specifically for the behavior settings page
	 */
	public static function display_nginx_permabase_warning() {
		// Only show if nginx is detected
		if ( ! self::is_nginx() ) {
			return;
		}

		$fmc_settings = get_option( 'fmc_settings' );
		$permabase = isset( $fmc_settings['permabase'] ) ? $fmc_settings['permabase'] : 'idx';
		$rules = self::get_nginx_rewrite_rules();
		$rules_text = implode( "\n", $rules );
		
		// Check if permalink base or destination page was recently changed (within last 5 minutes)
		$last_permabase_change = get_transient( 'fmc_permabase_changed' );
		$last_destlink_change = get_transient( 'fmc_destlink_changed' );
		$recently_changed_permabase = $last_permabase_change && ( time() - $last_permabase_change ) < 300; // 5 minutes
		$recently_changed_destlink = $last_destlink_change && ( time() - $last_destlink_change ) < 300; // 5 minutes
		$recently_changed = $recently_changed_permabase || $recently_changed_destlink;
		
		// Generate test URLs
		$test_url = home_url( '/' . $permabase . '/search' );
		$test_listing_url = home_url( '/' . $permabase . '/test-listing' );
		
		?>
		<div id="nginx-configuration-guidance" class="nginx-permabase-guidance" style="background: #e7f3ff; padding: 15px; margin: 10px 0; border: 1px solid #b3d9ff; border-radius: 4px;">
			<details <?php echo $recently_changed ? 'open' : ''; ?> style="margin: 0;">
				<summary style="cursor: pointer; font-weight: bold; color: #004085; list-style: none; padding: 0; margin: 0 0 10px 0;">
					<h4 style="margin: 0; display: inline;">
						<?php echo $recently_changed ? '🔄' : 'ℹ️'; ?> nginx Server Detected - Testing Guide
					</h4>
				</summary>
				
				<div style="margin-top: 15px;">
					<?php if ( $recently_changed ): ?>
						<?php 
						$changed_items = array();
						if ( $recently_changed_permabase ) {
							$changed_items[] = 'Permalink Base';
						}
						if ( $recently_changed_destlink ) {
							$changed_items[] = 'Destination Page';
						}
						$changed_text = implode( ' and ', $changed_items );
						?>
						<div style="background: #fff3cd; padding: 12px; margin: 0 0 15px 0; border: 1px solid #ffeaa7; border-radius: 4px;">
							<p style="margin: 0 0 8px 0; color: #856404; font-weight: bold;">You just changed the <?php echo esc_html( $changed_text ); ?>!</p>
							<p style="margin: 0; color: #856404; font-size: 13px;">
								Current permalink base: <code><?php echo esc_html( $permabase ); ?></code>
								<?php if ( $recently_changed_destlink ): ?>
									<br>Current destination page ID: <code><?php echo esc_html( $fmc_settings['destlink'] ); ?></code>
								<?php endif; ?>
							</p>
						</div>
					<?php else: ?>
						<p style="margin: 0 0 15px 0; color: #004085;"><strong>Your site is running on nginx.</strong> This is just informational - you may not need to make any changes!</p>
					<?php endif; ?>
					
					<div style="background: #d4edda; padding: 15px; margin: 0 0 15px 0; border: 1px solid #c3e6cb; border-radius: 4px;">
						<h5 style="margin: 0 0 10px 0; color: #155724;">🧪 First, Test Your URLs</h5>
						<p style="margin: 0 0 10px 0; color: #155724; font-size: 14px;">Before making any nginx changes, test if your IDX URLs are working:</p>
						<ol style="margin: 0 0 10px 0; padding-left: 20px; color: #155724;">
							<li><strong>Test search page:</strong> <a href="<?php echo esc_url( $test_url ); ?>" target="_blank" style="color: #155724; text-decoration: underline;"><?php echo esc_html( $test_url ); ?></a></li>
							<li><strong>Check if listings appear</strong> on the search page</li>
							<li><strong>If no listings show</strong> → Try enabling "Allow multiple summary lists per page" setting above</li>
							<li><strong>Click on a listing</strong> to see if the detail page loads</li>
							<li><strong>If both work</strong> → You don't need to change anything! 🎉</li>
							<li><strong>If you get 404 errors</strong> → You may need nginx configuration (see below)</li>
						</ol>
						<p style="margin: 0; color: #155724; font-size: 13px; font-style: italic;">Many hosting providers already have nginx configured correctly for WordPress permalinks.</p>
					</div>
					
					<details style="margin: 10px 0;">
						<summary style="cursor: pointer; font-weight: bold; color: #004085;">🔧 nginx Configuration (Only if URLs don't work)</summary>
						<div style="margin-top: 15px;">
							<div style="background: #f8f9fa; padding: 12px; margin: 0 0 15px 0; border: 1px solid #dee2e6; border-radius: 4px;">
								<p style="margin: 0 0 8px 0; color: #495057; font-weight: bold;">⚠️ Important Notes:</p>
								<ul style="margin: 0; padding-left: 20px; color: #495057; font-size: 13px;">
									<li>Only needed if your IDX URLs return 404 errors</li>
									<li>Many shared hosting providers don't allow nginx config changes</li>
									<li>Contact your hosting provider if you can't access nginx configuration</li>
								</ul>
							</div>
							
							<p style="margin: 0 0 10px 0; color: #495057;">If your test URLs don't work, add these rules to your nginx server block configuration file:</p>
							<div style="position: relative;">
								<textarea id="nginx-permabase-config" readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 11px; background: #fff; border: 1px solid #ddd; padding: 10px; resize: vertical;"><?php echo esc_textarea( $rules_text ); ?></textarea>
								<button type="button" onclick="copyNginxPermabaseConfig()" style="position: absolute; top: 10px; right: 10px; background: #0073aa; color: white; border: none; padding: 6px 10px; border-radius: 3px; cursor: pointer; font-size: 11px;">Copy</button>
							</div>
							<script>
							function copyNginxPermabaseConfig() {
								const textarea = document.getElementById('nginx-permabase-config');
								textarea.select();
								textarea.setSelectionRange(0, 99999);
								document.execCommand('copy');
								
								const button = event.target;
								const originalText = button.textContent;
								button.textContent = 'Copied!';
								button.style.background = '#28a745';
								setTimeout(() => {
									button.textContent = originalText;
									button.style.background = '#0073aa';
								}, 2000);
							}
							</script>
							
							<div style="background: #d1ecf1; padding: 12px; margin: 15px 0; border: 1px solid #bee5eb; border-radius: 4px;">
								<h6 style="margin: 0 0 8px 0; color: #0c5460;">Steps to Update nginx (if needed):</h6>
								<ol style="margin: 0; padding-left: 20px; color: #0c5460; font-size: 13px;">
									<li>Add the rules above to your nginx configuration file</li>
									<li>Test configuration: <code>nginx -t</code></li>
									<li>Reload nginx: <code>systemctl reload nginx</code></li>
									<li>Clear any caching plugins</li>
									<li>Test your URLs again</li>
								</ol>
							</div>
						</div>
					</details>
					
					<div style="background: #fff3cd; padding: 12px; margin: 15px 0 0 0; border: 1px solid #ffeaa7; border-radius: 4px;">
						<p style="margin: 0; color: #856404; font-size: 13px;">
							<strong>Need help?</strong> If you can't access nginx configuration or need assistance, contact your hosting provider.
						</p>
					</div>
				</div>
			</details>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get nginx configuration rules with custom parameters
	 */
	public static function ajax_get_nginx_rules() {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'], 'fmc_nginx_rules_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Get parameters from AJAX request
		$permabase = sanitize_text_field( $_POST['permabase'] );
		$destlink = sanitize_text_field( $_POST['destlink'] );
		
		// Temporarily update settings for rule generation
		$fmc_settings = get_option( 'fmc_settings' );
		$original_permabase = $fmc_settings['permabase'];
		$original_destlink = $fmc_settings['destlink'];
		
		$fmc_settings['permabase'] = $permabase;
		$fmc_settings['destlink'] = $destlink;
		
		// Generate rules with temporary settings
		$rules = self::get_nginx_rewrite_rules_with_params( $permabase, $destlink );
		$rules_text = implode( "\n", $rules );
		
		// Restore original settings
		$fmc_settings['permabase'] = $original_permabase;
		$fmc_settings['destlink'] = $original_destlink;
		
		wp_send_json_success( array(
			'rules' => $rules_text,
			'permabase' => $permabase,
			'destlink' => $destlink
		) );
	}

	/**
	 * Generate nginx rewrite rules with specific parameters
	 * 
	 * @param string $permabase The permalink base
	 * @param string $destlink The destination page ID
	 * @return array Array of nginx rewrite rules
	 */
	public static function get_nginx_rewrite_rules_with_params( $permabase, $destlink ) {
		// Detect if WordPress is in a subdirectory
		$wp_path = '';
		$site_url = site_url();
		
		// Extract the path from the site URL
		$parsed_url = parse_url( $site_url );
		if ( isset( $parsed_url['path'] ) && $parsed_url['path'] !== '/' ) {
			$wp_path = rtrim( $parsed_url['path'], '/' );
		}
		
		$rules = array(
			'# Flexmls IDX Plugin Rewrite Rules for nginx',
			'# Add these rules to your nginx server block configuration',
			'# Place these rules BEFORE the main WordPress location block',
			'',
			'# OAuth callback rules',
			'location ~ ^' . $wp_path . '/oauth/callback/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&oauth_tag=oauth-login;',
			'}',
			'',
			'location ~ ^' . $wp_path . '/oauth/spark-logout/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&oauth_tag=oauth-logout;',
			'}',
			'',
			'# IDX permalink rules (matches WordPress: permabase/([^/]+)?)',
			'location ~ ^' . $wp_path . '/' . $permabase . '/([^/]+)/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&fmc_tag=$1' . ( $destlink ? '&page_id=' . $destlink : '' ) . ';',
			'}',
			'',
			'# Portal rules (matches WordPress: portal/([^/]+)?)',
			'location ~ ^' . $wp_path . '/portal/([^/]+)/?$ {',
			'    try_files $uri $uri/ ' . $wp_path . '/index.php?plugin=flexmls-idx&fmc_vow_tag=$1' . ( $destlink ? '&page_id=' . $destlink : '' ) . ';',
			'}',
			'',
			'# End Flexmls IDX Plugin Rewrite Rules'
		);

		return $rules;
	}

	/**
	 * Get server information for debugging
	 * 
	 * @return array Server information
	 */
	public static function get_server_info() {
		$info = array(
			'server_software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
			'is_nginx' => self::is_nginx(),
			'is_apache' => function_exists( 'apache_get_modules' ),
			'permalinks_enabled' => get_option( 'permalink_structure' ) !== '',
			'nginx_headers' => array(),
			'litespeed_headers' => array(),
			'proxy_headers' => array(),
			'server_indicators' => array()
		);

		// Check for nginx-specific headers
		$nginx_headers = array(
			'HTTP_X_NGINX_PROXY',
			'HTTP_X_NGINX_UPSTREAM',
			'HTTP_X_NGINX_CACHE',
			'NGINX_VERSION'
		);

		foreach ( $nginx_headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) ) {
				$info['nginx_headers'][ $header ] = $_SERVER[ $header ];
			}
		}

		// Check for LiteSpeed-specific headers
		$litespeed_headers = array(
			'LSWS_EDITION',
			'HTTP_X_LITESPEED_CACHE'
		);

		foreach ( $litespeed_headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) ) {
				$info['litespeed_headers'][ $header ] = $_SERVER[ $header ];
			}
		}

		// Check for generic proxy headers (for debugging purposes)
		$proxy_headers = array(
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_PROTO'
		);

		foreach ( $proxy_headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) ) {
				$info['proxy_headers'][ $header ] = $_SERVER[ $header ];
			}
		}

		// Check for server-specific indicators
		$server_indicators = array(
			'LSWS_EDITION',
			'HTTP_X_LITESPEED_CACHE',
			'IIS',
			'Microsoft-IIS',
			'TOMCAT_HOME',
			'CATALINA_HOME',
			'JETTY_HOME',
			'CADDY_HOME',
			'LIGHTTPD_HOME',
			'NGINX_VERSION'
		);

		foreach ( $server_indicators as $indicator ) {
			if ( isset( $_SERVER[ $indicator ] ) ) {
				$info['server_indicators'][ $indicator ] = $_SERVER[ $indicator ];
			}
		}

		return $info;
	}
}
