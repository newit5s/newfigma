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

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent multiple initialization
if ( defined( 'RESTAURANT_BOOKING_VERSION' ) ) {
    return;
}

// Define plugin constants with protection
if ( ! defined( 'RESTAURANT_BOOKING_VERSION' ) ) {
    define( 'RESTAURANT_BOOKING_VERSION', '2.0.0' );
}

if ( ! defined( 'RESTAURANT_BOOKING_PATH' ) ) {
    define( 'RESTAURANT_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'RESTAURANT_BOOKING_URL' ) ) {
    define( 'RESTAURANT_BOOKING_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'RESTAURANT_BOOKING_BASENAME' ) ) {
    define( 'RESTAURANT_BOOKING_BASENAME', plugin_basename( __FILE__ ) );
}

// Legacy constants with protection (for backward compatibility)
if ( ! defined( 'RB_PLUGIN_VERSION' ) ) {
    define( 'RB_PLUGIN_VERSION', RESTAURANT_BOOKING_VERSION );
}

if ( ! defined( 'RB_PLUGIN_FILE' ) ) {
    define( 'RB_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'RB_PLUGIN_DIR' ) ) {
    define( 'RB_PLUGIN_DIR', RESTAURANT_BOOKING_PATH );
}

if ( ! defined( 'RB_PLUGIN_URL' ) ) {
    define( 'RB_PLUGIN_URL', RESTAURANT_BOOKING_URL );
}

if ( ! defined( 'RB_PLUGIN_BASENAME' ) ) {
    define( 'RB_PLUGIN_BASENAME', RESTAURANT_BOOKING_BASENAME );
}

// Check if plugin loader exists
if ( ! file_exists( RESTAURANT_BOOKING_PATH . 'includes/class-plugin-loader.php' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo __( 'Restaurant Booking Manager: Missing plugin loader file. Please reinstall the plugin.', 'restaurant-booking' );
        echo '</p></div>';
    });
    return;
}

// Load plugin core classes
require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-loader.php';
require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-manager.php';

/**
 * Initialize the plugin
 */
function restaurant_booking_init() {
    // Load text domain for internationalization
    load_plugin_textdomain(
        'restaurant-booking',
        false,
        dirname( RESTAURANT_BOOKING_BASENAME ) . '/languages/'
    );

    // Initialize plugin manager
    $plugin = Restaurant_Booking_Plugin_Manager::instance();
    $plugin->run();
}

// Hook into WordPress initialization
add_action( 'plugins_loaded', 'restaurant_booking_init', 10 );

/**
 * Plugin activation hook
 */
function restaurant_booking_activate() {
    if ( ! file_exists( RESTAURANT_BOOKING_PATH . 'includes/class-plugin-activator.php' ) ) {
        wp_die( __( 'Restaurant Booking Manager: Missing activator file. Cannot activate plugin.', 'restaurant-booking' ) );
    }
    
    require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-activator.php';
    Restaurant_Booking_Plugin_Activator::activate();
}
register_activation_hook( __FILE__, 'restaurant_booking_activate' );

/**
 * Plugin deactivation hook
 */
function restaurant_booking_deactivate() {
    if ( file_exists( RESTAURANT_BOOKING_PATH . 'includes/class-plugin-deactivator.php' ) ) {
        require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-deactivator.php';
        Restaurant_Booking_Plugin_Deactivator::deactivate();
    }
}
register_deactivation_hook( __FILE__, 'restaurant_booking_deactivate' );

/**
 * Check for plugin conflicts on admin init
 */
function restaurant_booking_check_conflicts() {
    // Check for conflicting plugins that might use same constants
    $conflicting_plugins = array(
        'plugin-datban-version1-main/restaurant-booking-manager.php'
    );
    
    foreach ( $conflicting_plugins as $plugin ) {
        if ( is_plugin_active( $plugin ) ) {
            add_action( 'admin_notices', function() use ( $plugin ) {
                echo '<div class="notice notice-warning"><p>';
                printf( 
                    __( 'Restaurant Booking Manager: Potential conflict detected with %s. Please deactivate conflicting plugin.', 'restaurant-booking' ),
                    esc_html( $plugin )
                );
                echo '</p></div>';
            });
        }
    }
}
add_action( 'admin_init', 'restaurant_booking_check_conflicts' );

/**
 * Add plugin action links
 */
function restaurant_booking_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=rb-settings' ) . '">' . __( 'Settings', 'restaurant-booking' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . RESTAURANT_BOOKING_BASENAME, 'restaurant_booking_plugin_action_links' );

/**
 * Display admin notice on successful activation
 */
function restaurant_booking_activation_notice() {
    if ( get_transient( 'restaurant_booking_activated' ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        printf( 
            __( 'Restaurant Booking Manager activated successfully! <a href="%s">Configure settings</a>', 'restaurant-booking' ),
            admin_url( 'admin.php?page=rb-settings' )
        );
        echo '</p></div>';
        delete_transient( 'restaurant_booking_activated' );
    }
}
add_action( 'admin_notices', 'restaurant_booking_activation_notice' );

/**
 * Set activation flag
 */
function restaurant_booking_set_activation_flag() {
    set_transient( 'restaurant_booking_activated', true, 30 );
}
register_activation_hook( __FILE__, 'restaurant_booking_set_activation_flag' );
