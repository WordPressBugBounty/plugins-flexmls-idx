<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'enqueue_block_editor_assets', 'flex_mls_gtb_cgb_editor_assets' );
add_action( 'init', 'init_flexmls_gutenberg' );

function flex_mls_gtb_cgb_editor_assets() {

    if(!function_exists("register_block_type"))
        return;
    wp_enqueue_script(
        'flex_mls_gtb-cgb-block-js', // Handle.
        plugins_url( '/assets/js/blocks.js', dirname( __FILE__ ) ),
        array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components' ),
        true // Enqueue the script in the footer.
    );
    wp_enqueue_script( 'fmc_gtb_global', plugins_url( 'assets/js/flex_gtb.js', dirname( __FILE__ ) ), array( 'jquery', 'jquery-ui-core' ) );

    $htmlListingDetails = $htmlPhotos = $htmlMarketStats = $htmlSearch = $htmlLocationLinks =
        $htmlIDXLinksWidget = $htmlLeadgen = $htmlSearchResults = $htmlAccount = $htmlAgents =
            "<b>Flex connection error! Have you entered Flexmls® API credentials?</b>";
    $instance = array("_instance_type" => "shortcode", "_is_gutenberg_new" => true);

    if(class_exists("fmcListingDetails")) {
        $listingDetails = new fmcListingDetails();
        $htmlListingDetails = $listingDetails->settings_form($instance);
    }

    if(class_exists("fmcPhotos")) {
        $photos = new fmcPhotos();
        $htmlPhotos = $photos->settings_form($instance);
    }

    if(class_exists("fmcMarketStats")) {
        $marketStats = new fmcMarketStats();
        $htmlMarketStats = $marketStats->settings_form($instance);
    }

    if(class_exists("fmcSearch")) {
        $search = new fmcSearch();
        $htmlSearch = $search->settings_form($instance);
    }

    if(class_exists("fmcLocationLinks")) {
        $locationLinks = new fmcLocationLinks();
        $htmlLocationLinks = $locationLinks->settings_form($instance);
    }

    if(class_exists("fmcIDXLinksWidget")) {
        $idxLinksWidget = new fmcIDXLinksWidget();
        $htmlIDXLinksWidget = $idxLinksWidget->settings_form($instance);
    }

    if(class_exists("fmcSearchResults")) {
        $searchResults = new fmcSearchResults();
        $htmlSearchResults = $searchResults->settings_form($instance);
    }

    if(class_exists("FlexMLS\Widgets\LeadGeneration")) {
        $leadgen = new \FlexMLS\Widgets\LeadGeneration();
        $htmlLeadgen = $leadgen->get_form($instance);
    }


    if(class_exists("fmcAccount")) {
        $account = new fmcAccount();
        $htmlAccount = $account->settings_form($instance);
    }

    if(class_exists("fmcAgents")) {
        $agents = new fmcAgents();
        $htmlAgents = $agents->settings_form($instance);
    }


    $arr = array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'pluginurl' => plugins_url( '', dirname( __FILE__ ) ),
        'nonce' => wp_create_nonce( 'fmc_ajax' ),
        'htmlListingDetails' => $htmlListingDetails,
        'htmlPhotos' => $htmlPhotos,
        'htmlMarketStats' => $htmlMarketStats,
        'htmlSearch' => $htmlSearch,
        'htmlLocationLinks' => $htmlLocationLinks,
        'htmlIDXLinksWidget' => $htmlIDXLinksWidget,
        'htmlLeadgen' => $htmlLeadgen,
        'htmlSearchResults' => $htmlSearchResults,
        'htmlAccount' => $htmlAccount,
        'htmlAgents'  => $htmlAgents
    );

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
    ob_start();
    if(isset($attributes['sendData'])) {
        $url = admin_url('admin-ajax.php');
        // Internal cURL has no cookies; use one-time token so shortcode_generate nonce check passes.
        $attributes['sendData']['fmc_render_token'] = bin2hex( random_bytes( 16 ) );
        set_transient( 'fmc_render_' . $attributes['sendData']['fmc_render_token'], 1, 60 );

        $ch = curl_init();

        $post_data = http_build_query($attributes['sendData']);

        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_ENCODING,"");
        curl_setopt($ch, CURLOPT_MAXREDIRS,10);
        curl_setopt($ch, CURLOPT_TIMEOUT,30);
        curl_setopt($ch, CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER,array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
        ));

        if( defined( 'FMC_DEV' ) && FMC_DEV ) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        $err = curl_error($ch);
        curl_close ($ch);
        $output = flexmlsJSON::json_decode($server_output);
        if (is_array($output) && isset($output['body'])) {
            echo $output['body'];
        } else {
            echo "<div>Error loading content</div>";
        }

    }
    else {
        echo "<div>Flexmls Plugin</div>";
    }

    return ob_get_clean();
}
