<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

$fmc_settings = get_option( 'fmc_settings' );
$SparkAPI = new \SparkAPI\Core();
$auth_token = $SparkAPI->generate_auth_token();

updateUserOptions($auth_token);

?>
<h3><?php echo $auth_token ? 'Your Key & Secret:' : 'Activate Your Key & Secret'; ?><?php if( $auth_token ): ?> <span class="fmc-admin-badge fmc-admin-badge-success">Connected</span><?php endif; ?></h3>
<?php if ( ! $auth_token ): ?>
<p>Enter your Flexmls&reg; Key & Secret credentials below to connect your website, then click Save Credentials. If entered correctly, you will see a green button above that says Connected:</p>
<?php endif; ?>
<form action="<?php echo admin_url( 'admin.php?page=fmc_admin_intro&tab=api' ); ?>" method="post" id="fmc-credentials-form" class="<?php echo $auth_token ? 'fmc-credentials-locked' : ''; ?>">
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="api_key">Key</label>
				</th>
				<td class="fmc-credentials-key-cell">
					<span class="fmc-credentials-input-wrap">
						<input type="text" class="regular-text" name="fmc_settings[api_key]" id="api_key" value="<?php echo esc_attr($fmc_settings[ 'api_key' ]); ?>" autocomplete="off" <?php echo $auth_token ? 'readonly' : ''; ?> required>
						<?php if ( $auth_token ): ?>
						<button type="button" class="fmc-credentials-lock-btn button button-secondary" id="fmc-credentials-lock-btn" title="<?php esc_attr_e( 'Click to unlock and edit credentials', 'flexmls-idx' ); ?>" aria-label="<?php esc_attr_e( 'Unlock to edit', 'flexmls-idx' ); ?>">
							<span class="dashicons dashicons-lock"></span>
						</button>
						<?php endif; ?>
					</span>
				</td>
			</tr>
			<tr class="fmc-credentials-secret-row" <?php echo $auth_token ? 'style="display:none;"' : ''; ?>>
				<th scope="row">
					<label for="api_secret">Secret</label>
				</th>
				<td>
					<?php if ( $auth_token ): ?>
					<?php /* When locked we do not output the secret to the page; backend preserves it when POST has no secret. */ ?>
					<input type="password" class="regular-text" id="api_secret" value="" placeholder="<?php esc_attr_e( 'Enter new secret to change', 'flexmls-idx' ); ?>" autocomplete="new-password" style="display:none;">
					<?php else: ?>
					<input type="password" class="regular-text" name="fmc_settings[api_secret]" id="api_secret" value="<?php echo esc_attr($fmc_settings[ 'api_secret' ]); ?>" autocomplete="off" required>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<p><?php wp_nonce_field( 'update_api_credentials_action', 'update_api_credentials_nonce' ); ?><button type="submit" class="button-primary">Save Credentials</button></p>
</form>
<?php if ( ! $auth_token ): ?>
<hr />
<div class="key-content">
	<h3>Don't have a Key & Secret?</h3>
	<p>Fill out this <a href="https://fbsproducts.com/form/wordpress-plugin-secret-key-request/" target="_blank">quick form</a> or call 866-320-9977 to talk with an IDX Specialist.</p>
</div>
<?php endif; ?>
<?php if ( $auth_token ): ?>
<script>
(function() {
	var form = document.getElementById('fmc-credentials-form');
	var lockBtn = document.getElementById('fmc-credentials-lock-btn');
	var secretRow = form && form.querySelector('.fmc-credentials-secret-row');
	var keyInput = document.getElementById('api_key');
	var secretInput = document.getElementById('api_secret');
	var icon = lockBtn && lockBtn.querySelector('.dashicons');
	if (!form || !lockBtn || !secretRow) return;
	function unlock() {
		form.classList.remove('fmc-credentials-locked');
		keyInput.removeAttribute('readonly');
		keyInput.setAttribute('autocomplete', 'off');
		secretRow.style.display = '';
		if (secretInput) {
			secretInput.setAttribute('name', 'fmc_settings[api_secret]');
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
