<?php

namespace cmsc\classes\core;

class DB_Operations {

	const DEFAULT_DATETIME = '0000-00-00 00:00:00';

	public function __construct() {
		// We use remote client's DB
		// $this->create_intercom_conversation_table();
		$this->create_intermediate_trading_signal_table();
	}

	public static function table_exists( $table ): bool {
		if ( ! $table ) {
			return false;
		}

		global $wpdb;
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table )
			)
		);

		if ( \is_wp_error( $table_exists ) || \is_null( $table_exists ) ) {
			return false;
		}

		return true;
	}

	public static function procedure_exists( $procedure_name ): bool {
		if ( ! $procedure_name || ! defined( 'DB_NAME' ) ) {
			return false;
		}

		global $wpdb;
		$procedure = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*)
						FROM information_schema.routines
						WHERE ROUTINE_NAME = %s
						AND ROUTINE_SCHEMA = %s;',
				sanitize_text_field( $procedure_name ),
				DB_NAME
			)
		);

		return (bool) $procedure;
	}

	/*
	 * Create table for save user data from Panda DB (For Trading RSS)
	 */
	private function create_intermediate_trading_signal_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . Constants::TABLE_INTERMEDIATE_TRADING_SIGNAL_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		if ( self::table_exists( $table_name ) ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
        ID                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        email          		VARCHAR(100) NOT NULL default '',
        customer_id         VARCHAR(255) NULL,
        created_date        datetime NOT NULL default '0000-00-00 00:00:00',
        PRIMARY KEY (ID)
    	) $charset_collate;";

		$this->apply_sql( $sql );
	}

	/*
	 * Create table for conversations from Intercom
	 */
	private function create_intercom_conversation_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . Constants::TABLE_INTERCOM_CONVERSATION_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		if ( self::table_exists( $table_name ) ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
        ID                  BIGINT(20) UNSIGNED NOT NULL,
        related_to          VARCHAR(255) NOT NULL default '',
        assigned_to         VARCHAR(255) NOT NULL default '',
        Conversation_status VARCHAR(255) NOT NULL default '',
        rating              TINYINT UNSIGNED NOT NULL default 0,
        rating_assigned_to  VARCHAR(255) NOT NULL default '',
        rating_created_at   datetime NOT NULL default '0000-00-00 00:00:00',
        Modified_date       datetime NOT NULL default '0000-00-00 00:00:00',
        Created_date        datetime NOT NULL default '0000-00-00 00:00:00',
        PRIMARY KEY (ID)
    	) $charset_collate;";

		$this->apply_sql( $sql );
	}

	/*
	 * It is used as an alternative to wpdb->prepare, to support the null value
	 */
	public static function prepare_values( array $source_values ): string {
		if ( ! $source_values ) {
			return '';
		}

		$result = '(';
		foreach ( $source_values as $key => $value ) {
			switch ( true ) {
				case is_null( $value ):
					$param = 'null';
					break;
				case is_int( $value ):
					$param = $value;
					break;
				default:
					$param = "'" . esc_sql( $value ) . "'";
			}

			$result .= $param;
			$result .= $key !== array_key_last( $source_values )
				? ', '
				: ')';
		}

		return $result;
	}

	private function apply_sql( $sql ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

}
