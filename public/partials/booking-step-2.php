<?php
/**
 * Booking Modal Step 2 - Time selection.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="rb-booking-step" data-step="2" aria-hidden="true">
    <div class="rb-step-header">
        <h3><?php esc_html_e( 'Select Time', 'restaurant-booking' ); ?></h3>
        <p>
            <?php esc_html_e( 'Available time slots for', 'restaurant-booking' ); ?>
            <span id="selected-date-display">&mdash;</span>
        </p>
    </div>

    <div class="rb-time-slots-grid" id="time-slots" role="list" aria-live="polite">
        <!-- Time slots rendered via JavaScript -->
    </div>
    <p class="rb-inline-error" id="rb-time-slot-error" style="display:none;" role="alert"></p>

    <div class="rb-alternative-times" id="alternative-times" style="display:none;">
        <h4><?php esc_html_e( 'Alternative Times', 'restaurant-booking' ); ?></h4>
        <div class="rb-alt-slots" id="rb-alt-slots" role="list">
            <!-- Alternative suggestions -->
        </div>
    </div>
</div>
