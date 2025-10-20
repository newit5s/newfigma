<?php
/**
 * Booking Modal Step 3 - Customer details and summary.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="rb-booking-step" data-step="3" aria-hidden="true">
    <div class="rb-step-header">
        <h3><?php esc_html_e( 'Your Details', 'restaurant-booking' ); ?></h3>
        <p><?php esc_html_e( 'Complete your reservation', 'restaurant-booking' ); ?></p>
    </div>

    <div class="rb-form-row">
        <div class="rb-form-group">
            <label class="rb-label rb-required" for="first-name"><?php esc_html_e( 'First Name', 'restaurant-booking' ); ?></label>
            <input type="text" class="rb-input" id="first-name" name="first_name" data-rb-required autocomplete="given-name" />
            <span class="rb-error-message" aria-live="polite"></span>
        </div>

        <div class="rb-form-group">
            <label class="rb-label rb-required" for="last-name"><?php esc_html_e( 'Last Name', 'restaurant-booking' ); ?></label>
            <input type="text" class="rb-input" id="last-name" name="last_name" data-rb-required autocomplete="family-name" />
            <span class="rb-error-message" aria-live="polite"></span>
        </div>
    </div>

    <div class="rb-form-row">
        <div class="rb-form-group">
            <label class="rb-label rb-required" for="email"><?php esc_html_e( 'Email', 'restaurant-booking' ); ?></label>
            <input type="email" class="rb-input" id="email" name="email" data-rb-required autocomplete="email" />
            <span class="rb-error-message" aria-live="polite"></span>
        </div>

        <div class="rb-form-group">
            <label class="rb-label rb-required" for="phone"><?php esc_html_e( 'Phone', 'restaurant-booking' ); ?></label>
            <input type="tel" class="rb-input" id="phone" name="phone" data-rb-required autocomplete="tel" />
            <span class="rb-error-message" aria-live="polite"></span>
        </div>
    </div>

    <div class="rb-form-group">
        <label class="rb-label" for="special-requests"><?php esc_html_e( 'Special Requests', 'restaurant-booking' ); ?></label>
        <textarea class="rb-textarea" id="special-requests" name="special_requests" rows="3" placeholder="<?php esc_attr_e( 'Allergies, celebrations, accessibility needs...', 'restaurant-booking' ); ?>"></textarea>
        <span class="rb-error-message" aria-live="polite"></span>
    </div>

    <div class="rb-booking-summary" aria-live="polite">
        <h4><?php esc_html_e( 'Booking Summary', 'restaurant-booking' ); ?></h4>
        <div class="rb-summary-item">
            <span><?php esc_html_e( 'Location:', 'restaurant-booking' ); ?></span>
            <span id="summary-location">—</span>
        </div>
        <div class="rb-summary-item">
            <span><?php esc_html_e( 'Date:', 'restaurant-booking' ); ?></span>
            <span id="summary-date">—</span>
        </div>
        <div class="rb-summary-item">
            <span><?php esc_html_e( 'Time:', 'restaurant-booking' ); ?></span>
            <span id="summary-time">—</span>
        </div>
        <div class="rb-summary-item">
            <span><?php esc_html_e( 'Party Size:', 'restaurant-booking' ); ?></span>
            <span id="summary-party">—</span>
        </div>
    </div>
</div>
