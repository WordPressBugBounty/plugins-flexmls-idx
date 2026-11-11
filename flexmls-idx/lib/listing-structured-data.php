<?php

defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

/**
 * JSON-LD (schema.org) structured data for listing detail pages.
 *
 * Off by default (Listing Detail setting "listing_detail_structured_data"); the output can be
 * changed or removed with the flexmls_listing_structured_data filter.
 */
class flexmlsListingStructuredData {

	public static function is_enabled() {
		$options = get_option( 'fmc_settings' );
		$enabled = is_array( $options ) && ! empty( $options['listing_detail_structured_data'] );
		return (bool) apply_filters( 'flexmls_listing_structured_data_enabled', $enabled );
	}

	/**
	 * Print the JSON-LD script tag for a listing.
	 *
	 * @param array $listing Listing record from the API (with StandardFields).
	 */
	public static function output( $listing ) {
		if ( ! self::is_enabled() || ! is_array( $listing ) ) {
			return;
		}
		$graph = self::build( $listing );
		$graph = apply_filters( 'flexmls_listing_structured_data', $graph, $listing );
		if ( empty( $graph ) || ! is_array( $graph ) ) {
			return;
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( $graph ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);
		if ( ! $json ) {
			return;
		}
		echo '<script type="application/ld+json">' . $json . '</script>' . PHP_EOL;
	}

	/**
	 * @return array[] Schema nodes keyed by type (listing, breadcrumbs).
	 */
	public static function build( $listing ) {
		$sf = isset( $listing['StandardFields'] ) && is_array( $listing['StandardFields'] ) ? $listing['StandardFields'] : array();
		if ( empty( $sf['ListingId'] ) ) {
			return array();
		}

		$url     = flexmlsConnect::make_nice_address_url( $listing );
		$address = flexmlsConnect::format_listing_street_address( $listing );

		$place = array_filter( array(
			'@type'                  => self::has_value( $sf, 'BedsTotal' ) || self::floor_size( $sf ) ? 'Accommodation' : 'Place',
			'name'                   => $address[2],
			'address'                => self::postal_address( $sf, $address ),
			'geo'                    => self::geo( $sf ),
			'numberOfBedrooms'       => self::number( $sf, 'BedsTotal' ),
			'numberOfBathroomsTotal' => self::number( $sf, 'BathsTotal' ),
			'floorSize'              => self::floor_size( $sf ),
			'yearBuilt'              => self::number( $sf, 'YearBuilt' ),
		), array( __CLASS__, 'is_present' ) );

		$listing_node = array_filter( array(
			'@type'        => 'RealEstateListing',
			'@id'          => $url . '#listing',
			'url'          => $url,
			'name'         => flexmlsConnect::make_nice_address_title( $listing ),
			'description'  => self::has_value( $sf, 'PublicRemarks' ) ? wp_strip_all_tags( $sf['PublicRemarks'] ) : null,
			'datePosted'   => self::date( $sf, 'OnMarketDate' ),
			'dateModified' => self::date( $sf, 'ModificationTimestamp' ),
			'image'        => self::images( $sf ),
			'offers'       => self::offer( $sf, $url ),
			'about'        => $place,
		), array( __CLASS__, 'is_present' ) );

		return array(
			'listing'     => $listing_node,
			'breadcrumbs' => self::breadcrumbs( isset( $listing_node['name'] ) ? $listing_node['name'] : $address[2], $url ),
		);
	}

	private static function offer( $sf, $url ) {
		$price = null;
		foreach ( array( 'CurrentPricePublic', 'ListPrice' ) as $key ) {
			if ( self::has_value( $sf, $key ) && is_numeric( $sf[ $key ] ) ) {
				$price = 0 + $sf[ $key ];
				break;
			}
		}
		$status = isset( $sf['StandardStatus'] ) ? $sf['StandardStatus'] : '';
		if ( null === $price && 'Closed' === $status && self::has_value( $sf, 'ClosePrice' ) && is_numeric( $sf['ClosePrice'] ) ) {
			$price = 0 + $sf['ClosePrice'];
		}
		if ( null === $price ) {
			return null;
		}

		$availability = array(
			'Active'                => 'https://schema.org/InStock',
			'Active Under Contract' => 'https://schema.org/LimitedAvailability',
			'Pending'               => 'https://schema.org/LimitedAvailability',
			'Closed'                => 'https://schema.org/SoldOut',
		);

		return array_filter( array(
			'@type'         => 'Offer',
			'url'           => $url,
			'price'         => $price,
			'priceCurrency' => apply_filters( 'flexmls_opengraph_price_currency', 'USD' ),
			'availability'  => isset( $availability[ $status ] ) ? $availability[ $status ] : null,
		), array( __CLASS__, 'is_present' ) );
	}

	private static function postal_address( $sf, $address ) {
		return array_filter( array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $address[0],
			'addressLocality' => self::has_value( $sf, 'City' ) ? $sf['City'] : null,
			'addressRegion'   => self::has_value( $sf, 'StateOrProvince' ) ? $sf['StateOrProvince'] : null,
			'postalCode'      => self::has_value( $sf, 'PostalCode' ) ? $sf['PostalCode'] : null,
			'addressCountry'  => self::has_value( $sf, 'Country' ) ? $sf['Country'] : 'US',
		), array( __CLASS__, 'is_present' ) );
	}

	private static function geo( $sf ) {
		if ( ! self::has_value( $sf, 'Latitude' ) || ! self::has_value( $sf, 'Longitude' ) || ! is_numeric( $sf['Latitude'] ) || ! is_numeric( $sf['Longitude'] ) ) {
			return null;
		}
		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => 0 + $sf['Latitude'],
			'longitude' => 0 + $sf['Longitude'],
		);
	}

	private static function floor_size( $sf ) {
		foreach ( array( 'BuildingAreaTotal', 'LivingArea' ) as $key ) {
			if ( self::has_value( $sf, $key ) && is_numeric( $sf[ $key ] ) && $sf[ $key ] > 0 ) {
				return array(
					'@type'    => 'QuantitativeValue',
					'value'    => 0 + $sf[ $key ],
					'unitCode' => 'FTK',
				);
			}
		}
		return null;
	}

	private static function images( $sf ) {
		if ( empty( $sf['Photos'] ) || ! is_array( $sf['Photos'] ) ) {
			return null;
		}
		$images = array();
		foreach ( $sf['Photos'] as $photo ) {
			$uri = ! empty( $photo['Uri1280'] ) ? $photo['Uri1280'] : ( ! empty( $photo['UriLarge'] ) ? $photo['UriLarge'] : '' );
			if ( '' !== $uri ) {
				$images[] = $uri;
			}
			if ( count( $images ) >= 10 ) {
				break;
			}
		}
		return $images ? $images : null;
	}

	private static function breadcrumbs( $listing_name, $url ) {
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => get_bloginfo( 'name' ),
					'item'     => home_url( '/' ),
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => 'Property Search',
					'item'     => trailingslashit( flexmlsConnect::make_nice_tag_url( 'search' ) ),
				),
				array(
					'@type'    => 'ListItem',
					'position' => 3,
					'name'     => $listing_name,
					'item'     => $url,
				),
			),
		);
	}

	private static function date( $sf, $key ) {
		if ( ! self::has_value( $sf, $key ) ) {
			return null;
		}
		$time = strtotime( $sf[ $key ] );
		return ( false === $time ) ? null : gmdate( 'c', $time );
	}

	private static function number( $sf, $key ) {
		return ( self::has_value( $sf, $key ) && is_numeric( $sf[ $key ] ) ) ? 0 + $sf[ $key ] : null;
	}

	private static function has_value( $sf, $key ) {
		return isset( $sf[ $key ] ) && ! is_array( $sf[ $key ] ) && flexmlsConnect::is_not_blank_or_restricted( $sf[ $key ] );
	}

	private static function is_present( $value ) {
		return null !== $value && '' !== $value && array() !== $value;
	}
}
