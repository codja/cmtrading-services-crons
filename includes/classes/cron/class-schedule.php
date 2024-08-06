<?php

namespace cmsc\classes\cron;

use cmsc\classes\cron\tasks\Intercom_Conversation;
use cmsc\classes\cron\tasks\Optimove_Phone_Call_Ended;
use cmsc\classes\cron\tasks\Optimove_Trading_Signal;
use cmsc\classes\cron\tasks\Proline_Payments;
use cmsc\classes\helpers\Helpers;

/**
 * Schedule for Cron tasks
 */
class Schedule {

	public function __construct() {
		add_filter( 'cron_schedules', [ $this, 'register_new_intervals' ] );
//		add_action( Intercom_Conversation::HOOK_NAME_HOURLY, [ new Intercom_Conversation(), 'hourly' ] );
//		add_action( Intercom_Conversation::HOOK_NAME_DAILY, [ new Intercom_Conversation(), 'daily' ] );

		add_action( Proline_Payments::HOOK_NAME_DAILY, [ new Proline_Payments(), 'daily' ] );
		add_action( Proline_Payments::HOOK_NAME_WEEKLY, [ new Proline_Payments(), 'weekly' ] );
		add_action( Proline_Payments::HOOK_NAME_ONE_TIME, [ new Proline_Payments(), 'one_time' ] );

		add_action( Optimove_Phone_Call_Ended::HOOK_NAME_PHONE_CALL_ENDED, [ new Optimove_Phone_Call_Ended(), 'run' ] );
		add_action( Optimove_Trading_Signal::HOOK_NAME_TRADING_SIGNAL, [ new Optimove_Trading_Signal(), 'run' ] );
	}

	public static function add_tasks(): void {
//		if ( ! wp_next_scheduled( Intercom_Conversation::HOOK_NAME_HOURLY ) ) {
//			wp_schedule_event( strtotime( '+ 1 hour' ), 'hourly', Intercom_Conversation::HOOK_NAME_HOURLY );
//		}
//
//		if ( ! wp_next_scheduled( Intercom_Conversation::HOOK_NAME_DAILY ) ) {
//			wp_schedule_event( time(), 'daily', Intercom_Conversation::HOOK_NAME_DAILY );
//		}

		if ( ! wp_next_scheduled( Proline_Payments::HOOK_NAME_DAILY ) ) {
			wp_schedule_event( Helpers::get_timestamp_next_night(), 'daily', Proline_Payments::HOOK_NAME_DAILY );
		}

		if ( ! wp_next_scheduled( Proline_Payments::HOOK_NAME_WEEKLY ) ) {
			wp_schedule_event( Helpers::get_timestamp_next_saturday_night(), 'weekly', Proline_Payments::HOOK_NAME_WEEKLY );
		}

		if ( ! wp_next_scheduled( Proline_Payments::HOOK_NAME_ONE_TIME ) ) {
			wp_schedule_event( strtotime( '+ 5 years' ), 'monthly', Proline_Payments::HOOK_NAME_ONE_TIME );
		}

		if ( ! wp_next_scheduled( Optimove_Phone_Call_Ended::HOOK_NAME_PHONE_CALL_ENDED ) ) {
			wp_schedule_event( time(), '5_min', Optimove_Phone_Call_Ended::HOOK_NAME_PHONE_CALL_ENDED );
		}

		if ( ! wp_next_scheduled( Optimove_Trading_Signal::HOOK_NAME_TRADING_SIGNAL ) ) {
			wp_schedule_event( time(), '15_min', Optimove_Trading_Signal::HOOK_NAME_TRADING_SIGNAL );
		}

	}

	public static function remove_tasks(): void {
//		wp_clear_scheduled_hook( Intercom_Conversation::HOOK_NAME_HOURLY );
//		wp_clear_scheduled_hook( Intercom_Conversation::HOOK_NAME_DAILY );
		wp_clear_scheduled_hook( Proline_Payments::HOOK_NAME_DAILY );
		wp_clear_scheduled_hook( Proline_Payments::HOOK_NAME_WEEKLY );
		wp_clear_scheduled_hook( Proline_Payments::HOOK_NAME_ONE_TIME );
		wp_clear_scheduled_hook( Optimove_Phone_Call_Ended::HOOK_NAME_PHONE_CALL_ENDED );
		wp_clear_scheduled_hook( Optimove_Trading_Signal::HOOK_NAME_TRADING_SIGNAL );
	}

	public function register_new_intervals( $schedule ) {
		$schedule['20_min'] = [
			'interval' => 20 * 60,
			'display'  => esc_html__( 'Every 20 minutes', 'cmtrading-services-crons' ),
		];

		$schedule['15_min'] = [
			'interval' => 15 * 60,
			'display'  => esc_html__( 'Every 15 minutes', 'cmtrading-services-crons' ),
		];

		$schedule['5_min'] = [
			'interval' => MINUTE_IN_SECONDS * 5,
			'display'  => esc_html__( 'Every 5 minutes', 'cmtrading-services-crons' ),
		];

		$schedule['monthly'] = [
			'interval' => MONTH_IN_SECONDS,
			'display'  => esc_html__( '30 days', 'cmtrading-services-crons' ),
		];

		$schedule['weekly'] = [
			'interval' => WEEK_IN_SECONDS,
			'display'  => esc_html__( 'Once Weekly', 'cmtrading-services-crons' ),
		];

		return $schedule;
	}

}
