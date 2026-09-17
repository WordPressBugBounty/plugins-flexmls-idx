<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

$fmc_settings = get_option( 'fmc_settings' );
if ( ! is_array( $fmc_settings ) ) {
	$fmc_settings = array();
}
$SparkAPI = new \SparkAPI\Core();
$auth_token = $SparkAPI->generate_auth_token();

updateUserOptions($auth_token);

// This page is registered with `edit_posts` so authors can read the widget
// instructions below. Credentials are administrator-only: users without
// manage_options must not see the key, Connected badge, or receive a save nonce.
$fmc_can_manage_credentials = current_user_can( \FlexMLS\Admin\Settings::SETTINGS_CAPABILITY );

?>
<?php if ( $fmc_can_manage_credentials ): ?>
<h3><?php echo $auth_token ? 'Your Key & Secret:' : 'Activate Your Key & Secret'; ?><?php if( $auth_token ): ?> <span class="fmc-admin-badge fmc-admin-badge-success">Connected</span><?php endif; ?></h3>
<?php if ( ! $auth_token ): ?>
<p>Enter your Flexmls&reg; Key & Secret credentials below to connect your website, then click Save Credentials. If entered correctly, you will see a green button above that says Connected:</p>
<?php endif; ?>
<form action="<?php echo admin_url( 'admin.php?page=fmc_admin_intro&tab=api' ); ?>" method="post" id="fmc-credentials-form" class="<?php echo $auth_token ? 'fmc-credentials-locked' : ''; ?>" autocomplete="off">
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="api_key">Key</label>
				</th>
				<td class="fmc-credentials-key-cell">
					<span class="fmc-credentials-input-wrap">
						<input type="text" class="regular-text" name="fmc_settings[api_key]" id="api_key" value="<?php echo esc_attr( isset( $fmc_settings[ 'api_key' ] ) ? $fmc_settings[ 'api_key' ] : '' ); ?>" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" <?php echo $auth_token ? 'readonly' : ''; ?> required>
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
					<input type="password" class="regular-text" name="fmc_settings[api_secret]" id="api_secret" value="" placeholder="<?php esc_attr_e( 'Enter your secret', 'flexmls-idx' ); ?>" autocomplete="new-password" required>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<p><?php wp_nonce_field( 'update_api_credentials_action', 'update_api_credentials_nonce' ); ?><button type="submit" class="button-primary">Save Credentials</button></p>
</form>
<hr />
<?php elseif ( ! $auth_token ): ?>
<p>This site is not yet connected to Flexmls&reg;. Please ask a site administrator to enter the Key &amp; Secret on this page, or visit the <a href="<?php echo esc_url( admin_url( 'admin.php?page=fmc_admin_intro&tab=support' ) ); ?>">Support</a> tab for help.</p>
<?php else: ?>
<p>Add a Flexmls&reg; widget to a page using the instructions below.</p>
<hr />
<?php endif; ?>
<?php if ( $auth_token ): ?>
<?php
$active_plugin_files = get_option( 'active_plugins', array() );
if ( is_multisite() ) {
	$network_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
	if ( ! empty( $network_active_plugins ) ) {
		$active_plugin_files = array_merge( $active_plugin_files, array_keys( $network_active_plugins ) );
	}
}
$active_plugin_files = array_values( array_unique( $active_plugin_files ) );

$has_elementor = in_array( 'elementor/elementor.php', $active_plugin_files, true );
$has_wpbakery = in_array( 'js_composer/js_composer.php', $active_plugin_files, true );
$has_classic_editor = in_array( 'classic-editor/classic-editor.php', $active_plugin_files, true );

$other_builder_plugins = array(
	'divi-builder/divi-builder.php',
	'beaver-builder-lite-version/fl-builder.php',
	'siteorigin-panels/siteorigin-panels.php',
	'visualcomposer/plugin-wordpress.php',
	'brizy/brizy.php',
	'oxygen/functions.php',
	'breakdance/plugin.php',
);
$has_other_builder = ! empty( array_intersect( $active_plugin_files, $other_builder_plugins ) );

$active_theme = wp_get_theme();
$theme_name = strtolower( (string) $active_theme->get( 'Name' ) );
$theme_template = strtolower( (string) $active_theme->get( 'Template' ) );
if ( false !== strpos( $theme_name, 'divi' ) || false !== strpos( $theme_template, 'divi' ) ) {
	$has_other_builder = true;
}
$show_other_tab = $has_other_builder || ( ! $has_elementor && ! $has_wpbakery && ! $has_classic_editor );
$instructions_img_dir = FMC_PLUGIN_DIR . 'assets/images/admin/instructions/';
$instructions_img_base = plugins_url( 'assets/images/admin/instructions/', FMC_PLUGIN_DIR . 'flexmls_connect.php' );
$instruction_image_url = function( $filename ) use ( $instructions_img_dir, $instructions_img_base ) {
	if ( file_exists( $instructions_img_dir . $filename ) ) {
		return $instructions_img_base . $filename;
	}
	return '';
};
?>
<div class="fmc-connected-next-steps">
	<h3>Add Your Flexmls Widget to a Page</h3>
	<p>Need more details? Visit our <a href="https://fbsidx.com/help/plugin" target="_blank" rel="noopener noreferrer">Plugin Documentation Help Center</a>.</p>

	<h2 class="nav-tab-wrapper wp-clearfix" id="fmc-builder-tabs">
		<a href="#" class="nav-tab nav-tab-active" data-fmc-tab="gutenberg">Gutenberg (WP Default Editor)</a>
		<?php if ( $has_elementor ): ?>
			<a href="#" class="nav-tab" data-fmc-tab="elementor">Elementor</a>
		<?php endif; ?>
		<?php if ( $has_wpbakery ): ?>
			<a href="#" class="nav-tab" data-fmc-tab="wpbakery">WPBakery</a>
		<?php endif; ?>
		<?php if ( $has_classic_editor ): ?>
			<a href="#" class="nav-tab" data-fmc-tab="classic">Classic Editor</a>
		<?php endif; ?>
		<?php if ( $show_other_tab ): ?>
			<a href="#" class="nav-tab" data-fmc-tab="other">Other</a>
		<?php endif; ?>
	</h2>

	<div class="fmc-builder-tab-panels">
		<div class="fmc-builder-panel is-active" data-fmc-panel="gutenberg">
			<ol>
				<li>
					Edit or create a page or post, then click the <em>+</em> button to add a block.
					<?php $img = $instruction_image_url( 'gutenberg-add-block.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block fmc-shot-block-small">
							<img src="<?php echo esc_url( $img ); ?>" alt="Click Add Block button in Gutenberg">
						</figure>
					<?php endif; ?>
				</li>
				<li>
					Search for and select a Flexmls widget block (IDX Search, IDX Listing Summary, or IDX Slideshow).
					<?php $img = $instruction_image_url( 'gutenberg-search-widget-blocks.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block">
							<img src="<?php echo esc_url( $img ); ?>" alt="Search for Flexmls blocks in Gutenberg block picker">
						</figure>
					<?php endif; ?>
				</li>
				<li>Configure the widget settings in the block sidebar and publish or update your page.</li>
			</ol>
		</div>

		<?php if ( $has_elementor ): ?>
		<div class="fmc-builder-panel" data-fmc-panel="elementor">
			<ol>
				<li>
					Edit your page with Elementor.
					<?php $img = $instruction_image_url( 'elementor-edit-with.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block fmc-shot-block-small">
							<img src="<?php echo esc_url( $img ); ?>" alt="Edit with Elementor button">
						</figure>
					<?php endif; ?>
				</li>
				<li>
					Search for Flexmls widgets in the Elementor panel.
					<?php $img = $instruction_image_url( 'elementor-search-widget-blocks.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block">
							<img src="<?php echo esc_url( $img ); ?>" alt="Search for Flexmls widgets in Elementor panel">
						</figure>
					<?php endif; ?>
				</li>
				<li>Drag in IDX Search, IDX Listing Summary, or IDX Slideshow, configure, then update the page.</li>
			</ol>
		</div>
		<?php endif; ?>

		<?php if ( $has_wpbakery ): ?>
		<div class="fmc-builder-panel" data-fmc-panel="wpbakery">
			<ol>
				<li>
					Edit your page with WPBakery.
					<?php $img = $instruction_image_url( 'wpbakery-edit-with.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block fmc-shot-block-small">
							<img src="<?php echo esc_url( $img ); ?>" alt="WPBakery Page Builder button">
						</figure>
					<?php endif; ?>
				</li>
				<li>
					Click <em>Add Element</em> and search for Flexmls widgets.
					<?php $img = $instruction_image_url( 'wpbakery-add-element.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block">
							<img src="<?php echo esc_url( $img ); ?>" alt="WPBakery add element screen with Flexmls tab">
						</figure>
					<?php endif; ?>
				</li>
				<li>
					Choose IDX Search, IDX Listing Summary, or IDX Slideshow, configure, then save and update.
					<?php $img = $instruction_image_url( 'wpbakery-flexmls-add-widget.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block">
							<img src="<?php echo esc_url( $img ); ?>" alt="Select a Flexmls widget in WPBakery">
						</figure>
					<?php endif; ?>
				</li>
			</ol>
		</div>
		<?php endif; ?>

		<?php if ( $has_classic_editor ): ?>
		<div class="fmc-builder-panel" data-fmc-panel="classic">
			<ol>
				<li>
					Open the page in Classic Editor and place your cursor where the widget should appear.
					<?php $img = $instruction_image_url( 'classic-flexmls-shortcode-generator.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block">
							<img src="<?php echo esc_url( $img ); ?>" alt="Flexmls shortcode generator button in Classic Editor">
						</figure>
					<?php endif; ?>
				</li>
				<li>Click the Flexmls shortcode generator button in the editor toolbar.</li>
				<li>Select the widget, configure options, insert the shortcode, then save or publish.</li>
			</ol>
		</div>
		<?php endif; ?>

		<?php if ( $show_other_tab ): ?>
		<div class="fmc-builder-panel" data-fmc-panel="other">
			<ol>
				<li>
					Open a page in the Gutenberg editor and add a <em>Classic</em> block.
					<?php $img = $instruction_image_url( 'classic-search-classic-block.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block">
							<img src="<?php echo esc_url( $img ); ?>" alt="Search and insert the Classic block in Gutenberg">
						</figure>
					<?php endif; ?>
				</li>
				<li>
					Use the Flexmls shortcode generator from the Classic toolbar to build your shortcode.
					<?php $img = $instruction_image_url( 'classic-flexmls-shortcode-generator.png' ); if ( $img ): ?>
						<figure class="fmc-shot-block">
							<img src="<?php echo esc_url( $img ); ?>" alt="Flexmls shortcode generator in Classic block toolbar">
						</figure>
					<?php endif; ?>
				</li>
				<li>Copy the generated shortcode.</li>
				<li>Paste it into your editor's shortcode/text/code block, then save or publish.</li>
			</ol>
		</div>
		<?php endif; ?>
	</div>

	<p>
		If you want users to run their own search, use the <strong>Flexmls: IDX Search</strong> widget.
		If you want to showcase selected listings, use <strong>IDX Listing Summary</strong> or <strong>IDX Slideshow</strong>.
		These can also use saved search IDX links from your Flexmls account's IDX Manager page.
	</p>
</div>

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
		keyInput.setAttribute('autocomplete', 'one-time-code');
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

	var tabsWrap = document.getElementById('fmc-builder-tabs');
	if (tabsWrap) {
		var tabs = tabsWrap.querySelectorAll('[data-fmc-tab]');
		var panels = document.querySelectorAll('.fmc-builder-panel[data-fmc-panel]');

		function activateTab(tabKey) {
			tabs.forEach(function(tab) {
				tab.classList.toggle('nav-tab-active', tab.getAttribute('data-fmc-tab') === tabKey);
			});
			panels.forEach(function(panel) {
				panel.classList.toggle('is-active', panel.getAttribute('data-fmc-panel') === tabKey);
			});
		}

		tabs.forEach(function(tab) {
			tab.addEventListener('click', function(event) {
				event.preventDefault();
				activateTab(tab.getAttribute('data-fmc-tab'));
			});
		});
	}
})();
</script>
<style>
.fmc-connected-next-steps {
	margin-top: 16px;
}
.fmc-builder-tab-panels {
	background: #fff;
	border: 1px solid #dcdcde;
	border-top: none;
	padding: 12px 16px 2px;
}
.fmc-builder-panel {
	display: none;
}
.fmc-builder-panel.is-active {
	display: block;
}
.fmc-step-inline {
	display: list-item;
}
.fmc-step-inline-wrap {
	display: flex;
	align-items: center;
	gap: 12px;
}
.fmc-step-inline-wrap > span {
	flex: 1;
	min-width: 0;
}
.fmc-shot-inline {
	flex-shrink: 0;
	max-height: 40px;
	max-width: 220px;
	width: auto;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	background: #fff;
	padding: 2px;
}
.fmc-shot-block {
	margin: 10px 0 0;
}
.fmc-shot-block-small img {
	max-height: 120px;
}
.fmc-shot-block img {
	display: block;
	max-width: 100%;
	max-height: 460px;
	height: auto;
	width: auto;
	border: 1px solid #dcdcde;
	border-radius: 4px;
}
</style>
<?php elseif ( $fmc_can_manage_credentials ): ?>
<div class="key-content">
	<h3>Don't have a Key & Secret?</h3>
	<p>Fill out this <a href="https://fbsproducts.com/form/wordpress-plugin-secret-key-request/" target="_blank">quick form</a> or call 866-320-9977 to talk with an IDX Specialist.</p>
</div>
<?php endif; ?>
