<?php
class Location_Control extends \Elementor\Base_Data_Control {

	public function get_type() {
        return 'location_control';
    }

    public function enqueue() {
		// Styles
		wp_register_style( 'location', plugins_url('../styles/location-control.css', __FILE__), [], '1.0.0' );
		wp_enqueue_style( 'location' );

		// Scripts (depends on admin bundle for window.AdminLocationSearch)
		wp_register_script( 'location-control', plugins_url('../scripts/location-control.js', __FILE__), [ 'jquery', 'flexmls_admin_script_' ], '1.0.1', true );
		wp_enqueue_script( 'location-control' );
	}


	public function content_template() {
        $control_uid = $this->get_control_uid('location');
        ?>

        <div class="elementor-control-field-location">
            <div class="flexmls_connect_label_wrapper">
                <label for="<?php echo esc_attr( $control_uid ); ?>" class="elementor-control-title">{{{ data.label }}}</label>
                <button type="button" class="flexmls_connect__location_button_apply">Apply</button>
            </div>
            <div>
            <# if(data.multiple) { #>
            <select 
                class="flexmlsAdminLocationSearch" 
                type="hidden" 
                id="<?php echo $control_uid; ?>" 
                name="fmc_shortcode_field_{{data.name}}" 
                data-portal-slug="{{data.field_slug}}" 
                style="height: 110px;" 
                multiple="" 
            ></select>
            <# } else { #>
            <select 
                class="flexmlsAdminLocationSearch" 
                type="hidden" 
                id="<?php echo $control_uid; ?>" 
                name="fmc_shortcode_field_{{data.name}}" 
                data-portal-slug="{{data.field_slug}}" 
                style="width: 100%;" 
            ></select>
            <# } #>

            <input fmc-field="{{data.name}}" fmc-type='text' type='text' value="" data-setting="{{ data.name }}" class='flexmls_connect__location_fields'/>
            </div>
        </div>
        <?php
    }

    protected function get_default_settings() {
		return [
            'multiple' => false,
            'field_slug' => '',
		];
	}

	/* public function enqueue() {}

	protected function get_default_settings() {}

	public function get_default_value() {}

	public function get_value() {}

	public function get_style_value() {} */
}