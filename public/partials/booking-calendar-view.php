<?php
/**
 * Calendar view partial for booking management (Phase 5)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="rb-calendar-container">
    <div class="rb-calendar-header">
        <div class="rb-calendar-nav">
            <button type="button" class="rb-btn rb-btn-sm rb-btn-icon" id="calendar-prev" aria-label="<?php esc_attr_e( 'Previous period', 'restaurant-booking' ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" />
                </svg>
            </button>
            <div class="rb-calendar-title">
                <h3 id="calendar-month-year"><?php echo esc_html( wp_date( 'F Y' ) ); ?></h3>
            </div>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-icon" id="calendar-next" aria-label="<?php esc_attr_e( 'Next period', 'restaurant-booking' ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 6l1.41 1.41L7.83 11H20v2H7.83l3.58 3.59L10 18l-6-6z" />
                </svg>
            </button>
        </div>
        <div class="rb-calendar-controls">
            <div class="rb-calendar-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Calendar view mode', 'restaurant-booking' ); ?>">
                <button type="button" class="rb-btn rb-btn-sm rb-calendar-view-btn" data-view="month"><?php esc_html_e( 'Month', 'restaurant-booking' ); ?></button>
                <button type="button" class="rb-btn rb-btn-sm rb-calendar-view-btn rb-active" data-view="week"><?php esc_html_e( 'Week', 'restaurant-booking' ); ?></button>
                <button type="button" class="rb-btn rb-btn-sm rb-calendar-view-btn" data-view="day"><?php esc_html_e( 'Day', 'restaurant-booking' ); ?></button>
            </div>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" id="calendar-today"><?php esc_html_e( 'Today', 'restaurant-booking' ); ?></button>
        </div>
    </div>

    <div class="rb-calendar-grid">
        <div class="rb-calendar-week-header">
            <div class="rb-calendar-day-header"><?php esc_html_e( 'Mon', 'restaurant-booking' ); ?></div>
            <div class="rb-calendar-day-header"><?php esc_html_e( 'Tue', 'restaurant-booking' ); ?></div>
            <div class="rb-calendar-day-header"><?php esc_html_e( 'Wed', 'restaurant-booking' ); ?></div>
            <div class="rb-calendar-day-header"><?php esc_html_e( 'Thu', 'restaurant-booking' ); ?></div>
            <div class="rb-calendar-day-header"><?php esc_html_e( 'Fri', 'restaurant-booking' ); ?></div>
            <div class="rb-calendar-day-header"><?php esc_html_e( 'Sat', 'restaurant-booking' ); ?></div>
            <div class="rb-calendar-day-header"><?php esc_html_e( 'Sun', 'restaurant-booking' ); ?></div>
        </div>
        <div class="rb-calendar-days" id="calendar-days"></div>
    </div>

    <div class="rb-calendar-legend">
        <div class="rb-legend-item">
            <span class="rb-legend-color rb-legend-confirmed"></span>
            <span><?php esc_html_e( 'Confirmed', 'restaurant-booking' ); ?></span>
        </div>
        <div class="rb-legend-item">
            <span class="rb-legend-color rb-legend-pending"></span>
            <span><?php esc_html_e( 'Pending', 'restaurant-booking' ); ?></span>
        </div>
        <div class="rb-legend-item">
            <span class="rb-legend-color rb-legend-cancelled"></span>
            <span><?php esc_html_e( 'Cancelled', 'restaurant-booking' ); ?></span>
        </div>
        <div class="rb-legend-item">
            <span class="rb-legend-color rb-legend-no-show"></span>
            <span><?php esc_html_e( 'No Show', 'restaurant-booking' ); ?></span>
        </div>
    </div>
</div>
