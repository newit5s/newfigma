<?php
/**
 * Bookings management interface.
 *
 * @package RestaurantBooking
 */

$current_page = 'rb-bookings';
require __DIR__ . '/shared/header.php';
?>
<div class="rb-admin-wrapper">
    <aside class="rb-admin-sidebar" aria-label="<?php esc_attr_e( 'Booking insights', 'restaurant-booking' ); ?>">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Status distribution', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-grid" id="rb-admin-booking-status-cards">
                <div class="rb-admin-stat-card" data-status="confirmed">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Confirmed', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-status-confirmed">--</span>
                </div>
                <div class="rb-admin-stat-card" data-status="pending">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Pending', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-status-pending">--</span>
                </div>
                <div class="rb-admin-stat-card" data-status="completed">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Completed', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-status-completed">--</span>
                </div>
                <div class="rb-admin-stat-card" data-status="cancelled">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Cancelled', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-status-cancelled">--</span>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Saved filters', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <button class="rb-admin-activity-item rb-admin-quick-filter" type="button" data-status="pending">
                    <div class="rb-admin-activity-icon" aria-hidden="true">⏳</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Pending today', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Show unconfirmed bookings scheduled for today.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
                <button class="rb-admin-activity-item rb-admin-quick-filter" type="button" data-status="confirmed">
                    <div class="rb-admin-activity-icon" aria-hidden="true">✅</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Ready to seat', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'All confirmed bookings within the next 24 hours.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
                <button class="rb-admin-activity-item rb-admin-quick-filter" type="button" data-status="cancelled">
                    <div class="rb-admin-activity-icon" aria-hidden="true">❌</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Recent cancellations', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Track trends over the last seven days.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
            </div>
        </section>
    </aside>

    <main class="rb-admin-content" aria-live="polite">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <div>
                    <h1 class="rb-admin-title"><?php esc_html_e( 'Bookings', 'restaurant-booking' ); ?></h1>
                    <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Manage reservations, adjust statuses, and export reports.', 'restaurant-booking' ); ?></p>
                </div>
                <div class="rb-admin-actions">
                    <button class="rb-btn rb-btn-outline" type="button" id="rb-bookings-refresh">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Refresh', 'restaurant-booking' ); ?>
                    </button>
                    <button class="rb-btn rb-btn-outline" type="button" id="rb-bookings-export">
                        <span class="dashicons dashicons-download" aria-hidden="true"></span>
                        <?php esc_html_e( 'Export CSV', 'restaurant-booking' ); ?>
                    </button>
                    <button class="rb-btn rb-btn-primary" type="button" id="rb-bookings-add">
                        <span class="dashicons dashicons-plus" aria-hidden="true"></span>
                        <?php esc_html_e( 'New booking', 'restaurant-booking' ); ?>
                    </button>
                </div>
            </header>

            <div class="rb-admin-filter-bar" role="search" aria-label="<?php esc_attr_e( 'Filter bookings', 'restaurant-booking' ); ?>">
                <div class="rb-admin-filter-group">
                    <label for="rb-bookings-status-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'restaurant-booking' ); ?></label>
                    <select id="rb-bookings-status-filter" class="rb-select">
                        <option value=""><?php esc_html_e( 'All statuses', 'restaurant-booking' ); ?></option>
                        <option value="pending"><?php esc_html_e( 'Pending', 'restaurant-booking' ); ?></option>
                        <option value="confirmed"><?php esc_html_e( 'Confirmed', 'restaurant-booking' ); ?></option>
                        <option value="completed"><?php esc_html_e( 'Completed', 'restaurant-booking' ); ?></option>
                        <option value="cancelled"><?php esc_html_e( 'Cancelled', 'restaurant-booking' ); ?></option>
                    </select>
                </div>
                <div class="rb-admin-filter-group">
                    <label for="rb-bookings-date-from"><?php esc_html_e( 'Date from', 'restaurant-booking' ); ?></label>
                    <input type="date" id="rb-bookings-date-from" class="rb-input" />
                </div>
                <div class="rb-admin-filter-group">
                    <label for="rb-bookings-date-to"><?php esc_html_e( 'Date to', 'restaurant-booking' ); ?></label>
                    <input type="date" id="rb-bookings-date-to" class="rb-input" />
                </div>
                <div class="rb-admin-filter-group">
                    <label for="rb-bookings-location-filter"><?php esc_html_e( 'Location', 'restaurant-booking' ); ?></label>
                    <select id="rb-bookings-location-filter" class="rb-select">
                        <option value=""><?php esc_html_e( 'All locations', 'restaurant-booking' ); ?></option>
                    </select>
                </div>
                <div class="rb-admin-filter-group rb-admin-filter-group--search">
                    <label for="rb-bookings-search-filter" class="screen-reader-text"><?php esc_html_e( 'Search bookings', 'restaurant-booking' ); ?></label>
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="search" id="rb-bookings-search-filter" class="rb-input" placeholder="<?php esc_attr_e( 'Search guests or contact details…', 'restaurant-booking' ); ?>" />
                </div>
                <div class="rb-admin-filter-group rb-admin-filter-group--compact">
                    <label for="rb-bookings-per-page"><?php esc_html_e( 'Per page', 'restaurant-booking' ); ?></label>
                    <select id="rb-bookings-per-page" class="rb-select">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="rb-admin-filter-actions">
                    <button class="rb-btn rb-btn-outline" type="button" id="rb-bookings-clear"><?php esc_html_e( 'Clear', 'restaurant-booking' ); ?></button>
                </div>
            </div>

            <div class="rb-admin-table-wrapper">
                <table class="rb-admin-table" aria-describedby="rb-bookings-summary">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Customer', 'restaurant-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Date & time', 'restaurant-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Party size', 'restaurant-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Table', 'restaurant-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Location', 'restaurant-booking' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Status', 'restaurant-booking' ); ?></th>
                            <th scope="col" class="rb-admin-column-actions"><?php esc_html_e( 'Actions', 'restaurant-booking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="rb-admin-bookings-body">
                        <tr class="rb-admin-skeleton-row">
                            <td><span class="rb-admin-skeleton-bar"></span></td>
                            <td><span class="rb-admin-skeleton-bar"></span></td>
                            <td><span class="rb-admin-skeleton-bar"></span></td>
                            <td><span class="rb-admin-skeleton-bar"></span></td>
                            <td><span class="rb-admin-skeleton-bar"></span></td>
                            <td><span class="rb-admin-skeleton-badge"></span></td>
                            <td><span class="rb-admin-skeleton-pill"></span></td>
                        </tr>
                    </tbody>
                </table>
                <div class="rb-admin-empty-state" id="rb-admin-bookings-empty" hidden>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zm2 4v8h10V8H7z"/></svg>
                    <p><?php esc_html_e( 'No bookings match the current filters.', 'restaurant-booking' ); ?></p>
                </div>
            </div>

            <footer class="rb-admin-pagination" id="rb-admin-bookings-pagination" aria-live="polite">
                <div class="rb-admin-pagination-meta" id="rb-bookings-summary">
                    <?php esc_html_e( 'Loading booking totals…', 'restaurant-booking' ); ?>
                </div>
                <div class="rb-admin-pagination-controls">
                    <button class="rb-btn rb-btn-outline" type="button" data-page="prev" disabled>
                        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                        <?php esc_html_e( 'Previous', 'restaurant-booking' ); ?>
                    </button>
                    <span class="rb-admin-pagination-state" id="rb-admin-bookings-page-state"></span>
                    <button class="rb-btn rb-btn-outline" type="button" data-page="next" disabled>
                        <?php esc_html_e( 'Next', 'restaurant-booking' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </button>
                </div>
            </footer>

            <div class="rb-loading-overlay" id="rb-admin-bookings-loading" role="status" aria-live="polite" aria-busy="true">
                <div class="rb-loading-spinner"></div>
            </div>
        </section>
    </main>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
