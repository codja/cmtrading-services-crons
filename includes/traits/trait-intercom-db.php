<?php

namespace cmsc\traits;

trait Intercom_DB {

	/*
	 * Connecting to a remote client database to collect data from the Intercom and Proline
	 */
	private function connect_cm_intercom_db(): ?\Wpdb {
		if (
			! defined( 'CM_INTERCOM_DB_USER' ) ||
			! defined( 'CM_INTERCOM_DB_PASS' ) ||
			! defined( 'CM_INTERCOM_DB_NAME' ) ||
			! defined( 'CM_INTERCOM_DB_HOST' )
		) {
			return null;
		}

		$wpdb = new \Wpdb(
			CM_INTERCOM_DB_USER,
			CM_INTERCOM_DB_PASS,
			CM_INTERCOM_DB_NAME,
			CM_INTERCOM_DB_HOST
		);

		return ! empty( $wpdb->error )
			? null
			: $wpdb;
	}

}

