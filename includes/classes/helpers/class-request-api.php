<?php

namespace cmsc\classes\helpers;

/*
 * Class for send remote request
*/

abstract class Request_Api {

	/**
	 * Send remote request, get response
	 *
	 * @param string $url
	 * @param mixed $body
	 * @param string $method
	 * @param array $headers
	 *
	 * @return false|mixed
	 */
	public static function send_api(
		string $url,
		$body = [],
		string $method = 'GET',
		array $headers = []
	) {
		$request = wp_remote_request(
			$url,
			[
				'headers' => $headers,
				'method'  => $method,
				'body'    => $body,
				'timeout' => 90,
			],
		);

		return is_wp_error( $request )
			? false
			: json_decode( wp_remote_retrieve_body( $request ), true );
	}

}
