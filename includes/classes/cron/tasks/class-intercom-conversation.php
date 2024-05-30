<?php

/*
 * Connect to intercom API and start reading conversations from there and insert to a database.
 * Like ETL process, which means the process will work once an hour and read by modified time and then will go to the database and check if ID exists
 * if yes it will update the record, if not it will insert it.
 * Daily process to run on the entire day in case we missed anything.
 */

namespace cmsc\classes\cron\tasks;

use cmsc\classes\core\Constants;
use cmsc\classes\core\DB_Operations;
use cmsc\classes\helpers\Request_Api;
use cmsc\traits\Intercom_DB;

class Intercom_Conversation {

	use Intercom_DB;

	const HOOK_NAME_HOURLY = 'intercom_conversation_hourly';

	const HOOK_NAME_DAILY = 'intercom_conversation_daily';

	const LAST_LAUNCH_TIME_OPTION_NAME = 'cm_intercom_conversation_last_launch_time';

	const ALLOWED_RESOURCE_TYPES = [ 'contacts', 'admins' ];

	public function hourly() {
		$wpdb = $this->connect_cm_intercom_db();
		if ( ! $wpdb ) {
			return;
		}

		$last_launch_time = get_option( self::LAST_LAUNCH_TIME_OPTION_NAME, strtotime( 'today UTC' ) );
		update_option( self::LAST_LAUNCH_TIME_OPTION_NAME, time(), false );
		$this->run_import( $last_launch_time, $wpdb );
	}

	public function daily() {
		$wpdb = $this->connect_cm_intercom_db();
		if ( ! $wpdb ) {
			return;
		}

		$this->run_import( strtotime( '- 1 day' ), $wpdb );
	}

	private function request_to_intercom( $start_launch_time, $pagination = [] ) {
		if ( ! $start_launch_time || ! defined( 'INTERCOM_CONVERSATION_TOKEN' ) ) {
			return false;
		}

		$args = [
			'query' => [
				'operator' => 'AND',
				'value'    => [
					[
						'field'    => 'updated_at',
						'operator' => '>',
						'value'    => $start_launch_time,
					],
				],
			],
		];

		if ( $pagination ) {
			$args['pagination'] = $pagination;
		}

		return Request_Api::send_api(
			'https://api.intercom.io/conversations/search',
			wp_json_encode( $args ),
			'POST',
			[
				'Authorization'    => 'Bearer ' . INTERCOM_CONVERSATION_TOKEN,
				'Intercom-Version' => '2.10',
				'Content-Type'     => 'application/json',
			]
		);
	}

	private function run_import( $start_launch_time, \Wpdb $wpdb, $next_page = [] ) {
		if ( ! $start_launch_time ) {
			return;
		}
		set_time_limit( 0 );
		$response = $this->request_to_intercom( $start_launch_time, $next_page );
		if ( ! $response ) {
			return;
		}

		$this->insert_conversations_into_db( $response['conversations'] ?? [], $wpdb );
		if ( ! empty( $response['pages']['next'] ) ) {
			$this->run_import( $start_launch_time, $wpdb, $response['pages']['next'] );
		}
	}

	private function insert_conversations_into_db( $conversations, \Wpdb $wpdb ) {
		if ( empty( $conversations ) ) {
			return;
		}

		$tracking_table = Constants::TABLE_INTERCOM_CONVERSATION_NAME;
		$sql            = "INSERT INTO $tracking_table (
							ID,
							related_to,
							assigned_to,
							Conversation_status,
							conversation_source,
							rating,
							rating_assigned_to,
							rating_created_at,
							time_to_assignment,
							time_to_admin_reply,
							time_to_first_close,
							time_to_last_close,
							median_time_to_reply,
							first_contact_reply_at,
							first_assignment_at,
							first_admin_reply_at,
							first_close_at,
							last_assignment_at,
							last_assignment_admin_reply_at,
							last_contact_reply_at,
							last_admin_reply_at,
							last_close_at,
							last_closed_by_id,
							count_reopens,
							count_assignments,
							count_conversation_parts,
							Modified_date,
							Created_date ) VALUES ";

		foreach ( $conversations as $key => $conversation ) {
			// This is the conversation 'ID'
			$id = (int) ( $conversation['id'] ?? 0 );
			// This is who the email/conversation was sent to
			$related_to = $this->get_related_to( $conversation );
			// This field will take the email of the sender
			$assign_to = $this->get_assign_to( $conversation );
			// This is the ‘state' field
			$conversation_status = $conversation['state'] ?? '';
			// There is an object called ‘source’ we need the field called 'type' from there
			$conversation_source = $conversation['source']['type'] ?? null;
			// This is the ‘rating' field
			$rating = ! empty( $conversation['conversation_rating']['rating'] )
				? (int) $conversation['conversation_rating']['rating']
				: null;
			// From the field id under the 'teammate' object, but we need it as the assigned to - we need the user email address
			$rating_assigned_to = $this->get_resource_email( [ $conversation['conversation_rating']['teammate'] ?? [] ], 'admins' );
			// This is the ‘created_at’ for rating field, and it’s in unix time so need to convert it into regular UTC time
			$rating_created_at = $conversation['conversation_rating']['created_at'] ?? null;
			$rating_created_at = $rating_created_at
				? wp_date( 'Y-m-d H:i:s', $rating_created_at, new \DateTimeZone( 'UTC' ) )
				: null;
			// There is an object called ‘statistics' and from there we need all the fields besides 'type’
			$time_to_assignment             = $conversation['statistics']['time_to_assignment'] ?? null;
			$time_to_admin_reply            = $conversation['statistics']['time_to_admin_reply'] ?? null;
			$time_to_first_close            = $conversation['statistics']['time_to_first_close'] ?? null;
			$time_to_last_close             = $conversation['statistics']['time_to_last_close'] ?? null;
			$median_time_to_reply           = $conversation['statistics']['median_time_to_reply'] ?? null;
			$first_contact_reply_at         = $conversation['statistics']['first_contact_reply_at'] ?? null;
			$first_assignment_at            = $conversation['statistics']['first_assignment_at'] ?? null;
			$first_admin_reply_at           = $conversation['statistics']['first_admin_reply_at'] ?? null;
			$first_close_at                 = $conversation['statistics']['first_close_at'] ?? null;
			$last_assignment_at             = $conversation['statistics']['last_assignment_at'] ?? null;
			$last_assignment_admin_reply_at = $conversation['statistics']['last_assignment_admin_reply_at'] ?? null;
			$last_contact_reply_at          = $conversation['statistics']['last_contact_reply_at'] ?? null;
			$last_admin_reply_at            = $conversation['statistics']['last_admin_reply_at'] ?? null;
			$last_close_at                  = $conversation['statistics']['last_close_at'] ?? null;
			$last_closed_by_id              = $conversation['statistics']['last_closed_by_id'] ?? null;
			$count_reopens                  = $conversation['statistics']['count_reopens'] ?? null;
			$count_assignments              = $conversation['statistics']['count_assignments'] ?? null;
			$count_conversation_parts       = $conversation['statistics']['count_conversation_parts'] ?? null;
			// This is the ‘created_at’ field, and it’s in unix time so need to convert it into regular UTC time
			$modified_date = $conversation['updated_at'] ?? null;
			// This is the ‘created_at’ field, and it’s in unix time so need to convert it into regular UTC time
			$created_date = $conversation['created_at'] ?? null;

			if (
				! $id
				|| ! $related_to
				|| ! $assign_to
				|| ! $conversation_status
				|| ! $modified_date
				|| ! $created_date
			) {
				continue;
			}

			$sql .= DB_Operations::prepare_values(
				[
					$id,
					$related_to,
					$assign_to,
					$conversation_status,
					$conversation_source,
					$rating,
					$rating_assigned_to,
					$rating_created_at,
					$time_to_assignment,
					$time_to_admin_reply,
					$time_to_first_close,
					$time_to_last_close,
					$median_time_to_reply,
					$first_contact_reply_at,
					$first_assignment_at,
					$first_admin_reply_at,
					$first_close_at,
					$last_assignment_at,
					$last_assignment_admin_reply_at,
					$last_contact_reply_at,
					$last_admin_reply_at,
					$last_close_at,
					$last_closed_by_id,
					$count_reopens,
					$count_assignments,
					$count_conversation_parts,
					wp_date( 'Y-m-d H:i:s', $modified_date, new \DateTimeZone( 'UTC' ) ),
					wp_date( 'Y-m-d H:i:s', $created_date, new \DateTimeZone( 'UTC' ) ),
				]
			);

			$sql .= ', ';
		}

		$sql  = rtrim( $sql, ', ' );
		$sql .= ' ON DUPLICATE KEY UPDATE
			related_to = VALUES(related_to),
			assigned_to = VALUES(assigned_to),
			Conversation_status = VALUES(Conversation_status),
			conversation_source = VALUES(conversation_source),
			rating = VALUES(rating),
			rating_assigned_to = VALUES(rating_assigned_to),
			rating_created_at = VALUES(rating_created_at),
			time_to_assignment = VALUES(time_to_assignment),
			time_to_admin_reply = VALUES(time_to_admin_reply),
			time_to_first_close = VALUES(time_to_first_close),
			time_to_last_close = VALUES(time_to_last_close),
			median_time_to_reply = VALUES(median_time_to_reply),
			first_contact_reply_at = VALUES(first_contact_reply_at),
			first_assignment_at = VALUES(first_assignment_at),
			first_admin_reply_at = VALUES(first_admin_reply_at),
			first_close_at = VALUES(first_close_at),
			last_assignment_at = VALUES(last_assignment_at),
			last_assignment_admin_reply_at = VALUES(last_assignment_admin_reply_at),
			last_contact_reply_at = VALUES(last_contact_reply_at),
			last_admin_reply_at = VALUES(last_admin_reply_at),
			last_close_at = VALUES(last_close_at),
			last_closed_by_id = VALUES(last_closed_by_id),
			count_reopens = VALUES(count_reopens),
			count_assignments = VALUES(count_assignments),
			count_conversation_parts = VALUES(count_conversation_parts),
			Modified_date = VALUES(Modified_date),
            Created_date = VALUES(Created_date);';

		 $wpdb->query( $sql ); // phpcs:ignore
	}

	private function get_related_to( $conversation ) {
		if ( ! $conversation ) {
			return '';
		}

		$contacts    = $conversation['contacts']['contacts'] ?? [];
		$admin_email = $this->get_resource_email( $contacts, 'contacts' );
		if ( $admin_email ) {
			return $admin_email;
		}

		return $conversation['contacts']['contacts'][0]['external_id'] ?? '';
	}

	private function get_assign_to( $conversation ): string {
		if ( ! $conversation ) {
			return '';
		}

		$admins      = $conversation['teammates']['admins'] ?? [];
		$admin_email = $this->get_resource_email( $admins, 'admins' );
		if ( $admin_email ) {
			return $admin_email;
		}

		return $conversation['source']['author']['email'] ?? '';
	}

	private function get_resource_email( array $data, string $type ): string {
		if ( ! $data || ! in_array( $type, self::ALLOWED_RESOURCE_TYPES, true ) ) {
			return '';
		}

		$resource    = reset( $data );
		$resource_id = $resource['id'] ?? null;

		if ( ! $resource_id ) {
			return '';
		}

		$response = Request_Api::send_api(
			"https://api.intercom.io/{$type}/{$resource_id}",
			[],
			'GET',
			[
				'Authorization'    => 'Bearer ' . INTERCOM_CONVERSATION_TOKEN,
				'Intercom-Version' => '2.10',
				'Content-Type'     => 'application/json',
			]
		);

		if ( ! $response ) {
			return '';
		}

		return $response['email'] ?? '';
	}

}
