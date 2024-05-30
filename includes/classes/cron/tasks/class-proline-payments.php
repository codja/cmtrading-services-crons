<?php

/*
 * Runs to proline and read data from a certain report they have
 */

namespace cmsc\classes\cron\tasks;

use cmsc\classes\core\Constants;
use cmsc\classes\core\DB_Operations;
use cmsc\classes\helpers\Request_Api;
use cmsc\traits\Intercom_DB;

class Proline_Payments {

	use Intercom_DB;

	const HOOK_NAME_DAILY = 'proline_payments_daily';

	const HOOK_NAME_WEEKLY = 'proline_payments_weekly';

	const HOOK_NAME_ONE_TIME = 'proline_payments_one_time';

	public function daily() {
		$wpdb = $this->connect_cm_intercom_db();
		if ( ! $wpdb ) {
			return;
		}

		$this->run_import( $this->get_time_range_previous_five_days(), $wpdb );
	}

	public function weekly() {
		$wpdb = $this->connect_cm_intercom_db();
		if ( ! $wpdb ) {
			return;
		}

		$this->run_import( $this->get_time_range_previous_month(), $wpdb );
	}

	public function one_time() {
		$wpdb = $this->connect_cm_intercom_db();
		if ( ! $wpdb ) {
			return;
		}

		$this->run_import( $this->get_time_range( '-1 day', 'today' ), $wpdb );
	}


	private function run_import( $time_range, \Wpdb $wpdb ) {
		if ( ! $time_range ) {
			return;
		}
		set_time_limit( 0 );
		$response = $this->request_to_proline( $time_range );
		if ( ! $response ) {
			return;
		}

		$this->insert_into_db( $response['DataTable'] ?? [], $wpdb );
	}

	private function insert_into_db( $data, \Wpdb $wpdb ) {
		if ( empty( $data ) ) {
			return;
		}

		$tracking_table = Constants::TABLE_PROLINE_PAYMENTS_NAME;
		$sql            = "INSERT INTO $tracking_table (AccountID, AffiliateId, CrmId, CurrentDealId, PerformanceCommissionMainCurrency, SignupDate, QualifiedFtdDate, AcquisitionDate, AcquisitionCost, AcquisitionDealId, AcquisitionDealType) VALUES ";

		foreach ( $data as $data_item ) {
			$account_id = ! empty( $data_item['AccountId'] ) ? (int) $data_item['AccountId'] : null;
			if ( ! $account_id ) {
				continue;
			}

			error_log(
				'[' . gmdate( 'Y-m-d H:i:s' ) . "] account_id: { $account_id } post: { {$data_item['CrmId']} } PerformanceCommissionMainCurrency: { {$data_item['PerformanceCommissionMainCurrency']} AcquisitionDate: {$data_item['AcquisitionDate']} AcquisitionCost: {$data_item['AcquisitionCost']} AcquisitionDealId: {$data_item['AcquisitionDealId']} AcquisitionDealType: {$data_item['AcquisitionDealType']} }  \n===========\n",
				3,
				WP_CONTENT_DIR . '/proline_cron_check.log'
			);

			$sql .= DB_Operations::prepare_values(
				[
					$account_id,
					! empty( $data_item['AffiliateId'] ) ? (int) $data_item['AffiliateId'] : null,
					$data_item['CrmId'] ?? null,
					! empty( $data_item['CurrentDealId'] ) ? (int) $data_item['CurrentDealId'] : null,
					$data_item['PerformanceCommissionMainCurrency'] ?? null,
					$data_item['SignupDate'] ?? null,
					$data_item['QualifiedFtdDate'] ?? null,
					$data_item['AcquisitionDate'] ?? null,
					! empty( $data_item['AcquisitionCost'] ) ? (float) $data_item['AcquisitionCost'] : null,
					! empty( $data_item['AcquisitionDealId'] ) ? (int) $data_item['AcquisitionDealId'] : null,
					$data_item['AcquisitionDealType'] ?? null,
				]
			);

			$sql .= ', ';
		}

		$sql  = rtrim( $sql, ', ' );
		$sql .= ' ON DUPLICATE KEY UPDATE
			PerformanceCommissionMainCurrency = VALUES(PerformanceCommissionMainCurrency),
			AcquisitionDate = VALUES(AcquisitionDate),
			AcquisitionCost = VALUES(AcquisitionCost),
			AcquisitionDealId = VALUES(AcquisitionDealId),
			AcquisitionDealType = VALUES(AcquisitionDealType);';

		 $wpdb->query( $sql ); // phpcs:ignore
	}

	private function request_to_proline( $time_range ) {
		if ( ! $time_range || ! defined( 'CM_PROLINE_TOKEN' ) ) {
			return false;
		}

		$args = [
			'userId'     => 110002,
			'reportName' => 'Customers',
			'selections' => [
				'filters'     => [
					'ActivityRange' => [
						'value' => $time_range,
						'isNot' => false,
					],
				],
				'columns'     => [
					'AccountId',
					'AffiliateId',
					'CrmId',
					'CurrentDealId',
					'PerformanceCommissionMainCurrency',
					'SignupDate',
					'QualifiedFtdDate',
					'AcquisitionDate',
					'AcquisitionCost',
					'AcquisitionDealId',
					'AcquisitionDealType',
				],
				'drillDownBy' => [],
			],
		];

		return Request_Api::send_api(
			'https://partners.cmtrading.com/WebApi/apiByToken/Affiliates/Report',
			wp_json_encode( $args ),
			'POST',
			[
				'Authorization' => CM_PROLINE_TOKEN,
				'Content-Type'  => 'application/json',
			]
		);
	}

	private function get_time_range_previous_five_days(): ?string {
		return $this->get_time_range( '-5 days', '-1 day' );
	}

	private function get_time_range_previous_day(): ?string {
		return $this->get_time_range( '-1 day', '-1 day' );
	}

	private function get_time_range_previous_two_months(): ?string {
		return $this->get_time_range( '-60 days', '-1 day' );
	}

	private function get_time_range_previous_month(): ?string {
		return $this->get_time_range( '-31 days', '-1 day' );
	}

	/*
	 * format '2024/02/13,2024/02/13 23:59:59'
	 */
	private function get_time_range( string $begin_offset, string $end_offset ): ?string {
		$begin_time = strtotime( $begin_offset );
		$end_time   = strtotime( $end_offset );

		if ( ! $begin_time || ! $end_time ) {
			return null;
		}

		$begin = wp_date( 'Y/m/d', $begin_time );
		$end   = wp_date( 'Y/m/d 23:59:59', $end_time );

		return $begin . ',' . $end;
	}

}
