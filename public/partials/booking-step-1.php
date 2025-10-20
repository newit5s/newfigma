<?php
/**
 * Booking Modal Step 1 - Location, party size, and date selection.
 *
 * @var string $preset_location Provided from shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$today = current_time( 'Y-m-d' );
?>
<div class="rb-booking-step rb-active" data-step="1" aria-hidden="false">
    <div class="rb-step-header">
        <h3><?php esc_html_e( 'Select Date & Location', 'restaurant-booking' ); ?></h3>
        <p><?php esc_html_e( 'Choose your preferred dining details', 'restaurant-booking' ); ?></p>
    </div>

    <div class="rb-form-row">
        <div class="rb-form-group">
            <label class="rb-label rb-required" for="location-select"><?php esc_html_e( 'Location', 'restaurant-booking' ); ?></label>
            <select class="rb-select" id="location-select" name="location" data-rb-required data-placeholder="<?php esc_attr_e( 'Choose location', 'restaurant-booking' ); ?>" data-preset="<?php echo esc_attr( $preset_location ); ?>"<?php if ( $locations_json ) : ?> data-locations="<?php echo esc_attr( $locations_json ); ?>"<?php endif; ?>>
                <option value=""><?php esc_html_e( 'Choose location', 'restaurant-booking' ); ?></option>
            </select>
            <span class="rb-error-message" aria-live="polite"></span>
        </div>

        <div class="rb-form-group">
            <label class="rb-label rb-required" for="party-size"><?php esc_html_e( 'Party Size', 'restaurant-booking' ); ?></label>
            <select class="rb-select" id="party-size" name="party_size" data-rb-required>
                <option value=""><?php esc_html_e( 'Select party size', 'restaurant-booking' ); ?></option>
                <option value="1">1 <?php esc_html_e( 'Person', 'restaurant-booking' ); ?></option>
                <option value="2">2 <?php esc_html_e( 'People', 'restaurant-booking' ); ?></option>
                <option value="4">4 <?php esc_html_e( 'People', 'restaurant-booking' ); ?></option>
                <option value="6">6 <?php esc_html_e( 'People', 'restaurant-booking' ); ?></option>
                <option value="8">8+ <?php esc_html_e( 'People', 'restaurant-booking' ); ?></option>
            </select>
            <span class="rb-error-message" aria-live="polite"></span>
        </div>
    </div>

    <div class="rb-form-group">
        <label class="rb-label rb-required" for="booking-date"><?php esc_html_e( 'Date', 'restaurant-booking' ); ?></label>
        <input type="date" class="rb-input" id="booking-date" name="date" data-rb-required min="<?php echo esc_attr( $today ); ?>" />
        <span class="rb-error-message" aria-live="polite"></span>
    </div>
</div>
