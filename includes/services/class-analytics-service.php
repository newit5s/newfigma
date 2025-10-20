<?php
/**
 * Analytics service implementation.
 *
 * @package RestaurantBooking\Services
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Analytics_Service' ) ) {

    /**
     * Provides aggregated analytics data for dashboards.
     */
    class RB_Analytics_Service {

        /**
         * Singleton instance.
         *
         * @var RB_Analytics_Service|null
         */
        protected static $instance = null;

        /**
         * Retrieve singleton instance.
         *
         * @return RB_Analytics_Service
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Return dashboard statistics payload.
         *
         * @param int         $location_id Location identifier.
         * @param string|null $date        Target date (Y-m-d).
         *
         * @return array
         */
        public function get_dashboard_stats( $location_id, $date = null ) {
            $location_id = absint( $location_id );
            $date        = $this->normalize_date( $date );

            $booking_model = new RB_Booking();
            $stats         = $booking_model->get_location_stats( $location_id, $date );

            $yesterday          = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
            $previous_stats     = $booking_model->get_location_stats( $location_id, $yesterday );
            $available_tables   = isset( $stats['available_tables'] ) ? (int) $stats['available_tables'] : 0;
            $pending            = isset( $stats['pending'] ) ? (int) $stats['pending'] : 0;
            $confirmed          = isset( $stats['confirmed'] ) ? (int) $stats['confirmed'] : 0;
            $currency           = isset( $stats['currency'] ) ? $stats['currency'] : apply_filters( 'rb_booking_currency', '$', $location_id );
            $todays_bookings    = isset( $stats['total_bookings'] ) ? (int) $stats['total_bookings'] : 0;
            $todays_revenue     = isset( $stats['revenue'] ) ? (float) $stats['revenue'] : 0.0;
            $occupancy_rate     = isset( $stats['occupancy_rate'] ) ? (float) $stats['occupancy_rate'] : 0.0;

            return array(
                'location_id'      => $location_id,
                'date'             => $date,
                'todays_bookings'  => $todays_bookings,
                'todays_revenue'   => $todays_revenue,
                'currency'         => $currency,
                'occupancy_rate'   => $occupancy_rate,
                'available_tables' => $available_tables,
                'pending_count'    => $pending,
                'confirmed_count'  => $confirmed,
                'comparison'       => array(
                    'bookings_change' => $this->calculate_percentage_change( isset( $previous_stats['total_bookings'] ) ? $previous_stats['total_bookings'] : 0, $todays_bookings ),
                    'revenue_change'  => $this->calculate_percentage_change( isset( $previous_stats['revenue'] ) ? $previous_stats['revenue'] : 0, $todays_revenue ),
                    'pending_change'  => $this->calculate_percentage_change( isset( $previous_stats['pending'] ) ? $previous_stats['pending'] : 0, $pending ),
                ),
            );
        }

        /**
         * Generate booking trend data for charts.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Period token (7d, 30d, 90d).
         *
         * @return array
         */
        public function get_booking_trends( $location_id, $period = '7d' ) {
            $location_id = absint( $location_id );
            $days        = $this->resolve_period_days( $period );

            $labels   = array();
            $bookings = array();
            $revenue  = array();

            $booking_model = new RB_Booking();
            $end_date      = $this->normalize_date( null );
            $end_timestamp = strtotime( $end_date );

            for ( $offset = $days - 1; $offset >= 0; $offset-- ) {
                $target_timestamp = strtotime( '-' . $offset . ' days', $end_timestamp );
                $target_date      = gmdate( 'Y-m-d', $target_timestamp );

                $daily_stats = $booking_model->get_location_stats( $location_id, $target_date );

                $labels[]   = gmdate( 'M j', $target_timestamp );
                $bookings[] = isset( $daily_stats['total_bookings'] ) ? (int) $daily_stats['total_bookings'] : 0;
                $revenue[]  = isset( $daily_stats['revenue'] ) ? (float) $daily_stats['revenue'] : 0.0;
            }

            return array(
                'labels'   => $labels,
                'bookings' => $bookings,
                'revenue'  => $revenue,
            );
        }

        /**
         * Analyse booking times for popular slots.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Period token (7d, 30d, 90d).
         *
         * @return array
         */
        public function get_popular_times( $location_id, $period = '30d' ) {
            $location_id = absint( $location_id );
            $days        = $this->resolve_period_days( $period );

            $end_date   = $this->normalize_date( null );
            $start_date = gmdate( 'Y-m-d', strtotime( $end_date . ' -' . ( $days - 1 ) . ' days' ) );

            $filters = array(
                'location_id' => $location_id,
                'date_from'   => $start_date,
                'date_to'     => $end_date,
                'no_limit'    => true,
            );

            $result = RB_Booking::get_bookings_with_filters( $filters, 1, 500, 'booking_datetime', 'asc' );
            $slots  = array();

            if ( isset( $result['bookings'] ) && is_array( $result['bookings'] ) ) {
                foreach ( $result['bookings'] as $booking ) {
                    $time  = isset( $booking['booking_time'] ) ? $booking['booking_time'] : '';
                    $party = isset( $booking['party_size'] ) ? (int) $booking['party_size'] : 0;

                    if ( empty( $time ) ) {
                        continue;
                    }

                    $hour = substr( $time, 0, 2 );
                    if ( ! is_numeric( $hour ) ) {
                        continue;
                    }

                    $slot_key = sprintf( '%02d:00', (int) $hour );

                    if ( ! isset( $slots[ $slot_key ] ) ) {
                        $slots[ $slot_key ] = array(
                            'slot'     => $slot_key,
                            'bookings' => 0,
                            'guests'   => 0,
                        );
                    }

                    $slots[ $slot_key ]['bookings']++; 
                    $slots[ $slot_key ]['guests'] += $party;
                }
            }

            uasort(
                $slots,
                static function ( $a, $b ) {
                    if ( $a['bookings'] === $b['bookings'] ) {
                        return $b['guests'] <=> $a['guests'];
                    }

                    return $b['bookings'] <=> $a['bookings'];
                }
            );

            return array_values( $slots );
        }

        /**
         * Resolve number of days for a period token.
         *
         * @param string $period Period key.
         *
         * @return int
         */
        protected function resolve_period_days( $period ) {
            switch ( $period ) {
                case '90d':
                    return 90;
                case '30d':
                    return 30;
                case '7d':
                default:
                    return 7;
            }
        }

        /**
         * Calculate percentage change helper.
         *
         * @param float|int $old_value Baseline value.
         * @param float|int $new_value New value.
         *
         * @return float
         */
        protected function calculate_percentage_change( $old_value, $new_value ) {
            $old_value = (float) $old_value;
            $new_value = (float) $new_value;

            if ( 0.0 === $old_value ) {
                return $new_value > 0 ? 100.0 : 0.0;
            }

            return round( ( ( $new_value - $old_value ) / $old_value ) * 100, 1 );
        }

        /**
         * Normalise dates for analytics.
         *
         * @param string|null $date Input date.
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
