<?php
/**
 * Portal dashboard template (Phase 4)
 */

$user_name    = isset( $this->current_user['name'] ) ? $this->current_user['name'] : __( 'Manager', 'restaurant-booking' );
$user_initial = strtoupper( mb_substr( wp_strip_all_tags( $user_name ), 0, 2, 'UTF-8' ) );
$locations    = isset( $locations ) ? $locations : array();
$current_location = isset( $current_location ) ? $current_location : $this->get_current_location();
$notifications = isset( $notifications ) ? $notifications : array();
$notification_count = count( $notifications );
$metric_periods = isset( $metric_periods ) ? $metric_periods : $this->get_default_metric_periods();

$mobile_time_slots   = isset( $schedule_data['timeSlots'] ) ? $schedule_data['timeSlots'] : array();
$mobile_summary      = isset( $schedule_data['summary'] ) ? $schedule_data['summary'] : array();
$mobile_date_label   = isset( $schedule_data['dateLabel'] ) ? $schedule_data['dateLabel'] : wp_date( 'F j, Y' );
$mobile_stats        = array(
    'bookings'   => isset( $initial_stats['bookings'] ) ? $initial_stats['bookings'] : array(),
    'revenue'    => isset( $initial_stats['revenue'] ) ? $initial_stats['revenue'] : array(),
    'occupancy'  => isset( $initial_stats['occupancy'] ) ? $initial_stats['occupancy'] : array(),
);
$mobile_pending_stat = isset( $initial_stats['pending'] ) ? $initial_stats['pending'] : array();
$mobile_pending_count = isset( $mobile_pending_stat['value'] ) ? (int) $mobile_pending_stat['value'] : 0;

$format_mobile_stat = static function ( $stat, $decimals = 0 ) {
    if ( empty( $stat ) || ! is_array( $stat ) ) {
        return '0';
    }

    $value  = isset( $stat['value'] ) ? $stat['value'] : 0;
    $prefix = isset( $stat['prefix'] ) ? $stat['prefix'] : '';
    $suffix = isset( $stat['suffix'] ) ? $stat['suffix'] : '';

    if ( is_numeric( $value ) ) {
        $value = number_format_i18n( (float) $value, $decimals );
    }

    return $prefix . $value . $suffix;
};

$format_mobile_change = static function ( $stat ) {
    $percentage = isset( $stat['change']['percentage'] ) ? (float) $stat['change']['percentage'] : 0;
    $period     = isset( $stat['change']['period'] ) ? $stat['change']['period'] : __( 'vs. previous period', 'restaurant-booking' );
    $class      = 'neutral';

    if ( $percentage > 0 ) {
        $class = 'positive';
    } elseif ( $percentage < 0 ) {
        $class = 'negative';
    }

    $formatted = sprintf(
        '%1$s%2$s%% %3$s',
        $percentage > 0 ? '+' : '',
        number_format_i18n( abs( $percentage ), 0 ),
        $period
    );

    return array( 'class' => $class, 'label' => $formatted );
};

$mobile_guest_label = static function ( $party_size ) {
    $party_size = (int) $party_size;
    if ( $party_size <= 0 ) {
        return __( 'No party size', 'restaurant-booking' );
    }

    return sprintf(
        _n( '%s guest', '%s guests', $party_size, 'restaurant-booking' ),
        number_format_i18n( $party_size )
    );
};
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="<?php echo esc_url( plugins_url( 'manifest.json', RB_PLUGIN_FILE ) ); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'rb-portal-dashboard' ); ?>>
    <div class="rb-dashboard-loading" id="dashboard-loader" style="display: none;">
        <div class="rb-loading-spinner" aria-hidden="true"></div>
        <span><?php esc_html_e( 'Loading dashboard...', 'restaurant-booking' ); ?></span>
    </div>

    <div class="rb-refresh-indicator" id="refresh-indicator" style="display: none;">
        <span><?php esc_html_e( 'Dashboard updated', 'restaurant-booking' ); ?></span>
    </div>

    <?php
    $current_view = function_exists( 'restaurant_booking_get_portal_view' )
        ? restaurant_booking_get_portal_view()
        : ( isset( $_GET['rb_portal'] ) ? sanitize_key( wp_unslash( $_GET['rb_portal'] ) ) : '' );
    if ( empty( $current_view ) ) {
        $current_view = 'dashboard';
    }
    $mobile_nav_items = array(
        'dashboard' => array(
            'title' => __( 'Dashboard', 'restaurant-booking' ),
            'icon'  => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
            'url'   => $this->build_portal_url( 'dashboard' ),
            'badge' => 0,
        ),
        'bookings' => array(
            'title' => __( 'Bookings', 'restaurant-booking' ),
            'icon'  => 'M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z',
            'url'   => $this->build_portal_url( 'bookings' ),
            'badge' => $mobile_pending_count,
        ),
        'tables' => array(
            'title' => __( 'Tables', 'restaurant-booking' ),
            'icon'  => 'M20 6v2h-2v6h-1.5v-6h-5v6H10V8H8V6h12zM6 10v2H4v6H2.5v-6H1v-2h5z',
            'url'   => $this->build_portal_url( 'tables' ),
            'badge' => 0,
        ),
        'customers' => array(
            'title' => __( 'Customers', 'restaurant-booking' ),
            'icon'  => 'M12 5.9c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1S9.9 9.16 9.9 8s.94-2.1 2.1-2.1m0 9c2.97 0 6.1 1.46 6.1 2.1v1.1H5.9V17c0-.64 3.13-2.1 6.1-2.1M12 4C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 9c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z',
            'url'   => $this->build_portal_url( 'customers' ),
            'badge' => 0,
        ),
        'reports' => array(
            'title' => __( 'Reports', 'restaurant-booking' ),
            'icon'  => 'M3 13h2v-2H3v2m0 4h2v-2H3v2m0-8h2V7H3v2m4 8h14v-2H7v2m0-4h14v-2H7v2m0-6v2h14V7H7z',
            'url'   => $this->build_portal_url( 'reports' ),
            'badge' => 0,
        ),
    );
    ?>

    <div class="rb-mobile-shell">
        <header class="rb-mobile-header">
            <button class="rb-mobile-icon-button" type="button" id="hamburgerBtn" aria-label="<?php esc_attr_e( 'Open navigation', 'restaurant-booking' ); ?>">
                <span class="rb-hamburger" aria-hidden="true"><span></span><span></span><span></span></span>
            </button>
            <div class="rb-mobile-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
            <div class="rb-mobile-header-actions">
                <button class="rb-mobile-icon-button" type="button" aria-label="<?php esc_attr_e( 'Notifications', 'restaurant-booking' ); ?>">
                    <span class="sr-only"><?php esc_html_e( 'Notifications', 'restaurant-booking' ); ?></span>
                    <svg width="22" height="22" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2m6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2Z" />
                    </svg>
                    <?php if ( $notification_count > 0 ) : ?>
                        <span class="rb-mobile-notification-badge"><?php echo esc_html( number_format_i18n( $notification_count ) ); ?></span>
                    <?php endif; ?>
                </button>
                <span class="rb-mobile-avatar" aria-hidden="true"><?php echo esc_html( $user_initial ); ?></span>
            </div>
        </header>

        <nav class="rb-mobile-nav" id="mobileNav" aria-label="<?php esc_attr_e( 'Mobile navigation', 'restaurant-booking' ); ?>">
            <div class="rb-mobile-nav-header">
                <div class="rb-mobile-logo">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8.1 13.34 10.93 10.5 3.91 3.5a4 4 0 0 0 0 5.66l4.19 4.18Zm6.78-1.81c1.53.71 3.68.21 5.27-1.38 1.91-1.91 2.28-4.65.81-6.12-1.46-1.46-4.2-1.1-6.12.81-1.59 1.59-2.09 3.74-1.38 5.27L3.7 19.87l1.41 1.41L12 14.41l6.88 6.88 1.41-1.41L13.41 13l1.47-1.47Z" />
                    </svg>
                    <span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                </div>
                <button class="rb-mobile-nav-close" type="button" id="closeMobileNav" aria-label="<?php esc_attr_e( 'Close menu', 'restaurant-booking' ); ?>">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m19 6.41-1.41-1.4L12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12Z" />
                    </svg>
                </button>
            </div>
            <div class="rb-mobile-nav-menu">
                <?php foreach ( $mobile_nav_items as $key => $item ) :
                    $active = $current_view === $key ? 'active' : '';
                    ?>
                    <a href="<?php echo esc_url( $item['url'] ); ?>" class="rb-mobile-nav-item <?php echo esc_attr( $active ); ?>">
                        <svg class="rb-nav-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="<?php echo esc_attr( $item['icon'] ); ?>" />
                        </svg>
                        <span><?php echo esc_html( $item['title'] ); ?></span>
                        <?php if ( ! empty( $item['badge'] ) ) : ?>
                            <span class="rb-nav-badge"><?php echo esc_html( number_format_i18n( $item['badge'] ) ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="rb-mobile-nav-footer">
                <div class="rb-mobile-user">
                    <div class="rb-mobile-avatar" aria-hidden="true"><?php echo esc_html( $user_initial ); ?></div>
                    <div class="rb-mobile-user-info">
                        <div class="rb-mobile-user-name"><?php echo esc_html( $user_name ); ?></div>
                        <div class="rb-mobile-user-role"><?php echo esc_html( wp_get_current_user()->roles[0] ?? __( 'User', 'restaurant-booking' ) ); ?></div>
                    </div>
                </div>
                <a class="rb-mobile-logout" href="<?php echo esc_url( wp_logout_url() ); ?>"><?php esc_html_e( 'Logout', 'restaurant-booking' ); ?></a>
            </div>
        </nav>

        <div class="rb-mobile-overlay" id="mobileOverlay" aria-hidden="true"></div>

        <main class="rb-mobile-content rb-pull-to-refresh" role="main">
            <section class="rb-mobile-stats" aria-label="<?php esc_attr_e( 'Dashboard statistics', 'restaurant-booking' ); ?>">
                <?php foreach ( array( 'bookings', 'revenue', 'occupancy' ) as $metric_key ) :
                    $stat   = isset( $mobile_stats[ $metric_key ] ) ? $mobile_stats[ $metric_key ] : array();
                    $label  = isset( $stat['label'] ) ? $stat['label'] : '';
                    $change = $format_mobile_change( $stat );
                    ?>
                    <article class="rb-mobile-stat-card" data-mobile-metric="<?php echo esc_attr( $metric_key ); ?>">
                        <div class="rb-mobile-stat-number"><?php echo esc_html( $format_mobile_stat( $stat ) ); ?></div>
                        <div class="rb-mobile-stat-label"><?php echo esc_html( $label ); ?></div>
                        <div class="rb-mobile-stat-change <?php echo esc_attr( $change['class'] ); ?>"><?php echo esc_html( $change['label'] ); ?></div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="rb-mobile-quick-actions" aria-label="<?php esc_attr_e( 'Quick actions', 'restaurant-booking' ); ?>">
                <h2 class="rb-mobile-section-title"><?php esc_html_e( 'Quick Actions', 'restaurant-booking' ); ?></h2>
                <div class="rb-mobile-action-grid">
                    <button class="rb-mobile-action-btn rb-btn-touch" type="button" data-action="new-booking">
                        <span class="rb-mobile-action-icon" aria-hidden="true">➕</span>
                        <span class="rb-mobile-action-text"><?php esc_html_e( 'New Booking', 'restaurant-booking' ); ?></span>
                    </button>
                    <button class="rb-mobile-action-btn rb-btn-touch" type="button" data-action="walk-in">
                        <span class="rb-mobile-action-icon" aria-hidden="true">👣</span>
                        <span class="rb-mobile-action-text"><?php esc_html_e( 'Walk-in', 'restaurant-booking' ); ?></span>
                    </button>
                    <button class="rb-mobile-action-btn rb-btn-touch" type="button" data-action="pending">
                        <span class="rb-mobile-action-icon" aria-hidden="true">⏳</span>
                        <span class="rb-mobile-action-text"><?php esc_html_e( 'Pending', 'restaurant-booking' ); ?></span>
                        <?php if ( $mobile_pending_count > 0 ) : ?>
                            <span class="rb-nav-badge"><?php echo esc_html( number_format_i18n( $mobile_pending_count ) ); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="rb-mobile-action-btn rb-btn-touch" type="button" data-action="tables">
                        <span class="rb-mobile-action-icon" aria-hidden="true">🪑</span>
                        <span class="rb-mobile-action-text"><?php esc_html_e( 'Tables', 'restaurant-booking' ); ?></span>
                    </button>
                    <button class="rb-mobile-action-btn rb-btn-touch" type="button" data-action="reports">
                        <span class="rb-mobile-action-icon" aria-hidden="true">📊</span>
                        <span class="rb-mobile-action-text"><?php esc_html_e( 'Reports', 'restaurant-booking' ); ?></span>
                    </button>
                </div>
            </section>

            <section class="rb-mobile-schedule" aria-label="<?php esc_attr_e( "Today's bookings", 'restaurant-booking' ); ?>">
                <div class="rb-mobile-schedule-header">
                    <h2 class="rb-mobile-section-title"><?php esc_html_e( "Today's Schedule", 'restaurant-booking' ); ?></h2>
                    <span id="mobileScheduleDate"><?php echo esc_html( $mobile_date_label ); ?></span>
                </div>
                <div class="rb-mobile-booking-list">
                    <?php
                    $rendered_bookings = 0;
                    foreach ( (array) $mobile_time_slots as $slot ) {
                        if ( empty( $slot['bookings'] ) ) {
                            continue;
                        }

                        $time_label = isset( $slot['timeLabel'] ) ? $slot['timeLabel'] : '';

                        foreach ( (array) $slot['bookings'] as $booking ) {
                            $rendered_bookings++;
                            $booking_id    = isset( $booking['id'] ) ? $booking['id'] : '';
                            $customer_name = isset( $booking['customerName'] ) ? $booking['customerName'] : '';
                            $details       = isset( $booking['details'] ) ? $booking['details'] : '';
                            $status        = isset( $booking['status'] ) ? $booking['status'] : '';
                            $status_class  = isset( $booking['statusClass'] ) ? $booking['statusClass'] : '';
                            $party_size    = isset( $booking['partySize'] ) ? $booking['partySize'] : '';
                            ?>
                            <article class="rb-booking-card-mobile" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
                                <div class="rb-booking-card-content">
                                    <div class="rb-booking-meta">
                                        <span class="rb-booking-time"><?php echo esc_html( $time_label ); ?></span>
                                        <?php if ( $party_size ) : ?>
                                            <span class="rb-booking-party"><?php echo esc_html( $mobile_guest_label( $party_size ) ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rb-booking-details">
                                        <div class="rb-booking-customer"><?php echo esc_html( $customer_name ); ?></div>
                                        <?php if ( $details ) : ?>
                                            <div class="rb-booking-notes"><?php echo esc_html( $details ); ?></div>
                                        <?php endif; ?>
                                        <div class="rb-booking-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></div>
                                    </div>
                                </div>
                                <div class="rb-booking-card-swipe-actions" aria-hidden="true">
                                    <button class="rb-swipe-action" type="button" data-action="confirm">
                                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M9 16.17 4.83 12 3.41 13.41 9 19l12-12-1.41-1.41Z" />
                                        </svg>
                                        <span><?php esc_html_e( 'Confirm', 'restaurant-booking' ); ?></span>
                                    </button>
                                    <button class="rb-swipe-action" type="button" data-action="reschedule">
                                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2m0 16H5V8h14Z" />
                                        </svg>
                                        <span><?php esc_html_e( 'Reschedule', 'restaurant-booking' ); ?></span>
                                    </button>
                                    <button class="rb-swipe-action" type="button" data-action="cancel">
                                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="m19 6.41-1.41-1.4L12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12Z" />
                                        </svg>
                                        <span><?php esc_html_e( 'Cancel', 'restaurant-booking' ); ?></span>
                                    </button>
                                </div>
                            </article>
                            <?php
                        }
                    }

                    if ( 0 === $rendered_bookings ) :
                        ?>
                        <div class="rb-mobile-empty">
                            <svg width="36" height="36" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M19 4H5a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3h6v2H8v2h8v-2h-3v-2h6a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9h16Z" />
                            </svg>
                            <span><?php esc_html_e( 'No bookings scheduled for today.', 'restaurant-booking' ); ?></span>
                            <button class="rb-btn rb-btn-sm rb-btn-primary" type="button" data-action="new-booking"><?php esc_html_e( 'Add booking', 'restaurant-booking' ); ?></button>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <nav class="rb-mobile-bottom-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'restaurant-booking' ); ?>">
            <?php foreach ( array( 'dashboard', 'bookings', 'tables', 'reports' ) as $key ) :
                if ( ! isset( $mobile_nav_items[ $key ] ) ) {
                    continue;
                }
                $item = $mobile_nav_items[ $key ];
                $active = $current_view === $key ? 'active' : '';
                ?>
                <a class="rb-bottom-nav-item <?php echo esc_attr( $active ); ?>" href="<?php echo esc_url( $item['url'] ); ?>" data-nav="<?php echo esc_attr( $key ); ?>">
                    <svg class="rb-bottom-nav-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="<?php echo esc_attr( $item['icon'] ); ?>" />
                    </svg>
                    <span><?php echo esc_html( $item['title'] ); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="rb-dashboard-container">
        <div class="rb-dashboard-header">
            <div class="rb-dashboard-title-section">
                <h1 class="rb-dashboard-title"><?php esc_html_e( 'Dashboard', 'restaurant-booking' ); ?></h1>
                <div class="rb-dashboard-date" id="current-date"><?php echo esc_html( sprintf( __( 'Today: %s', 'restaurant-booking' ), wp_date( 'F j, Y' ) ) ); ?></div>
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
                    <input type="checkbox" id="auto-refresh-toggle" checked>
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

    <?php wp_footer(); ?>
</body>
</html>
