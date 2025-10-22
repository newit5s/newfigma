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
         * Calendar service instance used for availability checks.
         *
         * @var RB_Calendar_Service|null
         */
        protected $calendar_service = null;

        /**
         * Booking repository used for persisting demo bookings.
         *
         * @var RB_Fallback_Booking_Repository|null
         */
        protected $booking_repository = null;

        /**
         * Notification service for transactional emails.
         *
         * @var RB_Notification_Service|null
         */
        protected $notification_service = null;

        /**
         * Register hooks.
         */
        public function __construct() {
            if ( class_exists( 'RB_Calendar_Service' ) ) {
                $this->calendar_service = new RB_Calendar_Service();
            }

            if ( class_exists( 'RB_Fallback_Booking_Repository' ) ) {
                $this->booking_repository = RB_Fallback_Booking_Repository::instance();
            }

            if ( class_exists( 'RB_Notification_Service' ) ) {
                $this->notification_service = new RB_Notification_Service();
            }

            add_shortcode( 'modern_restaurant_booking', array( $this, 'render_booking_widget' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

            add_action( 'wp_ajax_rb_get_locations', array( $this, 'ajax_get_locations' ) );
            add_action( 'wp_ajax_nopriv_rb_get_locations', array( $this, 'ajax_get_locations' ) );
            add_action( 'wp_ajax_rb_check_availability', array( $this, 'ajax_check_availability' ) );
            add_action( 'wp_ajax_nopriv_rb_check_availability', array( $this, 'ajax_check_availability' ) );
            add_action( 'wp_ajax_rb_create_booking', array( $this, 'ajax_create_booking' ) );
            add_action( 'wp_ajax_nopriv_rb_create_booking', array( $this, 'ajax_create_booking' ) );
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
                'rb-animations',
                $base_url . 'assets/css/animations.css',
                array( 'rb-design-system' ),
                $version
            );

            wp_enqueue_style(
                'rb-booking-modal',
                $base_url . 'assets/css/booking-modal.css',
                array( 'rb-design-system', 'rb-components', 'rb-animations' ),
                $version
            );

            wp_enqueue_script(
                'rb-theme-manager',
                $base_url . 'assets/js/theme-manager.js',
                array(),
                $version,
                true
            );

            wp_enqueue_script(
                'rb-booking-widget',
                $base_url . 'assets/js/booking-widget.js',
                array( 'rb-theme-manager' ),
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

        /**
         * AJAX handler: return available locations for the booking widget.
         */
        public function ajax_get_locations() {
            $this->verify_ajax_nonce();

            $locations = array();

            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_all_locations' ) ) {
                $records = RB_Location::get_all_locations();

                if ( is_array( $records ) || $records instanceof Traversable ) {
                    foreach ( $records as $record ) {
                        $id   = isset( $record->id ) ? (string) $record->id : '';
                        $name = isset( $record->name ) ? $record->name : '';

                        if ( '' === $id && '' === $name ) {
                            continue;
                        }

                        $locations[] = array(
                            'id'    => $id,
                            'value' => $id,
                            'name'  => $name,
                            'label' => $name,
                        );
                    }
                }
            }

            if ( empty( $locations ) ) {
                $locations[] = array(
                    'id'    => '0',
                    'value' => '0',
                    'name'  => __( 'Main Dining Room', 'restaurant-booking' ),
                    'label' => __( 'Main Dining Room', 'restaurant-booking' ),
                );
            }

            $default_location = isset( $locations[0]['id'] ) ? $locations[0]['id'] : '';

            $this->send_json_success(
                array(
                    'locations'        => $locations,
                    'default_location' => $default_location,
                    'placeholder'      => __( 'Choose location', 'restaurant-booking' ),
                )
            );
        }

        /**
         * AJAX handler: retrieve availability for the requested date.
         */
        public function ajax_check_availability() {
            $this->verify_ajax_nonce();

            $location_id = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : '0';
            $date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
            $party_size  = isset( $_POST['party_size'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['party_size'] ) ) : 2;

            if ( '' === $date ) {
                $this->send_json_error( __( 'Please select a reservation date.', 'restaurant-booking' ), 400 );
            }

            $party_size = max( 1, $party_size );

            $service      = $this->get_calendar_service();
            $availability = $service
                ? $service->get_time_slots( absint( $location_id ), $date, $party_size )
                : array(
                    'date'              => $date,
                    'available_slots'   => array(),
                    'alternative_slots' => array(),
                );

            $this->send_json_success( $availability );
        }

        /**
         * AJAX handler: persist a booking created through the widget.
         */
        public function ajax_create_booking() {
            $this->verify_ajax_nonce();

            $repository = $this->get_booking_repository();

            if ( ! $repository ) {
                $this->send_json_error( __( 'Booking service temporarily unavailable.', 'restaurant-booking' ), 501 );
            }

            $payload = array(
                'first_name'        => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
                'last_name'         => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
                'email'             => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
                'phone'             => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
                'special_requests'  => isset( $_POST['special_requests'] ) ? wp_kses_post( wp_unslash( $_POST['special_requests'] ) ) : '',
                'location_id'       => isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0,
                'party_size'        => isset( $_POST['party_size'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['party_size'] ) ) : 0,
                'date'              => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
                'time'              => isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '',
            );

            if ( '' === $payload['first_name'] || '' === $payload['last_name'] ) {
                $this->send_json_error( __( 'Please provide your full name.', 'restaurant-booking' ), 400 );
            }

            if ( '' === $payload['email'] || ! is_email( $payload['email'] ) ) {
                $this->send_json_error( __( 'Enter a valid email address.', 'restaurant-booking' ), 400 );
            }

            if ( '' === $payload['phone'] ) {
                $this->send_json_error( __( 'Please provide a contact phone number.', 'restaurant-booking' ), 400 );
            }

            if ( '' === $payload['date'] || '' === $payload['time'] ) {
                $this->send_json_error( __( 'Please choose a reservation date and time.', 'restaurant-booking' ), 400 );
            }

            $payload['party_size'] = max( 1, $payload['party_size'] );

            $booking = $repository->add_booking( $payload );

            if ( empty( $booking ) ) {
                $this->send_json_error( __( 'Unable to complete your reservation. Please try again later.', 'restaurant-booking' ), 500 );
            }

            $notification_service = $this->get_notification_service();

            if ( $notification_service ) {
                $notification_service->send_booking_confirmation( $booking );
            }

            $this->send_json_success(
                array(
                    'message' => __( 'Booking confirmed! Check your email.', 'restaurant-booking' ),
                    'booking' => $this->format_booking_response( $booking ),
                )
            );
        }

        /**
         * Validate nonce for AJAX requests.
         */
        protected function verify_ajax_nonce() {
            if ( ! check_ajax_referer( 'rb_booking_nonce', 'nonce', false ) ) {
                $this->send_json_error( __( 'Security check failed. Please refresh and try again.', 'restaurant-booking' ), 403 );
            }
        }

        /**
         * Retrieve the calendar service instance.
         *
         * @return RB_Calendar_Service|null
         */
        protected function get_calendar_service() {
            if ( $this->calendar_service instanceof RB_Calendar_Service ) {
                return $this->calendar_service;
            }

            if ( class_exists( 'RB_Calendar_Service' ) ) {
                $this->calendar_service = new RB_Calendar_Service();

                return $this->calendar_service;
            }

            return null;
        }

        /**
         * Retrieve the booking repository instance.
         *
         * @return RB_Fallback_Booking_Repository|null
         */
        protected function get_booking_repository() {
            if ( $this->booking_repository instanceof RB_Fallback_Booking_Repository ) {
                return $this->booking_repository;
            }

            if ( class_exists( 'RB_Fallback_Booking_Repository' ) ) {
                $this->booking_repository = RB_Fallback_Booking_Repository::instance();

                return $this->booking_repository;
            }

            return null;
        }

        /**
         * Retrieve the notification service instance.
         *
         * @return RB_Notification_Service|null
         */
        protected function get_notification_service() {
            if ( $this->notification_service instanceof RB_Notification_Service ) {
                return $this->notification_service;
            }

            if ( class_exists( 'RB_Notification_Service' ) ) {
                $this->notification_service = new RB_Notification_Service();

                return $this->notification_service;
            }

            return null;
        }

        /**
         * Format booking payload for frontend consumption.
         *
         * @param array $booking Booking dataset.
         *
         * @return array
         */
        protected function format_booking_response( $booking ) {
            if ( ! is_array( $booking ) ) {
                return array();
            }

            return array(
                'id'            => isset( $booking['id'] ) ? (int) $booking['id'] : 0,
                'customer_name' => isset( $booking['customer_name'] ) ? sanitize_text_field( $booking['customer_name'] ) : '',
                'email'         => isset( $booking['customer_email'] ) ? sanitize_email( $booking['customer_email'] ) : '',
                'phone'         => isset( $booking['customer_phone'] ) ? sanitize_text_field( $booking['customer_phone'] ) : '',
                'booking_date'  => isset( $booking['booking_date'] ) ? sanitize_text_field( $booking['booking_date'] ) : '',
                'booking_time'  => isset( $booking['booking_time'] ) ? sanitize_text_field( $booking['booking_time'] ) : '',
                'party_size'    => isset( $booking['party_size'] ) ? (int) $booking['party_size'] : 0,
                'status'        => isset( $booking['status'] ) ? sanitize_text_field( $booking['status'] ) : 'pending',
                'location'      => isset( $booking['location_name'] ) ? sanitize_text_field( $booking['location_name'] ) : '',
            );
        }

        /**
         * Send a successful JSON response.
         *
         * @param array $data Additional payload.
         * @param int   $code HTTP status code.
         */
        protected function send_json_success( $data = array(), $code = 200 ) {
            $payload = array_merge( array( 'success' => true ), $data );

            wp_send_json( $payload, $code );
        }

        /**
         * Send an error JSON response.
         *
         * @param string $message Error message.
         * @param int    $code    HTTP status code.
         */
        protected function send_json_error( $message, $code = 400 ) {
            wp_send_json(
                array(
                    'success' => false,
                    'message' => $message,
                ),
                $code
            );
        }
    }
}
