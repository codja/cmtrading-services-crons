<?php

/*
 * Send event (phone call ended) to optimove
 * We need to query it every 5 minutes and see if a new call above 90 seconds happened and send it as event.
 * https://academy.optimove.com/hc/en-us/articles/8759478086557-Using-Server-Side-Events
 */

namespace cmsc\classes\cron\tasks;

use cmsc\classes\external\Optimove;
use cmsc\classes\external\Panda_DB;

class Optimove_Phone_Call_Ended {

	const HOOK_NAME_PHONE_CALL_ENDED = 'optimove_phone_call_ended';

	const OPTION_NAME_LAST_CALL_DATETIME = 'optimove_last_call_datetime';

	const OPTION_NAME_START = 'optimove_cron_start';

	public function run() {
		if (
			! defined( 'CM_OPTIMOVE_TENANT_ID' ) ||
			! defined( 'CM_OPTIMOVE_ENDPOINT' )
			// ! defined( 'CM_OPTIMOVE_TOKEN' )
		) {
			return null;
		}
		set_time_limit( 0 );

		$is_launch = get_option( self::OPTION_NAME_START, false );
		if ( $is_launch ) {
			return;
		}
		update_option( self::OPTION_NAME_START, true, false );

		$last_call_datetime = get_option( self::OPTION_NAME_LAST_CALL_DATETIME, wp_date( 'Y-m-d h:i:s', strtotime( '- 5 minutes' ) ) );
		$calls              = Panda_DB::instance()->get_effective_calls( $last_call_datetime );

		if ( ! $calls ) {
			update_option( self::OPTION_NAME_START, false, false );
			return;
		}

		foreach ( $calls as $call ) {
			if ( ! empty( $call['related_to'] ) ) {
				$customer_data = Panda_DB::instance()->get_data_from_vtiger_account_by_account_id( $call['related_to'], 'customer_id, email' );

				$this->send_event_effective_call_optimove(
					$call['related_to'],
					$customer_data['customer_id'] ?? '',
					$call['time_of_call'] ?? ''
				);
			}
		}

		$last_call      = end( $calls );
		$last_call_time = empty( $last_call['time_of_call'] )
			? wp_date( 'Y-m-d h:i:s', strtotime( '- 5 minutes' ) )
			: $last_call['time_of_call'];

		update_option( self::OPTION_NAME_LAST_CALL_DATETIME, $last_call_time, false );
		update_option( self::OPTION_NAME_START, false, false );
	}

	private function send_event_effective_call_optimove( $related_to, $customer_id, $time_of_call ): void {
		if ( ! $related_to || ! $customer_id || ! $time_of_call ) {
			return;
		}

		$optimove = new Optimove();
		$optimove->send_event_to_optimove(
			'effective_call_ended',
			[
				'context'   => [
					'related_to' => $related_to,
				],
				'customer'  => $customer_id,
				'timestamp' => wp_date( 'Y-m-d\TH:i:s.v\Z', strtotime( $time_of_call ) ),
			]
		);
	}

}
