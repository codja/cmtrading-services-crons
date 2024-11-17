<?php

namespace cmsc\classes\helpers;

abstract class Helpers {

	/**
	 * Check if value is array and return it or return an empty array if value is not array
	 */
	public static function get_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return $value;
	}

	/**
	 * Trim string or return second argument if string is empty
	 *
	 * @param $value
	 * @param $return_if_not_string
	 *
	 * @return mixed|string
	 */
	public static function trim_string( $value, $return_if_not_string = '' ) {
		if ( ! is_string( $value ) ) {
			return $return_if_not_string;
		}

		return trim( $value );
	}


	public static function get_timestamp_next_night(): int {
		return self::get_timestamp_night();
	}

	public static function get_timestamp_next_saturday_night(): int {
		return self::get_timestamp_night( 'next saturday' );
	}

	public static function get_timestamp_night( $datetime = 'tomorrow' ): int {
		$tomorrow = strtotime( $datetime );
		if ( ! $tomorrow ) {
			return time();
		}

		$tomorrow_begin_day = strtotime( wp_date( 'Y-m-d 00:00:00', $tomorrow ) );
		if ( ! $tomorrow_begin_day ) {
			return $tomorrow;
		}

		$next_hour_timestamp = strtotime( '+ 2 hour 30 minutes', $tomorrow_begin_day );
		if ( ! $next_hour_timestamp ) {
			return $tomorrow_begin_day;
		}

		return $next_hour_timestamp;
	}

	/**
	 * @throws \Exception
	 */
	public static function is_time_within_range( string $start_time, string $end_time, string $timezone = 'Asia/Jerusalem' ): bool {
		if ( ! $start_time || ! $end_time ) {
			return false;
		}

		$timezone_he  = new \DateTimeZone( $timezone );
		$current_time = new \DateTime( 'now', $timezone_he );
		$start_time   = new \DateTime( $start_time, $timezone_he );
		$end_time     = new \DateTime( $end_time, $timezone_he );

		return $current_time >= $start_time && $current_time <= $end_time;
	}

	public static function convert( $size ): string {
		if ( ! $size ) {
			return '';
		}

		$unit = [ 'b', 'kb', 'mb', 'gb', 'tb', 'pb' ];
		return @round( $size / pow( 1024, ( $i = floor( log( $size, 1024 ) ) ) ), 2 ) . ' ' . $unit[ $i ]; // phpcs:ignore
	}

	public static function log_error( string $title, string $error, string $filename = 'error' ): void {
		if ( ! $title || ! $error ) {
			return;
		}

		error_log(
			'[' . gmdate( 'Y-m-d H:i:s' ) . '] Error: {' . $title . ':' . $error . "} \n===========\n",
			3,
			WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $filename . '-' . wp_date( 'd-m-Y' ) . '.log'
		);
	}

}
