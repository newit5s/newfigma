<?php
/**
 * Locations management placeholder.
 */
?>
<div class="rb-admin-container rb-animate-slide-up">
    <header class="rb-admin-section-header">
        <h1><?php esc_html_e( 'Locations', 'restaurant-booking' ); ?></h1>
    </header>
    <div class="rb-card">
        <div class="rb-card-body" id="rb-locations-list">
            <p><?php esc_html_e( 'Locations will sync from RB_Location::get_all_locations().', 'restaurant-booking' ); ?></p>
        </div>
    </div>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
