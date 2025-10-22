<?php
/**
 * Shared admin header component.
 *
 * @package RestaurantBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_page = isset( $current_page ) ? $current_page : 'rb-dashboard';
$settings_slug = function_exists( 'restaurant_booking_get_settings_page_slug' )
    ? restaurant_booking_get_settings_page_slug()
    : 'restaurant-booking-settings';

if ( 'rb-settings' === $current_page ) {
    $current_page = $settings_slug;
}

$menu_items = array(
    'rb-dashboard' => array(
        'label' => __( 'Dashboard', 'restaurant-booking' ),
        'icon'  => 'dashicons-chart-pie',
    ),
    'rb-bookings'  => array(
        'label' => __( 'Bookings', 'restaurant-booking' ),
        'icon'  => 'dashicons-calendar-alt',
    ),
    'rb-locations' => array(
        'label' => __( 'Locations', 'restaurant-booking' ),
        'icon'  => 'dashicons-location-alt',
    ),
    $settings_slug => array(
        'label' => __( 'Settings', 'restaurant-booking' ),
        'icon'  => 'dashicons-admin-generic',
    ),
    'rb-reports'   => array(
        'label' => __( 'Reports', 'restaurant-booking' ),
        'icon'  => 'dashicons-analytics',
    ),
);

$badge_counts = apply_filters( 'rb_admin_menu_badge_counts', array() );

$current_user = wp_get_current_user();
$display_name = $current_user && $current_user->exists() ? $current_user->display_name : __( 'Admin', 'restaurant-booking' );
$display_name = $display_name ? $display_name : __( 'Admin', 'restaurant-booking' );

$initials = strtoupper( mb_substr( $display_name, 0, 1 ) );

if ( strpos( $display_name, ' ' ) !== false ) {
    $parts    = preg_split( '/\s+/', $display_name );
    $initials = strtoupper( mb_substr( $parts[0], 0, 1 ) . ( isset( $parts[ count( $parts ) - 1 ] ) ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '' ) );
}

$theme_toggle_id = isset( $theme_toggle_id ) ? $theme_toggle_id : 'rb-admin-theme-toggle';
?>
<header class="rb-admin-header" aria-label="<?php esc_attr_e( 'Restaurant Booking admin header', 'restaurant-booking' ); ?>">
    <div class="rb-admin-branding">
        <div class="rb-admin-logo">
            <span class="rb-admin-logo-mark" aria-hidden="true">🍽️</span>
            <span class="rb-admin-logo-text"><?php esc_html_e( 'Restaurant Booking', 'restaurant-booking' ); ?></span>
        </div>
        <button class="rb-btn rb-btn-icon rb-theme-toggle" id="<?php echo esc_attr( $theme_toggle_id ); ?>" type="button">
            <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php esc_html_e( 'Toggle dark mode', 'restaurant-booking' ); ?></span>
        </button>
    </div>

    <nav class="rb-admin-nav" aria-label="<?php esc_attr_e( 'Restaurant Booking navigation', 'restaurant-booking' ); ?>">
        <ul class="rb-admin-menu">
            <?php foreach ( $menu_items as $slug => $item ) :
                $is_active = $slug === $current_page;
                $badge     = isset( $badge_counts[ $slug ] ) ? (int) $badge_counts[ $slug ] : 0;
                $menu_url  = function_exists( 'restaurant_booking_get_admin_page_url' )
                    ? restaurant_booking_get_admin_page_url( $slug )
                    : admin_url( 'admin.php?page=' . $slug );
                ?>
                <li class="rb-admin-menu-entry">
                    <a href="<?php echo esc_url( $menu_url ); ?>" class="rb-admin-menu-item<?php echo $is_active ? ' rb-active' : ''; ?>" data-menu-item="<?php echo esc_attr( $slug ); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                        <span class="rb-admin-menu-icon dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
                        <span class="rb-admin-menu-label"><?php echo esc_html( $item['label'] ); ?></span>
                        <?php if ( $badge > 0 ) : ?>
                            <span class="rb-admin-badge" aria-label="<?php echo esc_attr( sprintf( _n( '%d item', '%d items', $badge, 'restaurant-booking' ), $badge ) ); ?>"><?php echo esc_html( $badge ); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="rb-admin-user">
        <button class="rb-admin-user-btn" type="button">
            <span class="rb-admin-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
            <span class="rb-admin-user-name"><?php echo esc_html( $display_name ); ?></span>
            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
        </button>
    </div>
</header>
