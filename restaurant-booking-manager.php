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
        esc_url( restaurant_booking_get_settings_page_url() ),
        esc_html__( 'Settings', 'restaurant-booking' )
    );
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . RESTAURANT_BOOKING_BASENAME, 'restaurant_booking_plugin_action_links' );

/**
 * Resolve the slug used for the plugin settings screen.
 *
 * @return string
 */
function restaurant_booking_get_settings_page_slug() {
    $default_slug = 'restaurant-booking-settings';

    /**
     * Filter the admin settings page slug.
     *
     * @since 2.0.0
     *
     * @param string $slug Default slug.
     */
    $slug = apply_filters( 'restaurant_booking_settings_page_slug', $default_slug );

    if ( ! is_string( $slug ) ) {
        $slug = $default_slug;
    }

    $slug = strtolower( $slug );
    $slug = preg_replace( '/[^a-z0-9_\-]/', '', $slug );

    if ( empty( $slug ) ) {
        $slug = $default_slug;
    }

    return $slug;
}

/**
 * Retrieve the admin URL for the plugin settings screen.
 *
 * @return string
 */
function restaurant_booking_get_settings_page_url() {
    $slug = restaurant_booking_get_settings_page_slug();

    return admin_url( 'admin.php?page=' . $slug );
}

/**
 * Retrieve the default plugin settings.
 *
 * @return array
 */
function restaurant_booking_get_default_settings() {
    $defaults = array(
        'restaurant_name'       => __( 'Modern Restaurant', 'restaurant-booking' ),
        'default_currency'      => 'USD',
        'max_party_size'        => 6,
        'buffer_time'           => 30,
        'allow_walkins'         => false,
        'reminder_hours'        => 24,
        'confirmation_template' => __( 'Thank you for your reservation! We look forward to welcoming you.', 'restaurant-booking' ),
        'send_sms'              => false,
        'send_followup'         => false,
        'auto_cancel_minutes'   => 15,
        'hold_time_minutes'     => 10,
        'integrations'          => array(),
        'maintenance_mode'      => false,
    );

    /**
     * Filter the default restaurant booking settings.
     *
     * @since 2.0.0
     *
     * @param array $defaults Default settings values.
     */
    return apply_filters( 'restaurant_booking_default_settings', $defaults );
}

/**
 * Retrieve the stored plugin settings merged with defaults.
 *
 * @return array
 */
function restaurant_booking_get_settings() {
    $defaults = restaurant_booking_get_default_settings();
    $options  = get_option( 'restaurant_booking_settings', array() );

    if ( ! is_array( $options ) ) {
        $options = array();
    }

    $settings = array_merge( $defaults, $options );

    /**
     * Filter the resolved restaurant booking settings.
     *
     * @since 2.0.0
     *
     * @param array $settings Resolved settings values.
     */
    return apply_filters( 'restaurant_booking_settings', $settings );
}

/**
 * Retrieve a single restaurant booking setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value when the key is missing.
 *
 * @return mixed
 */
function restaurant_booking_get_setting( $key, $default = null ) {
    $settings = restaurant_booking_get_settings();

    if ( array_key_exists( $key, $settings ) ) {
        return $settings[ $key ];
    }

    return $default;
}

/**
 * Sanitize plugin settings before persisting.
 *
 * @param array $settings Raw settings submitted from the form.
 *
 * @return array
 */
function restaurant_booking_sanitize_settings( $settings ) {
    $defaults = restaurant_booking_get_default_settings();

    if ( ! is_array( $settings ) ) {
        $settings = array();
    }

    $settings = wp_unslash( $settings );

    $clean = array();

    $clean['restaurant_name'] = isset( $settings['restaurant_name'] )
        ? sanitize_text_field( $settings['restaurant_name'] )
        : $defaults['restaurant_name'];

    $allowed_currencies = array( 'USD', 'EUR', 'GBP', 'JPY' );
    $currency           = isset( $settings['default_currency'] ) ? strtoupper( sanitize_text_field( $settings['default_currency'] ) ) : $defaults['default_currency'];
    if ( ! in_array( $currency, $allowed_currencies, true ) ) {
        $currency = $defaults['default_currency'];
    }
    $clean['default_currency'] = $currency;

    $clean['max_party_size'] = isset( $settings['max_party_size'] )
        ? max( 1, min( 30, (int) $settings['max_party_size'] ) )
        : $defaults['max_party_size'];

    $clean['buffer_time'] = isset( $settings['buffer_time'] )
        ? max( 0, min( 180, (int) $settings['buffer_time'] ) )
        : $defaults['buffer_time'];

    $clean['allow_walkins'] = ! empty( $settings['allow_walkins'] );

    $clean['reminder_hours'] = isset( $settings['reminder_hours'] )
        ? max( 1, min( 168, (int) $settings['reminder_hours'] ) )
        : $defaults['reminder_hours'];

    $clean['confirmation_template'] = isset( $settings['confirmation_template'] )
        ? wp_kses_post( $settings['confirmation_template'] )
        : $defaults['confirmation_template'];

    $clean['send_sms'] = ! empty( $settings['send_sms'] );

    $clean['send_followup'] = ! empty( $settings['send_followup'] );

    $clean['auto_cancel_minutes'] = isset( $settings['auto_cancel_minutes'] )
        ? max( 0, min( 240, (int) $settings['auto_cancel_minutes'] ) )
        : $defaults['auto_cancel_minutes'];

    $clean['hold_time_minutes'] = isset( $settings['hold_time_minutes'] )
        ? max( 0, min( 180, (int) $settings['hold_time_minutes'] ) )
        : $defaults['hold_time_minutes'];

    $allowed_integrations = array( 'zapier', 'slack', 'teams', 'webhooks' );
    $integrations         = array();

    if ( ! empty( $settings['integrations'] ) ) {
        $raw_integrations = $settings['integrations'];

        if ( ! is_array( $raw_integrations ) ) {
            $raw_integrations = array( $raw_integrations );
        }

        foreach ( $raw_integrations as $integration ) {
            $normalized = sanitize_key( $integration );

            if ( in_array( $normalized, $allowed_integrations, true ) ) {
                $integrations[] = $normalized;
            }
        }

        $integrations = array_values( array_unique( $integrations ) );
    }

    $clean['integrations'] = $integrations;

    $clean['maintenance_mode'] = ! empty( $settings['maintenance_mode'] );

    $sanitized = array_merge( $defaults, $clean );

    /**
     * Filter the sanitized restaurant booking settings.
     *
     * @since 2.0.0
     *
     * @param array $sanitized Sanitized settings.
     * @param array $settings  Raw submitted settings.
     */
    return apply_filters( 'restaurant_booking_sanitized_settings', $sanitized, $settings );
}

/**
 * Retrieve legacy settings slugs that should redirect to the current page.
 *
 * @return array
 */
function restaurant_booking_get_legacy_settings_slugs() {
    /**
     * Filter the legacy admin settings slugs for backward compatibility.
     *
     * @since 2.0.0
     *
     * @param array $slugs Legacy slugs.
     */
    $slugs = apply_filters( 'restaurant_booking_legacy_settings_slugs', array( 'rb-settings' ) );

    if ( ! is_array( $slugs ) ) {
        return array( 'rb-settings' );
    }

    return array_values( array_filter( array_map( function ( $slug ) {
        if ( ! is_string( $slug ) ) {
            return '';
        }

        $slug = strtolower( $slug );
        $slug = preg_replace( '/[^a-z0-9_\-]/', '', $slug );

        return $slug;
    }, $slugs ) ) );
}

/**
 * Redirect requests for legacy settings slugs to the current settings page.
 */
function restaurant_booking_redirect_legacy_settings_slug() {
    if ( ! is_admin() ) {
        return;
    }

    if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $requested_slug = strtolower( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_slug   = restaurant_booking_get_settings_page_slug();

    if ( $requested_slug === $current_slug ) {
        return;
    }

    $legacy_slugs = restaurant_booking_get_legacy_settings_slugs();

    if ( in_array( $requested_slug, $legacy_slugs, true ) ) {
        wp_safe_redirect( restaurant_booking_get_settings_page_url(), 301 );
        exit;
    }
}
add_action( 'admin_init', 'restaurant_booking_redirect_legacy_settings_slug', 1 );

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
 * Ensure administrators retain access when the custom capability is missing.
 *
 * Supports both the default `manage_bookings` capability and any custom
 * capability provided via the `restaurant_booking_manage_capability` filter.
 *
 * @param array  $caps    Required primitive capabilities.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User identifier.
 * @param array  $args    Additional arguments.
 *
 * @return array
 */
function restaurant_booking_map_manage_capability( $caps, $cap, $user_id, $args ) {
    $managed_caps = array( 'manage_bookings' );

    $configured_cap = restaurant_booking_get_manage_capability();
    if (
        ! empty( $configured_cap )
        && 'manage_options' !== $configured_cap
        && ! in_array( $configured_cap, $managed_caps, true )
    ) {
        $managed_caps[] = $configured_cap;
    }

    if ( ! in_array( $cap, $managed_caps, true ) ) {
        return $caps;
    }

    $user = get_userdata( $user_id );
    if ( ! $user instanceof WP_User ) {
        return $caps;
    }

    if ( ! empty( $user->allcaps['manage_bookings'] ) ) {
        return $caps;
    }

    if ( ! empty( $user->allcaps['manage_options'] ) ) {
        return array( 'manage_options' );
    }

    return $caps;
}
add_filter( 'map_meta_cap', 'restaurant_booking_map_manage_capability', 10, 4 );

/**
 * Surface a diagnostic notice when an administrator is missing the manage capability.
 */
function restaurant_booking_show_manage_capability_notice() {
    if ( ! is_admin() ) {
        return;
    }

    if ( wp_doing_ajax() ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $capability = restaurant_booking_get_manage_capability();

    if ( current_user_can( $capability ) ) {
        return;
    }

    $diagnostics = restaurant_booking_get_manage_access_diagnostics( get_current_user_id() );
    $issues      = isset( $diagnostics['issues'] ) && is_array( $diagnostics['issues'] )
        ? array_filter( array_map( 'wp_strip_all_tags', $diagnostics['issues'] ) )
        : array();

    $command = sprintf(
        'wp restaurant-booking doctor --user=%d',
        get_current_user_id()
    );

    echo '<div class="notice notice-warning"><p>';
    printf(
        /* translators: %1$s capability slug, %2$s WP-CLI command. */
        esc_html__(
            'Restaurant Booking Manager detected that your account is missing the "%1$s" capability required to access the booking settings. Run %2$s to inspect role assignments or re-add the capability for your role.',
            'restaurant-booking'
        ),
        esc_html( $capability ),
        '<code>' . esc_html( $command ) . '</code>'
    );
    echo '</p>';

    if ( ! empty( $issues ) ) {
        echo '<p>' . esc_html__( 'Diagnostic summary:', 'restaurant-booking' ) . '</p>';
        echo '<ul>';

        foreach ( $issues as $issue ) {
            echo '<li>' . esc_html( $issue ) . '</li>';
        }

        echo '</ul>';
    }

    echo '</div>';
}
add_action( 'admin_notices', 'restaurant_booking_show_manage_capability_notice' );

/**
 * Resolve the preferred portal login URL.
 *
 * @return string
 */
function restaurant_booking_get_portal_login_url() {
    $login_url = apply_filters( 'rb_portal_login_url', home_url( '/portal/' ) );

    if ( empty( $login_url ) ) {
        $login_url = wp_login_url( admin_url( 'admin.php?page=rb-dashboard' ) );
    }

    return esc_url_raw( $login_url );
}

/**
 * Render a consistent permission notice with a portal login link.
 *
 * @param string $feature_label Feature name for context.
 *
 * @return string
 */
function restaurant_booking_render_permission_notice( $feature_label ) {
    $feature    = is_string( $feature_label ) ? $feature_label : '';
    $login_url  = restaurant_booking_get_portal_login_url();
    $message    = sprintf(
        /* translators: 1: feature label, 2: portal login URL */
        __( 'You do not have permission to view the %1$s. Please <a href="%2$s">sign in to the manager portal</a>.', 'restaurant-booking' ),
        esc_html( $feature ),
        esc_url( $login_url )
    );

    return '<div class="rb-alert rb-alert-warning">' . wp_kses(
        $message,
        array(
            'a' => array( 'href' => array() ),
        )
    ) . '</div>';
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
 * Build diagnostic data explaining why access to the settings may be missing.
 *
 * @param int|null $user_id Optional user identifier. Defaults to the current user when available.
 *
 * @return array {
 *     @type string     $capability                       Required capability for managing bookings.
 *     @type string     $fallback                         Fallback capability granted to administrators.
 *     @type bool       $has_filter                       Whether a customization filter is registered.
 *     @type array      $roles                            Role-level capability information.
 *     @type array      $roles_with_capability            Role slugs that explicitly grant the capability.
 *     @type array      $roles_with_capability_labels     Human friendly labels for roles that grant the capability.
 *     @type array      $roles_with_fallback              Roles that inherit access via the fallback capability.
 *     @type array      $roles_with_fallback_labels       Human friendly labels for fallback roles.
 *     @type array      $roles_missing_capability         Roles missing both the capability and fallback.
 *     @type array      $roles_missing_capability_labels  Human friendly labels for missing roles.
 *     @type array|null $user                             Current or inspected user diagnostic details.
 *     @type array      $issues                           Human readable list of detected issues.
 * }
 */
function restaurant_booking_get_manage_access_diagnostics( $user_id = null ) {
    $capability = restaurant_booking_get_manage_capability();
    $fallback   = 'manage_options';

    $roles_data                    = array();
    $roles_with_capability         = array();
    $roles_with_fallback           = array();
    $roles_missing_capability      = array();
    $issues                        = array();
    $role_labels_map               = array();
    $has_custom_capability_filter  = false;

    if ( function_exists( 'has_filter' ) ) {
        $has_custom_capability_filter = has_filter( 'restaurant_booking_manage_capability' );
    }

    $wp_roles = wp_roles();

    if ( $wp_roles instanceof WP_Roles ) {
        $role_labels_map = is_array( $wp_roles->role_names ) ? $wp_roles->role_names : array();

        foreach ( $wp_roles->role_objects as $role_slug => $role ) {
            if ( ! $role instanceof WP_Role ) {
                continue;
            }

            $role_has_capability = $role->has_cap( $capability );
            $role_has_fallback   = ( 'manage_options' !== $capability ) ? $role->has_cap( $fallback ) : $role_has_capability;

            $roles_data[ $role_slug ] = array(
                'label'          => isset( $role_labels_map[ $role_slug ] ) ? $role_labels_map[ $role_slug ] : $role_slug,
                'has_capability' => $role_has_capability,
                'has_fallback'   => $role_has_fallback,
            );

            if ( $role_has_capability ) {
                $roles_with_capability[] = $role_slug;
            } elseif ( $role_has_fallback ) {
                $roles_with_fallback[] = $role_slug;
            } else {
                $roles_missing_capability[] = $role_slug;
            }
        }

        if ( empty( $roles_with_capability ) && 'manage_options' !== $capability ) {
            $issues[] = sprintf(
                /* translators: %s capability slug. */
                __( 'No roles currently grant the "%s" capability.', 'restaurant-booking' ),
                $capability
            );
        }
    }

    if ( $has_custom_capability_filter && 'manage_bookings' !== $capability ) {
        $issues[] = sprintf(
            /* translators: %s capability slug. */
            __( 'The required capability has been changed to "%s" via a filter. Ensure roles grant this capability.', 'restaurant-booking' ),
            $capability
        );
    }

    $format_role_label = function( $role_slug ) use ( $role_labels_map ) {
        return isset( $role_labels_map[ $role_slug ] ) ? $role_labels_map[ $role_slug ] : $role_slug;
    };

    $user_info = null;
    $resolved_user_id = null === $user_id ? get_current_user_id() : (int) $user_id;

    if ( $resolved_user_id > 0 ) {
        $user = get_user_by( 'id', $resolved_user_id );

        if ( $user instanceof WP_User ) {
            $user_has_capability = user_can( $user, $capability );
            $user_has_fallback   = ( 'manage_options' !== $capability ) ? user_can( $user, $fallback ) : $user_has_capability;

            $role_labels = array_map( $format_role_label, $user->roles );

            $user_info = array(
                'id'             => $user->ID,
                'login'          => $user->user_login,
                'roles'          => $user->roles,
                'role_labels'    => $role_labels,
                'has_capability' => $user_has_capability,
                'has_fallback'   => $user_has_fallback,
            );

            if ( ! $user_has_capability && ! $user_has_fallback ) {
                if ( empty( $user->roles ) ) {
                    $issues[] = __( 'This account does not have any roles assigned.', 'restaurant-booking' );
                } else {
                    $issues[] = sprintf(
                        /* translators: 1: comma separated roles, 2: capability slug. */
                        __( 'The assigned roles (%1$s) do not grant the "%2$s" capability.', 'restaurant-booking' ),
                        implode( ', ', $role_labels ),
                        $capability
                    );

                    if ( ! empty( $roles_with_capability ) ) {
                        $issues[] = sprintf(
                            /* translators: 1: capability slug, 2: comma separated roles. */
                            __( 'Roles that currently provide access: %2$s. Assign one of these roles or add the "%1$s" capability manually.', 'restaurant-booking' ),
                            $capability,
                            implode( ', ', array_map( $format_role_label, $roles_with_capability ) )
                        );
                    }
                }
            }
        } else {
            $issues[] = __( 'The specified user could not be located for diagnostics.', 'restaurant-booking' );

            $user_info = array(
                'id'             => $resolved_user_id,
                'login'          => '',
                'roles'          => array(),
                'role_labels'    => array(),
                'has_capability' => false,
                'has_fallback'   => false,
            );
        }
    }

    return array(
        'capability'                      => $capability,
        'fallback'                        => $fallback,
        'has_filter'                      => $has_custom_capability_filter,
        'roles'                           => $roles_data,
        'roles_with_capability'           => $roles_with_capability,
        'roles_with_capability_labels'    => array_map( $format_role_label, $roles_with_capability ),
        'roles_with_fallback'             => $roles_with_fallback,
        'roles_with_fallback_labels'      => array_map( $format_role_label, $roles_with_fallback ),
        'roles_missing_capability'        => $roles_missing_capability,
        'roles_missing_capability_labels' => array_map( $format_role_label, $roles_missing_capability ),
        'user'                            => $user_info,
        'issues'                          => array_values( array_unique( $issues ) ),
    );
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
            esc_url( restaurant_booking_get_settings_page_url() )
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

if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI_Command' ) ) {
    if ( ! class_exists( 'Restaurant_Booking_CLI_Command' ) ) {
        /**
         * WP-CLI helpers for diagnosing booking capability issues.
         */
        class Restaurant_Booking_CLI_Command extends WP_CLI_Command {

            /**
             * Diagnose management capability assignments.
             *
             * ## OPTIONS
             *
             * [--user=<id|login|email>]
             * : Inspect a specific user.
             *
             * ## EXAMPLES
             *
             *     wp restaurant-booking doctor
             *     wp restaurant-booking doctor --user=admin
             *
             * @param array $args       Positional arguments.
             * @param array $assoc_args Associative arguments.
             */
            public function doctor( $args, $assoc_args ) {
                if ( ! function_exists( 'restaurant_booking_get_manage_access_diagnostics' ) ) {
                    WP_CLI::error( 'Capability diagnostics are unavailable. Ensure the plugin is loaded.' );
                }

                $target_user = null;

                if ( isset( $assoc_args['user'] ) ) {
                    $target_user = $this->locate_user_for_doctor( $assoc_args['user'] );

                    if ( ! $target_user instanceof WP_User ) {
                        WP_CLI::error( 'Unable to locate the requested user.' );
                    }

                    $diagnostics = restaurant_booking_get_manage_access_diagnostics( $target_user->ID );
                } else {
                    $diagnostics = restaurant_booking_get_manage_access_diagnostics();
                }

                $capability = isset( $diagnostics['capability'] ) ? $diagnostics['capability'] : restaurant_booking_get_manage_capability();
                $fallback   = isset( $diagnostics['fallback'] ) ? $diagnostics['fallback'] : 'manage_options';

                WP_CLI::log( sprintf( 'Configured manage capability: %s', $capability ) );

                if ( $capability !== $fallback ) {
                    WP_CLI::log( sprintf( 'Fallback capability (when available): %s', $fallback ) );
                } else {
                    WP_CLI::log( 'Configured capability already matches manage_options.' );
                }

                if ( ! empty( $diagnostics['roles'] ) && is_array( $diagnostics['roles'] ) ) {
                    WP_CLI::log( 'Role capability assignments:' );

                    foreach ( $diagnostics['roles'] as $role_slug => $role_info ) {
                        if ( ! is_array( $role_info ) ) {
                            continue;
                        }

                        $status = sprintf( 'missing %s', $capability );

                        if ( ! empty( $role_info['has_capability'] ) ) {
                            $status = sprintf( 'has %s', $capability );
                        } elseif ( $capability !== $fallback && ! empty( $role_info['has_fallback'] ) ) {
                            $status = sprintf( 'falls back via %s', $fallback );
                        }

                        WP_CLI::line( sprintf( '  - %s: %s', $role_slug, $status ) );
                    }
                } else {
                    WP_CLI::warning( 'No roles were detected. Ensure WordPress is fully loaded.' );
                }

                $user_info = isset( $diagnostics['user'] ) && is_array( $diagnostics['user'] )
                    ? $diagnostics['user']
                    : null;

                if ( $user_info ) {
                    $user_login = ! empty( $user_info['login'] ) ? $user_info['login'] : 'unknown';
                    $user_id    = isset( $user_info['id'] ) ? (int) $user_info['id'] : 0;

                    WP_CLI::log( sprintf( 'User %s (%d):', $user_login, $user_id ) );

                    if ( ! empty( $user_info['has_capability'] ) ) {
                        WP_CLI::success( sprintf( 'User already has %s.', $capability ) );
                    } elseif ( $capability !== $fallback && ! empty( $user_info['has_fallback'] ) ) {
                        WP_CLI::warning( sprintf( 'User lacks %1$s but inherits access through %2$s.', $capability, $fallback ) );
                    } else {
                        WP_CLI::warning( sprintf( 'User is missing both %1$s and %2$s.', $capability, $fallback ) );

                        if ( isset( $user_info['role_labels'] ) ) {
                            if ( ! empty( $user_info['role_labels'] ) ) {
                                WP_CLI::log( sprintf( 'Assigned roles: %s', implode( ', ', $user_info['role_labels'] ) ) );
                            } else {
                                WP_CLI::log( 'Assigned roles: (none)' );
                            }
                        }
                    }
                } else {
                    WP_CLI::log( 'Provide --user=<id|login|email> to inspect a specific account.' );
                }

                if ( ! empty( $diagnostics['issues'] ) && is_array( $diagnostics['issues'] ) ) {
                    WP_CLI::log( 'Potential issues detected:' );

                    foreach ( $diagnostics['issues'] as $issue ) {
                        WP_CLI::line( '  - ' . $issue );
                    }
                }

                WP_CLI::log( sprintf( 'Add the capability to a role with: wp cap add <role> %s', $capability ) );
            }

            /**
             * Locate a user by id, login or email for the doctor command.
             *
             * @param string $identifier User identifier.
             *
             * @return WP_User|null
             */
            private function locate_user_for_doctor( $identifier ) {
                if ( is_numeric( $identifier ) ) {
                    $user = get_user_by( 'id', (int) $identifier );
                    if ( $user instanceof WP_User ) {
                        return $user;
                    }
                }

                $user = get_user_by( 'login', $identifier );
                if ( $user instanceof WP_User ) {
                    return $user;
                }

                $user = get_user_by( 'email', $identifier );
                if ( $user instanceof WP_User ) {
                    return $user;
                }

                return null;
            }
        }
    }

    WP_CLI::add_command( 'restaurant-booking', 'Restaurant_Booking_CLI_Command' );
}
