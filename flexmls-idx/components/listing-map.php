<?php
/**
 * Generates a map of listings.
 */

class flexmlsListingMap {

	/**
	 * Google Maps API Key.
	 * @var string The Google Maps API Key
	 */
	private $api_key = '';

	/**
	 * User set map height.
	 *
	 * @var string
	 */
	private $map_height = '';

	/**
	 * The plugin's base URL
	 * @var string The URL of the plugin folder
	 */
	private $plugin_url = '';

	/**
	 * Provides an array of location data to pass to the maps API
	 * @var array An array of location data.
	 */
	private $locations = array();

	/**
	 * Whether to lazy-load the map (load API only when user clicks "Open Map").
	 *
	 * @var bool
	 */
	private $lazy_load = false;

	/**
	 * Unique ID for this map container (for multiple widgets on the same page).
	 *
	 * @var string
	 */
	private $map_id = 'idx-map';

	/**
	 * Counter for generating unique map IDs.
	 *
	 * @var int
	 */
	private static $map_counter = 0;

	/**
	 * Kick things off.
	 *
	 * @param array $locations Location data for the map.
	 * @param array $opts      Optional. 'lazy_load' => true to defer loading until "Open Map" is clicked.
	 */
	public function __construct( $locations, $opts = array() ) {
		global $fmc_plugin_url, $fmc_plugin_dir;
		$options = get_option('fmc_settings');
		$this->api_key = isset( $options['google_maps_api_key'] ) ? $options['google_maps_api_key'] : '';
		$this->plugin_url = $fmc_plugin_url;
		$this->lazy_load = ! empty( $opts['lazy_load'] );
		self::$map_counter++;
		$this->map_id = 'idx-map-' . self::$map_counter;
		/**
		 * Allows you to filter the array of locations being sent to the map.
		 *
		 * @param array $locations {
		 *      An array of location data to display on the map.
		 *      $latitude  string The latitude coordinate of the location.
		 *      $longitude string The longitude coordinate of the location.
		 *      $listprice string The list price of the property formatted with $ and commas.
		 *      $rawprice  string The unformatted list price of the property.
		 *      $link      string The link to the individual property page.
		 *      $image     string The URL of the property's featured image.
		 *      $imagealt  string Alt tag text for the property's featured image.
		 *      $bedrooms  string The number of bedrooms for the property.
		 *      $bathrooms string The number of bathrooms for the property.
		 * }
		 */
		$this->locations = apply_filters( 'idx_map_locations', $locations );
		$this->set_map_height( $options );
		if ( ! $this->lazy_load ) {
			$this->enqueue();
		}
	}

	/**
	 * Enqueue JavaScript files
	 */
	public function enqueue() {
		\FlexMLS\Admin\Enqueue::enqueue_google_maps();
		wp_enqueue_script( 'fmc_flexmls_map', plugins_url( 'assets/js/map.js', dirname( __FILE__ ) ), array( 'google-maps' ), FMC_PLUGIN_VERSION );
	}

	public function set_map_height( $options = NULL ) {
		// If they've saved 'px' in their value
		if ( empty( $options['map_height'] ) ) {
			$this->map_height = '500px';
		} elseif ( strpos( $options['map_height'], 'px' ) !== false || strpos( $options['map_height'], '%' ) !== false ) {
			// If they typed 'px' or '%' in the value
			$this->map_height = $options['map_height'];
		} else {
			$unit = 'px';
			$this->map_height = intval( $options['map_height'] ) . $unit;
		}
	}

	/**
	 * Renders the HTML output for the map.
	 */
	public function render_map() {
		// If there is no API Key, we don't show the map
		if ( ! $this->api_key ) {
			return;
		}

		/**
		 * Allows you to change the map height conditionally free from the setting.
		 *
		 * @param string $map_height A formatted map height in pixels or percentage.
		 * @param array $locations {
		 *      An array of location data to display on the map.
		 *      $latitude  string The latitude coordinate of the location.
		 *      $longitude string The longitude coordinate of the location.
		 *      $listprice string The list price of the property formatted with $ and commas.
		 *      $rawprice  string The unformatted list price of the property.
		 *      $link      string The link to the individual property page.
		 *      $image     string The URL of the property's featured image.
		 *      $imagealt  string Alt tag text for the property's featured image.
		 *      $bedrooms  string The number of bedrooms for the property.
		 *      $bathrooms string The number of bathrooms for the property.
		 * }
		 */

		$map_height = apply_filters( 'idx_map_height', $this->map_height, $this->locations );

		/**
		 * Outputs before the Map HTML.
		 *
		 * @param array $locations {
		 *      An array of location data to display on the map.
		 *      $latitude  string The latitude coordinate of the location.
		 *      $longitude string The longitude coordinate of the location.
		 *      $listprice string The list price of the property formatted with $ and commas.
		 *      $rawprice  string The unformatted list price of the property.
		 *      $link      string The link to the individual property page.
		 *      $image     string The URL of the property's featured image.
		 *      $imagealt  string Alt tag text for the property's featured image.
		 *      $bedrooms  string The number of bedrooms for the property.
		 *      $bathrooms string The number of bathrooms for the property.
		 * }
		 */
		do_action( 'idx_before_map', $this->locations );
		// Expose this map's config so map.js can init it (supports multiple widgets on the same page).
		echo '<script type="text/javascript">window.fmcMapConfigs=window.fmcMapConfigs||[];window.fmcMapConfigs.push({id:' . wp_json_encode( $this->map_id ) . ',locations:' . wp_json_encode( $this->locations ) . '});</script>';
		?>
		<div id="<?php echo esc_attr( $this->map_id ); ?>" class="flex-map" style="height:<?php echo esc_attr( $map_height ); ?>"></div>
		<?php
		/**
		 * Outputs after the Map HTML.
		 *
		 * @param array $locations {
		 *      An array of location data to display on the map.
		 *      $latitude  string The latitude coordinate of the location.
		 *      $longitude string The longitude coordinate of the location.
		 *      $listprice string The list price of the property formatted with $ and commas.
		 *      $rawprice  string The unformatted list price of the property.
		 *      $link      string The link to the individual property page.
		 *      $image     string The URL of the property's featured image.
		 *      $imagealt  string Alt tag text for the property's featured image.
		 *      $bedrooms  string The number of bedrooms for the property.
		 *      $bathrooms string The number of bathrooms for the property.
		 * }
		 */
		do_action( 'idx_after_map', $this->locations );
	}

}
