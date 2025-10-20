<?php
/**
 * Settings placeholder.
 */
?>
<div class="rb-admin-container rb-animate-slide-right">
    <header class="rb-admin-section-header">
        <h1><?php esc_html_e( 'Settings', 'restaurant-booking' ); ?></h1>
    </header>
    <div class="rb-card">
        <div class="rb-card-body">
            <p><?php esc_html_e( 'Configure booking preferences and theme options here.', 'restaurant-booking' ); ?></p>
        </div>
    </div>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
