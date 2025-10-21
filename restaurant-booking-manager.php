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

    if ( function_exists( 'restaurant_booking_add_role_capabilities' ) ) {
        restaurant_booking_add_role_capabilities();
    }
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
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'admin.php?page=rb-settings' ) ),
        esc_html__( 'Settings', 'restaurant-booking' )
    );
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . RESTAURANT_BOOKING_BASENAME, 'restaurant_booking_plugin_action_links' );

/**
 * Determine the capability required to manage the plugin.
 *
 * @return string
 */
function restaurant_booking_get_manage_capability() {
    $default = 'manage_bookings';

    /**
     * Filter the capability used for accessing restaurant booking management tools.
     *
     * @param string $capability Default capability slug.
     */
    $capability = apply_filters( 'restaurant_booking_manage_capability', $default );

    if ( empty( $capability ) || ! is_string( $capability ) ) {
        $capability = $default;
    }

    return $capability;
}

/**
 * Resolve the required management capability for the current user context.
 *
 * @return string
 */
function restaurant_booking_resolve_manage_capability() {
    $capability = restaurant_booking_get_manage_capability();

    if ( current_user_can( $capability ) ) {
        return $capability;
    }

    if ( 'manage_options' !== $capability && current_user_can( 'manage_options' ) ) {
        return 'manage_options';
    }

    return $capability;
}

/**
 * Check whether the current user can manage restaurant bookings.
 *
 * @return bool
 */
function restaurant_booking_user_can_manage() {
    $capability = restaurant_booking_get_manage_capability();

    if ( current_user_can( $capability ) ) {
        return true;
    }

    if ( 'manage_options' !== $capability && current_user_can( 'manage_options' ) ) {
        return true;
    }

    return false;
}

/**
 * Assign management capabilities to default roles.
 */
function restaurant_booking_add_role_capabilities() {
    $capability = restaurant_booking_get_manage_capability();

    $roles = apply_filters(
        'restaurant_booking_manage_capability_roles',
        array( 'administrator', 'editor' )
    );

    foreach ( $roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role && ! $role->has_cap( $capability ) ) {
            $role->add_cap( $capability );
        }
    }
}

add_action( 'init', 'restaurant_booking_add_role_capabilities', 5 );

/**
 * Remove management capability from default roles.
 */
function restaurant_booking_remove_role_capabilities() {
    $capability = restaurant_booking_get_manage_capability();

    $roles = apply_filters(
        'restaurant_booking_manage_capability_roles',
        array( 'administrator', 'editor' )
    );

    foreach ( $roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role && $role->has_cap( $capability ) ) {
            $role->remove_cap( $capability );
        }
    }
}

/**
 * Register rewrite rules for portal pretty URLs.
 */
function restaurant_booking_register_rewrite_rules() {
    add_rewrite_rule( '^portal/dashboard/?$', 'index.php?rb_portal=dashboard', 'top' );
    add_rewrite_rule( '^portal/([^/]+)/?$', 'index.php?rb_portal=$matches[1]', 'top' );
}
add_action( 'init', 'restaurant_booking_register_rewrite_rules', 5 );

/**
 * Determine whether the portal rewrite rules are currently registered.
 *
 * @return bool
 */
function restaurant_booking_portal_rewrite_rules_active() {
    $rules = get_option( 'rewrite_rules' );

    if ( empty( $rules ) || ! is_array( $rules ) ) {
        return false;
    }

    $has_dashboard_rule = false;
    $has_portal_rule    = false;

    foreach ( $rules as $rewrite ) {
        if ( false !== strpos( $rewrite, 'rb_portal=dashboard' ) ) {
            $has_dashboard_rule = true;
        }

        if ( false !== strpos( $rewrite, 'rb_portal=$matches[1]' ) ) {
            $has_portal_rule = true;
        }

        if ( $has_dashboard_rule && $has_portal_rule ) {
            return true;
        }
    }

    return false;
}

/**
 * Flag missing rewrite rules so an admin notice can provide recovery guidance.
 */
function restaurant_booking_check_portal_rewrite_rules() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( empty( get_option( 'permalink_structure' ) ) ) {
        // Pretty permalinks are disabled so .htaccess rules are not required.
        delete_transient( 'restaurant_booking_rewrite_missing' );
        return;
    }

    if ( restaurant_booking_portal_rewrite_rules_active() ) {
        delete_transient( 'restaurant_booking_rewrite_missing' );
        return;
    }

    set_transient( 'restaurant_booking_rewrite_missing', 1, MINUTE_IN_SECONDS * 10 );
}
add_action( 'admin_init', 'restaurant_booking_check_portal_rewrite_rules', 20 );

/**
 * Display an admin notice when rewrite rules need to be regenerated manually.
 */
function restaurant_booking_portal_rewrite_notice() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! get_transient( 'restaurant_booking_rewrite_missing' ) ) {
        return;
    }

    $permalink_url = admin_url( 'options-permalink.php' );

    echo '<div class="notice notice-warning">';
    $message = sprintf(
        /* translators: %s: Permalink settings admin URL. */
        __( 'The Restaurant Booking portal URL rewrite rules are missing. Visit <a href="%s">Settings → Permalinks</a> and click “Save Changes” to regenerate them. If WordPress cannot write to <code>.htaccess</code>, copy the suggested rewrite block into your server configuration manually.', 'restaurant-booking' ),
        esc_url( $permalink_url )
    );

    echo '<p>' . wp_kses(
        $message,
        array(
            'a'    => array( 'href' => array() ),
            'code' => array(),
        )
    ) . '</p>';

    $htaccess_block = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>";

    echo '<p><strong>' . esc_html__( 'Standard WordPress rewrite snippet:', 'restaurant-booking' ) . '</strong></p>';
    echo '<pre><code>' . esc_html( $htaccess_block ) . '</code></pre>';
    echo '</div>';
}
add_action( 'admin_notices', 'restaurant_booking_portal_rewrite_notice' );

/**
 * Register custom query vars.
 *
 * @param array $vars Existing query vars.
 *
 * @return array
 */
function restaurant_booking_register_query_vars( $vars ) {
    if ( ! in_array( 'rb_portal', $vars, true ) ) {
        $vars[] = 'rb_portal';
    }

    return $vars;
}
add_filter( 'query_vars', 'restaurant_booking_register_query_vars' );

/**
 * Resolve the requested portal view from either query vars or request parameters.
 *
 * @return string
 */
function restaurant_booking_get_portal_view() {
    if ( isset( $_GET['rb_portal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return sanitize_key( wp_unslash( $_GET['rb_portal'] ) );
    }

    $query_var = get_query_var( 'rb_portal', '' );
    if ( ! empty( $query_var ) ) {
        return sanitize_key( $query_var );
    }

    return '';
}

/**
 * Display admin notice on successful activation
 */
function restaurant_booking_activation_notice() {
    if ( get_transient( 'restaurant_booking_activated' ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            __( 'Restaurant Booking Manager activated successfully! <a href="%s">Configure settings</a>', 'restaurant-booking' ),
            esc_url( admin_url( 'admin.php?page=rb-settings' ) )
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
