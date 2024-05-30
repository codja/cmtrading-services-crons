<?php

namespace cmsc\classes\external;

use cmsc\traits\Singleton;
use Wpdb;

class Panda_DB {

	use Singleton;

	/**
	 * @var Wpdb|null
	 */
	private $panda_db;

	public function __construct() {
		$this->panda_db = $this->init();
	}

	/**
	 * Get data from Panda DB by email, from table vtiger_account
	 */
	public function get_data_from_vtiger_account_by_account_id( $account_id, string $params ): ?array {
		if ( ! $account_id || ! $params ) {
			return null;
		}

		$result = $this->execute_query(
			"SELECT $params FROM vtiger_account WHERE accountid = %d",
			absint( $account_id )
		);

		return $result
			? reset( $result )
			: null;
	}

	/**
	 * Get data from Panda DB by email, from table vtiger_account
	 */
	public function get_data_from_vtiger_account_by_email( string $email, string $params ): ?array {
		if ( ! $email || ! $params ) {
			return null;
		}

		$result = $this->execute_query(
			"SELECT $params FROM vtiger_account WHERE email = %s",
			sanitize_email( $email )
		);

		return $result
			? reset( $result )
			: null;
	}

	/**
	 * Get data from Panda DB by id, from table vtiger_users
	 */
	public function get_data_from_vtiger_users_by_id( int $id, $params ): ?array {
		if ( ! $id || ! $params ) {
			return null;
		}

		$result = $this->execute_query(
			"SELECT $params FROM vtiger_users WHERE id = %d",
			absint( $id )
		);

		return $result
			? reset( $result )
			: null;
	}

	/**
	 * Get users emails who have total and login date < 30 Days
	 */
	public function get_users_emails_if_user_active(): ?array {
		return $this->execute_query(
			"SELECT email, customer_id FROM vtiger_account WHERE state = 'Live' AND login_date > %s",
			strtotime( '- 30 Days' )
		);
	}

	/**
	 * Get effective calls (call is effective if the duration is > 600)
	 */
	public function get_effective_calls( $last_call_datetime ): ?array {
		if ( ! $last_call_datetime ) {
			return null;
		}

		// time_of_call format: 2022-01-20 15:06:35
		return $this->execute_query(
			'SELECT related_to, time_of_call FROM calls WHERE duration > 600 AND time_of_call > %s ORDER BY time_of_call ASC',
			$last_call_datetime
		);
	}

	private function execute_query( string $query, ...$args ): ?array {
		if ( ! $this->panda_db ) {
			return null;
		}

		$result = $this->panda_db->get_results(
			$this->panda_db->prepare(
				$query,
				...$args
			),
			ARRAY_A
		);

		return $result ? $result : null;
	}

	private function init(): ?Wpdb {
		if (
			! defined( 'PANDA_DB_USER' ) ||
			! defined( 'PANDA_DB_PASS' ) ||
			! defined( 'PANDA_DB_NAME' ) ||
			! defined( 'PANDA_DB_HOST' ) ||
			! $this->check_connection() ) {
			return null;
		}

		$wpdb = new Wpdb(
			PANDA_DB_USER,
			PANDA_DB_PASS,
			PANDA_DB_NAME,
			PANDA_DB_HOST
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
			PANDA_DB_HOST,
			PANDA_DB_USER,
			PANDA_DB_PASS,
			PANDA_DB_NAME
		) ) {
			return false;
		}

		$mysqli->close();

		return true;
	}

}
