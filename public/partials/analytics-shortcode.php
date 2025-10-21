<?php
/**
 * Analytics shortcode template.
 *
 * @package RestaurantBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$locations         = isset( $locations ) && is_array( $locations ) ? $locations : array();
$current_location  = isset( $current_location ) ? $current_location : '';
$user_name         = isset( $user_name ) ? $user_name : __( 'Manager', 'restaurant-booking' );
$user_initial      = strtoupper( mb_substr( wp_strip_all_tags( $user_name ), 0, 2, 'UTF-8' ) );
$notifications     = isset( $notifications ) ? $notifications : array();
$notification_count = is_array( $notifications ) ? count( $notifications ) : 0;
$metric_periods    = isset( $metric_periods ) ? $metric_periods : array();
$initial_stats     = isset( $initial_stats ) ? $initial_stats : array();
$schedule_data     = isset( $schedule_data ) ? $schedule_data : array();
?>
<div class="rb-dashboard-container rb-dashboard-container--shortcode">
    <div class="rb-dashboard-header">
        <div class="rb-dashboard-title-section">
            <h2 class="rb-dashboard-title"><?php esc_html_e( 'Analytics Overview', 'restaurant-booking' ); ?></h2>
            <div class="rb-dashboard-date"><?php echo esc_html( wp_date( 'F j, Y' ) ); ?></div>
        </div>
        <div class="rb-dashboard-controls">
            <div class="rb-location-selector">
                <label class="screen-reader-text" for="location-selector"><?php esc_html_e( 'Select location', 'restaurant-booking' ); ?></label>
                <select class="rb-location-select" id="location-selector">
                    <?php foreach ( $locations as $location ) : ?>
                        <option value="<?php echo esc_attr( $location['id'] ); ?>" <?php selected( (int) $location['id'], (int) $current_location ); ?>>
                            <?php echo esc_html( '📍 ' . $location['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="rb-auto-refresh" for="auto-refresh-toggle">
                <input type="checkbox" id="auto-refresh-toggle" checked />
                <span><?php esc_html_e( 'Auto refresh', 'restaurant-booking' ); ?></span>
            </label>

            <button class="rb-btn rb-btn-icon" id="theme-toggle" type="button" title="<?php esc_attr_e( 'Toggle theme', 'restaurant-booking' ); ?>">
                <svg class="rb-theme-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.37a5.389 5.389 0 01-4.4 2.375 5.403 5.403 0 01-3.4-9.73A8.81 8.81 0 0012 3z" />
                </svg>
            </button>

            <div class="rb-notifications-wrapper">
                <button class="rb-btn rb-btn-icon" id="notifications-toggle" type="button" title="<?php esc_attr_e( 'Notifications', 'restaurant-booking' ); ?>">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5S10.5 3.17 10.5 4v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
                    </svg>
                    <?php if ( $notification_count > 0 ) : ?>
                        <span class="rb-notification-badge"><?php echo esc_html( $notification_count ); ?></span>
                    <?php endif; ?>
                </button>
                <div class="rb-notifications-panel" id="notifications-panel">
                    <?php foreach ( $notifications as $notification ) : ?>
                        <div class="rb-notification-item">
                            <div class="rb-notification-icon">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8" />
                                </svg>
                            </div>
                            <div class="rb-notification-content">
                                <div class="rb-notification-title"><?php echo esc_html( isset( $notification['title'] ) ? $notification['title'] : '' ); ?></div>
                                <div class="rb-notification-meta"><?php echo esc_html( isset( $notification['meta'] ) ? $notification['meta'] : '' ); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rb-user-menu">
                <div class="rb-user-avatar" aria-hidden="true"><?php echo esc_html( $user_initial ); ?></div>
                <span class="rb-user-name"><?php echo esc_html( $user_name ); ?></span>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/dashboard-stats-section.php'; ?>
    <?php include __DIR__ . '/dashboard-content-section.php'; ?>
</div>
