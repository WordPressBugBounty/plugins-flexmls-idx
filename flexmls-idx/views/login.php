<?php
global $fmc_api;

$page = new flexmlsConnectPageCore( $fmc_api );
$page->render_template_styles();

$fmc_settings = get_option( 'fmc_settings' );

$flexmls_button_background_color = ( isset( $fmc_settings['search_listing_template_primary_color'] ) ) ? 'flexmls-primary-color-background' : 'flexmls_connect__button' ;
?>
<div class="dialog flexmls_log_in__custom" style="padding:15px;border-width:1px;border-style:solid;border-bottom:1px solid;text-align:center;">
<h6>Create A Real Estate Portal</h6>
</div>
<div style="margin-bottom:10px;border-width:1px;border-style:solid;padding:15px;text-align:center;">
  <?php echo $portal_text; ?>
  <div>
    <div class="flexmls_dialog__buttons" style="margin-top:15px;">
    <button class='flexmls_connect__page_content portal-button-primary <?php echo $flexmls_button_background_color; ?>' 
        href='<?php echo $login_link; ?>'> Log In </button>
      
      <button class='flexmls_connect__page_content portal-button-primary <?php echo $flexmls_button_background_color; ?>' 
        href='<?php echo $signup_link ?>'> Sign Up </button>
    </div>
  </div>
</div>