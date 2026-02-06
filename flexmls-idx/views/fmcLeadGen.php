<?php echo $before_widget; ?>

<?php 
  if ( !empty($title) ) {
    echo $before_title;
    echo $title;
    echo $after_title;
  }

  if ( !empty($blurb) ) {
    echo "<p>" . $blurb . "</p>";
  }

?>

<form data-connect-validate='true' data-connect-ajax='true' action='<?php echo admin_url('admin-ajax.php'); ?>'>
  
  <?php // This is the spam test field. It's hidden with css. ?>
  <input type="text" name="flexmls_connect__important" id="flexmls_connect__important" tabindex="1000"/>

  <input type='hidden' name='action' value='fmcLeadGen_submit' />
  <input type='hidden' name='nonce' value='<?php echo wp_create_nonce('fmcLeadGen'); ?>' />
  <input type='hidden' name='callback' value='?' />
  <input type='hidden' name='success-message' value='<?php echo htmlspecialchars($success, ENT_QUOTES); ?>' />

  <div class="flexmls_connect__form_row">
    <input class="flexmls_connect__form_input" type='text' name='name' id="name" 
      placeholder="Your Name" aria-label="Your Name"
      data-connect-default='Your Name' data-connect-validate='text' />
  </div>
  
  <div class="flexmls_connect__form_row">
    <input class="flexmls_connect__form_input" type='text' name='email' id="email" 
      placeholder="Email Address" aria-label="Email Address"
      data-connect-default='Email Address' data-connect-validate='email' />
  </div>

  <?php if ( in_array('address', $api_prefs['RequiredFields']) ) { ?>
    <div class="flexmls_connect__form_row">
      <input class="flexmls_connect__form_input" type='text' name='address' id="address" 
        placeholder="Home Address" aria-label="Home Address"
        data-connect-default='Home Address' data-connect-validate='text' />
    </div>
  <?php } ?>

  <?php if ( in_array('address', $api_prefs['RequiredFields']) ) { ?>
    <div class="flexmls_connect__form_row">
      <input class="flexmls_connect__form_input" type='text' name='city' id="city" 
        placeholder="City" aria-label="City"
        data-connect-default='City' data-connect-validate='text' />
    </div>
  <?php } ?>

  <?php if ( in_array('address', $api_prefs['RequiredFields']) ) { ?>
    <div class="flexmls_connect__form_row">
      <input class="flexmls_connect__form_input" type='text' name='state' id="state" 
        placeholder="State" aria-label="State"
        data-connect-default='State' data-connect-validate='text' />
    </div>
  <?php } ?>

  <?php if ( in_array('address', $api_prefs['RequiredFields']) ) { ?>
    <div class="flexmls_connect__form_row">
      <input class="flexmls_connect__form_input" type='text' name='zip' id="zip" 
        placeholder="Zip Code" aria-label="Zip Code"
        data-connect-default='Zip Code' data-connect-validate='text' />
    </div>
  <?php } ?>

  <?php if ( in_array('phone', $api_prefs['RequiredFields']) ) { ?>
    <div class="flexmls_connect__form_row">
      <input class="flexmls_connect__form_input" type='text' name='phone' id="phone" 
        placeholder="Phone Number" aria-label="Phone Number"
        data-connect-default='Phone Number' data-connect-validate='phone' />
    </div>
  <?php } ?>
    
  <div class="flexmls_connect__form_row">
    <textarea class="flexmls_connect__form_textarea" name='message_body' id="message" 
      placeholder="Your Message" aria-label="Your Message"
      data-connect-default='Your Message' data-connect-validate='text' rows='5'></textarea>
  </div>

  <?php 
    if ( $use_captcha ) { 
      $a = rand(1, 10); $b = rand(1, 10); $sum = $a + $b;
    ?>
    <div class="flexmls_connect__form_row">
      <label for="captcha" class="flexmls_connect__form_label">
        What is <?php echo $a; ?> + <?php echo $b; ?>?</label>
      <input type="hidden" name="captcha-answer" value="<?php echo $sum; ?>" />
      <input class="flexmls_connect__form_input flexmls-captcha-input" type='text' name='captcha' id="captcha" 
        data-connect-validate='captcha' />
      <span class="flexmls-captcha-hint">Hint: It's <?php echo $sum; ?></span>
    </div>
  <?php } ?>

  <input class="flexmls_connect__form_submit" type='submit' value='<?php echo $buttontext; ?>' />

</form>

<?php echo $after_widget; ?>
