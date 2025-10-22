<?php
/**
 * Calendar service bridging booking availability requests with
 * the fallback repository so time slot data is available even
 * when custom database tables are not installed.
 *
 * @package RestaurantBooking\Services
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Calendar_Service' ) ) {

    class RB_Calendar_Service {

        /**
         * Tracks whether integration hooks have been registered.
         *
         * @var bool
         */
        protected static $hooks_registered = false;

        /**
         * Fallback repository instance.
         *
         * @var RB_Fallback_Booking_Repository|null
         */
        protected $repository = null;

        public function __construct() {
            if ( class_exists( 'RB_Fallback_Booking_Repository' ) ) {
                $this->repository = RB_Fallback_Booking_Repository::instance();
            }

            if ( ! self::$hooks_registered ) {
                add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
                add_filter( 'rb_booking_manager_calendar_data', array( $this, 'filter_calendar_data' ), 10, 5 );

                self::$hooks_registered = true;
            }
        }

        /**
         * Retrieve availability for a date range.
         *
         * @param int   $location_id Location identifier.
         * @param array $date_range  Array containing `start` and `end`.
         *
         * @param int   $party_size  Party size.
         *
         * @return array
         */
        public function get_availability( $location_id, $date_range = array(), $party_size = 2 ) {
            $start = isset( $date_range['start'] ) ? $this->normalize_date( $date_range['start'] ) : gmdate( 'Y-m-d' );
            $end   = isset( $date_range['end'] ) ? $this->normalize_date( $date_range['end'] ) : $start;

            $party_size = max( 1, (int) $party_size );

            $dates = $this->expand_date_range( $start, $end );
            $data  = array();

            foreach ( $dates as $date ) {
                $data[ $date ] = $this->get_time_slots( $location_id, $date, $party_size );
            }

            return $data;
        }

        /**
         * Return time slots for a specific date.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Target date.
         * @param int    $party_size  Party size.
         *
         * @return array
         */
        public function get_time_slots( $location_id, $date, $party_size = 2 ) {
            $date = $this->normalize_date( $date );

            if ( $this->repository ) {
                return $this->repository->get_time_slots( $location_id, $date, $party_size );
            }

            return array(
                'date'              => $date,
                'available_slots'   => array(),
                'alternative_slots' => array(),
            );
        }

        /**
         * Register REST routes used for calendar availability requests.
         */
        public function register_rest_routes() {
            if ( ! function_exists( 'register_rest_route' ) || ! class_exists( 'WP_REST_Server' ) ) {
                return;
            }

            register_rest_route(
                'rb/v1',
                '/calendar/availability',
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_get_availability' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'location'   => array(
                            'sanitize_callback' => 'absint',
                            'default'           => 0,
                        ),
                        'party_size' => array(
                            'sanitize_callback' => 'absint',
                            'default'           => 2,
                        ),
                        'date'       => array(
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'start'      => array(
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'end'        => array(
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                )
            );
        }

        /**
         * REST callback returning availability for a date range.
         *
         * @param WP_REST_Request $request Request instance.
         *
         * @return WP_REST_Response|WP_Error|array
         */
        public function rest_get_availability( WP_REST_Request $request ) {
            $location_id = absint( $request->get_param( 'location' ) );
            $party_size  = max( 1, (int) $request->get_param( 'party_size' ) );

            $start = $this->prepare_rest_date( $request->get_param( 'start' ) );
            $end   = $this->prepare_rest_date( $request->get_param( 'end' ) );
            $date  = $this->prepare_rest_date( $request->get_param( 'date' ) );

            if ( ! $start && $date ) {
                $start = $date;
            }

            if ( ! $start ) {
                return new WP_Error(
                    'rb_missing_date',
                    __( 'Provide a valid date parameter.', 'restaurant-booking' ),
                    array( 'status' => 400 )
                );
            }

            if ( ! $end ) {
                $end = $start;
            }

            $availability = $this->get_availability(
                $location_id,
                array(
                    'start' => $start,
                    'end'   => $end,
                ),
                $party_size
            );

            return rest_ensure_response(
                array(
                    'location_id'  => $location_id,
                    'party_size'   => $party_size,
                    'date_range'   => array(
                        'start' => $start,
                        'end'   => $end,
                    ),
                    'availability' => $availability,
                )
            );
        }

        /**
         * Provide fallback calendar data for the booking manager calendar view.
         *
         * @param array  $data    Existing calendar dataset.
         * @param int    $month   Requested month.
         * @param int    $year    Requested year.
         * @param string $view    Calendar view (month|week|day).
         * @param array  $filters Additional filters.
         *
         * @return array
         */
        public function filter_calendar_data( $data, $month, $year, $view, $filters ) {
            if ( ! empty( $data ) ) {
                return $data;
            }

            if ( ! $this->repository || ! method_exists( $this->repository, 'get_calendar_data' ) ) {
                return $data;
            }

            $filters = is_array( $filters ) ? $filters : array();

            $calendar = $this->repository->get_calendar_data( (int) $month, (int) $year, $filters );

            if ( ! is_array( $calendar ) ) {
                return $data;
            }

            $normalized = array();

            foreach ( $calendar as $date_key => $entry ) {
                $date = $this->normalize_date( $date_key );

                $bookings = array();
                if ( isset( $entry['bookings'] ) && is_array( $entry['bookings'] ) ) {
                    foreach ( $entry['bookings'] as $booking ) {
                        $bookings[] = array(
                            'id'            => isset( $booking['id'] ) ? (int) $booking['id'] : 0,
                            'customer_name' => isset( $booking['customer_name'] ) ? sanitize_text_field( $booking['customer_name'] ) : '',
                            'time'          => isset( $booking['time'] ) ? sanitize_text_field( $booking['time'] ) : ( isset( $booking['booking_time'] ) ? sanitize_text_field( $booking['booking_time'] ) : '' ),
                            'status'        => isset( $booking['status'] ) ? sanitize_key( $booking['status'] ) : 'pending',
                            'party_size'    => isset( $booking['party_size'] ) ? (int) $booking['party_size'] : 0,
                            'table_number'  => isset( $booking['table_number'] ) ? sanitize_text_field( $booking['table_number'] ) : '',
                        );
                    }
                }

                $normalized[ $date ] = array(
                    'date'     => $date,
                    'bookings' => $bookings,
                    'count'    => isset( $entry['count'] ) ? (int) $entry['count'] : count( $bookings ),
                );
            }

            return $normalized;
        }

        /**
         * Expand a date range into an array of Y-m-d strings.
         *
         * @param string $start Start date.
         * @param string $end   End date.
         *
         * @return array
         */
        protected function expand_date_range( $start, $end ) {
            $range = array();

            $start_time = strtotime( $start );
            $end_time   = strtotime( $end );

            if ( false === $start_time || false === $end_time ) {
                return array( gmdate( 'Y-m-d' ) );
            }

            if ( $start_time > $end_time ) {
                $tmp        = $start_time;
                $start_time = $end_time;
                $end_time   = $tmp;
            }

            for ( $time = $start_time; $time <= $end_time; $time += DAY_IN_SECONDS ) {
                $range[] = gmdate( 'Y-m-d', $time );
            }

            return $range;
        }

        /**
         * Normalise a date string into Y-m-d format.
         *
         * @param string $date Date string.
         *
         * @return string
         */
        protected function normalize_date( $date ) {
            if ( empty( $date ) ) {
                return gmdate( 'Y-m-d' );
            }

            $timestamp = strtotime( $date );

            if ( false === $timestamp ) {
                return gmdate( 'Y-m-d' );
            }

            return gmdate( 'Y-m-d', $timestamp );
        }

        /**
         * Normalise REST date parameters, returning an empty string when invalid.
         *
         * @param mixed $value Raw request value.
         *
         * @return string
         */
        protected function prepare_rest_date( $value ) {
            if ( empty( $value ) || ! is_scalar( $value ) ) {
                return '';
            }

            $value      = sanitize_text_field( wp_unslash( $value ) );
            $timestamp  = strtotime( $value );

            if ( false === $timestamp ) {
                return '';
            }

            return gmdate( 'Y-m-d', $timestamp );
        }
    }
}
