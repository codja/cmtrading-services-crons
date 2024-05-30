<?php
/**
 * Plugin Name: Cmtrading Services Crons
 * Plugin URI: https://rgbcode.com/
 * Description: Plugin for external cron tasks.
 * Author: rgbcode
 * Author URI: https://rgbcode.com/
 * Version: 1.0.0
 * Text Domain: cmtrading-services-crons
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) || ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Currently plugin version.
 */
define( 'CMSC_AUTOLOGIN_CORE_VERSION', '1.0.0' );
define( 'CMSC_AUTOLOGIN_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'CMSC_AUTOLOGIN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMSC_AUTOLOGIN_PLUGIN_URL', plugins_url( '/' , __FILE__ ) );

require_once CMSC_AUTOLOGIN_PLUGIN_PATH . 'includes/class-autoloader.php';

/**
 * The code that runs during plugin activation.
 */
function activate_cmsc_plugin(): void {
	new \cmsc\classes\core\Activator();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_cmsc_plugin(): void {
	new \cmsc\classes\core\Deactivator();
}

register_activation_hook( __FILE__, 'activate_cmsc_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_cmsc_plugin' );
