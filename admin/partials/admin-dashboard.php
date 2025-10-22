<?php
/**
 * Modern admin dashboard layout.
 *
 * @package RestaurantBooking
 */

$current_page = 'rb-dashboard';
require __DIR__ . '/shared/header.php';

$settings_slug = function_exists( 'restaurant_booking_get_settings_page_slug' )
    ? restaurant_booking_get_settings_page_slug()
    : 'restaurant-booking-settings';

$settings_url = function_exists( 'restaurant_booking_get_settings_page_url' )
    ? restaurant_booking_get_settings_page_url()
    : admin_url( 'admin.php?page=' . $settings_slug );

$bookings_url = function_exists( 'restaurant_booking_get_admin_page_url' )
    ? restaurant_booking_get_admin_page_url( 'rb-bookings' )
    : admin_url( 'admin.php?page=rb-bookings' );

$locations_url = function_exists( 'restaurant_booking_get_admin_page_url' )
    ? restaurant_booking_get_admin_page_url( 'rb-locations' )
    : admin_url( 'admin.php?page=rb-locations' );
?>
<div class="rb-admin-wrapper">
    <aside class="rb-admin-sidebar" aria-label="<?php esc_attr_e( 'Quick statistics', 'restaurant-booking' ); ?>">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Today\'s snapshot', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-grid" id="rb-admin-sidebar-stats">
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Total bookings', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-summary-bookings">--</span>
                    <span class="rb-admin-stat-change" id="rb-admin-summary-bookings-change">0%</span>
                </div>
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Revenue', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-summary-revenue">--</span>
                    <span class="rb-admin-stat-change" id="rb-admin-summary-revenue-change">0%</span>
                </div>
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Occupancy', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-summary-occupancy">--</span>
                    <span class="rb-admin-stat-change" id="rb-admin-summary-occupancy-change">0%</span>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Quick actions', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <a class="rb-admin-activity-item" href="<?php echo esc_url( $bookings_url ); ?>">
                    <div class="rb-admin-activity-icon" aria-hidden="true">✅</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Confirm bookings', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Review pending reservations awaiting approval.', 'restaurant-booking' ); ?></div>
                    </div>
                </a>
                <a class="rb-admin-activity-item" href="<?php echo esc_url( $locations_url ); ?>">
                    <div class="rb-admin-activity-icon" aria-hidden="true">📍</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Manage locations', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Update seating capacity and operating hours.', 'restaurant-booking' ); ?></div>
                    </div>
                </a>
                <a class="rb-admin-activity-item" href="<?php echo esc_url( $settings_url ); ?>">
                    <div class="rb-admin-activity-icon" aria-hidden="true">⚙️</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Adjust policies', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Fine-tune booking buffers and notifications.', 'restaurant-booking' ); ?></div>
                    </div>
                </a>
            </div>
        </section>
    </aside>

    <main class="rb-admin-content" aria-live="polite">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <div>
                    <h1 class="rb-admin-title"><?php esc_html_e( 'Multi-location overview', 'restaurant-booking' ); ?></h1>
                    <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Monitor performance across every branch in real time.', 'restaurant-booking' ); ?></p>
                </div>
                <div class="rb-admin-actions">
                    <button class="rb-btn rb-btn-outline" type="button" data-action="refresh" id="rb-dashboard-refresh">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Refresh', 'restaurant-booking' ); ?>
                    </button>
                    <button class="rb-btn rb-btn-primary" type="button">
                        <span class="dashicons dashicons-plus" aria-hidden="true"></span>
                        <?php esc_html_e( 'Add location', 'restaurant-booking' ); ?>
                    </button>
                </div>
            </header>
            <div class="rb-admin-grid cols-2 rb-admin-dashboard-grid" id="rb-admin-dashboard-root">
                <div class="rb-admin-panel rb-admin-panel-skeleton">
                    <div class="rb-admin-skeleton-title"></div>
                    <div class="rb-admin-skeleton-grid">
                        <span class="rb-admin-skeleton-bar"></span>
                        <span class="rb-admin-skeleton-bar"></span>
                        <span class="rb-admin-skeleton-bar"></span>
                    </div>
                </div>
                <div class="rb-admin-panel rb-admin-panel-skeleton">
                    <div class="rb-admin-skeleton-title"></div>
                    <div class="rb-admin-skeleton-grid">
                        <span class="rb-admin-skeleton-bar"></span>
                        <span class="rb-admin-skeleton-bar"></span>
                        <span class="rb-admin-skeleton-bar"></span>
                    </div>
                </div>
            </div>
            <div class="rb-loading-overlay" id="rb-admin-loading" role="status" aria-live="polite" aria-busy="true">
                <div class="rb-loading-spinner"></div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <div>
                    <h2><?php esc_html_e( 'System analytics', 'restaurant-booking' ); ?></h2>
                    <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Revenue, booking trends, and guest sentiment at a glance.', 'restaurant-booking' ); ?></p>
                </div>
                <div class="rb-admin-actions rb-admin-actions-pill">
                    <button class="rb-btn rb-btn-outline" type="button" data-range="7">
                        <?php esc_html_e( '7 days', 'restaurant-booking' ); ?>
                    </button>
                    <button class="rb-btn rb-btn-outline" type="button" data-range="30">
                        <?php esc_html_e( '30 days', 'restaurant-booking' ); ?>
                    </button>
                    <button class="rb-btn rb-btn-outline" type="button" data-range="90">
                        <?php esc_html_e( '90 days', 'restaurant-booking' ); ?>
                    </button>
                </div>
            </header>
            <div class="rb-admin-grid cols-2">
                <div class="rb-admin-chart" id="rb-admin-chart-placeholder">
                    <div class="rb-chart-empty"><?php esc_html_e( 'Connect analytics to see charts populated with live data.', 'restaurant-booking' ); ?></div>
                </div>
                <div class="rb-admin-card-list" id="rb-admin-highlights">
                    <div class="rb-admin-activity-item">
                        <div class="rb-admin-activity-icon" aria-hidden="true">⭐</div>
                        <div>
                            <div class="rb-admin-stat-label"><?php esc_html_e( 'Top location', 'restaurant-booking' ); ?></div>
                            <div class="rb-admin-stat-value" id="rb-admin-top-location">—</div>
                            <div class="rb-admin-activity-meta" id="rb-admin-top-location-meta"></div>
                        </div>
                    </div>
                    <div class="rb-admin-activity-item">
                        <div class="rb-admin-activity-icon" aria-hidden="true">⏱️</div>
                        <div>
                            <div class="rb-admin-stat-label"><?php esc_html_e( 'Peak dining time', 'restaurant-booking' ); ?></div>
                            <div class="rb-admin-stat-value" id="rb-admin-peak-time">—</div>
                            <div class="rb-admin-activity-meta" id="rb-admin-peak-time-meta"></div>
                        </div>
                    </div>
                    <div class="rb-admin-activity-item">
                        <div class="rb-admin-activity-icon" aria-hidden="true">💬</div>
                        <div>
                            <div class="rb-admin-stat-label"><?php esc_html_e( 'Guest sentiment', 'restaurant-booking' ); ?></div>
                            <div class="rb-admin-stat-value" id="rb-admin-sentiment">—</div>
                            <div class="rb-admin-activity-meta" id="rb-admin-sentiment-meta"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Recent activity', 'restaurant-booking' ); ?></h2>
                <button class="rb-btn rb-btn-outline" type="button" id="rb-admin-export-recent">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php esc_html_e( 'Export', 'restaurant-booking' ); ?>
                </button>
            </header>
            <div class="rb-admin-activity-feed" id="rb-admin-activity-feed">
                <div class="rb-admin-empty-state">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2a5 5 0 110 10 5 5 0 010-10zm0 12c5.33 0 8 2.67 8 8H4c0-5.33 2.67-8 8-8z"/></svg>
                    <p><?php esc_html_e( 'Activity will appear here after bookings are processed today.', 'restaurant-booking' ); ?></p>
                </div>
            </div>
        </section>
    </main>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
