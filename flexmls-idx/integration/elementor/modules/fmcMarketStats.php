<?php
    class EL_fmcMarketStats extends EL_FMC_shortcode{


        
        protected function integrationWithElementor(){
            $this->settings_fmc = ['title', 'width_', 'height_', 'chart_type', 'type', 'display', 'property_type', 'location'];
            // Static chart types — do not require Spark at widget register (WP-1392).
            foreach ( array_keys( $this->market_stat_type_options() ) as $val ) {
                $this->settings_fmc[] = 'display_'.$val;
            }
        }

        /**
         * Chart type keys used in Elementor settings. Matches fmcMarketStats::get_type_options().
         *
         * @return array<string, string>
         */
        protected function market_stat_type_options() {
            return array(
                'absorption' => 'Absorption Rate',
                'inventory'  => 'Inventory',
                'price'      => 'Prices',
                'ratio'      => 'Sale to Original List Price Ratio',
                'dom'        => 'Sold DOM',
                'volume'     => 'Volume',
            );
        }

        /**
         * Display metrics per type. Matches fmcMarketStats::$stat_types (no Spark).
         *
         * @return array<string, array<int, array<string, mixed>>>
         */
        protected function market_stat_display_types() {
            return array(
                'absorption' => array(
                    array( 'label' => 'Absorption Rate (in Months)', 'value' => 'AbsorptionRate', 'selected' => true ),
                ),
                'inventory'  => array(
                    array( 'label' => 'Number of Active Listings', 'value' => 'ActiveListings', 'selected' => true ),
                    array( 'label' => 'Number of New Listings', 'value' => 'NewListings', 'selected' => true ),
                    array( 'label' => 'Number of Pended Listings', 'value' => 'PendedListings' ),
                    array( 'label' => 'Number of Sold Listings', 'value' => 'SoldListings' ),
                ),
                'price'      => array(
                    array( 'label' => 'Active Avg List Price (in Dollars)', 'value' => 'ActiveAverageListPrice', 'selected' => true ),
                    array( 'label' => 'New Avg List Price (in Dollars)', 'value' => 'NewAverageListPrice', 'selected' => true ),
                    array( 'label' => 'Pended Avg List Price (in Dollars)', 'value' => 'PendedAverageListPrice' ),
                    array( 'label' => 'Sold Avg List Price (in Dollars)', 'value' => 'SoldAverageListPrice' ),
                    array( 'label' => 'Sold Avg Sale Price (in Dollars)', 'value' => 'SoldAverageSoldPrice' ),
                    array( 'label' => 'Active Median List Price (in Dollars)', 'value' => 'ActiveMedianListPrice', 'selected' => true ),
                    array( 'label' => 'New Median List Price (in Dollars)', 'value' => 'NewMedianListPrice', 'selected' => true ),
                    array( 'label' => 'Pended Median List Price (in Dollars)', 'value' => 'PendedMedianListPrice' ),
                    array( 'label' => 'Sold Median List Price (in Dollars)', 'value' => 'SoldMedianListPrice' ),
                    array( 'label' => 'Sold Median Sale Price (in Dollars)', 'value' => 'SoldMedianSoldPrice' ),
                ),
                'ratio'      => array(
                    array( 'label' => 'Sale to Original List Price (Percentage)', 'value' => 'SaleToOriginalListPriceRatio', 'selected' => true ),
                    array( 'label' => 'Sale to List Price (Percentage)', 'value' => 'SaleToListPriceRatio' ),
                ),
                'dom'        => array(
                    array( 'label' => 'Average CDOM (in Days)', 'value' => 'AverageCdom', 'selected' => true ),
                    array( 'label' => 'Average ADOM (in Days)', 'value' => 'AverageDom' ),
                ),
                'volume'     => array(
                    array( 'label' => 'Active List Volume (in Dollars)', 'value' => 'ActiveListVolume', 'selected' => true ),
                    array( 'label' => 'New List Volume (in Dollars)', 'value' => 'NewListVolume', 'selected' => true ),
                    array( 'label' => 'Pended List Volume (in Dollars)', 'value' => 'PendedListVolume' ),
                    array( 'label' => 'Sold List Volume (in Dollars)', 'value' => 'SoldListVolume' ),
                    array( 'label' => 'Sold Sale Volume (in Dollars)', 'value' => 'SoldSaleVolume' ),
                ),
            );
        }

        protected $stat_types;
        protected $chart_type;
        protected $type_options;
        protected $display_options;
        
        protected function render_hook($settings){
            $props = is_array( $settings ) ? $settings : array();
            $types = $this->market_stat_type_options();
            $type  = ( isset( $props['type'] ) && isset( $types[ $props['type'] ] ) ) ? $props['type'] : 'absorption';
            $props['type'] = $type;

            foreach ( $types as $val => $label ) {
                if ( $val !== $type ) {
                    unset( $props[ 'display_' . $val ] );
                }
            }

            $display_values = isset( $props[ 'display_' . $type ] ) ? $props[ 'display_' . $type ] : array();
            if ( is_string( $display_values ) ) {
                $display_values = ( '' === $display_values ) ? array() : explode( ',', $display_values );
            }
            if ( ! is_array( $display_values ) ) {
                $display_values = array();
            }
            $display_values = array_values( array_filter( $display_values, 'strlen' ) );
            if ( empty( $display_values ) ) {
                foreach ( $this->market_stat_display_types()[ $type ] as $row ) {
                    if ( ! empty( $row['selected'] ) ) {
                        $display_values[] = $row['value'];
                    }
                }
            }

            $props['display'] = implode( ',', $display_values );
            $props['width']   = isset( $props['width_']['size'] ) ? $props['width_']['size'] : '';
            $props['height']  = isset( $props['height_']['size'] ) ? $props['height_']['size'] : '';
            unset( $props[ 'display_' . $type ], $props['width_'], $props['height_'] );

            return $props + array( 'integration' => 'elementor' );
        }


        protected function get_market_stat_version(){

            $fmc_settings = get_option( 'fmc_settings' );

            $market_stat_version = isset( $fmc_settings['market_stat_version'] ) ? $fmc_settings['market_stat_version'] : 'v1';

            return $market_stat_version;
        }
  
        protected function setControlls() {


           $market_stat_version = $this->get_market_stat_version();

            extract($this->integration_control_vars());

            if ( ! isset( $width ) || '' === $width ) {
                $width = 480;
            }
            if ( ! isset( $height ) || '' === $height ) {
                $height = 200;
            }

            $this->chart_type   = ! empty( $chart_type ) && is_array( $chart_type )
                ? $chart_type
                : array(
                    'LineChart'   => 'Line',
                    'ColumnChart' => 'Column',
                    'BarChart'    => 'Bar',
                    'AreaChart'   => 'Area',
                );
            $this->stat_types   = ! empty( $stat_types ) && is_array( $stat_types )
                ? $stat_types
                : $this->market_stat_display_types();
            $this->type_options = ! empty( $type_options ) && is_array( $type_options )
                ? $type_options
                : $this->market_stat_type_options();
            $this->display_options = array();

            if ( ! is_array( $property_type_options ) ) {
                $property_type_options = array();
            }
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
                        'options' => $this->chart_type,
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
                    'options' => $this->type_options,
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
            $types = is_array( $this->type_options ) ? $this->type_options : array();
            $stat  = is_array( $this->stat_types ) ? $this->stat_types : array();

            $return = array();
            $i = 0;

            foreach ($types as $val => $label) {
                $types_array = $this->modify_types( isset( $stat[ $val ] ) && is_array( $stat[ $val ] ) ? $stat[ $val ] : array() );
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