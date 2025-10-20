<?php
/**
 * Admin dashboard layout placeholder.
 */
?>
<div class="rb-admin-header-bar">
    <div class="rb-admin-branding">
        <div class="rb-admin-logo">
            <span class="rb-admin-logo-text"><?php esc_html_e( 'Restaurant Booking', 'restaurant-booking' ); ?></span>
        </div>
        <button class="rb-btn rb-btn-icon rb-theme-toggle" id="rb-admin-theme-toggle">
            <span class="screen-reader-text"><?php esc_html_e( 'Toggle theme', 'restaurant-booking' ); ?></span>
        </button>
    </div>
    <nav class="rb-admin-nav">
        <a href="?page=rb-dashboard" class="rb-admin-menu-item rb-active"><?php esc_html_e( 'Dashboard', 'restaurant-booking' ); ?></a>
        <a href="?page=rb-bookings" class="rb-admin-menu-item"><?php esc_html_e( 'Bookings', 'restaurant-booking' ); ?></a>
        <a href="?page=rb-locations" class="rb-admin-menu-item"><?php esc_html_e( 'Locations', 'restaurant-booking' ); ?></a>
        <a href="?page=rb-settings" class="rb-admin-menu-item"><?php esc_html_e( 'Settings', 'restaurant-booking' ); ?></a>
        <a href="?page=rb-reports" class="rb-admin-menu-item"><?php esc_html_e( 'Reports', 'restaurant-booking' ); ?></a>
    </nav>
</div>

<div class="rb-admin-container rb-animate-fade-in">
    <div class="rb-admin-content" id="rb-admin-dashboard-root">
        <div class="rb-loading-overlay" id="rb-admin-loading" style="display: none;">
            <div class="rb-loading-spinner rb-animate-spin"></div>
        </div>
    </div>
</div>
<?php
wp_enqueue_script( 'rb-modern-admin' );
?>
