<?php
/**
 * Booking management template (Phase 5)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$manager        = isset( $this ) ? $this : null;
$current_user   = isset( $manager->current_user ) ? $manager->current_user : array();
$user_name      = isset( $current_user['name'] ) ? $current_user['name'] : __( 'Manager', 'restaurant-booking' );
$user_initials  = strtoupper( mb_substr( wp_strip_all_tags( $user_name ), 0, 2, 'UTF-8' ) );
$today          = wp_date( 'Y-m-d' );
$next_week      = wp_date( 'Y-m-d', strtotime( '+7 days' ) );
$current_status = '';
$current_loc    = isset( $manager ) && method_exists( $manager, 'get_current_location' ) ? $manager->get_current_location() : '';
$is_embed       = isset( $is_embed ) ? (bool) $is_embed : false;

$locations = array();
if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_all_locations' ) ) {
    $raw_locations = RB_Location::get_all_locations();
    if ( is_array( $raw_locations ) || $raw_locations instanceof Traversable ) {
        foreach ( $raw_locations as $location ) {
            $locations[] = array(
                'id'   => isset( $location->id ) ? $location->id : 0,
                'name' => isset( $location->name ) ? $location->name : __( 'Location', 'restaurant-booking' ),
            );
        }
    }
}
if ( empty( $locations ) ) {
    $locations[] = array(
        'id'   => 0,
        'name' => __( 'Main Dining Room', 'restaurant-booking' ),
    );
}
?>
<?php if ( ! $is_embed ) : ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'rb-booking-management-page' ); ?>>
<?php endif; ?>

<div class="rb-booking-management">
    <div class="rb-management-header">
        <div class="rb-header-title">
            <h1 class="rb-page-title"><?php esc_html_e( 'Booking Management', 'restaurant-booking' ); ?></h1>
            <div class="rb-page-subtitle"><?php esc_html_e( 'Manage reservations, confirmations, and schedules in real time.', 'restaurant-booking' ); ?></div>
        </div>
        <div class="rb-header-actions">
            <div class="rb-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Toggle booking view', 'restaurant-booking' ); ?>">
                <button type="button" class="rb-btn rb-btn-sm rb-view-btn rb-active" data-view="table">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 3h18v2H3V3zm0 4h18v2H3V7zm0 4h18v2H3v-2zm0 4h18v2H3v-2z" />
                    </svg>
                    <?php esc_html_e( 'Table View', 'restaurant-booking' ); ?>
                </button>
                <button type="button" class="rb-btn rb-btn-sm rb-view-btn" data-view="calendar">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" />
                    </svg>
                    <?php esc_html_e( 'Calendar View', 'restaurant-booking' ); ?>
                </button>
            </div>
            <button type="button" class="rb-btn rb-btn-primary" id="add-booking-btn">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                </svg>
                <?php esc_html_e( 'Add Booking', 'restaurant-booking' ); ?>
            </button>
        </div>
    </div>

    <div class="rb-filters-bar" role="region" aria-label="<?php esc_attr_e( 'Booking filters', 'restaurant-booking' ); ?>">
        <div class="rb-filters-section">
            <div class="rb-filter-group">
                <label for="date-from" class="rb-filter-label"><?php esc_html_e( 'Date range', 'restaurant-booking' ); ?></label>
                <div class="rb-date-range-picker">
                    <input type="date" id="date-from" class="rb-input rb-input-sm" value="<?php echo esc_attr( $today ); ?>" />
                    <span class="rb-date-separator"><?php esc_html_e( 'to', 'restaurant-booking' ); ?></span>
                    <input type="date" id="date-to" class="rb-input rb-input-sm" value="<?php echo esc_attr( $next_week ); ?>" />
                </div>
            </div>

            <div class="rb-filter-group">
                <label for="status-filter" class="rb-filter-label"><?php esc_html_e( 'Status', 'restaurant-booking' ); ?></label>
                <select id="status-filter" class="rb-select rb-select-sm">
                    <option value=""><?php esc_html_e( 'All statuses', 'restaurant-booking' ); ?></option>
                    <option value="confirmed"><?php esc_html_e( 'Confirmed', 'restaurant-booking' ); ?></option>
                    <option value="pending"><?php esc_html_e( 'Pending', 'restaurant-booking' ); ?></option>
                    <option value="cancelled"><?php esc_html_e( 'Cancelled', 'restaurant-booking' ); ?></option>
                    <option value="completed"><?php esc_html_e( 'Completed', 'restaurant-booking' ); ?></option>
                    <option value="no-show"><?php esc_html_e( 'No Show', 'restaurant-booking' ); ?></option>
                </select>
            </div>

            <div class="rb-filter-group">
                <label for="location-filter" class="rb-filter-label"><?php esc_html_e( 'Location', 'restaurant-booking' ); ?></label>
                <select id="location-filter" class="rb-select rb-select-sm">
                    <option value=""><?php esc_html_e( 'All locations', 'restaurant-booking' ); ?></option>
                    <?php foreach ( $locations as $location ) : ?>
                        <option value="<?php echo esc_attr( $location['id'] ); ?>" <?php selected( (int) $location['id'], (int) $current_loc ); ?>>
                            <?php echo esc_html( $location['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rb-filter-group rb-filter-search">
                <label for="search-bookings" class="rb-filter-label"><?php esc_html_e( 'Search', 'restaurant-booking' ); ?></label>
                <div class="rb-search-input">
                    <svg class="rb-search-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16a6.471 6.471 0 004.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                    </svg>
                    <input type="search" id="search-bookings" class="rb-input rb-input-sm" placeholder="<?php esc_attr_e( 'Search customer, email, phone…', 'restaurant-booking' ); ?>" />
                    <button type="button" class="rb-btn rb-btn-sm rb-btn-icon" id="clear-search" style="display:none" aria-label="<?php esc_attr_e( 'Clear search', 'restaurant-booking' ); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="rb-filters-actions">
            <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" id="reset-filters">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M17.65 6.35A8 8 0 104 12h2a6 6 0 116.34 5.97l.66.03V19H10v3h10v-3h-2v1.26A8 8 0 0017.65 6.35z" />
                </svg>
                <?php esc_html_e( 'Reset', 'restaurant-booking' ); ?>
            </button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" id="export-bookings">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 2.5L18.5 9H14V4.5zM12 12H8v-2h4v2zm4 4H8v-2h8v2z" />
                </svg>
                <?php esc_html_e( 'Export', 'restaurant-booking' ); ?>
            </button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-icon" id="refresh-bookings" title="<?php esc_attr_e( 'Refresh bookings', 'restaurant-booking' ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4a8 8 0 00-7.88 6.7A1 1 0 005.1 12H7a1 1 0 00.99-.86 6 6 0 0110.41-3.45L16 10h6V4l-2.35 2.35zM19 13h-1.9a1 1 0 00-.99.86 6 6 0 01-10.41 3.45L8 16H2v6l2.35-2.35A8 8 0 0020 13a1 1 0 00-1-1z" />
                </svg>
            </button>
        </div>
    </div>

    <div class="rb-bulk-actions-bar" id="bulk-actions-bar">
        <div class="rb-bulk-selection">
            <span class="rb-selected-count">0 <?php esc_html_e( 'bookings selected', 'restaurant-booking' ); ?></span>
            <button type="button" class="rb-btn-text" id="clear-selection"><?php esc_html_e( 'Clear selection', 'restaurant-booking' ); ?></button>
        </div>
        <div class="rb-bulk-buttons">
            <button type="button" class="rb-btn rb-btn-sm rb-btn-success" id="bulk-confirm"><?php esc_html_e( 'Confirm', 'restaurant-booking' ); ?></button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-warning" id="bulk-pending"><?php esc_html_e( 'Mark Pending', 'restaurant-booking' ); ?></button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" id="bulk-email"><?php esc_html_e( 'Send Reminders', 'restaurant-booking' ); ?></button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-error" id="bulk-cancel"><?php esc_html_e( 'Cancel', 'restaurant-booking' ); ?></button>
        </div>
    </div>

    <div class="rb-table-view" id="table-view">
        <div class="rb-table-container">
            <table class="rb-bookings-table" id="bookings-table">
                <thead>
                <tr>
                    <th scope="col" class="rb-checkbox-col">
                        <label class="rb-checkbox-label">
                            <input type="checkbox" class="rb-checkbox" id="select-all-bookings" />
                            <span class="rb-checkbox-custom"></span>
                        </label>
                    </th>
                    <th scope="col" class="rb-customer-col rb-sortable" data-sort="customer_name">
                        <?php esc_html_e( 'Customer', 'restaurant-booking' ); ?>
                        <svg class="rb-sort-icon" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 14l5-5 5 5z" />
                        </svg>
                    </th>
                    <th scope="col" class="rb-datetime-col rb-sortable" data-sort="booking_datetime">
                        <?php esc_html_e( 'Date & Time', 'restaurant-booking' ); ?>
                        <svg class="rb-sort-icon" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 14l5-5 5 5z" />
                        </svg>
                    </th>
                    <th scope="col" class="rb-party-col rb-sortable" data-sort="party_size">
                        <?php esc_html_e( 'Party Size', 'restaurant-booking' ); ?>
                        <svg class="rb-sort-icon" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 14l5-5 5 5z" />
                        </svg>
                    </th>
                    <th scope="col" class="rb-table-col rb-sortable" data-sort="table_number">
                        <?php esc_html_e( 'Table', 'restaurant-booking' ); ?>
                        <svg class="rb-sort-icon" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 14l5-5 5 5z" />
                        </svg>
                    </th>
                    <th scope="col" class="rb-status-col rb-sortable" data-sort="status">
                        <?php esc_html_e( 'Status', 'restaurant-booking' ); ?>
                        <svg class="rb-sort-icon" width="12" height="12" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 14l5-5 5 5z" />
                        </svg>
                    </th>
                    <th scope="col" class="rb-actions-col"><?php esc_html_e( 'Actions', 'restaurant-booking' ); ?></th>
                </tr>
                </thead>
                <tbody id="bookings-table-body"></tbody>
            </table>

            <div class="rb-table-loading" id="table-loading" role="status" aria-live="polite">
                <div class="rb-loading-spinner" aria-hidden="true"></div>
                <span><?php esc_html_e( 'Loading bookings…', 'restaurant-booking' ); ?></span>
            </div>

            <div class="rb-table-empty" id="table-empty" style="display:none" aria-live="polite">
                <div class="rb-empty-icon" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 24 24">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z" />
                    </svg>
                </div>
                <h3 class="rb-empty-title"><?php esc_html_e( 'No bookings found', 'restaurant-booking' ); ?></h3>
                <p class="rb-empty-description"><?php esc_html_e( 'Try adjusting your filters or create the first booking.', 'restaurant-booking' ); ?></p>
                <button type="button" class="rb-btn rb-btn-primary" id="create-first-booking">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z" />
                    </svg>
                    <?php esc_html_e( 'Create Booking', 'restaurant-booking' ); ?>
                </button>
            </div>
        </div>

        <div class="rb-table-pagination" id="table-pagination">
            <div class="rb-pagination-info">
                <span>
                    <?php esc_html_e( 'Showing', 'restaurant-booking' ); ?>
                    <strong id="pagination-start">0</strong>
                    <?php esc_html_e( 'to', 'restaurant-booking' ); ?>
                    <strong id="pagination-end">0</strong>
                    <?php esc_html_e( 'of', 'restaurant-booking' ); ?>
                    <strong id="pagination-total">0</strong>
                    <?php esc_html_e( 'bookings', 'restaurant-booking' ); ?>
                </span>
            </div>
            <div class="rb-pagination-controls">
                <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" id="pagination-prev" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z" />
                    </svg>
                    <?php esc_html_e( 'Previous', 'restaurant-booking' ); ?>
                </button>
                <div class="rb-pagination-pages" id="pagination-pages"></div>
                <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" id="pagination-next">
                    <?php esc_html_e( 'Next', 'restaurant-booking' ); ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 6l1.41 1.41L7.83 11H20v2H7.83l3.58 3.59L10 18l-6-6z" />
                    </svg>
                </button>
            </div>
            <div class="rb-pagination-size">
                <label for="pagination-size"><?php esc_html_e( 'Per page', 'restaurant-booking' ); ?></label>
                <select id="pagination-size" class="rb-select rb-select-sm">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <div class="rb-calendar-view" id="calendar-view" style="display:none">
        <?php include __DIR__ . '/booking-calendar-view.php'; ?>
    </div>
</div>

<div class="rb-management-toast" id="booking-management-toast" role="status" aria-live="polite"></div>

<?php if ( ! $is_embed ) : ?>
<?php wp_footer(); ?>
</body>
</html>
<?php endif; ?>
