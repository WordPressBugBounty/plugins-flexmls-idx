<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'enqueue_block_editor_assets', 'flex_mls_gtb_cgb_editor_assets' );
add_action( 'init', 'init_flexmls_gutenberg' );

function flex_mls_gtb_cgb_editor_assets() {

    if(!function_exists("register_block_type"))
        return;

    $fmc_opts = get_option( 'fmc_settings', array() );
    if ( ! isset( $fmc_opts['select2_turn_off'] ) ) {
        $fmc_opts['select2_turn_off'] = 0;
    }
    $block_js_deps = array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'jquery', 'fmc_gtb_global' );
    $fmc_gtb_deps = array( 'jquery', 'jquery-ui-core' );
    if ( $fmc_opts['select2_turn_off'] !== 'admin' && $fmc_opts['select2_turn_off'] !== 'all' ) {
        wp_enqueue_script( 'select2-4.0.5', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.full.min.js', array( 'jquery' ), '4.0.5-full', true );
        wp_enqueue_style( 'select2-4.0.5', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css', array(), '4.0.5-full' );
        $block_js_deps[] = 'select2-4.0.5';
        $fmc_gtb_deps[] = 'select2-4.0.5';
    }

    $assets_dir = dirname( dirname( __FILE__ ) ) . '/assets/js/';
    $flex_gtb_ver = file_exists( $assets_dir . 'flex_gtb.js' ) ? filemtime( $assets_dir . 'flex_gtb.js' ) : ( defined( 'FMC_PLUGIN_VERSION' ) ? FMC_PLUGIN_VERSION : null );
    $blocks_ver = file_exists( $assets_dir . 'blocks.js' ) ? filemtime( $assets_dir . 'blocks.js' ) : ( defined( 'FMC_PLUGIN_VERSION' ) ? FMC_PLUGIN_VERSION : null );

    wp_enqueue_script( 'fmc_gtb_global', plugins_url( 'assets/js/flex_gtb.js', dirname( __FILE__ ) ), $fmc_gtb_deps, $flex_gtb_ver );

    wp_enqueue_script(
        'flex_mls_gtb-cgb-block-js', // Handle.
        plugins_url( '/assets/js/blocks.js', dirname( __FILE__ ) ),
        $block_js_deps,
        $blocks_ver,
        true // Enqueue the script in the footer.
    );

    $instance = array("_instance_type" => "shortcode", "_is_gutenberg_new" => true);
    $form_classes = array(
        'htmlListingDetails' => 'fmcListingDetails',
        'htmlPhotos'         => 'fmcPhotos',
        'htmlMarketStats'    => 'fmcMarketStats',
        'htmlSearch'         => 'fmcSearch',
        'htmlLocationLinks'  => 'fmcLocationLinks',
        'htmlIDXLinksWidget' => 'fmcIDXLinksWidget',
        'htmlSearchResults'  => 'fmcSearchResults',
        'htmlLeadgen'        => 'FlexMLS\Widgets\LeadGeneration',
        'htmlAccount'        => 'fmcAccount',
        'htmlAgents'         => 'fmcAgents',
    );
    $forms = array();

    foreach ( $form_classes as $key => $class ) {
        $forms[ $key ] = "<b>Flex connection error! Have you entered Flexmls® plugin credentials?</b>";
        if ( ! class_exists( $class ) ) {
            continue;
        }

        // Call settings_form() here, not from a callback: fmcWidget::is_called_for_widget() looks for this function in the call stack.
        $ob_level = ob_get_level();
        try {
            $widget = new $class();
            $forms[ $key ] = ( $widget instanceof fmcWidget ) ? $widget->settings_form( $instance ) : $widget->get_form( $instance );
        } catch ( \Throwable $e ) {
            $detail = flexmls_recover_from_error( "{$class} block settings", $e, $ob_level );
            $forms[ $key ] = flexmls_settings_form_error_html( $detail );
            wp_add_inline_script( 'fmc_gtb_global', 'console.warn( "Flexmls IDX:", ' . wp_json_encode( $detail ) . ' );' );
        }
    }

    $arr = array_merge( array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'pluginurl' => plugins_url( '', dirname( __FILE__ ) ),
        'nonce' => wp_create_nonce( 'fmc_ajax' ),
        'idxLinksSelect2' => array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'fmc_ajax' ),
            'action'  => 'flexmls_idx_links_select2',
            'enabled' => flexmlsConnect::idx_links_select2_enabled() ? 1 : 0,
        ),
        'officeAgentsSelect2' => array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'fmc_ajax' ),
            'action'  => 'flexmls_office_agents_select2',
            'enabled' => flexmlsConnect::office_agents_select2_enabled() ? 1 : 0,
        ),
    ), $forms );

    wp_localize_script( 'flex_mls_gtb-cgb-block-js', 'flexGtbData',  $arr);

}

function init_flexmls_gutenberg()
{

    if(!function_exists("register_block_type"))
        return;

    wp_register_style(
        'flexmls-blocks-editor-canvas',
        plugins_url( 'assets/css/blocks_editor_canvas.css', dirname( __FILE__ ) ),
        array(),
        defined( 'FMC_PLUGIN_VERSION' ) ? FMC_PLUGIN_VERSION : '1.0'
    );

    add_filter( 'block_categories_all', function( $categories, $post ) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug' => 'flex',
                    'title' => 'Flexmls'
                ),
            )
        );
    }, 10, 2 );

    $idx_block_args = array(
        'api_version'     => 3,
        'render_callback' => 'FlexMlsCallback',
        'editor_style'    => 'flexmls-blocks-editor-canvas',
        'attributes'      => array(
            'widgetName'    => array(
                'type' => 'string',
            ),
            'sendData'      => array(
                'type' => 'object',
            ),
            'inspectorHtml' => array(
                'type' => 'html',
            ),
        ),
        'category' => 'flex',
    );

    register_block_type( 'flex/market-stats', $idx_block_args );
    register_block_type( 'flex/photos', $idx_block_args );
    register_block_type( 'flex/search', $idx_block_args );
    register_block_type( 'flex/location-links', $idx_block_args );
    register_block_type( 'flex/idx-links-widget', $idx_block_args );
    register_block_type( 'flex/leadgen', $idx_block_args );
    register_block_type( 'flex/listing-details', $idx_block_args );
    register_block_type( 'flex/search-results', $idx_block_args );
    register_block_type( 'flex/account', $idx_block_args );
    register_block_type( 'flex/agents', $idx_block_args );

}
function FlexMlsCallback($attributes )
{
    if ( ! isset( $attributes['sendData'] ) ) {
        return "<div>Flexmls Plugin</div>";
    }

    $shortcode = flexmls_block_shortcode_string( $attributes['sendData'] );
    if ( '' === $shortcode ) {
        return "<div>Error loading content</div>";
    }

    // These filters run do_shortcode() after do_blocks(); block templates and
    // template parts run it before, so the shortcode has to be expanded here.
    if ( doing_filter( 'the_content' ) || doing_filter( 'widget_block_content' ) ) {
        return $shortcode;
    }

    return do_shortcode( $shortcode );
}

/**
 * @param array $send_data The block's saved sendData attribute.
 * @return string Empty when the settings don't match an available widget.
 */
function flexmls_block_shortcode_string( $send_data )
{
    if ( ! is_array( $send_data ) || ! isset( $send_data['action'] ) || ! is_string( $send_data['action'] ) ) {
        return '';
    }

    $send_data = flexmls_block_form_values( $send_data );
    $action = $send_data['action'];

    if ( 'tinymce_shortcodes_generate' === $action ) {
        return \FlexMLS\Admin\TinyMCE::build_shortcode_string( wp_slash( $send_data ) );
    }

    global $fmc_widgets;
    $suffix = '_shortcode_gen';
    if ( substr( $action, -strlen( $suffix ) ) !== $suffix ) {
        return '';
    }

    $widget_class = substr( $action, 0, -strlen( $suffix ) );
    if ( empty( $fmc_widgets[ $widget_class ]['shortcode'] ) || ! shortcode_exists( $fmc_widgets[ $widget_class ]['shortcode'] ) ) {
        return '';
    }

    return fmcWidget::build_shortcode_string( $widget_class, $send_data );
}

/**
 * Converts JSON attribute values to the strings a form submission would carry
 * (true/false become '1'/'0', null is dropped), which the shortcode builders expect.
 */
function flexmls_block_form_values( $values )
{
    $form_values = array();
    foreach ( $values as $key => $value ) {
        if ( is_array( $value ) ) {
            $form_values[ $key ] = flexmls_block_form_values( $value );
        } elseif ( is_bool( $value ) ) {
            $form_values[ $key ] = $value ? '1' : '0';
        } elseif ( is_scalar( $value ) ) {
            $form_values[ $key ] = (string) $value;
        }
    }
    return $form_values;
}
