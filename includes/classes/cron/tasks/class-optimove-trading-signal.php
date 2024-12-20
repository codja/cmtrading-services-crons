<?php

/*
 * Send event (Trading Signal) to optimove
 * We want to start working on connecting trading central RSS signals to Optimove.
 * We need to run this once every 15 minutes between 11:00 until 22:00
 */

namespace cmsc\classes\cron\tasks;

use cmsc\classes\external\db\CRM_DB;
use cmsc\classes\external\Optimove;
use cmsc\classes\helpers\Helpers;
use cmsc\classes\helpers\XML;
use cmsc\classes\models\Intermediate_Trading_Signal_Table;

class Optimove_Trading_Signal {

	const HOOK_NAME_TRADING_SIGNAL = 'optimove_trading_signal_hourly';

	const HOOK_NAME_TRADING_SIGNAL_RECURSE = 'optimove_trading_signal_recurse';

	const OPTION_NAME_START = 'optimove_trading_signal_start';

	const OPTION_TRADING_SIGNAL_DATA = 'trading_signal_data_from_feed';

	const FEED_URL = 'https://feed.tradingcentral.io/rss_ta.ashx?culture=en-US&product=&term=intraday&embedded_image=true&days=1&last_at=false&partner=1614';

	private Intermediate_Trading_Signal_Table $trading_signal_table;

	private Optimove $optimove;

	public function __construct() {
		$this->trading_signal_table = new Intermediate_Trading_Signal_Table();
		$this->optimove             = new Optimove();
	}

	/**
	 * @throws \Exception
	 */
	public function run() {
		if (
			! defined( 'CM_OPTIMOVE_TENANT_ID' )
			|| ! defined( 'CM_OPTIMOVE_ENDPOINT' )
			// ! defined( 'CM_OPTIMOVE_TOKEN' )
			|| ! Helpers::is_time_within_range( '11:00:00', '22:00:00' )
		) {
			return null;
		}
		set_time_limit( 0 );
		$start_panda = microtime( true );
		$memory      = memory_get_usage( true );

		if ( $this->trading_signal_table->get_data() ) {
			return null;
		}

		$this->save_data();

		$memory     = memory_get_usage( true ) - $memory;
		$panda_diff = wp_sprintf( '%.6f sec.', microtime( true ) - $start_panda );
		Helpers::log_error( 'Trading signal time', $panda_diff, 'trading_signal' );
		Helpers::log_error( 'Trading signal memory', Helpers::convert( $memory ), 'trading_signal' );
	}

	/**
	 * Save data from feed in option
	 * Save users from remote db in our db
	 *
	 * @return void
	 */
	public function save_data(): void {
		$data_from_feed = $this->get_data_from_feed();
		$users_data     = $this->get_users_data_from_crm_db();

		if ( ! $data_from_feed || ! $users_data ) {
			return;
		}

		// save users in temporary db table
		while ( $users_data ) {
			$part_users = array_splice( $users_data, 0, 900 );
			$this->trading_signal_table->save( $part_users );
		}

		// save data from feed in option
		update_option( self::OPTION_TRADING_SIGNAL_DATA, $data_from_feed, false );
	}

	/*
	 *  A recursive method for cron that sends a piece of data to optimove.
	 *  It calls every 5 min until it sends all the data from the intermediate table
	 */
	public function start_sending(): void {
		if (
			! defined( 'CM_OPTIMOVE_TENANT_ID' )
			|| ! defined( 'CM_OPTIMOVE_ENDPOINT' )
		) {
			return;
		}
		set_time_limit( 0 );

		$is_launch = get_option( self::OPTION_NAME_START, false );
		if ( $is_launch ) {
			return;
		}
		update_option( self::OPTION_NAME_START, true, false );

		$memory = memory_get_usage( true );
		// get 100 users from the table
		$parts          = $this->trading_signal_table->get_data( 200 );
		$data_from_feed = get_option( self::OPTION_TRADING_SIGNAL_DATA, [] );
		if ( ! $parts || ! $data_from_feed ) {
			update_option( self::OPTION_NAME_START, false, false );
			$this->trading_signal_table->truncate();
			return;
		}

		$list_for_remove = [];
		foreach ( $parts as $users_datum ) {
			$list_for_remove[] = $users_datum->ID;
			$user_data         = [
				'email'    => $users_datum->email,
				'customer' => $users_datum->customer_id,
			];

			foreach ( $data_from_feed as $item ) {
				$body            = $user_data;
				$body['context'] = $item;
				$this->optimove->send_event_to_optimove(
					'trading_signal',
					$body
				);
			}
		}
		$this->trading_signal_table->remove_records( $list_for_remove );

		update_option( self::OPTION_NAME_START, false, false );

		$memory = memory_get_usage( true ) - $memory;
		Helpers::log_error( 'Trading signal loop memory', Helpers::convert( $memory ), 'trading_signal' );
	}

	private function get_data_from_feed(): array {
		if ( ! defined( 'FEED_TRADING_CENTRAL_TOKEN' ) ) {
			return [];
		}

		$xml_url = add_query_arg(
			[ 'token' => FEED_TRADING_CENTRAL_TOKEN ],
			self::FEED_URL
		);

		$xml = XML::parse_from_url( $xml_url );
		if ( ! $xml instanceof \SimpleXMLElement ) {
			return [];
		}

		if ( empty( $xml->channel->item ) ) {
			return [];
		}

		$result        = [];
		$exists_titles = [];
		$i             = 0;
		$last_guid     = (string) get_option( 'cm_trading_signal_last_guid' );
		foreach ( $xml->channel->item as $item ) {
			$guid = $item->guid->__toString();
			if ( $i === 0 && $guid !== $last_guid ) {
				update_option( 'cm_trading_signal_last_guid', $guid, false );
			}

			if ( $guid === $last_guid ) {
				break;
			}

			$title = $item->title->__toString();
			if ( in_array( $title, $exists_titles, true ) ) {
				$i++;
				continue;
			}

			$result[]        = $this->get_processed_item( $item );
			$exists_titles[] = $title;
			$i++;
		}

		return $result;
	}

	private function get_processed_item( ?\SimpleXMLElement $item ): array {
		if ( ! $item ) {
			return [];
		}
		$description = $item->description->__toString();

		// Extract short description
		preg_match( '/<b>Our preference(:?)<\/b>(.*?)<br\/>/', $description, $matches );
		$short_description = isset( $matches[0] ) ? trim( wp_strip_all_tags( $matches[0] ) ) : '';
		if ( $short_description ) {
			$short_description = str_replace( 'Our preference', 'Market Trend', $short_description );
		}

		// Extract long description
		preg_match( '/Alternative scenario(.*?)<br\/><br\/><b>Supports/s', $description, $matches );
		$long_description = isset( $matches[1] ) ? trim( wp_strip_all_tags( $matches[0] ) ) : '';
		if ( $long_description ) {
			$long_description = str_replace( [ '.Comment', '.Supports' ], [ '. Comment', '. Supports' ], $long_description );
		}

		// Format supports and resistances
		preg_match( '/and resistances(.*?)<br\/><br\/>/', $description, $matches );
		$supports_and_resistances = isset( $matches[1] )
			? trim(
				wp_strip_all_tags(
					str_replace( // preg_replace
						[ '<br/>' ],
						[ ', ' ],
						$matches[1]
					)
				),
				','
			)
			: '';

		if ( $supports_and_resistances ) {
			$supports_and_resistances = str_replace( [ '*', 'Last', 'last', ':', ': ,', ' ,', ' :' ], [ '', ' Last:', ' Last:', ': ', ': ', ',', ':' ], $supports_and_resistances );
		}

		// Extract image URL
		preg_match( '/<img src=\"(.*?)\" \/>/', $description, $matches );
		$image_url = $matches[1] ?? '';

		return [
			'title'             => Helpers::trim_string( $item->title->__toString() ),
			'short_description' => $short_description,
			'long_description'  => Helpers::trim_string( $short_description . ' ' . $long_description . ' ' . $supports_and_resistances ),
			'image'             => $image_url,
		];
	}

	private function get_users_data_from_crm_db(): ?array {
		$crm_db = new CRM_DB();
		return $crm_db->get_users_emails_if_user_active();
	}
}
