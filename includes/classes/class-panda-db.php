<?php

namespace cmsc\classes;

use cmsc\traits\Singleton;

class Panda_DB {

	use Singleton;

	public function get_user_register_data( string $column, string $value, string $fields = 'account_no' ): ?array {
		if ( ! $column || ! $value ) {
			return null;
		}

		$panda_db = self::instance()->db();

		if ( ! $panda_db ) {
			return null;
		}

		$base_request = $panda_db->get_results(
			$panda_db->prepare(
				"SELECT $fields FROM vtiger_account WHERE $column = %s",
				$value
			),
			ARRAY_A
		);

		$panda_db->close();

		return $base_request
			? reset( $base_request )
			: null;
	}

	private function db(): ?\Wpdb {
		if ( ! $this->check_constants() ) {
			return null;
		}

		$panda_db = new \Wpdb(
			PANDA_DB_USER,
			PANDA_DB_PASS,
			PANDA_DB_NAME,
			PANDA_DB_HOST
		);

		if ( ! $panda_db->check_connection() ) {
			return null;
		}

		return $panda_db;
	}

	private function check_constants(): bool {
		return defined( 'PANDA_DB_USER' )
		&& defined( 'PANDA_DB_PASS' )
		&& defined( 'PANDA_DB_NAME' )
		&& defined( 'PANDA_DB_HOST' );
	}

}

