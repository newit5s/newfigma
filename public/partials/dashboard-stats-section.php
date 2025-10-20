<?php
/**
 * Dashboard statistics section (Phase 4)
 *
 * Variables expected:
 * - $initial_stats (array)
 * - $metric_periods (array)
 */

$format_number = static function ( $value ) {
    return number_format_i18n( (float) $value, 0 );
};

$bookings        = isset( $initial_stats['bookings'] ) ? $initial_stats['bookings'] : array();
$revenue         = isset( $initial_stats['revenue'] ) ? $initial_stats['revenue'] : array();
$occupancy       = isset( $initial_stats['occupancy'] ) ? $initial_stats['occupancy'] : array();
$pending         = isset( $initial_stats['pending'] ) ? $initial_stats['pending'] : array();

$bookings_change = isset( $bookings['change']['percentage'] ) ? (float) $bookings['change']['percentage'] : 0;
$revenue_change  = isset( $revenue['change']['percentage'] ) ? (float) $revenue['change']['percentage'] : 0;
$occupancy_change= isset( $occupancy['change']['percentage'] ) ? (float) $occupancy['change']['percentage'] : 0;

$change_class = static function ( $value ) {
    if ( $value > 0 ) {
        return 'rb-stat-change rb-positive';
    }

    if ( $value < 0 ) {
        return 'rb-stat-change rb-negative';
    }

    return 'rb-stat-change rb-neutral';
};
?>

<div class="rb-dashboard-stats">
    <div class="rb-stat-card" data-metric="bookings">
        <div class="rb-stat-header">
            <div class="rb-stat-icon rb-stat-icon-booking">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" />
                </svg>
            </div>
            <div class="rb-stat-period">
                <select class="rb-stat-period-select" data-metric="bookings">
                    <?php
                    $bookings_period = isset( $metric_periods['bookings'] ) ? $metric_periods['bookings'] : 'today';
                    $bookings_options = array(
                        'today' => __( 'Today', 'restaurant-booking' ),
                        'week'  => __( 'This Week', 'restaurant-booking' ),
                        'month' => __( 'This Month', 'restaurant-booking' ),
                    );
                    foreach ( $bookings_options as $value => $label ) {
                        printf(
                            '<option value="%1$s" %3$s>%2$s</option>',
                            esc_attr( $value ),
                            esc_html( $label ),
                            selected( $bookings_period, $value, false )
                        );
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="rb-stat-body">
            <div class="rb-stat-number" id="stat-bookings"><?php echo esc_html( $format_number( isset( $bookings['value'] ) ? $bookings['value'] : 0 ) ); ?></div>
            <div class="rb-stat-label"><?php echo esc_html( isset( $bookings['label'] ) ? $bookings['label'] : __( "Today's Bookings", 'restaurant-booking' ) ); ?></div>
            <div class="<?php echo esc_attr( $change_class( $bookings_change ) ); ?>" id="stat-bookings-change">
                <svg class="rb-stat-trend-icon" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7 14l5-5 5 5z" />
                </svg>
                <span>
                    <?php
                    $bookings_period_label = isset( $bookings['change']['period'] ) ? $bookings['change']['period'] : __( 'since yesterday', 'restaurant-booking' );
                    printf(
                        '%1$s%2$s %3$s',
                        $bookings_change > 0 ? '+' : '',
                        esc_html( $bookings_change ),
                        esc_html( $bookings_period_label )
                    );
                    ?>
                </span>
            </div>
        </div>
        <div class="rb-stat-loading" style="display: none;">
            <div class="rb-loading-spinner" aria-hidden="true"></div>
        </div>
    </div>

    <div class="rb-stat-card" data-metric="revenue">
        <div class="rb-stat-header">
            <div class="rb-stat-icon rb-stat-icon-revenue">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" />
                </svg>
            </div>
            <div class="rb-stat-period">
                <select class="rb-stat-period-select" data-metric="revenue">
                    <?php
                    $revenue_period = isset( $metric_periods['revenue'] ) ? $metric_periods['revenue'] : 'today';
                    foreach ( $bookings_options as $value => $label ) {
                        printf(
                            '<option value="%1$s" %3$s>%2$s</option>',
                            esc_attr( $value ),
                            esc_html( $label ),
                            selected( $revenue_period, $value, false )
                        );
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="rb-stat-body">
            <div class="rb-stat-number" id="stat-revenue">
                <?php
                $revenue_prefix = isset( $revenue['prefix'] ) ? $revenue['prefix'] : '$';
                echo esc_html( $revenue_prefix . $format_number( isset( $revenue['value'] ) ? $revenue['value'] : 0 ) );
                ?>
            </div>
            <div class="rb-stat-label"><?php echo esc_html( isset( $revenue['label'] ) ? $revenue['label'] : __( "Today's Revenue", 'restaurant-booking' ) ); ?></div>
            <div class="<?php echo esc_attr( $change_class( $revenue_change ) ); ?>" id="stat-revenue-change">
                <svg class="rb-stat-trend-icon" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7 14l5-5 5 5z" />
                </svg>
                <span>
                    <?php
                    $revenue_period_label = isset( $revenue['change']['period'] ) ? $revenue['change']['period'] : __( 'since yesterday', 'restaurant-booking' );
                    printf(
                        '%1$s%2$s%% %3$s',
                        $revenue_change > 0 ? '+' : '',
                        esc_html( $revenue_change ),
                        esc_html( $revenue_period_label )
                    );
                    ?>
                </span>
            </div>
        </div>
        <div class="rb-stat-loading" style="display: none;">
            <div class="rb-loading-spinner" aria-hidden="true"></div>
        </div>
    </div>

    <div class="rb-stat-card" data-metric="occupancy">
        <div class="rb-stat-header">
            <div class="rb-stat-icon rb-stat-icon-occupancy">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 6v2h-2v6h-1.5v-6h-5v6H10V8H8V6h12zM6 10v2H4v6H2.5v-6H1v-2h5z" />
                </svg>
            </div>
            <div class="rb-stat-period">
                <select class="rb-stat-period-select" data-metric="occupancy">
                    <?php
                    $occupancy_period = isset( $metric_periods['occupancy'] ) ? $metric_periods['occupancy'] : 'today';
                    $occupancy_options = array(
                        'now'   => __( 'Right Now', 'restaurant-booking' ),
                        'today' => __( 'Today', 'restaurant-booking' ),
                        'week'  => __( 'This Week', 'restaurant-booking' ),
                    );
                    foreach ( $occupancy_options as $value => $label ) {
                        printf(
                            '<option value="%1$s" %3$s>%2$s</option>',
                            esc_attr( $value ),
                            esc_html( $label ),
                            selected( $occupancy_period, $value, false )
                        );
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="rb-stat-body">
            <div class="rb-stat-number" id="stat-occupancy"><?php echo esc_html( $format_number( isset( $occupancy['value'] ) ? $occupancy['value'] : 0 ) . '%' ); ?></div>
            <div class="rb-stat-label"><?php echo esc_html( isset( $occupancy['label'] ) ? $occupancy['label'] : __( 'Table Occupancy', 'restaurant-booking' ) ); ?></div>
            <div class="<?php echo esc_attr( $change_class( $occupancy_change ) ); ?>" id="stat-occupancy-change">
                <svg class="rb-stat-trend-icon" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7 14l5-5 5 5z" />
                </svg>
                <span>
                    <?php
                    $occupancy_period_label = isset( $occupancy['change']['period'] ) ? $occupancy['change']['period'] : __( 'since yesterday', 'restaurant-booking' );
                    printf(
                        '%1$s%2$s%% %3$s',
                        $occupancy_change > 0 ? '+' : '',
                        esc_html( $occupancy_change ),
                        esc_html( $occupancy_period_label )
                    );
                    ?>
                </span>
            </div>
        </div>
        <div class="rb-stat-loading" style="display: none;">
            <div class="rb-loading-spinner" aria-hidden="true"></div>
        </div>
    </div>

    <div class="rb-stat-card" data-metric="pending">
        <div class="rb-stat-header">
            <div class="rb-stat-icon rb-stat-icon-pending">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                </svg>
            </div>
            <div class="rb-stat-badge rb-badge-warning"<?php echo empty( $pending['badge'] ) ? ' style="display:none;"' : ''; ?>>
                <?php echo esc_html( isset( $pending['badge'] ) ? $pending['badge'] : __( 'Review Required', 'restaurant-booking' ) ); ?>
            </div>
        </div>
        <div class="rb-stat-body">
            <div class="rb-stat-number" id="stat-pending"><?php echo esc_html( $format_number( isset( $pending['value'] ) ? $pending['value'] : 0 ) ); ?></div>
            <div class="rb-stat-label"><?php echo esc_html( isset( $pending['label'] ) ? $pending['label'] : __( 'Pending Approvals', 'restaurant-booking' ) ); ?></div>
            <div class="rb-stat-actions">
                <button class="rb-btn rb-btn-sm rb-btn-primary" type="button"><?php esc_html_e( 'Review All', 'restaurant-booking' ); ?></button>
            </div>
        </div>
        <div class="rb-stat-loading" style="display: none;">
            <div class="rb-loading-spinner" aria-hidden="true"></div>
        </div>
    </div>
</div>
