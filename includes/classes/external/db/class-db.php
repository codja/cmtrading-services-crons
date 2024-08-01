<?php

namespace cmsc\classes\external\db;

use cmsc\classes\helpers\Helpers;

abstract class DB {

	protected ?string $user;

	protected ?string $pass;

	protected ?string $host;

	protected ?string $db_name;

	/**
	 * @var Wpdb|null
	 */
	private ?Wpdb $crm_db;

	public function __construct() {
		$this->set_constants();
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
			$this->user,
			$this->pass,
			$this->db_name,
			$this->host,
		);

		return ! empty( $wpdb->error )
			? null
			: $wpdb;
	}

	private function check_connection(): bool {
		if ( ! $this->user
			|| ! $this->pass
			|| ! $this->host
			|| ! $this->db_name
		) {
			Helpers::log_error(
				'Error: ',
				static::class . __( ':  The properties for connecting to the database are not set', 'cmtrading-services-crons' ),
				'system.log'
			);

			return false;
		}

		$timeout = 5;
		$mysqli  = mysqli_init(); // phpcs:ignore

		if ( ! $mysqli ) {
			return false;
		}

		if ( ! $mysqli->options( MYSQLI_OPT_CONNECT_TIMEOUT, $timeout ) ) {
			return false;
		}

		if ( ! $mysqli->real_connect(
			$this->host,
			$this->user,
			$this->pass,
			$this->db_name,
		) ) {
			return false;
		}

		$mysqli->close();

		return true;
	}

	abstract protected function set_constants(): void;

}
