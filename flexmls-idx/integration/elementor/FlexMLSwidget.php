<?php

  class EL_FMC_widget extends \Elementor\Widget_WordPress {  

    private $categories = [];
    private $title;
  
    public function get_name() {
          return 'fmc-widget-' . $this->get_widget_instance()->id_base;
    }
  
    public function get_categories() {
          return $this->categories;
    }
    
    public function get_icon() {
          return 'flexmls-icon-logo';
    }
    
    public function get_keywords() {
          return [ 'flexmls', 'fms', 'widget' ];
    }

    public function get_title() {
        return $this->title;
    }

    /**
     * Flexmls widgets fetch live Spark data. Do not let Element Cache store a failed render (WP-1393).
     */
    protected function is_dynamic_content(): bool {
        return true;
    }
  
    public function __construct( $data = [], $args = null,  $cats = []) {
      $this->categories = $cats;
      parent::__construct( $data, $args );
      
      $this->title = $args['widget_title'];
  
    }  
  };

  class EL_FMC_shortcode extends \Elementor\Widget_Base{
    protected $categories = [];

    protected $module_info;
    protected $settings_fmc = [];
  
    public function get_name() {
          return $this->module_info['slug'];
    }

    public function get_title() {
        return $this->module_info['title'];
    }
  
    public function get_categories() {
        return $this->categories;
    }
    
    public function get_icon() {
        return 'flexmls-icon-logo';
    }
    
    public function get_keywords() {
          return [ 'flexmls', 'fms', 'widget' ];
    }

    /**
     * Flexmls widgets fetch live Spark data. Do not let Element Cache store a failed render (WP-1393).
     */
    protected function is_dynamic_content(): bool {
        return true;
    }
    
    protected function register_controls() {
		$this->ensure_integration_vars();

		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Content', 'plugin-name' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->setControlls();

		$this->end_controls_section();

    }

	/**
	 * Load Spark-backed control options only in the Elementor editor / admin-ajax.
	 * Front-end widget register and page views skip this (WP-1392).
	 */
	protected function should_fetch_spark_for_controls() {
		if ( wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return true;
		}
		if ( is_admin() ) {
			return true;
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor )
			&& is_object( \Elementor\Plugin::$instance->editor )
			&& method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' )
			&& \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return true;
		}
		return false;
	}

	/**
	 * Populate $this->module_info['vars'] from the component when the editor needs dropdowns.
	 */
	protected function ensure_integration_vars() {
		if ( ! empty( $this->module_info['vars'] ) && is_array( $this->module_info['vars'] ) ) {
			return;
		}

		$this->module_info['vars'] = array();

		if ( ! $this->should_fetch_spark_for_controls() ) {
			return;
		}

		$id_base = isset( $this->module_info['id_base'] ) ? $this->module_info['id_base'] : '';
		if ( '' === $id_base ) {
			return;
		}

		if ( ! class_exists( $id_base, false ) ) {
			return;
		}

		$component = isset( $this->module_info['component'] ) ? $this->module_info['component'] : null;
		if ( ! is_object( $component ) ) {
			$component                     = new $id_base();
			$this->module_info['component'] = $component;
		}

		if ( ! method_exists( $component, 'integration_view_vars' ) ) {
			return;
		}

		$vars = $component->integration_view_vars();
		$this->module_info['vars'] = is_array( $vars ) ? $vars : array();
	}

	/**
	 * Safe vars for setControlls() when Spark was skipped or the API missed.
	 *
	 * @return array<string, mixed>
	 */
	protected function integration_control_vars() {
		$vars = ( isset( $this->module_info['vars'] ) && is_array( $this->module_info['vars'] ) )
			? $this->module_info['vars']
			: array();

		$array_keys = array(
			'property_type',
			'property_type_options',
			'property_types',
			'property_sub_type',
			'api_links',
			'idx_links',
			'destination',
			'destination_options',
			'default_view',
			'default_view_options',
			'type_options',
			'stat_types',
			'chart_type',
			'display',
			'display_options',
			'display_day_options',
			'source',
			'source_options',
			'status',
			'sort',
			'sort_options',
			'available_fields',
			'additional_fields',
			'agent',
			'api_property_type_options',
			'horizontal',
			'vertical',
			'image_size',
			'auto_rotate',
			'days',
			'send_to',
			'theme_options',
			'orientation_options',
			'border_style_options',
			'submit_button_options',
			'listings_per_page_options',
		);
		foreach ( $array_keys as $key ) {
			if ( ! isset( $vars[ $key ] ) || ! is_array( $vars[ $key ] ) ) {
				$vars[ $key ] = array();
			}
		}

		$string_keys = array(
			'title',
			'location_slug',
			'portal_slug',
			'special_neighborhood_title_ability',
			'title_description',
		);
		foreach ( $string_keys as $key ) {
			if ( ! isset( $vars[ $key ] ) ) {
				$vars[ $key ] = '';
			}
		}

		return $vars;
	}
    
    protected function render_hook($settings){
        return $settings;
    }
    
    protected function render() {
        $shortcode_attrs = [];
        foreach ($this->settings_fmc as $attr) {
            $shortcode_attrs[$attr] = $this->get_settings($attr);
        }

        //$component = $this->module_info['component'];

        $shortcode_attrs = $this->render_hook($shortcode_attrs);
        $shortcode = $this->createShortcode($shortcode_attrs);

        if(is_admin()){
            echo do_shortcode($shortcode);
            //echo $shortcode;
        } else {
            echo $shortcode;
        }
        //$component->shortcode($shortcode_attrs); 
        
        //var_dump($shortcode_attrs);		
    }
    
    
    protected function integrationWithElementor(){}

    protected function modify_array($arr, $val = 'value', $label = 'display_text'){
        if ( ! is_array( $arr ) ) {
            return array();
        }
        $options = array();
        foreach ($arr as $data) {
          $options[$data[$val]] = $data[$label];
        };
  
        return $options;
    }

    protected function get_field_name($field_name){
        return 'fmc_shortcode_field_'.$field_name;
    }
      
    protected function get_field_id($field_name){
        return $field_name;
    }

    protected function inits(){
        $className = (string) str_replace('EL_', '', get_class($this));

        global $fmc_widgets_integration;
        if ( empty( $fmc_widgets_integration[ $className ] ) ) {
            $this->module_info = array(
                'title'       => $className,
                'id_base'     => $className,
                'slug'        => 'fmc-widget-' . strtolower( $className ),
                'description' => '',
                'shortcode'   => '',
                'component'   => null,
                'vars'        => array(),
            );
            return;
        }

        $info = $fmc_widgets_integration[ $className ];

        // Load the component file so the class exists later. Do not instantiate or
        // call integration_view_vars() here — that hits Spark on every Elementor
        // widget register, including front-end page views (WP-1392).
        if ( ! class_exists( $className, false ) && ! empty( $info['component'] ) ) {
            $component_file = FMC_PLUGIN_DIR . 'components/' . $info['component'];
            if ( file_exists( $component_file ) ) {
                require_once $component_file;
            }
        }

        $this->module_info = array(
            'title'       => $info['title'],
            'id_base'     => $className,
            'slug'        => 'fmc-widget-' . strtolower( $className ),
            'description' => $info['description'],
            'shortcode'   => $info['shortcode'],
            'component'   => null,
            'vars'        => array(),
        );
    } 

    protected function createShortcode($params, $params_empty = []){
        $output = '';
        $output .='[' . $this->module_info['shortcode'];
        foreach ($params as $key => $value) {
            if($value != '' || in_array($value, $params_empty)){
                $output .= ' ' . $key . '="' . $value . '"'; 
            }
        }
        $output .= ']';
        return $output;
    }

    public function __construct( $data = [], $args = null,  $cats = [] ) {
        $this->inits();
        $this->integrationWithElementor();
        $this->categories = $cats;
        parent::__construct( $data, $args );
    }  

  };

