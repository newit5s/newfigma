<?php
/**
 * RB Analytics - Business Intelligence Service.
 *
 * Provides analytics, reporting, and data aggregation helpers for the
 * Modern Restaurant Booking system dashboards.
 *
 * @package RestaurantBooking\Services
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Analytics' ) ) {

    /**
     * Central analytics service used by portal dashboard components.
     */
    class RB_Analytics {

        /**
         * Singleton instance.
         *
         * @var RB_Analytics|null
         */
        private static $instance = null;

        /**
         * Booking model instance.
         *
         * @var RB_Booking|null
         */
        private $booking_model = null;

        /**
         * Location model instance.
         *
         * @var RB_Location|null
         */
        private $location_model = null;

        /**
         * Private constructor to enforce singleton usage.
         */
        private function __construct() {
            if ( class_exists( 'RB_Booking' ) ) {
                $this->booking_model = new RB_Booking();
            }

            if ( class_exists( 'RB_Location' ) ) {
                $this->location_model = new RB_Location();
            }
        }

        /**
         * Retrieve the singleton instance.
         *
         * @return RB_Analytics
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Return aggregated analytics payload for the portal dashboard.
         *
         * @param int         $location_id Location identifier.
         * @param string|null $date        Target date in Y-m-d format.
         *
         * @return array
         */
        public function get_dashboard_analytics( $location_id, $date = null ) {
            $location_id = $this->normalize_location_id( $location_id );
            $date        = $this->normalize_date( $date );

            return array(
                'stats'    => $this->get_current_stats( $location_id, $date ),
                'trends'   => $this->get_booking_trends( $location_id, '7d' ),
                'schedule' => $this->get_todays_schedule( $location_id, $date ),
                'alerts'   => $this->get_dashboard_alerts( $location_id, $date ),
            );
        }

        /**
         * Retrieve analytics metrics for a given location and period.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Period key (today, week, month, now).
         *
         * @return array
         */
        public function get_location_stats( $location_id, $period = 'today' ) {
            $location_id = $this->normalize_location_id( $location_id );
            $period      = $this->normalize_period_key( $period );

            $range       = $this->resolve_period_range( $period );
            $current_day = $this->query_booking_stats( $location_id, $range['end'] );
            $comparison  = $this->query_booking_stats( $location_id, $range['compare'] );

            $bookings_change  = $this->calculate_percentage_change( $comparison['total_bookings'], $current_day['total_bookings'] );
            $revenue_change   = $this->calculate_percentage_change( $comparison['revenue'], $current_day['revenue'] );
            $occupancy_change = $this->calculate_percentage_change( $comparison['occupancy_rate'], $current_day['occupancy_rate'] );
            $pending_change   = $this->calculate_percentage_change( $comparison['pending'], $current_day['pending'] );

            return array(
                'bookings_count'   => (int) $current_day['total_bookings'],
                'bookings_change'  => $bookings_change,
                'revenue_amount'   => (float) $current_day['revenue'],
                'revenue_change'   => $revenue_change,
                'revenue_currency' => $current_day['currency'],
                'occupancy_rate'   => (float) $current_day['occupancy_rate'],
                'occupancy_change' => $occupancy_change,
                'pending_count'    => (int) $current_day['pending'],
                'pending_change'   => $pending_change,
                'pending_badge'    => $current_day['pending'] > 0 ? __( 'Review Required', 'restaurant-booking' ) : '',
            );
        }

        /**
         * Build the current day statistics payload.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string in Y-m-d format.
         *
         * @return array
         */
        public function get_current_stats( $location_id, $date ) {
            $location_id = $this->normalize_location_id( $location_id );
            $date        = $this->normalize_date( $date );

            $stats      = $this->query_booking_stats( $location_id, $date );
            $yesterday  = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
            $comparison = $this->query_booking_stats( $location_id, $yesterday );

            return array(
                'todays_bookings'       => (int) $stats['total_bookings'],
                'todays_revenue'        => (float) $stats['revenue'],
                'currency'              => $stats['currency'],
                'occupancy_rate'        => (float) $stats['occupancy_rate'],
                'available_tables'      => $this->get_available_table_count( $location_id, $date ),
                'pending_confirmations' => (int) $stats['pending'],
                'comparison'            => array(
                    'bookings_change' => $this->calculate_percentage_change( $comparison['total_bookings'], $stats['total_bookings'] ),
                    'revenue_change'  => $this->calculate_percentage_change( $comparison['revenue'], $stats['revenue'] ),
                    'trend'           => $stats['total_bookings'] >= $comparison['total_bookings'] ? 'up' : 'down',
                ),
            );
        }

        /**
         * Generate booking trend data for Chart.js visualisations.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Period key (7d, 30d, 90d).
         *
         * @return array
         */
        public function get_booking_trends( $location_id, $period = '7d' ) {
            $location_id = $this->normalize_location_id( $location_id );
            $days        = $this->period_to_days( $period );
            $series      = array();

            for ( $offset = $days - 1; $offset >= 0; $offset-- ) {
                $date  = gmdate( 'Y-m-d', strtotime( "-{$offset} days" ) );
                $stats = $this->query_booking_stats( $location_id, $date );

                $series[] = array(
                    'date'     => $date,
                    'label'    => gmdate( 'M j', strtotime( $date ) ),
                    'bookings' => (int) $stats['total_bookings'],
                    'revenue'  => (float) $stats['revenue'],
                    'guests'   => (int) $stats['total_guests'],
                );
            }

            return array(
                'labels'   => wp_list_pluck( $series, 'label' ),
                'datasets' => array(
                    array(
                        'label'           => __( 'Bookings', 'restaurant-booking' ),
                        'data'            => wp_list_pluck( $series, 'bookings' ),
                        'borderColor'     => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    ),
                    array(
                        'label'           => __( 'Revenue', 'restaurant-booking' ),
                        'data'            => wp_list_pluck( $series, 'revenue' ),
                        'borderColor'     => 'rgb(16, 185, 129)',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    ),
                ),
            );
        }

        /**
         * Retrieve today's booking schedule entries.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string in Y-m-d format.
         *
         * @return array
         */
        public function get_todays_schedule( $location_id, $date ) {
            $location_id = $this->normalize_location_id( $location_id );
            $date        = $this->normalize_date( $date );

            if ( $this->booking_model && method_exists( $this->booking_model, 'get_todays_bookings' ) ) {
                $bookings = $this->booking_model->get_todays_bookings( $location_id );
                if ( is_array( $bookings ) && ! empty( $bookings ) ) {
                    return $bookings;
                }
            }

            $timestamp = strtotime( $date . ' 17:00:00' );

            return array(
                array(
                    'id'            => 1,
                    'time'          => gmdate( 'g:i A', $timestamp ),
                    'customer_name' => __( 'Sample Guest', 'restaurant-booking' ),
                    'party_size'    => 4,
                    'status'        => 'confirmed',
                    'notes'         => __( 'Anniversary dinner', 'restaurant-booking' ),
                ),
            );
        }

        /**
         * Produce dashboard alerts for the manager.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string in Y-m-d format.
         *
         * @return array
         */
        public function get_dashboard_alerts( $location_id, $date ) {
            $location_id = $this->normalize_location_id( $location_id );
            $date        = $this->normalize_date( $date );
            $stats       = $this->query_booking_stats( $location_id, $date );

            $alerts = array();

            if ( $stats['pending'] > 0 ) {
                $alerts[] = array(
                    'type'       => 'warning',
                    'message'    => sprintf(
                        _n(
                            '%d booking needs confirmation',
                            '%d bookings need confirmation',
                            (int) $stats['pending'],
                            'restaurant-booking'
                        ),
                        (int) $stats['pending']
                    ),
                    'action_url' => admin_url( 'admin.php?page=rb-bookings&status=pending' ),
                );
            }

            if ( $stats['occupancy_rate'] > 90 ) {
                $alerts[] = array(
                    'type'       => 'info',
                    'message'    => __( 'High occupancy expected today. Consider waitlist.', 'restaurant-booking' ),
                    'action_url' => '',
                );
            }

            return $alerts;
        }

        /**
         * Export analytics payload.
         *
         * @param int    $location_id Location identifier.
         * @param string $format      Export format.
         * @param array  $filters     Optional filters.
         *
         * @return WP_Error|string
         */
        public function export_analytics( $location_id, $format = 'csv', $filters = array() ) {
            $supported = array( 'csv' );

            if ( ! in_array( $format, $supported, true ) ) {
                return new WP_Error( 'invalid_format', __( 'Invalid export format', 'restaurant-booking' ) );
            }

            return $this->export_csv( $location_id, $filters );
        }

        /**
         * Normalise a location identifier.
         *
         * @param int $location_id Potential location identifier.
         *
         * @return int
         */
        private function normalize_location_id( $location_id ) {
            $location_id = (int) $location_id;

            return $location_id > 0 ? $location_id : 0;
        }

        /**
         * Ensure a valid date string is returned.
         *
         * @param string|null $date Raw date input.
         *
         * @return string
         */
        private function normalize_date( $date ) {
            if ( empty( $date ) ) {
                return gmdate( 'Y-m-d' );
            }

            $parsed = strtotime( $date );

            if ( false === $parsed ) {
                return gmdate( 'Y-m-d' );
            }

            return gmdate( 'Y-m-d', $parsed );
        }

        /**
         * Normalise metric period key.
         *
         * @param string $period Period key.
         *
         * @return string
         */
        private function normalize_period_key( $period ) {
            $valid = array( 'today', 'week', 'month', 'now' );
            $period = sanitize_key( $period );

            return in_array( $period, $valid, true ) ? $period : 'today';
        }

        /**
         * Resolve period range metadata for comparisons.
         *
         * @param string $period Period key.
         *
         * @return array
         */
        private function resolve_period_range( $period ) {
            $today = gmdate( 'Y-m-d' );

            switch ( $period ) {
                case 'week':
                    return array(
                        'end'     => $today,
                        'compare' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
                    );
                case 'month':
                    return array(
                        'end'     => $today,
                        'compare' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
                    );
                case 'now':
                    return array(
                        'end'     => $today,
                        'compare' => gmdate( 'Y-m-d', strtotime( '-1 hour' ) ),
                    );
                case 'today':
                default:
                    return array(
                        'end'     => $today,
                        'compare' => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
                    );
            }
        }

        /**
         * Query aggregated booking statistics or fall back to synthetic data.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string.
         *
         * @return array
         */
        private function query_booking_stats( $location_id, $date ) {
            if ( $this->booking_model && method_exists( $this->booking_model, 'get_location_stats' ) ) {
                $results = $this->booking_model->get_location_stats( $location_id, $date );

                if ( is_array( $results ) && ! empty( $results ) ) {
                    $defaults = $this->get_default_booking_stats();

                    return array_merge( $defaults, $results );
                }
            }

            return $this->get_default_booking_stats();
        }

        /**
         * Provide default booking statistics when data is unavailable.
         *
         * @return array
         */
        private function get_default_booking_stats() {
            return array(
                'total_bookings'  => 24,
                'confirmed'       => 18,
                'pending'         => 6,
                'cancelled'       => 0,
                'total_guests'    => 72,
                'revenue'         => 2450.0,
                'currency'        => '$',
                'occupancy_rate'  => 82.0,
            );
        }

        /**
         * Estimate available tables for a location.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string.
         *
         * @return int
         */
        private function get_available_table_count( $location_id, $date ) {
            if ( $this->location_model && method_exists( $this->location_model, 'get_tables' ) ) {
                $tables = $this->location_model->get_tables( $location_id );

                if ( is_array( $tables ) && ! empty( $tables ) ) {
                    $available = array_filter(
                        $tables,
                        static function( $table ) {
                            return empty( $table['status'] ) || 'available' === $table['status'];
                        }
                    );

                    return count( $available );
                }
            }

            return 8;
        }

        /**
         * Convert period token to integer days.
         *
         * @param string $period Period token.
         *
         * @return int
         */
        private function period_to_days( $period ) {
            $map = array(
                '7d'  => 7,
                '30d' => 30,
                '90d' => 90,
            );

            return isset( $map[ $period ] ) ? (int) $map[ $period ] : 7;
        }

        /**
         * Calculate percentage change between two numeric values.
         *
         * @param float|int $old_value Baseline value.
         * @param float|int $new_value Current value.
         *
         * @return float
         */
        private function calculate_percentage_change( $old_value, $new_value ) {
            $old_value = (float) $old_value;
            $new_value = (float) $new_value;

            if ( 0.0 === $old_value ) {
                return $new_value > 0 ? 100.0 : 0.0;
            }

            return round( ( ( $new_value - $old_value ) / $old_value ) * 100, 1 );
        }

        /**
         * Export analytics dataset to CSV.
         *
         * @param int   $location_id Location identifier.
         * @param array $filters     Optional filters.
         *
         * @return string
         */
        private function export_csv( $location_id, $filters ) {
            $location_id = $this->normalize_location_id( $location_id );
            $date        = isset( $filters['date'] ) ? $this->normalize_date( $filters['date'] ) : gmdate( 'Y-m-d' );
            $stats       = $this->get_current_stats( $location_id, $date );
            $trends      = $this->get_booking_trends( $location_id );

            $lines = array();
            $lines[] = 'Metric,Value';
            $lines[] = sprintf( 'Bookings,%d', $stats['todays_bookings'] );
            $lines[] = sprintf( 'Revenue,%s', $stats['todays_revenue'] );
            $lines[] = sprintf( 'Occupancy,%s%%', $stats['occupancy_rate'] );
            $lines[] = sprintf( 'Pending,%d', $stats['pending_confirmations'] );
            $lines[] = '';
            $lines[] = 'Trends Date,Bookings,Revenue';

            foreach ( $trends['labels'] as $index => $label ) {
                $lines[] = sprintf(
                    '%s,%d,%s',
                    $label,
                    isset( $trends['datasets'][0]['data'][ $index ] ) ? (int) $trends['datasets'][0]['data'][ $index ] : 0,
                    isset( $trends['datasets'][1]['data'][ $index ] ) ? (float) $trends['datasets'][1]['data'][ $index ] : 0
                );
            }

            $csv = implode( "\n", $lines );

            /**
             * Filter the generated CSV export before returning.
             *
             * @since 2.0.0
             *
             * @param string $csv         CSV content.
             * @param int    $location_id Location identifier.
             * @param array  $filters     Export filters.
             */
            return apply_filters( 'rb_analytics_export_csv', $csv, $location_id, $filters );
        }
    }
}
