<?php
/**
 * Reports and analytics dashboard.
 *
 * @package RestaurantBooking
 */

$current_page = 'rb-reports';
require __DIR__ . '/shared/header.php';
?>
<div class="rb-admin-wrapper">
    <aside class="rb-admin-sidebar" aria-label="<?php esc_attr_e( 'Reporting presets', 'restaurant-booking' ); ?>">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Saved views', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <button class="rb-admin-activity-item rb-admin-report-preset" type="button" data-range="7">
                    <div class="rb-admin-activity-icon" aria-hidden="true">📆</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Last 7 days', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Quick snapshot of recent performance.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
                <button class="rb-admin-activity-item rb-admin-report-preset" type="button" data-range="30">
                    <div class="rb-admin-activity-icon" aria-hidden="true">📈</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Monthly trends', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Compare growth against the previous month.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
                <button class="rb-admin-activity-item rb-admin-report-preset" type="button" data-range="90">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🏆</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Quarterly review', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'See how each location contributes to revenue.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Exports', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <button class="rb-admin-activity-item" type="button" id="rb-reports-export-summary">
                    <div class="rb-admin-activity-icon" aria-hidden="true">⬇️</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Download summary CSV', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Includes bookings, revenue, and occupancy metrics.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
                <button class="rb-admin-activity-item" type="button" id="rb-reports-export-detailed">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🧾</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Detailed report', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Per-booking breakdown with customer insights.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
            </div>
        </section>
    </aside>

    <main class="rb-admin-content" aria-live="polite">
        <section class="rb-admin-panel" id="rb-admin-reports-root">
            <header class="rb-admin-panel-header">
                <div>
                    <h1 class="rb-admin-title"><?php esc_html_e( 'Reports & analytics', 'restaurant-booking' ); ?></h1>
                    <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Understand growth, busiest locations, and guest behavior.', 'restaurant-booking' ); ?></p>
                </div>
                <div class="rb-admin-actions">
                    <label class="rb-admin-report-range" for="rb-report-range">
                        <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                        <input type="text" id="rb-report-range" class="rb-input" placeholder="<?php esc_attr_e( 'Select date range', 'restaurant-booking' ); ?>" />
                    </label>
                    <select id="rb-report-interval" class="rb-select">
                        <option value="day"><?php esc_html_e( 'Daily', 'restaurant-booking' ); ?></option>
                        <option value="week"><?php esc_html_e( 'Weekly', 'restaurant-booking' ); ?></option>
                        <option value="month" selected><?php esc_html_e( 'Monthly', 'restaurant-booking' ); ?></option>
                    </select>
                    <button class="rb-btn rb-btn-outline" type="button" id="rb-reports-refresh">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Refresh', 'restaurant-booking' ); ?>
                    </button>
                </div>
            </header>

            <div class="rb-admin-grid cols-3" id="rb-admin-reports-kpis">
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Total revenue', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-report-revenue">--</span>
                    <span class="rb-admin-stat-change" id="rb-report-revenue-change">0%</span>
                </div>
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Bookings', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-report-bookings">--</span>
                    <span class="rb-admin-stat-change" id="rb-report-bookings-change">0%</span>
                </div>
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Average party size', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-report-party-size">--</span>
                    <span class="rb-admin-stat-change" id="rb-report-party-size-change">0%</span>
                </div>
            </div>

            <div class="rb-admin-grid cols-2">
                <div class="rb-admin-chart" id="rb-admin-reports-chart">
                    <div class="rb-chart-empty" id="rb-admin-reports-chart-empty"><?php esc_html_e( 'Select a date range to render the performance chart.', 'restaurant-booking' ); ?></div>
                </div>
                <div class="rb-admin-card-list" id="rb-admin-reports-insights">
                    <div class="rb-admin-activity-item">
                        <div class="rb-admin-activity-icon" aria-hidden="true">🏅</div>
                        <div>
                            <div class="rb-admin-stat-label"><?php esc_html_e( 'Best performing location', 'restaurant-booking' ); ?></div>
                            <div class="rb-admin-stat-value" id="rb-report-top-location">—</div>
                            <div class="rb-admin-activity-meta" id="rb-report-top-location-meta"></div>
                        </div>
                    </div>
                    <div class="rb-admin-activity-item">
                        <div class="rb-admin-activity-icon" aria-hidden="true">📊</div>
                        <div>
                            <div class="rb-admin-stat-label"><?php esc_html_e( 'Occupancy trend', 'restaurant-booking' ); ?></div>
                            <div class="rb-admin-stat-value" id="rb-report-occupancy">—</div>
                            <div class="rb-admin-activity-meta" id="rb-report-occupancy-meta"></div>
                        </div>
                    </div>
                    <div class="rb-admin-activity-item">
                        <div class="rb-admin-activity-icon" aria-hidden="true">💡</div>
                        <div>
                            <div class="rb-admin-stat-label"><?php esc_html_e( 'Recommendation', 'restaurant-booking' ); ?></div>
                            <div class="rb-admin-activity-meta" id="rb-report-recommendation"><?php esc_html_e( 'Adjust operating hours to capture peak demand.', 'restaurant-booking' ); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="rb-admin-panel">
                <header class="rb-admin-panel-header">
                    <h2><?php esc_html_e( 'Top locations', 'restaurant-booking' ); ?></h2>
                    <button class="rb-btn rb-btn-outline" type="button" id="rb-report-export-top">
                        <span class="dashicons dashicons-download" aria-hidden="true"></span>
                        <?php esc_html_e( 'Export', 'restaurant-booking' ); ?>
                    </button>
                </header>
                <div class="rb-admin-table-wrapper">
                    <table class="rb-admin-table" aria-describedby="rb-report-top-summary">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Location', 'restaurant-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Bookings', 'restaurant-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Revenue', 'restaurant-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Occupancy', 'restaurant-booking' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Change', 'restaurant-booking' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="rb-admin-reports-top-body">
                            <tr class="rb-admin-skeleton-row">
                                <td><span class="rb-admin-skeleton-bar"></span></td>
                                <td><span class="rb-admin-skeleton-bar"></span></td>
                                <td><span class="rb-admin-skeleton-bar"></span></td>
                                <td><span class="rb-admin-skeleton-bar"></span></td>
                                <td><span class="rb-admin-skeleton-bar"></span></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="rb-admin-empty-state" id="rb-admin-reports-empty" hidden>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 4h16v4H4zm0 6h16v10H4z"/></svg>
                        <p><?php esc_html_e( 'Data will appear once bookings are recorded for the selected range.', 'restaurant-booking' ); ?></p>
                    </div>
                </div>
                <div id="rb-report-top-summary" class="rb-admin-pagination-meta"><?php esc_html_e( 'Loading location performance…', 'restaurant-booking' ); ?></div>
            </section>
        </section>

        <div class="rb-loading-overlay" id="rb-admin-reports-loading" role="status" aria-live="polite" aria-busy="true">
            <div class="rb-loading-spinner"></div>
        </div>
    </main>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
