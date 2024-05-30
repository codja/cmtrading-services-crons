<?php

namespace cmsc\classes\models;

use cmsc\classes\core\Constants;
use cmsc\classes\core\DB_Operations;

class Intermediate_Trading_Signal_Table {

	private \wpdb $wpdb;

	private string $table_name;

	public function __construct() {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . Constants::TABLE_INTERMEDIATE_TRADING_SIGNAL_NAME;
	}

	/**
	 * Get data from the intermediate table
	 *
	 * @param int $limit
	 *
	 * @return array
	 */
	public function get_data( int $limit = 100 ): array {
		// phpcs:disable
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, email, customer_id FROM %i LIMIT %d",
				$this->table_name,
				$limit
			)
		);
		// phpcs:enable
	}

	/**
	 * Remove data from the intermediate table
	 *
	 * @param array $ids
	 */
	public function remove_records( array $ids ): void {
		if ( ! $ids ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM %i WHERE ID IN ($placeholders)",
				$this->table_name,
				...$ids
			)
		);
		// phpcs:enable
	}

	/**
	 * Save users to the intermediate table
	 *
	 * @param array $users
	 */
	public function save( array $users ): void {
		if ( ! $users ) {
			return;
		}

		$sql = "INSERT INTO $this->table_name (email, customer_id, created_date) VALUES ";
		foreach ( $users as $part_user ) {
			$sql .= DB_Operations::prepare_values(
				[
					$part_user['email'] ?? '',
					$part_user['customer_id'] ?? '',
					wp_date( 'Y-m-d H:i:s', null, new \DateTimeZone( 'UTC' ) ),
				]
			);
			$sql .= ', ';
		}
		$sql = rtrim( $sql, ', ' );

		$this->wpdb->query( $sql ); // phpcs:ignore
	}

	/*
	 * Truncate table (reset auto increment)
	 */
	public function truncate(): void {
		// phpcs:disable
		$this->wpdb->query(
			$this->wpdb->prepare(
				"TRUNCATE %i",
				$this->table_name
			)
		);
		// phpcs:enable
	}

}
