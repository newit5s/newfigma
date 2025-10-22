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
         * Fallback repository instance.
         *
         * @var RB_Fallback_Booking_Repository|null
         */
        protected $repository = null;

        public function __construct() {
            if ( class_exists( 'RB_Fallback_Booking_Repository' ) ) {
                $this->repository = RB_Fallback_Booking_Repository::instance();
            }
        }

        /**
         * Retrieve availability for a date range.
         *
         * @param int   $location_id Location identifier.
         * @param array $date_range  Array containing `start` and `end`.
         *
         * @return array
         */
        public function get_availability( $location_id, $date_range = array() ) {
            $start = isset( $date_range['start'] ) ? $this->normalize_date( $date_range['start'] ) : gmdate( 'Y-m-d' );
            $end   = isset( $date_range['end'] ) ? $this->normalize_date( $date_range['end'] ) : $start;

            $dates = $this->expand_date_range( $start, $end );
            $data  = array();

            foreach ( $dates as $date ) {
                $data[ $date ] = $this->get_time_slots( $location_id, $date );
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
    }
}
