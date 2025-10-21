<?php
/**
 * Locations management layout.
 *
 * @package RestaurantBooking
 */

$current_page = 'rb-locations';
require __DIR__ . '/shared/header.php';
?>
<div class="rb-admin-wrapper">
    <aside class="rb-admin-sidebar" aria-label="<?php esc_attr_e( 'Location quick tools', 'restaurant-booking' ); ?>">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Capacity overview', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-grid" id="rb-admin-location-capacity">
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Total tables', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-total-tables">--</span>
                </div>
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Seats available', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-total-seats">--</span>
                </div>
                <div class="rb-admin-stat-card">
                    <span class="rb-admin-stat-label"><?php esc_html_e( 'Open locations', 'restaurant-booking' ); ?></span>
                    <span class="rb-admin-stat-value" id="rb-admin-open-locations">--</span>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Playbooks', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <button class="rb-admin-activity-item rb-admin-location-playbook" type="button" data-template="weekend">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🎉</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Weekend mode', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Extend hours and enable waitlist automatically.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
                <button class="rb-admin-activity-item rb-admin-location-playbook" type="button" data-template="vip">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🌟</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'VIP event', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Reserve capacity and activate dedicated hosts.', 'restaurant-booking' ); ?></div>
                    </div>
                </button>
            </div>
        </section>
    </aside>

    <main class="rb-admin-content" aria-live="polite">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <div>
                    <h1 class="rb-admin-title"><?php esc_html_e( 'Locations', 'restaurant-booking' ); ?></h1>
                    <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Monitor occupancy, hours, and contact details for every branch.', 'restaurant-booking' ); ?></p>
                </div>
                <div class="rb-admin-actions">
                    <button class="rb-btn rb-btn-outline" type="button" id="rb-locations-refresh">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <?php esc_html_e( 'Refresh', 'restaurant-booking' ); ?>
                    </button>
                    <button class="rb-btn rb-btn-primary" type="button" id="rb-locations-create">
                        <span class="dashicons dashicons-plus" aria-hidden="true"></span>
                        <?php esc_html_e( 'Add location', 'restaurant-booking' ); ?>
                    </button>
                </div>
            </header>
            <div class="rb-admin-grid cols-2" id="rb-admin-locations-root">
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
            <div class="rb-loading-overlay" id="rb-admin-locations-loading" role="status" aria-live="polite" aria-busy="true">
                <div class="rb-loading-spinner"></div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <div>
                    <h2><?php esc_html_e( 'Location details', 'restaurant-booking' ); ?></h2>
                    <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Select a location to update address, hours, and contact info.', 'restaurant-booking' ); ?></p>
                </div>
                <div class="rb-admin-actions">
                    <button class="rb-btn rb-btn-outline" type="button" id="rb-location-reset" disabled><?php esc_html_e( 'Reset', 'restaurant-booking' ); ?></button>
                    <button class="rb-btn rb-btn-primary" type="button" id="rb-location-save" disabled><?php esc_html_e( 'Save changes', 'restaurant-booking' ); ?></button>
                </div>
            </header>
            <form id="rb-admin-location-form" class="rb-admin-settings-grid" autocomplete="off">
                <div class="rb-admin-settings-card">
                    <h3><?php esc_html_e( 'General information', 'restaurant-booking' ); ?></h3>
                    <div class="rb-admin-form-group">
                        <label for="rb-location-name"><?php esc_html_e( 'Location name', 'restaurant-booking' ); ?></label>
                        <input type="text" id="rb-location-name" class="rb-input" required />
                    </div>
                    <div class="rb-admin-form-group">
                        <label for="rb-location-email"><?php esc_html_e( 'Contact email', 'restaurant-booking' ); ?></label>
                        <input type="email" id="rb-location-email" class="rb-input" />
                    </div>
                    <div class="rb-admin-form-group">
                        <label for="rb-location-phone"><?php esc_html_e( 'Phone number', 'restaurant-booking' ); ?></label>
                        <input type="tel" id="rb-location-phone" class="rb-input" />
                    </div>
                    <div class="rb-admin-form-group">
                        <label for="rb-location-address"><?php esc_html_e( 'Address', 'restaurant-booking' ); ?></label>
                        <textarea id="rb-location-address" class="rb-input" rows="3"></textarea>
                    </div>
                </div>

                <div class="rb-admin-settings-card">
                    <h3><?php esc_html_e( 'Operating hours', 'restaurant-booking' ); ?></h3>
                    <div class="rb-admin-form-group">
                        <label for="rb-location-hours-weekday"><?php esc_html_e( 'Weekdays', 'restaurant-booking' ); ?></label>
                        <input type="text" id="rb-location-hours-weekday" class="rb-input" placeholder="09:00 - 22:00" />
                    </div>
                    <div class="rb-admin-form-group">
                        <label for="rb-location-hours-weekend"><?php esc_html_e( 'Weekends', 'restaurant-booking' ); ?></label>
                        <input type="text" id="rb-location-hours-weekend" class="rb-input" placeholder="10:00 - 23:00" />
                    </div>
                    <div class="rb-admin-form-group rb-admin-form-group--toggle">
                        <label for="rb-location-waitlist">
                            <input type="checkbox" id="rb-location-waitlist" />
                            <span><?php esc_html_e( 'Enable waitlist for this location', 'restaurant-booking' ); ?></span>
                        </label>
                    </div>
                    <div class="rb-admin-form-group rb-admin-form-group--toggle">
                        <label for="rb-location-private">
                            <input type="checkbox" id="rb-location-private" />
                            <span><?php esc_html_e( 'Hide from public booking widget', 'restaurant-booking' ); ?></span>
                        </label>
                    </div>
                </div>
            </form>
            <div class="rb-admin-alert" id="rb-location-unsaved" hidden>
                <span class="dashicons dashicons-info" aria-hidden="true"></span>
                <span><?php esc_html_e( 'Unsaved changes. Remember to save before leaving the page.', 'restaurant-booking' ); ?></span>
            </div>
        </section>
    </main>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
