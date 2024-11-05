<?php

/*
 * https://academy.optimove.com/hc/en-us/articles/8759478086557-Using-Server-Side-Events
 */

namespace cmsc\classes\external;

use cmsc\classes\helpers\Helpers;
use cmsc\classes\helpers\Request_Api;

class Optimove {

	public function send_event_to_optimove( $event, $data = [] ) {
		if ( ! defined( 'CM_OPTIMOVE_TENANT_ID' )
			|| ! defined( 'CM_OPTIMOVE_ENDPOINT' )
//			|| ! defined( 'CM_OPTIMOVE_TOKEN' )
			|| ! $event
		) {
			return null;
		}

		$data = wp_json_encode(
			[
				'tenant' => CM_OPTIMOVE_TENANT_ID,
				'event'  => $event,
			] + $data
		);

		$response = Request_Api::send_api(
			CM_OPTIMOVE_ENDPOINT,
			$data,
			'POST',
			[
				//'X-Optimove-Signature-Content' => $this->generate_hash( $data ), // for API V2
				//'X-Optimove-Signature-Version' => 1,
				'Content-Type' => 'application/json',
			]
		);

		Helpers::log_error( $event, wp_json_encode( $response ), 'optimove' );

		return $response;
	}

	private function generate_hash( $raw_text_body ): string {
		$hashed = hash_hmac( 'sha256', wp_json_encode( $raw_text_body ), CM_OPTIMOVE_TOKEN, true );
		return bin2hex( $hashed );
	}

}
