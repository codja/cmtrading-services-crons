<?php
/**
 * This file runs when the plugin in uninstalled (deleted).
 * This will not run when the plugin is deactivated.
 * Ideally you will add all your clean-up scripts here
 * that will clean up unused meta, options, etc. in the database.
 *
 * @package WordPress Plugin Template/Uninstall
 */

// If plugin is not being uninstalled, exit (do nothing).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Do something here if plugin is being uninstalled.
$acf_options_list = [
	'optimove_trading_signal_start',
	'optimove_cron_start',
	'cm_trading_signal_last_guid',
	'cm_intercom_conversation_last_launch_time',
	'optimove_last_call_datetime',
];

if ( function_exists( 'delete_field' ) ) {
	foreach ( $acf_options_list as $option_name ) {
		delete_field( $option_name, 'option' );
	}
}
