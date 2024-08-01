<?php

namespace cmsc\classes\external\db;

abstract class DB {

	protected const USER = '';

	protected const PASS = '';

	protected const DB_NAME = '';

	protected const HOST = '';

	/**
	 * @var Wpdb|null
	 */
	private ?Wpdb $crm_db;

	public function __construct() {
		$this->crm_db = $this->init();
	}

	protected function execute_query( string $query, ...$args ): ?array {
		if ( ! $this->crm_db ) {
			return null;
		}

		$result = $this->crm_db->get_results(
			$this->crm_db->prepare(
				$query,
				...$args
			),
			ARRAY_A
		);

		return $result ? $result : null;
	}

	private function init(): ?\Wpdb {
		if ( ! $this->check_connection() ) {
			return null;
		}

		$wpdb = new \Wpdb(
			static::USER,
			static::PASS,
			static::DB_NAME,
			static::HOST
		);

		return ! empty( $wpdb->error )
			? null
			: $wpdb;
	}

	private function check_connection(): bool {
		$timeout = 5;
		$mysqli  = mysqli_init(); // phpcs:ignore

		if ( ! $mysqli ) {
			return false;
		}

		if ( ! $mysqli->options( MYSQLI_OPT_CONNECT_TIMEOUT, $timeout ) ) {
			return false;
		}

		if ( ! $mysqli->real_connect(
			static::HOST,
			static::USER,
			static::PASS,
			static::DB_NAME,
		) ) {
			return false;
		}

		$mysqli->close();

		return true;
	}

}
