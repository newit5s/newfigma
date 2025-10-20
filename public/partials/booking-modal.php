<?php
/**
 * Booking modal wrapper template.
 *
 * Variables available from the shortcode handler:
 * @var string $button_text
 * @var string $preset_location
 * @var string $theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$button_text     = ! empty( $button_text ) ? $button_text : __( 'Book a Table', 'restaurant-booking' );
$preset_location = ! empty( $preset_location ) ? $preset_location : '';
$theme           = in_array( $theme, array( 'light', 'dark' ), true ) ? $theme : 'light';
$locations_json = isset( $locations_json ) && ! empty( $locations_json ) ? $locations_json : '';
?>
<div class="rb-booking-widget" data-rb-booking-widget data-theme="<?php echo esc_attr( $theme ); ?>">
    <div class="rb-booking-trigger">
        <button class="rb-btn rb-btn-primary rb-btn-lg" data-rb-booking-trigger="open" type="button">
            <?php echo esc_html( $button_text ); ?>
        </button>
    </div>

    <div class="rb-booking-modal-overlay" id="rb-booking-modal" aria-hidden="true" role="presentation">
        <div class="rb-booking-modal" role="dialog" aria-modal="true" aria-labelledby="rb-booking-title">
            <div class="rb-booking-header">
                <h2 class="rb-booking-title" id="rb-booking-title"><?php esc_html_e( 'Reserve a Table', 'restaurant-booking' ); ?></h2>
                <button class="rb-modal-close" id="rb-close-booking" type="button" aria-label="<?php esc_attr_e( 'Close booking modal', 'restaurant-booking' ); ?>">&times;</button>
            </div>

            <div class="rb-booking-progress" role="tablist" aria-label="<?php esc_attr_e( 'Booking progress', 'restaurant-booking' ); ?>">
                <div class="rb-progress-step rb-active" data-step="1">
                    <div class="rb-progress-number">1</div>
                    <span class="rb-progress-label"><?php esc_html_e( 'Date', 'restaurant-booking' ); ?></span>
                </div>
                <div class="rb-progress-step" data-step="2">
                    <div class="rb-progress-number">2</div>
                    <span class="rb-progress-label"><?php esc_html_e( 'Time', 'restaurant-booking' ); ?></span>
                </div>
                <div class="rb-progress-step" data-step="3">
                    <div class="rb-progress-number">3</div>
                    <span class="rb-progress-label"><?php esc_html_e( 'Details', 'restaurant-booking' ); ?></span>
                </div>
            </div>

            <form class="rb-booking-body" id="rb-booking-form" novalidate>
                <?php
                $step_1 = __DIR__ . '/booking-step-1.php';
                $step_2 = __DIR__ . '/booking-step-2.php';
                $step_3 = __DIR__ . '/booking-step-3.php';

                if ( file_exists( $step_1 ) ) {
                    include $step_1;
                }

                if ( file_exists( $step_2 ) ) {
                    include $step_2;
                }

                if ( file_exists( $step_3 ) ) {
                    include $step_3;
                }
                ?>
            </form>

            <div class="rb-booking-footer">
                <button class="rb-btn rb-btn-secondary" id="rb-prev-step" type="button" style="display: none;">
                    <?php esc_html_e( 'Back', 'restaurant-booking' ); ?>
                </button>
                <div class="rb-footer-status" id="rb-footer-status"></div>
                <button class="rb-btn rb-btn-primary" id="rb-next-step" type="button">
                    <?php esc_html_e( 'Continue', 'restaurant-booking' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
