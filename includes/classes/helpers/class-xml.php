<?php

namespace cmsc\classes\helpers;

/*
 * Class for work with XML
*/

abstract class XML {

	public static function parse_from_url( $url ) {
		if ( ! $url ) {
			return false;
		}

		// Fetch the XML data using WP_Http
		$response = wp_remote_get( $url );

		// Check for errors in the response
		if ( is_wp_error( $response ) ) {
			// Handle error
			return new \WP_Error( 'xml_parse_error', $response->get_error_message() );
		}

		// Extract XML data from the response body
		$xml_data = wp_remote_retrieve_body( $response );

		// Parse the XML data
		return simplexml_load_string( $xml_data );
	}

}
