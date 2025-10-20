<?php
/**
 * Modern Restaurant Booking Manager - Booking Widget Integration
 *
 * Registers the modern booking modal shortcode and front-end assets.
 * This file implements Phase 2 deliverables by connecting the UI layer with WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'RB_Modern_Booking_Widget' ) ) {

    class RB_Modern_Booking_Widget {

        /**
         * Register hooks.
         */
        public function __construct() {
            add_shortcode( 'modern_restaurant_booking', array( $this, 'render_booking_widget' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        }

        /**
         * Render the booking widget markup via shortcode.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_booking_widget( $atts ) {
            $atts = shortcode_atts(
                array(
                    'theme'       => 'light',
                    'location'    => '',
                    'button_text' => __( 'Book a Table', 'restaurant-booking' ),
                ),
                $atts,
                'modern_restaurant_booking'
            );

            $button_text      = sanitize_text_field( $atts['button_text'] );
            $preset_location  = sanitize_text_field( $atts['location'] );
            $theme            = sanitize_text_field( $atts['theme'] );
            $preset_locations = apply_filters( 'rb_booking_modal_locations', array(), $atts );
            $locations_json   = ! empty( $preset_locations ) ? wp_json_encode( $preset_locations ) : '';

            ob_start();

            $partial = plugin_dir_path( __FILE__ ) . 'partials/booking-modal.php';
            if ( file_exists( $partial ) ) {
                include $partial;
            }

            return ob_get_clean();
        }

        /**
         * Enqueue modal assets when the shortcode is present on the current post.
         */
        public function enqueue_assets() {
            if ( ! is_singular() ) {
                return;
            }

            global $post;

            if ( ! $post instanceof WP_Post ) {
                return;
            }

            if ( ! has_shortcode( $post->post_content, 'modern_restaurant_booking' ) ) {
                return;
            }

            $version = defined( 'RB_PLUGIN_VERSION' ) ? RB_PLUGIN_VERSION : '1.0.0';
            $base_url = plugin_dir_url( __FILE__ ) . '../';

            // Ensure core design assets are registered.
            wp_enqueue_style(
                'rb-design-system',
                $base_url . 'assets/css/design-system.css',
                array(),
                $version
            );

            wp_enqueue_style(
                'rb-components',
                $base_url . 'assets/css/components.css',
                array( 'rb-design-system' ),
                $version
            );

            wp_enqueue_style(
                'rb-booking-modal',
                $base_url . 'assets/css/booking-modal.css',
                array( 'rb-design-system', 'rb-components' ),
                $version
            );

            wp_enqueue_script(
                'rb-booking-widget',
                $base_url . 'assets/js/booking-widget.js',
                array(),
                $version,
                true
            );

            $localized = array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'rb_booking_nonce' ),
                'strings'  => array(
                    'loading'      => __( 'Loading...', 'restaurant-booking' ),
                    'error'        => __( 'Something went wrong. Please try again.', 'restaurant-booking' ),
                    'success'      => __( 'Booking confirmed! Check your email.', 'restaurant-booking' ),
                    'stepInvalid'  => __( 'Please complete the required fields before continuing.', 'restaurant-booking' ),
                    'selectTime'   => __( 'Select a time slot to continue.', 'restaurant-booking' ),
                    'submitLabel'  => __( 'Confirm Reservation', 'restaurant-booking' ),
                    'continueLabel'=> __( 'Continue', 'restaurant-booking' ),
                    'backLabel'    => __( 'Back', 'restaurant-booking' ),
                    'required'     => __( 'This field is required.', 'restaurant-booking' ),
                    'invalidEmail' => __( 'Enter a valid email address.', 'restaurant-booking' ),
                    'invalidPhone' => __( 'Enter a valid phone number.', 'restaurant-booking' ),
                    'noSlots'      => __( 'No available times for the selected date.', 'restaurant-booking' ),
                    'available'    => __( 'Available', 'restaurant-booking' ),
                    'unavailable'  => __( 'Fully Booked', 'restaurant-booking' ),
                ),
            );

            wp_localize_script( 'rb-booking-widget', 'rbBookingAjax', $localized );
        }
    }
}
