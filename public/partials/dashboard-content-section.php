<?php
/**
 * Dashboard content section (Phase 4)
 *
 * Variables expected:
 * - $schedule_data (array)
 * - $initial_stats (array)
 */

$time_slots = isset( $schedule_data['timeSlots'] ) ? $schedule_data['timeSlots'] : array();
$summary    = isset( $schedule_data['summary'] ) ? $schedule_data['summary'] : array();
$date_label = isset( $schedule_data['dateLabel'] ) ? $schedule_data['dateLabel'] : wp_date( 'F j, Y' );

$pending_stat = isset( $initial_stats['pending'] ) ? $initial_stats['pending'] : array();
$pending_count = isset( $pending_stat['value'] ) ? (int) $pending_stat['value'] : 0;

$format_number = static function ( $value ) {
    return number_format_i18n( (float) $value, 0 );
};
?>

<div class="rb-dashboard-content">
    <div class="rb-dashboard-main">
        <div class="rb-chart-container">
            <div class="rb-chart-header">
                <h3 class="rb-chart-title"><?php esc_html_e( 'Booking Trends', 'restaurant-booking' ); ?></h3>
                <div class="rb-chart-controls">
                    <div class="rb-chart-period" role="tablist">
                        <button class="rb-btn rb-btn-sm rb-active" data-period="7d" type="button"><?php esc_html_e( '7 Days', 'restaurant-booking' ); ?></button>
                        <button class="rb-btn rb-btn-sm" data-period="30d" type="button"><?php esc_html_e( '30 Days', 'restaurant-booking' ); ?></button>
                        <button class="rb-btn rb-btn-sm" data-period="90d" type="button"><?php esc_html_e( '90 Days', 'restaurant-booking' ); ?></button>
                    </div>
                    <button class="rb-btn rb-btn-sm rb-btn-outline" id="export-chart" type="button">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" />
                            <polyline points="14,2 14,8 20,8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                            <polyline points="10,9 9,9 8,9" />
                        </svg>
                        <?php esc_html_e( 'Export', 'restaurant-booking' ); ?>
                    </button>
                </div>
            </div>
            <div class="rb-chart-body">
                <canvas id="bookingTrendsChart" width="400" height="200" aria-label="<?php esc_attr_e( 'Booking trends chart', 'restaurant-booking' ); ?>"></canvas>
                <div class="rb-chart-loading" id="chart-loading" style="display: none;">
                    <div class="rb-loading-spinner" aria-hidden="true"></div>
                    <span><?php esc_html_e( 'Loading chart data...', 'restaurant-booking' ); ?></span>
                </div>
            </div>
            <div class="rb-chart-legend" aria-hidden="true">
                <div class="rb-legend-item">
                    <span class="rb-legend-color" style="background: var(--rb-primary-500);"></span>
                    <span class="rb-legend-label"><?php esc_html_e( 'Total Bookings', 'restaurant-booking' ); ?></span>
                </div>
                <div class="rb-legend-item">
                    <span class="rb-legend-color" style="background: var(--rb-success);"></span>
                    <span class="rb-legend-label"><?php esc_html_e( 'Confirmed', 'restaurant-booking' ); ?></span>
                </div>
                <div class="rb-legend-item">
                    <span class="rb-legend-color" style="background: var(--rb-warning);"></span>
                    <span class="rb-legend-label"><?php esc_html_e( 'Pending', 'restaurant-booking' ); ?></span>
                </div>
            </div>
        </div>

        <div class="rb-todays-schedule">
            <div class="rb-widget-header">
                <h3 class="rb-widget-title"><?php esc_html_e( "Today's Schedule", 'restaurant-booking' ); ?></h3>
                <div class="rb-schedule-date" id="schedule-date"><?php echo esc_html( $date_label ); ?></div>
            </div>
            <div class="rb-schedule-timeline" id="schedule-timeline">
                <?php foreach ( $time_slots as $slot ) :
                    $has_bookings = ! empty( $slot['bookings'] );
                    ?>
                    <div class="rb-schedule-time-slot<?php echo $has_bookings ? '' : ' rb-no-bookings'; ?>" data-time="<?php echo esc_attr( isset( $slot['time'] ) ? $slot['time'] : '' ); ?>">
                        <div class="rb-time-label"><?php echo esc_html( isset( $slot['timeLabel'] ) ? $slot['timeLabel'] : '' ); ?></div>
                        <div class="rb-bookings-list">
                            <?php if ( $has_bookings ) : ?>
                                <?php foreach ( $slot['bookings'] as $booking ) : ?>
                                    <div class="rb-booking-item" data-booking-id="<?php echo esc_attr( isset( $booking['id'] ) ? $booking['id'] : '' ); ?>">
                                        <div class="rb-booking-customer"><?php echo esc_html( isset( $booking['customerName'] ) ? $booking['customerName'] : '' ); ?></div>
                                        <div class="rb-booking-details"><?php echo esc_html( isset( $booking['details'] ) ? $booking['details'] : '' ); ?></div>
                                        <div class="rb-booking-status <?php echo esc_attr( isset( $booking['statusClass'] ) ? $booking['statusClass'] : '' ); ?>"><?php echo esc_html( isset( $booking['status'] ) ? $booking['status'] : '' ); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="rb-empty-slot">
                                    <span class="rb-empty-text"><?php esc_html_e( 'No bookings', 'restaurant-booking' ); ?></span>
                                    <button class="rb-btn rb-btn-sm rb-btn-outline" type="button"><?php esc_html_e( 'Add Booking', 'restaurant-booking' ); ?></button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="rb-schedule-summary">
                <div class="rb-summary-item">
                    <span class="rb-summary-label"><?php esc_html_e( 'Total Bookings:', 'restaurant-booking' ); ?></span>
                    <span class="rb-summary-value" data-summary="total-bookings"><?php echo esc_html( isset( $summary['totalBookings'] ) ? $summary['totalBookings'] : 0 ); ?></span>
                </div>
                <div class="rb-summary-item">
                    <span class="rb-summary-label"><?php esc_html_e( 'Expected Revenue:', 'restaurant-booking' ); ?></span>
                    <span class="rb-summary-value" data-summary="expected-revenue"><?php echo esc_html( isset( $summary['expectedRevenue'] ) ? $summary['expectedRevenue'] : '$0' ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="rb-dashboard-sidebar">
        <div class="rb-quick-actions">
            <div class="rb-widget-header">
                <h3 class="rb-widget-title"><?php esc_html_e( 'Quick Actions', 'restaurant-booking' ); ?></h3>
                <button class="rb-btn rb-btn-sm rb-btn-outline" type="button" id="customize-actions">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5 3.5 3.5 0 0 1 15.5 12 3.5 3.5 0 0 1 12 15.5m7.43-2.53c.04-.32.07-.64.07-.97s-.03-.65-.07-.97l2.11-1.63c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.31-.61-.22l-2.49 1c-.52-.39-1.06-.73-1.69-.98l-.37-2.65A.506.506 0 0 0 14 2h-4c-.25 0-.46.18-.5.42l-.37 2.65c-.63.25-1.17.59-1.69.98l-2.49-1c-.22-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64L4.57 11c-.04.32-.07.65-.07.97s.03.65.07.97l-2.11 1.63c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.31.61.22l2.49-1c.52.39 1.06.74 1.69.99l.37 2.65c.04.24.25.42.5.42h4c.25 0 .46-.18.5-.42l.37-2.65c.63-.25 1.17-.59 1.69-.99l2.49 1c.22.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64Z" />
                    </svg>
                    <?php esc_html_e( 'Customize', 'restaurant-booking' ); ?>
                </button>
            </div>
            <div class="rb-actions-grid" id="quick-actions-grid">
                <div class="rb-action-item" data-action="confirm-booking">
                    <div class="rb-action-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 16.17L4.83 12 3.41 13.41 9 19l12-12-1.41-1.41z" />
                        </svg>
                    </div>
                    <div class="rb-action-content">
                        <div class="rb-action-title"><?php esc_html_e( 'Confirm Booking', 'restaurant-booking' ); ?></div>
                        <div class="rb-action-description"><?php esc_html_e( 'Quick confirm pending reservations', 'restaurant-booking' ); ?></div>
                        <div class="rb-action-badge"><?php printf( esc_html__( '%d pending', 'restaurant-booking' ), $pending_count ); ?></div>
                    </div>
                </div>
                <div class="rb-action-item" data-action="add-walkin">
                    <div class="rb-action-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5.9c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1-2.1-.94-2.1-2.1.94-2.1 2.1-2.1m0 9c2.97 0 6.1 1.46 6.1 2.1v1.1H5.9V17c0-.64 3.13-2.1 6.1-2.1M12 4C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 9c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z" />
                        </svg>
                    </div>
                    <div class="rb-action-content">
                        <div class="rb-action-title"><?php esc_html_e( 'Add Walk-in', 'restaurant-booking' ); ?></div>
                        <div class="rb-action-description"><?php esc_html_e( 'Register walk-in customers', 'restaurant-booking' ); ?></div>
                    </div>
                </div>
                <div class="rb-action-item" data-action="view-calendar">
                    <div class="rb-action-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" />
                        </svg>
                    </div>
                    <div class="rb-action-content">
                        <div class="rb-action-title"><?php esc_html_e( 'View Calendar', 'restaurant-booking' ); ?></div>
                        <div class="rb-action-description"><?php esc_html_e( 'Open booking calendar view', 'restaurant-booking' ); ?></div>
                    </div>
                </div>
                <div class="rb-action-item" data-action="manage-tables">
                    <div class="rb-action-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 6v2h-2v6h-1.5v-6h-5v6H10V8H8V6h12zM6 10v2H4v6H2.5v-6H1v-2h5z" />
                        </svg>
                    </div>
                    <div class="rb-action-content">
                        <div class="rb-action-title"><?php esc_html_e( 'Manage Tables', 'restaurant-booking' ); ?></div>
                        <div class="rb-action-description"><?php esc_html_e( 'Edit table arrangements', 'restaurant-booking' ); ?></div>
                    </div>
                </div>
                <div class="rb-action-item" data-action="view-reports">
                    <div class="rb-action-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 13h2v-2H3v2m0 4h2v-2H3v2m0-8h2V7H3v2m4 8h14v-2H7v2m0-4h14v-2H7v2m0-6v2h14V7H7z" />
                        </svg>
                    </div>
                    <div class="rb-action-content">
                        <div class="rb-action-title"><?php esc_html_e( 'View Reports', 'restaurant-booking' ); ?></div>
                        <div class="rb-action-description"><?php esc_html_e( 'Review analytics dashboards', 'restaurant-booking' ); ?></div>
                    </div>
                </div>
                <div class="rb-action-item" data-action="settings">
                    <div class="rb-action-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 8a4 4 0 0 1 4 4c0 .74-.21 1.43-.57 2.02l3.7 3.71-1.41 1.41-3.71-3.7A3.97 3.97 0 0 1 12 16a4 4 0 0 1 0-8m0-6C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" />
                        </svg>
                    </div>
                    <div class="rb-action-content">
                        <div class="rb-action-title"><?php esc_html_e( 'Settings', 'restaurant-booking' ); ?></div>
                        <div class="rb-action-description"><?php esc_html_e( 'Manage portal preferences', 'restaurant-booking' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
