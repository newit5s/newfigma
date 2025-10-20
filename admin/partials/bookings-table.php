<?php
/**
 * Bookings table placeholder markup.
 */
?>
<div class="rb-admin-container rb-animate-fade-in">
    <header class="rb-admin-section-header">
        <h1><?php esc_html_e( 'Bookings', 'restaurant-booking' ); ?></h1>
        <button class="rb-btn rb-theme-toggle" id="rb-bookings-theme-toggle"><?php esc_html_e( 'Toggle theme', 'restaurant-booking' ); ?></button>
    </header>
    <div class="rb-card">
        <div class="rb-card-body">
            <p><?php esc_html_e( 'Booking data will appear here once connected to the REST endpoints.', 'restaurant-booking' ); ?></p>
            <div class="rb-loading-spinner rb-animate-spin"></div>
        </div>
    </div>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
