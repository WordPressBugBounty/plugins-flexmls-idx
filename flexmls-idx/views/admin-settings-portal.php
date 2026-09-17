<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

$fmc_settings = get_option( 'fmc_settings' );

$fmc_settings[ 'portal_carts' ] = ( isset( $fmc_settings[ 'portal_carts' ] ) && 1 == $fmc_settings[ 'portal_carts' ] ) ? 1 : 0;
$fmc_settings[ 'portal_search' ] = ( isset( $fmc_settings[ 'portal_search' ] ) && 1 == $fmc_settings[ 'portal_search' ] ) ? 1 : 0;
$fmc_settings[ 'portal_listing' ] = ( isset( $fmc_settings[ 'portal_listing' ] ) && 1 == $fmc_settings[ 'portal_listing' ] ) ? 1 : 0;
$fmc_settings[ 'portal_force' ] = ( isset( $fmc_settings[ 'portal_force' ] ) && 1 == $fmc_settings[ 'portal_force' ] ) ? 1 : 0;
$portal_snooze_amount = isset( $fmc_settings[ 'portal_snooze_amount' ] ) && is_numeric( $fmc_settings[ 'portal_snooze_amount' ] )
	? max( 1, intval( $fmc_settings[ 'portal_snooze_amount' ] ) )
	: ( isset( $fmc_settings[ 'portal_snooze_days' ] ) && is_numeric( $fmc_settings[ 'portal_snooze_days' ] )
		? max( 1, intval( $fmc_settings[ 'portal_snooze_days' ] ) )
		: 7 );
$portal_snooze_units = array(
	'minutes' => 'Minutes',
	'hours'   => 'Hours',
	'days'    => 'Days',
	'weeks'   => 'Weeks',
	'months'  => 'Months',
);
$portal_snooze_unit = isset( $fmc_settings[ 'portal_snooze_unit' ] ) && isset( $portal_snooze_units[ $fmc_settings[ 'portal_snooze_unit' ] ] )
	? $fmc_settings[ 'portal_snooze_unit' ]
	: 'days';

$SparkAPI   = new \SparkAPI\Core();
$auth_token = $SparkAPI->generate_auth_token();

$oauth_key    = isset( $fmc_settings[ 'oauth_key' ] ) ? (string) $fmc_settings[ 'oauth_key' ] : '';
$oauth_secret = isset( $fmc_settings[ 'oauth_secret' ] ) ? (string) $fmc_settings[ 'oauth_secret' ] : '';
$has_oauth    = ( '' !== $oauth_key && '' !== $oauth_secret );
$redirect_uri = home_url( 'index.php/oauth/callback' );
$api_key      = isset( $fmc_settings[ 'api_key' ] ) ? (string) $fmc_settings[ 'api_key' ] : '';

$oauth_request_text = "Please create an OAuth Client for my Flexmls IDX WordPress plugin.\n\n"
	. "OAuth Redirect URI: {$redirect_uri}\n"
	. "Plugin Key: {$api_key}\n";

?>
<form action="<?php echo admin_url( 'admin.php?page=fmc_admin_settings&tab=portal' ); ?>" method="post" id="fmc-oauth-credentials-form" class="<?php echo $has_oauth ? 'fmc-credentials-locked' : ''; ?>" autocomplete="off">
	<h3>OAuth Credentials<?php if ( $has_oauth ): ?> <span class="fmc-admin-badge fmc-admin-badge-success"><?php esc_html_e( 'Configured', 'flexmls-idx' ); ?></span><?php endif; ?></h3>

	<?php if ( ! $auth_token ): ?>
		<p><?php esc_html_e( 'Connect a working plugin key under Flexmls IDX → Credentials before you can request or enter OAuth credentials. OAuth allows portal leads to sign into their accounts in the plugin widgets securely.', 'flexmls-idx' ); ?></p>
	<?php elseif ( ! $has_oauth ): ?>
		<p><?php esc_html_e( 'OAuth credentials allow your portal leads to sign into their accounts in the plugin widgets securely.', 'flexmls-idx' ); ?></p>
		<div class="fmc-oauth-request-box">
			<div class="fmc-oauth-request-box-header">
				<h4><?php esc_html_e( 'Request your OAuth credentials', 'flexmls-idx' ); ?></h4>
				<button type="button" class="button button-secondary" id="fmc-copy-oauth-request"><?php esc_html_e( 'Copy to clipboard', 'flexmls-idx' ); ?></button>
			</div>
			<div class="fmc-oauth-request-box-body">
				<p><?php
					printf(
						/* translators: %s: support email mailto link */
						wp_kses(
							__( 'Email %s with the OAuth Redirect URI and your plugin credential key from the Credentials page. Copy the message below and send it as-is.', 'flexmls-idx' ),
							array( 'a' => array( 'href' => array() ) )
						),
						'<a href="mailto:idxsupport@flexmls.com">idxsupport@flexmls.com</a>'
					);
				?></p>
				<pre id="fmc-oauth-request-text" class="fmc-oauth-request-text"><?php echo esc_html( $oauth_request_text ); ?></pre>
			</div>
		</div>
		<p class="fmc-oauth-enter-cta">
			<button type="button" class="button button-primary" id="fmc-show-oauth-fields"><?php esc_html_e( 'I already have OAuth credentials', 'flexmls-idx' ); ?></button>
		</p>
		<table class="form-table" id="fmc-oauth-apply-fields" hidden>
			<tbody>
				<tr>
					<th scope="row">
						<label for="oauth_key"><?php esc_html_e( 'OAuth Client ID/Key', 'flexmls-idx' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" id="oauth_key" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" value="">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oauth_secret"><?php esc_html_e( 'OAuth Client Secret', 'flexmls-idx' ); ?></label>
					</th>
					<td>
						<input type="password" class="regular-text" id="oauth_secret" autocomplete="new-password" value="">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oauth_redirect_uri"><?php esc_html_e( 'OAuth Redirect URI', 'flexmls-idx' ); ?></label>
					</th>
					<td>
						<input type="text" class="fmc-oauth-redirect-uri" id="oauth_redirect_uri" size="<?php echo esc_attr( strlen( $redirect_uri ) ); ?>" style="width: <?php echo esc_attr( ( strlen( $redirect_uri ) + 1 ) ); ?>ch;" value="<?php echo esc_attr( $redirect_uri ); ?>" readonly="readonly" onclick="this.focus();this.select();">
					</td>
				</tr>
			</tbody>
		</table>
	<?php else: ?>
		<p><?php esc_html_e( 'OAuth credentials allow your portal leads to sign into their accounts in the plugin widgets securely.', 'flexmls-idx' ); ?></p>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="oauth_key"><?php esc_html_e( 'OAuth Client ID/Key', 'flexmls-idx' ); ?></label>
					</th>
					<td class="fmc-credentials-key-cell">
						<span class="fmc-credentials-input-wrap">
							<input type="text" class="regular-text" name="fmc_settings[oauth_key]" id="oauth_key" value="<?php echo esc_attr( $oauth_key ); ?>" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" readonly>
							<button type="button" class="fmc-credentials-lock-btn button button-secondary" id="fmc-oauth-lock-btn" title="<?php esc_attr_e( 'Click to unlock and edit credentials', 'flexmls-idx' ); ?>" aria-label="<?php esc_attr_e( 'Unlock to edit', 'flexmls-idx' ); ?>">
								<span class="dashicons dashicons-lock"></span>
							</button>
						</span>
					</td>
				</tr>
				<tr class="fmc-credentials-secret-row" style="display:none;">
					<th scope="row">
						<label for="oauth_secret"><?php esc_html_e( 'OAuth Client Secret', 'flexmls-idx' ); ?></label>
					</th>
					<td>
						<?php /* When locked we do not output the secret to the page; backend preserves it when POST has no secret. */ ?>
						<input type="password" class="regular-text" id="oauth_secret" value="" placeholder="<?php esc_attr_e( 'Enter new secret to change', 'flexmls-idx' ); ?>" autocomplete="new-password" style="display:none;">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="oauth_redirect_uri"><?php esc_html_e( 'OAuth Redirect URI', 'flexmls-idx' ); ?></label>
					</th>
					<td>
						<input type="text" class="fmc-oauth-redirect-uri" id="oauth_redirect_uri" size="<?php echo esc_attr( strlen( $redirect_uri ) ); ?>" style="width: <?php echo esc_attr( ( strlen( $redirect_uri ) + 1 ) ); ?>ch;" value="<?php echo esc_attr( $redirect_uri ); ?>" readonly="readonly" onclick="this.focus();this.select();">
					</td>
				</tr>
			</tbody>
		</table>
	<?php endif; ?>

	<h3>Portal Registration Popup</h3>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="portal_carts">Enable Listing Carts</label>
				</th>
				<td>
					<p>
						<label for="portal_carts"><input type="checkbox" name="fmc_settings[portal_carts]" id="portal_carts" value="1" <?php checked( $fmc_settings[ 'portal_carts' ], 1 ); ?>> Enable favorites and rejects on search results and detail pages</label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="portal_carts">Enable Saving Searches</label>
				</th>
				<td>
					<p>
						<?php
						$portal_saving_searches = isset( $fmc_settings[ 'portal_saving_searches' ] ) ? $fmc_settings[ 'portal_saving_searches' ] : false;
						?>
						<label for="portal_saving_searches"><input type="checkbox" name="fmc_settings[portal_saving_searches]" id="portal_saving_searches" value="1" <?php checked( $portal_saving_searches, 1 ); ?>> Enable saving searches on search results pages</label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label>Where To Show</label>
				</th>
				<td>
					<p><label for="portal_search"><input type="checkbox" name="fmc_settings[portal_search]" id="portal_search" value="1" <?php checked( $fmc_settings[ 'portal_search' ], 1 ); ?>> On search results pages</label></p>
					<p><label for="portal_listing"><input type="checkbox" name="fmc_settings[portal_listing]" id="portal_listing" value="1" <?php checked( $fmc_settings[ 'portal_listing' ], 1 ); ?>> On listing details pages</label></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label>When To Show</label>
				</th>
				<td>
					<p><label for="portal_mins">After <input type="number" class="small-text" name="fmc_settings[portal_mins]" id="portal_mins" value="<?php echo $fmc_settings[ 'portal_mins' ]; ?>"> minute(s) have passed</label></p>
					<p><label for="detail_page">After <input type="number" class="small-text" name="fmc_settings[detail_page]" id="detail_page" value="<?php echo $fmc_settings[ 'detail_page' ]; ?>"> listing details have been viewed</label></p>
					<p><label for="search_page">After <input type="number" class="small-text" name="fmc_settings[search_page]" id="search_page" value="<?php echo $fmc_settings[ 'search_page' ]; ?>"> listing summary pages have been viewed</label></p>
					<p><label for="portal_force"><input type="checkbox" name="fmc_settings[portal_force]" id="portal_force" value="1" <?php checked( $fmc_settings[ 'portal_force' ], 1 ); ?>> Force users to register/log in?</label></p>
					<div id="fmc-portal-snooze-setting" <?php echo $fmc_settings[ 'portal_force' ] ? 'hidden' : ''; ?>>
						<p>
							<label for="portal_snooze_amount" title="<?php esc_attr_e( 'When a visitor clicks Not Now, hide the popup for this duration. Page-view and time counters reset and start over after the snooze ends.', 'flexmls-idx' ); ?>">After &ldquo;Not Now&rdquo;, don&rsquo;t show again for</label>
							<input type="number" class="small-text" name="fmc_settings[portal_snooze_amount]" id="portal_snooze_amount" min="1" step="1" value="<?php echo esc_attr( $portal_snooze_amount ); ?>">
							<select name="fmc_settings[portal_snooze_unit]" id="portal_snooze_unit" aria-label="<?php esc_attr_e( 'Snooze duration unit', 'flexmls-idx' ); ?>">
								<?php foreach ( $portal_snooze_units as $unit_key => $unit_label ) : ?>
									<option value="<?php echo esc_attr( $unit_key ); ?>" <?php selected( $portal_snooze_unit, $unit_key ); ?>><?php echo esc_html( $unit_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p class="description"><?php esc_html_e( 'When a visitor clicks Not Now, the popup stays hidden for this duration. After it ends, when-to-show counters start over.', 'flexmls-idx' ); ?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label>Location On Page</label>
				</th>
				<td>
					<p>
						<select name="fmc_settings[portal_position_x]" id="portal_position_x">
							<option value="left" <?php selected( $fmc_settings[ 'portal_position_x' ], 'left' ); ?>>Left</option>
							<option value="center" <?php selected( $fmc_settings[ 'portal_position_x' ], 'center' ); ?>>Center</option>
							<option value="right" <?php selected( $fmc_settings[ 'portal_position_x' ], 'right' ); ?>>Right</option>
						</select>
						<label for="portal_position_x">Horizontal Position</label>
					</p>
					<p>
						<select name="fmc_settings[portal_position_y]" id="portal_position_y">
							<option value="top" <?php selected( $fmc_settings[ 'portal_position_y' ], 'top' ); ?>>Top</option>
							<option value="center" <?php selected( $fmc_settings[ 'portal_position_y' ], 'center' ); ?>>Center</option>
							<option value="bottom" <?php selected( $fmc_settings[ 'portal_position_y' ], 'bottom' ); ?>>Bottom</option>
						</select>
						<label for="portal_position_y">Vertical Position</label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					Portal Registration Text
				</th>
				<td>
					<?php
					remove_filter( 'mce_buttons', array('flexmlsConnect', 'filter_mce_button' ) );
					remove_filter( 'mce_external_plugins', array('flexmlsConnect', 'filter_mce_plugin' ) );
					wp_editor( $fmc_settings[ 'portal_text' ], 'fmc_portal_text_field', array(
						'media_buttons' => false,
						'textarea_name' => 'fmc_settings[portal_text]'
					) );
					?>
				</td>
			</tr>
			<tr>
                <th scope="row">
                    <h3>Contact Settings:</h3>
                    Contact Disclaimer (Optional):
                </th>
                 <td>
                        <label>If your Contact Manager requires your website to apply a disclaimer to your forms, you can apply it in the text field below. The applied text will be wrapped in <code> &lt;small&gt;</code> HTML tag</label></p>
                    <?php

					$fmc_settings[ 'contact_disclaimer' ] = (isset($fmc_settings[ 'contact_disclaimer' ])) ? $fmc_settings[ 'contact_disclaimer' ] : '';

                    remove_filter( 'mce_buttons', array('flexmlsConnect', 'filter_mce_button' ) );
                    remove_filter( 'mce_external_plugins', array('flexmlsConnect', 'filter_mce_plugin' ) );
                    wp_editor( $fmc_settings[ 'contact_disclaimer' ], 'fmc_contact_disclaimer_field', array(
                        'media_buttons' => false,
                        'textarea_name' => 'fmc_settings[contact_disclaimer]'
                    ) );
                    ?>
                    </td>
            </tr>
		</tbody>
	</table>
	<?php \FlexMLS\Admin\Settings::floating_save_button( 'update_fmc_portal_action', 'update_fmc_portal_nonce', 'Save Portal Settings' ); ?>
</form>
<script>
(function() {
	var force = document.getElementById('portal_force');
	var snooze = document.getElementById('fmc-portal-snooze-setting');
	if (!force || !snooze) return;
	function syncSnoozeVisibility() {
		snooze.hidden = !!force.checked;
	}
	force.addEventListener('change', syncSnoozeVisibility);
	syncSnoozeVisibility();
})();
</script>
<?php if ( $auth_token && ! $has_oauth ): ?>
<script>
(function() {
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

	var btn = document.getElementById('fmc-copy-oauth-request');
	var content = document.getElementById('fmc-oauth-request-text');
	if (btn && content) {
		btn.addEventListener('click', function() {
			var text = content.innerText || content.textContent || '';
			if (!text) return;
			var done = function() {
				btn.textContent = '<?php echo esc_js( __( 'Copied!', 'flexmls-idx' ) ); ?>';
				setTimeout(function() { btn.textContent = '<?php echo esc_js( __( 'Copy to clipboard', 'flexmls-idx' ) ); ?>'; }, 2000);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done).catch(function() {
					fallbackCopy(text, done);
				});
			} else {
				fallbackCopy(text, done);
			}
		});
	}

	var showBtn = document.getElementById('fmc-show-oauth-fields');
	var fields = document.getElementById('fmc-oauth-apply-fields');
	var cta = document.querySelector('.fmc-oauth-enter-cta');
	var keyInput = document.getElementById('oauth_key');
	var secretInput = document.getElementById('oauth_secret');
	if (showBtn && fields) {
		showBtn.addEventListener('click', function() {
			fields.hidden = false;
			if (cta) {
				cta.style.display = 'none';
			}
			if (keyInput) {
				keyInput.setAttribute('name', 'fmc_settings[oauth_key]');
			}
			if (secretInput) {
				secretInput.setAttribute('name', 'fmc_settings[oauth_secret]');
			}
		});
	}
})();
</script>
<?php endif; ?>
<?php if ( $has_oauth ): ?>
<script>
(function() {
	var form = document.getElementById('fmc-oauth-credentials-form');
	var lockBtn = document.getElementById('fmc-oauth-lock-btn');
	var secretRow = form && form.querySelector('.fmc-credentials-secret-row');
	var keyInput = document.getElementById('oauth_key');
	var secretInput = document.getElementById('oauth_secret');
	var icon = lockBtn && lockBtn.querySelector('.dashicons');
	if (!form || !lockBtn || !secretRow) return;
	function unlock() {
		form.classList.remove('fmc-credentials-locked');
		keyInput.removeAttribute('readonly');
		keyInput.setAttribute('autocomplete', 'one-time-code');
		secretRow.style.display = '';
		if (secretInput) {
			secretInput.setAttribute('name', 'fmc_settings[oauth_secret]');
			secretInput.style.display = '';
			secretInput.removeAttribute('required');
		}
		icon.classList.remove('dashicons-lock');
		icon.classList.add('dashicons-unlock');
		lockBtn.title = '<?php echo esc_js( __( 'Credentials unlocked for editing', 'flexmls-idx' ) ); ?>';
		lockBtn.setAttribute('aria-label', '<?php echo esc_js( __( 'Lock credentials', 'flexmls-idx' ) ); ?>');
	}
	function lock() {
		form.classList.add('fmc-credentials-locked');
		keyInput.setAttribute('readonly', 'readonly');
		secretRow.style.display = 'none';
		if (secretInput) {
			secretInput.style.display = 'none';
			secretInput.removeAttribute('name');
			secretInput.removeAttribute('required');
			secretInput.value = '';
		}
		icon.classList.remove('dashicons-unlock');
		icon.classList.add('dashicons-lock');
		lockBtn.title = '<?php echo esc_js( __( 'Click to unlock and edit credentials', 'flexmls-idx' ) ); ?>';
		lockBtn.setAttribute('aria-label', '<?php echo esc_js( __( 'Unlock to edit', 'flexmls-idx' ) ); ?>');
	}
	lockBtn.addEventListener('click', function() {
		if (form.classList.contains('fmc-credentials-locked')) {
			unlock();
		} else {
			lock();
		}
	});
})();
</script>
<?php endif; ?>
