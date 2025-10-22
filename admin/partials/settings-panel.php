<?php
/**
 * WordPress settings page for the Restaurant Booking plugin.
 *
 * @package RestaurantBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_page = 'rb-settings';
require __DIR__ . '/shared/header.php';

$settings = array();
if ( function_exists( 'restaurant_booking_get_settings' ) ) {
    $settings = restaurant_booking_get_settings();
}

if ( ! is_array( $settings ) ) {
    $settings = array();
}

$defaults = function_exists( 'restaurant_booking_get_default_settings' )
    ? restaurant_booking_get_default_settings()
    : array();

$settings = wp_parse_args( $settings, $defaults );

$buffer_time      = isset( $settings['buffer_time'] ) ? (int) $settings['buffer_time'] : 0;
$max_party        = isset( $settings['max_party_size'] ) ? (int) $settings['max_party_size'] : 0;
$reminder_hours   = isset( $settings['reminder_hours'] ) ? (int) $settings['reminder_hours'] : 0;
$allow_walkins    = ! empty( $settings['allow_walkins'] );
$maintenance      = ! empty( $settings['maintenance_mode'] );
$hold_minutes     = isset( $settings['hold_time_minutes'] ) ? (int) $settings['hold_time_minutes'] : 0;
$auto_cancel      = isset( $settings['auto_cancel_minutes'] ) ? (int) $settings['auto_cancel_minutes'] : 0;
$theme_preference = isset( $settings['theme_preference'] ) ? sanitize_key( $settings['theme_preference'] ) : 'system';
$integrations     = isset( $settings['integrations'] ) && is_array( $settings['integrations'] ) ? array_filter( $settings['integrations'] ) : array();

$buffer_summary = sprintf(
    1 === $buffer_time
        ? __( '%s minute buffer', 'restaurant-booking' )
        : __( '%s minutes buffer', 'restaurant-booking' ),
    number_format_i18n( $buffer_time )
);

$party_summary = sprintf(
    1 === $max_party
        ? __( '%s guest', 'restaurant-booking' )
        : __( '%s guests', 'restaurant-booking' ),
    number_format_i18n( $max_party )
);

$reminder_summary = sprintf(
    1 === $reminder_hours
        ? __( '%s hour prior', 'restaurant-booking' )
        : __( '%s hours prior', 'restaurant-booking' ),
    number_format_i18n( $reminder_hours )
);

$walkins_summary = $allow_walkins
    ? ( $hold_minutes > 0
        ? sprintf(
            _n(
                'Walk-ins enabled (hold %s minute)',
                'Walk-ins enabled (hold %s minutes)',
                $hold_minutes,
                'restaurant-booking'
            ),
            number_format_i18n( $hold_minutes )
        )
        : __( 'Walk-ins enabled (no hold buffer)', 'restaurant-booking' )
    )
    : __( 'Walk-ins disabled by default', 'restaurant-booking' );

$maintenance_summary = $maintenance
    ? __( 'Maintenance mode is active', 'restaurant-booking' )
    : __( 'Maintenance mode is turned off', 'restaurant-booking' );

switch ( $theme_preference ) {
    case 'dark':
        $theme_summary = __( 'Dark mode', 'restaurant-booking' );
        break;
    case 'light':
        $theme_summary = __( 'Light mode', 'restaurant-booking' );
        break;
    default:
        $theme_summary = __( 'Matches system preference', 'restaurant-booking' );
        break;
}

$custom_templates = array();

$current_confirmation = isset( $settings['confirmation_template'] ) ? trim( (string) $settings['confirmation_template'] ) : '';
$default_confirmation = isset( $defaults['confirmation_template'] ) ? trim( (string) $defaults['confirmation_template'] ) : '';
if ( $current_confirmation !== $default_confirmation ) {
    $custom_templates[] = __( 'Confirmation', 'restaurant-booking' );
}

$current_reminder = isset( $settings['reminder_template'] ) ? trim( (string) $settings['reminder_template'] ) : '';
$default_reminder = isset( $defaults['reminder_template'] ) ? trim( (string) $defaults['reminder_template'] ) : '';
if ( $current_reminder !== $default_reminder ) {
    $custom_templates[] = __( 'Reminder', 'restaurant-booking' );
}

$current_cancellation = isset( $settings['cancellation_template'] ) ? trim( (string) $settings['cancellation_template'] ) : '';
$default_cancellation = isset( $defaults['cancellation_template'] ) ? trim( (string) $defaults['cancellation_template'] ) : '';
if ( $current_cancellation !== $default_cancellation ) {
    $custom_templates[] = __( 'Cancellation', 'restaurant-booking' );
}

$templates_summary = ! empty( $custom_templates )
    ? sprintf( __( 'Custom templates: %s', 'restaurant-booking' ), implode( ', ', $custom_templates ) )
    : __( 'Using default templates', 'restaurant-booking' );

$followup_summary = ! empty( $settings['send_followup'] )
    ? __( 'Post-visit follow-ups enabled', 'restaurant-booking' )
    : __( 'Follow-up emails disabled', 'restaurant-booking' );

$auto_cancel_summary = $auto_cancel > 0
    ? sprintf(
        _n(
            'Auto-cancel after %s minute',
            'Auto-cancel after %s minutes',
            $auto_cancel,
            'restaurant-booking'
        ),
        number_format_i18n( $auto_cancel )
    )
    : __( 'Auto-cancel disabled', 'restaurant-booking' );

$integrations_count = count( $integrations );
$integrations_summary = $integrations_count > 0
    ? sprintf(
        _n(
            '%s integration active',
            '%s integrations active',
            $integrations_count,
            'restaurant-booking'
        ),
        number_format_i18n( $integrations_count )
    )
    : __( 'No integrations enabled', 'restaurant-booking' );

$unsaved_message = __( 'Unsaved changes. Remember to save before leaving this page.', 'restaurant-booking' );
?>
<div class="rb-admin-wrapper">
    <aside class="rb-admin-sidebar" aria-label="<?php esc_attr_e( 'Settings overview', 'restaurant-booking' ); ?>">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Reservation policies', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">⏱️</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Buffer between parties', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-stat-value" id="rb-policy-lead-time"><?php echo esc_html( $buffer_summary ); ?></div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">👥</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Maximum party size', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-stat-value" id="rb-policy-party-size"><?php echo esc_html( $party_summary ); ?></div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🔔</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Reminder cadence', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-stat-value" id="rb-policy-reminders"><?php echo esc_html( $reminder_summary ); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Theme & appearance', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🎨</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Admin theme', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-theme-summary"><?php echo esc_html( $theme_summary ); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'Email automation', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">✉️</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Email templates', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-email-templates-summary"><?php echo esc_html( $templates_summary ); ?></div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">📬</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Follow-up emails', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-email-followup-summary"><?php echo esc_html( $followup_summary ); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <h2><?php esc_html_e( 'System configuration', 'restaurant-booking' ); ?></h2>
            </header>
            <div class="rb-admin-card-list">
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🚶</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Walk-in reservations', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-system-walkins"><?php echo esc_html( $walkins_summary ); ?></div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">⏳</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Auto-cancel policy', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-system-auto-cancel"><?php echo esc_html( $auto_cancel_summary ); ?></div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🛠️</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Maintenance mode', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-system-maintenance"><?php echo esc_html( $maintenance_summary ); ?></div>
                    </div>
                </div>
                <div class="rb-admin-activity-item">
                    <div class="rb-admin-activity-icon" aria-hidden="true">🔗</div>
                    <div>
                        <div class="rb-admin-stat-label"><?php esc_html_e( 'Integrations', 'restaurant-booking' ); ?></div>
                        <div class="rb-admin-activity-meta" id="rb-system-integrations"><?php echo esc_html( $integrations_summary ); ?></div>
                    </div>
                </div>
            </div>
        </section>
    </aside>

    <main class="rb-admin-content" aria-live="polite">
        <section class="rb-admin-panel">
            <header class="rb-admin-panel-header">
                <div>
                    <h1 class="rb-admin-title"><?php esc_html_e( 'Settings', 'restaurant-booking' ); ?></h1>
                    <p class="rb-admin-panel-subtitle"><?php esc_html_e( 'Configure booking policies, notifications, and automation without leaving the WordPress dashboard.', 'restaurant-booking' ); ?></p>
                </div>
            </header>

            <?php settings_errors( 'restaurant_booking_settings' ); ?>

            <form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" id="rb-admin-settings-form" class="rb-admin-settings-form">
                <?php
                settings_fields( 'restaurant_booking_settings' );
                ?>
                <div class="rb-admin-settings-sections">
                    <?php do_settings_sections( 'restaurant_booking_settings' ); ?>
                </div>
                <div class="rb-admin-actions">
                    <?php
                    submit_button(
                        __( 'Save settings', 'restaurant-booking' ),
                        'primary',
                        'submit',
                        false,
                        array(
                            'id'    => 'rb-settings-save',
                            'class' => 'rb-btn rb-btn-primary',
                        )
                    );
                    ?>
                    <button type="button" class="rb-btn rb-btn-outline" id="rb-settings-reset"><?php esc_html_e( 'Reset changes', 'restaurant-booking' ); ?></button>
                </div>
            </form>

            <div class="rb-admin-alert" id="rb-settings-unsaved" data-default-message="<?php echo esc_attr( $unsaved_message ); ?>" hidden>
                <span class="dashicons dashicons-info" aria-hidden="true"></span>
                <span><?php echo esc_html( $unsaved_message ); ?></span>
            </div>
        </section>
    </main>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
