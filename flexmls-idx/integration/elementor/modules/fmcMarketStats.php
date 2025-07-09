<?php
    class EL_fmcMarketStats extends EL_FMC_shortcode{


        
        protected function integrationWithElementor(){
            $this->settings_fmc = ['title', 'width_', 'height_', 'chart_type', 'type', 'display', 'property_type', 'location'];
            $types = $this->module_info['vars']['type_options'];
            foreach ($types as $val => $label) {
                $this->settings_fmc[] = 'display_'.$val;
            }
        }

        protected $stat_types;
        protected $chart_type;
        protected $type_options;
        protected $display_options;
        
        protected function render_hook($settings){
            $props = $settings;
            $types = $this->module_info['vars']['type_options'];

            foreach ($types as $val => $label) {
                if($props['type'] == $val){
                    $display = $val;
                } else {
                    unset($props['display_'.$val]);
                }
            }
    
            $props['display'] = implode(',', $props['display_'.$display]);
            $props['width'] = $props['width_']['size'];
            $props['height'] = $props['height_']['size'];
            unset($props['display_'.$display]);
            unset($props['width_']);
            unset($props['height_']);

            $return = $props + ['integration' => 'elementor'];

            return $return;
        }


        protected function get_market_stat_version(){

            $fmc_settings = get_option( 'fmc_settings' );

            $market_stat_version = isset( $fmc_settings['market_stat_version'] ) ? $fmc_settings['market_stat_version'] : 'v1';

            return $market_stat_version;
        }
  
        protected function setControlls() {


           $market_stat_version = $this->get_market_stat_version();

            extract($this->module_info['vars']);

            $this->chart_type = $chart_type;
            $this->stat_types = $stat_types;
            $this->type_options = $type_options;
            $this->display_options = array();

            $property_type_options = array_merge(['' => 'All'], $property_type_options);


            $this->add_control(
                'title',
                    [
                        'label' => __( 'Title', 'flexmls-idx' ),
                        'type' => \Elementor\Controls_Manager::TEXT,
                        'input_type' => 'text',
                    ]
            );
            $this->add_control(
                'width_',
                    [
                        'label' => __( 'Width', 'flexmls-idx' ),
                        'type' => \Elementor\Controls_Manager::SLIDER,
                        'size_units' => [ 'px' ],
                        'range' => [
                            'px' => [
                                'min' => 0,
                                'max' => 1000,
                                'step' => 5,
                            ]
                        ],
                        'default' => [
                            'unit' => 'px',
                            'size' => $width,
                        ]
                    ]
            );
            $this->add_control(
                'height_',
                    [
                        'label' => __( 'Height', 'flexmls-idx' ),
                        'type' => \Elementor\Controls_Manager::SLIDER,
                        'size_units' => [ 'px' ],
                        'range' => [
                            'px' => [
                                'min' => 0,
                                'max' => 1000,
                                'step' => 5,
                            ]
                        ],
                        'default' => [
                            'unit' => 'px',
                            'size' => $height,
                        ]
                    ]
            );

            if( $market_stat_version == 'v2' ) {
                $this->add_control(
                    'chart_type',
                    [
                        'label' => __('Chart Type', 'flexmls-idx'),
                        'type' => \Elementor\Controls_Manager::SELECT,
                        'options' => $chart_type,
                        'description' => 'Which type of chart to display',
                        'default' => 'LineChart',
                    ]
                );
            }

            $this->add_control(
                'type',
                [
                    'label' => __( 'Type', 'flexmls-idx' ),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $type_options,
                    //added - changed the word chart to data
                    'description' => 'Which type of data to display',
                    'default' => 'absorption',
                ]
            );

            $this->set_stat_types('display');

            $this->add_control(
                'property_type',
                [
                    'label' => __( 'Property Type', 'flexmls-idx' ),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $property_type_options,
                    'default' => '',
                ]
            );

            $this->add_control(
                'location',
                [
                    'label' => __( 'Location', 'flexmls-idx' ),
                    'type' => 'location_control',
                    'multiple' => false,
                    'field_slug' => $location_slug,                    
                ]
            );
        }  

        private function set_stat_types($param){
            $types = $this->type_options;
            $stat = $this->stat_types;
    
            $return = array();
            $i = 0;
    
            foreach ($types as $val => $label) {
                $types_array = $this->modify_types($stat[$val]);
                $this->display_options[$val] = $types_array['options'];
                $this->add_control(
                    $param.'_'.$val,
                    [
                        'label'           => __( 'Display', 'flexmls-idx' ),
                        'type' => \Elementor\Controls_Manager::SELECT2,
                        'options' => $types_array['options'],
                        'multiple' => true,
                        'default' => $types_array['default'],
                        'condition' => [
                            'type' => ($val == 'absorption') ? '': $val
                        ]
                    ]
                );
                
                $i = $i + 1;
            }
    
            return $return;
        }

        private function modify_types($types){
            $return = array(
                'options' => array(),
                'default' => array()
            );
            foreach ($types as $data) {            
                $return['options'][$data['value']] = $data['label'];
                if(!empty($data['selected'])){
                    $return['default'][] = $data['value'];
                } 
            }
    
            return $return;
        }
    
  };