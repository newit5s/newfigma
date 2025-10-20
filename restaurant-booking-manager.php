<?php
/**
 * Plugin Name: Modern Restaurant Booking Manager
 * Plugin URI: https://github.com/your-repo/modern-restaurant-booking
 * Description: Modern booking interface for restaurants with dark mode, advanced analytics, and multi-location support.
 * Version: 2.0.0
 * Author: Your Company
 * Author URI: https://yourcompany.com
 * License: GPL v2 or later
 * Text Domain: restaurant-booking
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RESTAURANT_BOOKING_VERSION', '2.0.0' );
define( 'RESTAURANT_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'RESTAURANT_BOOKING_URL', plugin_dir_url( __FILE__ ) );
define( 'RESTAURANT_BOOKING_BASENAME', plugin_basename( __FILE__ ) );

define( 'RB_PLUGIN_VERSION', RESTAURANT_BOOKING_VERSION );

define( 'RB_PLUGIN_FILE', __FILE__ );
define( 'RB_PLUGIN_DIR', RESTAURANT_BOOKING_PATH );

define( 'RB_PLUGIN_URL', RESTAURANT_BOOKING_URL );

define( 'RB_PLUGIN_BASENAME', RESTAURANT_BOOKING_BASENAME );

require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-loader.php';
require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-manager.php';

function restaurant_booking_init() {
    load_plugin_textdomain(
        'restaurant-booking',
        false,
        dirname( RESTAURANT_BOOKING_BASENAME ) . '/languages/'
    );

    $plugin = Restaurant_Booking_Plugin_Manager::instance();
    $plugin->run();
}
add_action( 'plugins_loaded', 'restaurant_booking_init', 10 );

function restaurant_booking_activate() {
    require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-activator.php';
    Restaurant_Booking_Plugin_Activator::activate();
}
register_activation_hook( __FILE__, 'restaurant_booking_activate' );

function restaurant_booking_deactivate() {
    require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-deactivator.php';
    Restaurant_Booking_Plugin_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'restaurant_booking_deactivate' );
