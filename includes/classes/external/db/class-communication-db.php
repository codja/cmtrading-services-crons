<?php

namespace cmsc\classes\external\db;

class Communication_DB extends DB {

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

	protected function set_constants(): void {
		$this->user    = defined( 'CRM_COMM_DB_USER' ) ? CRM_COMM_DB_USER : null;
		$this->pass    = defined( 'CRM_COMM_DB_PASS' ) ? CRM_COMM_DB_PASS : null;
		$this->db_name = defined( 'CRM_COMM_DB_NAME' ) ? CRM_COMM_DB_NAME : null;
		$this->host    = defined( 'CRM_COMM_DB_HOST' ) ? CRM_COMM_DB_HOST : null;
	}
}

