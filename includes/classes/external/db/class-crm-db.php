<?php

namespace cmsc\classes\external\db;

class CRM_DB extends DB {

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

	protected function set_constants(): void {
		$this->user    = defined( 'CRM_DB_USER' ) ? CRM_DB_USER : null;
		$this->pass    = defined( 'CRM_DB_PASS' ) ? CRM_DB_PASS : null;
		$this->db_name = defined( 'CRM_DB_NAME' ) ? CRM_DB_NAME : null;
		$this->host    = defined( 'CRM_DB_HOST' ) ? CRM_DB_HOST : null;
	}

}
