<?php
/**
 * WordPress settings page for the Restaurant Booking plugin.
 *
 * @package RestaurantBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dashboard_url = admin_url( 'admin.php?page=rb-dashboard' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Restaurant Booking Settings', 'restaurant-booking' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Manage booking configuration directly inside the WordPress dashboard.', 'restaurant-booking' ); ?></p>

    <?php settings_errors( 'restaurant_booking_settings' ); ?>

    <form action="options.php" method="post" id="restaurant-booking-settings-form">
        <?php
        settings_fields( 'restaurant_booking_settings' );
        do_settings_sections( 'restaurant_booking_settings' );
        submit_button();
        ?>
    </form>

    <hr class="wp-header-end" />

    <p>
        <?php
        $dashboard_link = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url( $dashboard_url ),
            esc_html__( 'Restaurant Booking console', 'restaurant-booking' )
        );

        echo wp_kses_post(
            sprintf(
                /* translators: %s: link to the booking dashboard */
                __( 'Need to manage bookings and tables? Open the %s.', 'restaurant-booking' ),
                $dashboard_link
            )
        );
        ?>
    </p>
</div>
