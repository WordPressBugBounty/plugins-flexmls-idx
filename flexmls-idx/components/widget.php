<?php


class fmcWidget extends WP_Widget {

	// holds the path for the view templates
	public $page_view;
	public $admin_page_view;
	public $admin_view_vars;

	public function __construct() {
		global $fmc_plugin_dir;

		// set the view template for the widget
		$class_name = get_class($this);
		$this->page_view = $fmc_plugin_dir . "/views/{$class_name}.php";
		$this->admin_page_view = $fmc_plugin_dir . "/views/admin/{$class_name}.php";

		$this->options = new Fmc_Settings;
	}


	function shortcode_form() {
		global $fmc_widgets;

		$widget_info = $fmc_widgets[ get_class($this) ];

		$settings_content = $this->settings_form( array('_instance_type' => 'shortcode') );

		$response = array(
				'title' => $widget_info['title'] .' widget',
				'body' => flexmlsConnect::shortcode_header() . $settings_content . flexmlsConnect::shortcode_footer()
		);

		echo flexmlsJSON::json_encode($response);

		exit;

	}


	function cache_jelly($args, $instance, $type) {
		global $fmc_widgets;

		$widget_info = $fmc_widgets[ get_class($this) ];

		$cache_item_name = md5(get_class($this) .'_'. serialize($instance) . $type);
		$cache = get_transient('fmc_cache_'. $cache_item_name);

		if (!empty($cache) && flexmlsConnect::cache_turned_on() == true) {
			$return = $cache;
		}
		else {
			$return = $this->jelly($args, $instance, $type);
			$transient_name = 'fmc_cache_' . $cache_item_name;
			$cache_set_result = set_transient($transient_name, $return, $widget_info['max_cache_time']);

			// update transient item which tracks cache items (existing system)
			$cache_tracker = get_transient('fmc_cache_tracker');
			if ( ! is_array( $cache_tracker ) ) {
				$cache_tracker = array();
			}
			$cache_tracker[ $cache_item_name ] = true;
			set_transient('fmc_cache_tracker', $cache_tracker, 60*60*24*7);

			// Also track in the new system for object cache compatibility
			$spark = new \SparkAPI\Core();
			$spark->track_transient( $transient_name, 'fmc_cache_' );
		}

		return $return;

	}

	// form for generating a widget. used in appearence > widgets. not used for shortcode forms
	function form($instance) {
		echo "<div class='flexmls-widget-settings'>";
		echo $this->settings_form($instance);
		echo "</div>";
	}

	function settings_form($instance) {
    $this->instance = $instance;
    $this->admin_view_vars = $this->admin_view_vars();
    return $this->render_admin_view();
  }

	function shortcode_generate() {
			flexmls_verify_ajax_nonce();
			$shortcode = $this->get_shortcode_string();
			$response = array(
					'body' => $shortcode
			);
		echo  flexmlsJSON::json_encode($response);
		wp_die();
	}

	function get_shortcode_string()
	{
			global $fmc_widgets;

			$widget_info = $fmc_widgets[ get_class($this) ];

			$shortcode = "[{$widget_info['shortcode']}";

			$is_service_lacking_filter_support = false;
			$shortcode_source = flexmlsConnect::wp_input_get_post('source');
			if ($shortcode_source != "location") {
					$is_service_lacking_filter_support = true;
			}
			$is_slideshow_widget = ($widget_info['shortcode'] == "idx_slideshow") ? true : false;

			$listing_summary_source = isset( $_REQUEST['source'] ) ? trim( (string) $_REQUEST['source'] ) : '';

			foreach ($_REQUEST as $k => $v) {
					if ( $k === 'action' || $k === 'nonce' || $k === 'fmc_render_token' || $k === 'agent_display' || $k === 'link_display' ) {
							continue;
					}

					if ($is_slideshow_widget && $is_service_lacking_filter_support && ($k == "property_type" || $k == "location")) {
							continue;
					}

					// ListAgentId only applies when "Filter by" is Specific agent.
					if ( $k === 'agent' && $listing_summary_source !== 'agent' ) {
							continue;
					}

					if ( $k === 'property_sub_type' && is_string( $v ) ) {
							$v = trim( $v, " ,\t\n\r\0\x0B" );
					}

					if (!empty($v)) {
							$v = htmlentities(stripslashes($v), ENT_QUOTES);
							$shortcode .= " {$k}=\"{$v}\"";
					}
			}

			$shortcode .= "]";
			return $shortcode;
	}

	function render_view($name = null, $view_vars = null) {
		global $fmc_plugin_dir;
		if ($name == null) {
			$name = $class_name;
		}
		$path = $fmc_plugin_dir . "/views/{$name}.php";
		return $this->render($path, $view_vars);
	}

	function render_admin_view() {
    return $this->render($this->admin_page_view, $this->admin_view_vars);
  }

	protected function render($path_to_view, $view_vars) {
		if (file_exists($path_to_view)) {
			if(is_array($view_vars)){
				extract($view_vars);
			}
			ob_start();
				require($path_to_view);
				$view = ob_get_contents();
			ob_end_clean();
			return $view;
		} else {
			return false;
		}
	}


	function get_field_id($val) {
		$widget = $this->is_called_for_widget();
		if ($widget) {
			return parent::get_field_id($val);
		}
		else {
			return "fmc_shortcode_field_{$val}";
		}
	}


	function get_field_name($val) {
		$widget = $this->is_called_for_widget();
		if ($widget) {
			return parent::get_field_name($val);
		}
		else {
			return $val;
		}
	}


	function is_called_for_widget() {
		// find out what context this was called from
		$backtrace = debug_backtrace();
		if ($backtrace[3]['function'] == "shortcode_form" || $backtrace[3]['function'] == "flex_mls_gtb_cgb_editor_assets") {
			return false;
		}
		else {
			return true;
		}
	}


	function requestVariableArray($key) {
		if ( isset($_GET[$key]) ) {
			if(is_array($_GET[$key])) {
				return $_GET[$key];
			} elseif (is_string($_GET[$key])) {
				return explode(',', $_GET[$key]);
			}
		} else {
			return array();
		}
	}


	protected function label_tag($for, $display_text) {
		echo '<label for="' . $this->get_field_id($for) . '" class="flexmls-admin-field-label">';
			_e($display_text);
		echo '</label>';
	}

	protected function text_field_tag($for, $args = array()) {
		$size = array_key_exists('size', $args) ? $args['size'] : null;
		$class = array_key_exists('class', $args) ? $args['class'] : 'widefat';

		$data_alpha =   array_key_exists('data-alpha', $args) ? "data-alpha='".$args['data-alpha']."'" : '';
		$default = array_key_exists('default', $args) ? $args['default'] : null;
		$value = $this->get_field_value($for) != false ? $this->get_field_value($for) : $default;

		echo "<input fmc-field=\"$for\" fmc-type='text' size='$size' type='text' class='$class'
			id='{$this->get_field_id($for)}' name='{$this->get_field_name($for)}'
			value='{$value}'$data_alpha>";
	}

	protected function font_field_tag($for, $args = array()) {
		$size = array_key_exists('size', $args) ? $args['size'] : null;
		$class = array_key_exists('class', $args) ? $args['class'] : 'widefat';

		$data_alpha =   array_key_exists('data-alpha', $args) ? "data-alpha='".$args['data-alpha']."'" : '';
		$default = array_key_exists('default', $args) ? $args['default'] : null;
		$value = $this->get_field_value($for) != false ? $this->get_field_value($for) : $default;
		$fonts = array_key_exists('fonts', $args) ? $args['fonts'] : fmcWidget::available_fonts();
		$fonts_json_encoded = json_encode( $fonts );

		echo "<input fmc-field=\"$for\" fmc-type='text' size='$size' type='text' class='$class'
			id='{$this->get_field_id($for)}' name='{$this->get_field_name($for)}'
			value='{$value}'$data_alpha data-fonts='{$fonts_json_encoded}'>";
	}

	protected function hidden_field_tag($for, $args = array()) {
		$default = array_key_exists('default', $args) ? $args['default'] : null;
		$value = $this->get_field_value($for) != false ? $this->get_field_value($for) : $default;

		echo "<input fmc-field=\"$for\" fmc-type='text' type='hidden'
			id='{$this->get_field_id($for)}' name='{$this->get_field_name($for)}'
			value='{$value}'>";
	}

	protected function textarea_tag($for, $args = array()) {
		echo "<textarea fmc-field=\"$for\" fmc-type='text' id='{$this->get_field_id($for)}'
			class='flexmls-admin-textarea' name='{$this->get_field_name($for)}'>";
		echo $this->get_field_value($for);
		echo "</textarea>";
	}

	protected function checkbox_tag($for, $args = array()) {
		$default = array_key_exists('default', $args) ? $args["default"] : null;
		$previous_value = $this->get_field_value($for);
		$checked = $default === true ? "checked" : null;

		if ($previous_value === true) {
			$checked = "checked";
		} elseif ($previous_value === false) {
			$checked = null;
		}
		echo "<input fmc-field=\"$for\" type='checkbox' fmc-type='checkbox' name='{$this->get_field_name($for)}'
			id='{$this->get_field_id($for)}' value='true' $checked >";
	}

  protected function select_tag($args) {

    $fmc_field = array_key_exists('fmc_field', $args) ? $args['fmc_field'] : null;
    $collection = array_key_exists('collection', $args) ? $args['collection'] : null;
    $option_value_attr = array_key_exists('option_value_attr', $args) ? $args['option_value_attr'] : null;
    $option_display_attr = array_key_exists('option_display_attr', $args) ? $args['option_display_attr'] : null;
    $class = array_key_exists('class', $args) ? $args['class'] : null;
    $default = array_key_exists('default', $args) ? $args['default'] : null;
		$parent_input_value = array_key_exists('parent_input_value', $args) ? $args['parent_input_value'] : null;
		if ( is_array( $parent_input_value ) ) {
			$parent_input_value = json_encode( $parent_input_value );
		}
		$parent_input_attr = $parent_input_value ? "data-parent-value='{$parent_input_value}'" : '';

    $instance_value = $this->get_field_value($fmc_field);
    $selected_value = $instance_value != false ? $instance_value : $default;

    $output = "<select fmc-field=\"{$fmc_field}\" fmc-type='select' class='{$class}'
      id='{$this->get_field_id($fmc_field)}' name='{$this->get_field_name($fmc_field)}' {$parent_input_attr}>";

    foreach ($collection as $item) {

      $value = $option_value_attr == null ? $item : $item[$option_value_attr];
      $display_text = $option_display_attr == null ? $item : $item[$option_display_attr];

      $selected = $selected_value == $value ? 'selected="selected"' : null;
      $output .= "<option value='{$value}' {$selected}>";
      $output .= $display_text;
      $output .= "</option>";
    }

    $output .= "</select>";
    echo $output;
  }

	/**
	 * IDX link dropdown loaded via Select2 + AJAX (see flexmls_idx_links_select2).
	 * Falls back to a full plain &lt;select&gt; when Select2 is turned off in plugin settings.
	 *
	 * @param array $args fmc_field, only_saved_search (bool), static_options (list of value+text), class?, default?
	 */
	protected function lazy_idx_links_select_tag( $args ) {
		$fmc_field = isset( $args['fmc_field'] ) ? $args['fmc_field'] : null;
		if ( ! $fmc_field ) {
			return;
		}
		$only_saved = ! empty( $args['only_saved_search'] );
		$class = array_key_exists( 'class', $args ) ? $args['class'] : 'widefat';
		$default = array_key_exists( 'default', $args ) ? $args['default'] : null;
		$static_options = array_key_exists( 'static_options', $args ) && is_array( $args['static_options'] ) ? $args['static_options'] : array();
		$instance_value = $this->get_field_value( $fmc_field );
		$selected_value = ( false !== $instance_value && null !== $instance_value ) ? $instance_value : $default;

		if ( ! flexmlsConnect::idx_links_select2_enabled() ) {
			$collection = array();
			foreach ( $static_options as $o ) {
				if ( ! is_array( $o ) || ! array_key_exists( 'value', $o ) ) {
					continue;
				}
				$collection[] = array(
					'value'         => $o['value'],
					'display_text' => isset( $o['text'] ) ? $o['text'] : '',
				);
			}
			$api_links = flexmlsConnect::get_all_idx_links( $only_saved );
			if ( is_array( $api_links ) ) {
				foreach ( $api_links as $l_d ) {
					$collection[] = array(
						'value'         => $l_d['LinkId'],
						'display_text' => $l_d['Name'],
					);
				}
			}
			$this->select_tag(
				array(
					'fmc_field'           => $fmc_field,
					'collection'          => $collection,
					'option_value_attr'   => 'value',
					'option_display_attr' => 'display_text',
					'class'               => $class,
					'default'             => $default,
				)
			);
			return;
		}

		$sel_class = trim( $class . ' flexmls-admin-idx-link-select' );
		$data_saved = $only_saved ? '1' : '0';
		$output = '<select fmc-field="' . esc_attr( $fmc_field ) . '" fmc-type="select" class="' . esc_attr( $sel_class ) . '"'
			. ' id="' . esc_attr( $this->get_field_id( $fmc_field ) ) . '"'
			. ' name="' . esc_attr( $this->get_field_name( $fmc_field ) ) . '"'
			. ' data-only-saved-search="' . esc_attr( $data_saved ) . '"'
			. ' data-flexmls-idx-select="1">';

		// Determine whether an explicit, real IDX link is saved vs. "use the account default".
		// Empty, null, and the "default" sentinel all mean "use default". The placeholder option must
		// use the "default" sentinel value (never a real LinkId) so that selecting the configured
		// default link in Select2 is a genuine value change. Otherwise the chosen LinkId equals the
		// pre-selected placeholder's value, select2:select never fires, and the field stays "Use Default".
		$raw_instance = ( isset( $this->instance ) && is_array( $this->instance ) && array_key_exists( $fmc_field, $this->instance ) && null !== $this->instance[ $fmc_field ] )
			? trim( (string) $this->instance[ $fmc_field ] )
			: '';
		$is_explicit_link = ( '' !== $raw_instance && 'default' !== $raw_instance );
		if ( ! $is_explicit_link ) {
			$selected_value = 'default';
		}

		foreach ( $static_options as $o ) {
			if ( ! is_array( $o ) || ! array_key_exists( 'value', $o ) ) {
				continue;
			}
			$v = $o['value'];
			$t = isset( $o['text'] ) ? $o['text'] : '';
			$sel = ( (string) $selected_value === (string) $v ) ? ' selected="selected"' : '';
			$output .= '<option value="' . esc_attr( $v ) . '"' . $sel . '>' . esc_html( $t ) . '</option>';
		}

		$pref = $is_explicit_link ? flexmlsConnect::idx_links_select2_prefetch_option( $raw_instance ) : null;
		if ( $pref ) {
			$already = false;
			foreach ( $static_options as $o ) {
				if ( is_array( $o ) && isset( $o['value'] ) && (string) $o['value'] === (string) $pref['id'] ) {
					$already = true;
					break;
				}
			}
			if ( ! $already ) {
				$output .= '<option value="' . esc_attr( $pref['id'] ) . '" selected="selected">' . esc_html( $pref['text'] ) . '</option>';
			}
		} elseif ( ! $is_explicit_link ) {
			$default_in_static = false;
			foreach ( $static_options as $o ) {
				if ( is_array( $o ) && isset( $o['value'] ) && (string) $o['value'] === 'default' ) {
					$default_in_static = true;
					break;
				}
			}
			if ( ! $default_in_static ) {
				$output .= '<option value="default" selected="selected">' . esc_html__( 'Use Default', 'flexmls-idx' ) . '</option>';
			}
		}

		$output .= '</select>';
		echo $output;
	}

	/**
	 * Office agent dropdown (office role) via Select2 + AJAX (see flexmls_office_agents_select2).
	 *
	 * @param array $args fmc_field, static_options (value+text), class?, parent_input_value (string|array for toggled_inputs)?
	 */
	protected function lazy_office_agents_select_tag( $args ) {
		$fmc_field = isset( $args['fmc_field'] ) ? $args['fmc_field'] : null;
		if ( ! $fmc_field || ! flexmlsConnect::is_office() ) {
			return;
		}
		global $fmc_api;
		$class               = array_key_exists( 'class', $args ) ? $args['class'] : 'widefat';
		$static_options      = array_key_exists( 'static_options', $args ) && is_array( $args['static_options'] ) ? $args['static_options'] : array();
		$parent_input_value  = array_key_exists( 'parent_input_value', $args ) ? $args['parent_input_value'] : null;
		if ( is_array( $parent_input_value ) ) {
			$parent_input_value = wp_json_encode( $parent_input_value );
		}
		$parent_input_attr = $parent_input_value ? " data-parent-value='" . esc_attr( $parent_input_value ) . "'" : '';

		$instance_value = $this->get_field_value( $fmc_field );
		$selected_value = ( false !== $instance_value && null !== $instance_value ) ? $instance_value : null;

		if ( ! flexmlsConnect::office_agents_select2_enabled() ) {
			$collection = array();
			foreach ( $static_options as $o ) {
				if ( ! is_array( $o ) || ! array_key_exists( 'value', $o ) ) {
					continue;
				}
				$collection[] = array(
					'value'          => $o['value'],
					'display_text'  => isset( $o['text'] ) ? $o['text'] : '',
				);
			}
			$api_my_account = $fmc_api ? $fmc_api->GetMyAccount() : null;
			if ( is_array( $api_my_account ) && ! empty( $api_my_account['OfficeId'] ) ) {
				$roster = flexmlsConnect::get_accounts_by_office_all_pages( $api_my_account['OfficeId'] );
				if ( is_array( $roster ) ) {
					foreach ( $roster as $agent ) {
						if ( ! is_array( $agent ) || empty( $agent['Id'] ) ) {
							continue;
						}
						$collection[] = array(
							'value'          => $agent['Id'],
							'display_text'  => isset( $agent['Name'] ) ? $agent['Name'] : $agent['Id'],
						);
					}
				}
			}
			$this->select_tag(
				array(
					'fmc_field'           => $fmc_field,
					'collection'          => $collection,
					'option_value_attr'   => 'value',
					'option_display_attr' => 'display_text',
					'class'               => $class,
					'parent_input_value'  => array_key_exists( 'parent_input_value', $args ) ? $args['parent_input_value'] : null,
				)
			);
			return;
		}

		$sel_class = trim( $class . ' flexmls-admin-office-agent-select' );
		$output    = '<select fmc-field="' . esc_attr( $fmc_field ) . '" fmc-type="select" class="' . esc_attr( $sel_class ) . '"'
			. ' id="' . esc_attr( $this->get_field_id( $fmc_field ) ) . '"'
			. ' name="' . esc_attr( $this->get_field_name( $fmc_field ) ) . '"'
			. ' data-flexmls-office-agent-select="1"'
			. $parent_input_attr . '>';

		foreach ( $static_options as $o ) {
			if ( ! is_array( $o ) || ! array_key_exists( 'value', $o ) ) {
				continue;
			}
			$v   = $o['value'];
			$t   = isset( $o['text'] ) ? $o['text'] : '';
			$sel = ( null !== $selected_value && (string) $selected_value === (string) $v ) ? ' selected="selected"' : '';
			$output .= '<option value="' . esc_attr( $v ) . '"' . $sel . '>' . esc_html( $t ) . '</option>';
		}

		$explicit_saved = isset( $this->instance ) && is_array( $this->instance ) && array_key_exists( $fmc_field, $this->instance )
			&& null !== $this->instance[ $fmc_field ] && '' !== trim( (string) $this->instance[ $fmc_field ] );

		if ( $explicit_saved ) {
			$pref = flexmlsConnect::office_agents_select2_prefetch_option( $this->instance[ $fmc_field ] );
			if ( $pref ) {
				$already = false;
				foreach ( $static_options as $o ) {
					if ( is_array( $o ) && isset( $o['value'] ) && (string) $o['value'] === (string) $pref['id'] ) {
						$already = true;
						break;
					}
				}
				if ( ! $already ) {
					$sel = ( null !== $selected_value && (string) $selected_value === (string) $pref['id'] ) ? ' selected="selected"' : '';
					$output .= '<option value="' . esc_attr( $pref['id'] ) . '"' . $sel . '>' . esc_html( $pref['text'] ) . '</option>';
				}
			}
		}

		$output .= '</select>';
		echo $output;
	}

	protected function get_field_value($field) {
		if ( isset( $this->instance ) && is_array( $this->instance ) && array_key_exists( $field, $this->instance ) ) {
			$value = $this->instance[$field];
			return ($value === true || $value === false) ? $value : esc_attr($value);
		} else {
			return ($this->is_bool_field($field)) ? "off" : null;
		}
	}

	private function is_bool_field($field) {
		$bool_fields = array("allow_sold_searching");
		return in_array($field, $bool_fields);
	}

	function widget($args, $instance){
	 //This is being overridden in the sub classes for each widget
	}

	static function available_fonts() {
		return [
			'default', 'Arial', 'Verdana', 'Tahoma', 'Times', 'Georgia', 'Garamond'
		];
	}

	function idx_links() {
		$api_links = flexmlsConnect::get_all_idx_links();
		$idx_links = [];

		foreach ($api_links as $l_d) {
			$idx_links []= [
				'value' => $l_d['LinkId'],
				'display_text' => $l_d['Name']
			];
		}

		return $idx_links;
	}


	protected function get_view_property_types() {
		if ( ! $this->is_new_version_widget() ) {
			return parent::get_view_property_types();
		}

		global $fmc_api;
		$output = array();
		$types = $fmc_api->GetPropertyTypes();
		if ( is_array( $types ) ) {
			foreach ($types as $id => $name) {
				$output []= [
					'value' => $id,
					'display_text' => flexmlsConnect::nice_property_type_label( $id )
				];
			}
		}

		return $output;
	}

	function has_new_version_widget() {
		return ! empty( $this->widget_version );
	}

	/**
	 * Whether the instance explicitly requests the Version 1 listing template
	 * via widget_version="1" (overrides Style → Version 2).
	 *
	 * @param array|false $instance Widget/shortcode settings.
	 * @return bool
	 */
	function is_widget_version_one( $instance = false ) {
		if ( empty( $instance ) || ! is_array( $instance ) ) {
			return false;
		}
		return isset( $instance['widget_version'] ) && (string) $instance['widget_version'] === '1';
	}

	function is_new_version_widget( $instance = false ) {
		if ( empty( $instance ) && ! empty( $this->instance ) ) {
			$instance = $this->instance;
		}

		// Explicit shortcode/widget opt-into Version 1 (e.g. widget_version="1").
		if ( $this->is_widget_version_one( $instance ) ) {
			return false;
		}

		if ( $this->has_new_version_widget() ) {
			if ( empty( $instance ) ) {
				// This is a new/clean instance
				return true;
			} else {
				// All potential places for a "new version" widget are enumerated and checked here.
				$has_widget_version = ! empty( $instance['widget_version'] );
				$is_new_gutenberg_widget = ! empty( $instance['_is_gutenberg_new'] );
				$is_new_shortcode_instance = ! empty( $instance['_instance_type'] ) && ( $instance['_instance_type'] == 'shortcode' );
				return $has_widget_version || $is_new_gutenberg_widget || $is_new_shortcode_instance;
			}
		}

		return false;
	}

	function update( $new_instance, $old_instance ) {
		if ( $this->is_new_version_widget( $new_instance ) ) {
			return $this->update_v2( $new_instance, $old_instance );
		} else {
			return $this->update_v1( $new_instance, $old_instance );
		}
	}

	function update_v2( $new_instance, $old_instance ) {
		$instance = $old_instance;

		foreach ( static::settings_fields_v2() as $name => $details ) {
			switch ( $details['type'] ) {
				case 'text':
				case 'color':
				case 'select':
				case 'font':
				case 'toggled_inputs':
				case 'hidden':
					$instance[$name] = strip_tags( $new_instance[$name] );
					break;
				case 'list':
					$instance[$name] = strip_tags( $new_instance[$name] );
					break;
				case 'enabler':
					$instance[$name] = ( $new_instance[$name] == "on" ) ? "on" : "off";
					break;
			}
		}

		return $instance;
	}

	function info_icon( $description ) {
		?>
		<div class="flexmls-info-wrapper">
			<span class="flexmls-info-icon">?</span>
			<div class="description">
				<?php echo esc_html( $description ); ?>
			</div>
		</div>
		<?php
	}
}
