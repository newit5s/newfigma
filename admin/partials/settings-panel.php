<?php
/**
 * Settings interface.
 *
 * @package RestaurantBooking
 */

$current_page = 'rb-settings';
require __DIR__ . '/shared/header.php';
?>
<div class="rb-admin-wrapper">
    <aside class="rb-admin-sidebar" aria-label="<?php esc_attr_e( 'Configuration summary', 'restaurant-booking' ); ?>">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Booking policies', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list" id="rb-admin-policy-summary">
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🕒</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Lead time', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-policy-lead-time">—</div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">👥</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Max party size', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-policy-party-size">—</div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🔔</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Reminders', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-policy-reminders">—</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Automation status', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <div class="rb-admin-activity-item" id="rb-policy-automation-booking">
                    <div class="rb-admin-activity-icon" aria-hidden="true">📧</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Booking emails', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Sent instantly after confirmation.', 'restaurant-booking' ); ?></div>
                    </div>
                </div>
                <div class="rb-admin-activity-item" id="rb-policy-automation-reminders">
                    <div class="rb-admin-activity-icon" aria-hidden="true">📱</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'SMS reminders', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta"><?php esc_html_e( 'Trigger 24 hours before arrival.', 'restaurant-booking' ); ?></div>
                    </div>
                </div>
            </div>
        </section>
    </aside>

    <main class="rb-admin-content" aria-live="polite">
        <form id="rb-admin-settings-form" class="rb-admin-panels" method="post">
            <section class="rb-admin-panel">
                <header class="rb-admin-panel-header">
                    <div>
                        <h1 class="rb-admin-title"><?php esc_html_e( 'Settings', 'restaurant-booking' ); ?></h1>
                        <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Control availability windows, notifications, and advanced options.', 'restaurant-booking' ); ?></p>
                    </div>
                    <div class="rb-admin-actions">
                        <button class="rb-btn rb-btn-outline" type="button" id="rb-settings-reset">
                            <span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
                            <?php esc_html_e( 'Reset defaults', 'restaurant-booking' ); ?>
                        </button>
                        <button class="rb-btn rb-btn-primary" type="submit" id="rb-settings-save" disabled>
                            <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                            <?php esc_html_e( 'Save changes', 'restaurant-booking' ); ?>
                        </button>
                    </div>
                </header>

                <div class="rb-admin-settings-grid">
                    <div class="rb-admin-settings-card">
                        <h2><?php esc_html_e( 'General settings', 'restaurant-booking' ); ?></h2>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-restaurant-name"><?php esc_html_e( 'Restaurant name', 'restaurant-booking' ); ?></label>
                            <input type="text" id="rb-setting-restaurant-name" class="rb-input" required />
                        </div>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-default-currency"><?php esc_html_e( 'Default currency', 'restaurant-booking' ); ?></label>
                            <select id="rb-setting-default-currency" class="rb-select">
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                                <option value="JPY">JPY</option>
                            </select>
                        </div>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-max-party"><?php esc_html_e( 'Maximum party size', 'restaurant-booking' ); ?></label>
                            <input type="number" id="rb-setting-max-party" class="rb-input" min="1" max="30" value="6" />
                        </div>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-buffer"><?php esc_html_e( 'Buffer time (minutes)', 'restaurant-booking' ); ?></label>
                            <input type="number" id="rb-setting-buffer" class="rb-input" min="0" step="5" value="30" />
                        </div>
                        <div class="rb-admin-form-group rb-admin-form-group--toggle">
                            <label for="rb-setting-walkins">
                                <input type="checkbox" id="rb-setting-walkins" />
                                <span><?php esc_html_e( 'Allow walk-in reservations', 'restaurant-booking' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="rb-admin-settings-card">
                        <h2><?php esc_html_e( 'Notifications', 'restaurant-booking' ); ?></h2>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-reminder-hours"><?php esc_html_e( 'Reminder timing (hours before)', 'restaurant-booking' ); ?></label>
                            <input type="number" id="rb-setting-reminder-hours" class="rb-input" min="1" max="72" value="24" />
                        </div>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-confirmation-template"><?php esc_html_e( 'Confirmation template', 'restaurant-booking' ); ?></label>
                            <textarea id="rb-setting-confirmation-template" class="rb-input" rows="4" placeholder="<?php esc_attr_e( 'Thank you for your reservation…', 'restaurant-booking' ); ?>"></textarea>
                        </div>
                        <div class="rb-admin-form-group rb-admin-form-group--toggle">
                            <label for="rb-setting-send-sms">
                                <input type="checkbox" id="rb-setting-send-sms" />
                                <span><?php esc_html_e( 'Send SMS reminder', 'restaurant-booking' ); ?></span>
                            </label>
                        </div>
                        <div class="rb-admin-form-group rb-admin-form-group--toggle">
                            <label for="rb-setting-send-followup">
                                <input type="checkbox" id="rb-setting-send-followup" />
                                <span><?php esc_html_e( 'Send post-visit follow-up', 'restaurant-booking' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="rb-admin-settings-card">
                        <h2><?php esc_html_e( 'Advanced options', 'restaurant-booking' ); ?></h2>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-auto-cancel"><?php esc_html_e( 'Auto-cancel no-shows after (minutes)', 'restaurant-booking' ); ?></label>
                            <input type="number" id="rb-setting-auto-cancel" class="rb-input" min="0" step="5" value="15" />
                        </div>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-hold-time"><?php esc_html_e( 'Hold table for walk-ins (minutes)', 'restaurant-booking' ); ?></label>
                            <input type="number" id="rb-setting-hold-time" class="rb-input" min="0" step="5" value="10" />
                        </div>
                        <div class="rb-admin-form-group">
                            <label for="rb-setting-integrations"><?php esc_html_e( 'External integrations', 'restaurant-booking' ); ?></label>
                            <select id="rb-setting-integrations" class="rb-select" multiple>
                                <option value="zapier"><?php esc_html_e( 'Zapier', 'restaurant-booking' ); ?></option>
                                <option value="slack"><?php esc_html_e( 'Slack', 'restaurant-booking' ); ?></option>
                                <option value="teams"><?php esc_html_e( 'Microsoft Teams', 'restaurant-booking' ); ?></option>
                                <option value="webhooks"><?php esc_html_e( 'Webhook callback', 'restaurant-booking' ); ?></option>
                            </select>
                        </div>
                        <div class="rb-admin-form-group rb-admin-form-group--toggle">
                            <label for="rb-setting-maintenance">
                                <input type="checkbox" id="rb-setting-maintenance" />
                                <span><?php esc_html_e( 'Maintenance mode (disable public bookings)', 'restaurant-booking' ); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>
        </form>

        <div class="rb-admin-alert" id="rb-settings-unsaved" hidden>
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <span><?php esc_html_e( 'Settings changed. Save to apply updates across all booking surfaces.', 'restaurant-booking' ); ?></span>
        </div>
    </main>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
