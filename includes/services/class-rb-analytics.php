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
            if ( class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'instance' ) ) {
                $this->booking_model = RB_Booking::instance();
            } elseif ( class_exists( 'RB_Booking' ) ) {
                $this->booking_model = new RB_Booking();
            }

            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'instance' ) ) {
                $this->location_model = RB_Location::instance();
            } elseif ( class_exists( 'RB_Location' ) ) {
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
                'stats'    => $this->get_dashboard_stats( $location_id, $date ),
                'trends'   => $this->get_booking_trends( $location_id, '7d', $date ),
                'schedule' => $this->get_todays_schedule( $location_id, $date ),
                'alerts'   => $this->get_dashboard_alerts( $location_id, $date ),
            );
        }

        /**
         * Get dashboard headline statistics for a specific date.
         *
         * @param int         $location_id Location identifier.
         * @param string|null $date        Date to evaluate.
         *
         * @return array
         */
        public function get_dashboard_stats( $location_id, $date = null ) {
            $location_id = $this->normalize_location_id( $location_id );
            $date        = $this->normalize_date( $date );

            $current_stats = $this->query_daily_stats( $location_id, $date );
            $previous_date = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
            $previous      = $this->query_daily_stats( $location_id, $previous_date );

            return array(
                'todays_bookings'       => (int) $current_stats['total_bookings'],
                'todays_revenue'        => (float) $current_stats['revenue'],
                'currency'              => $current_stats['currency'],
                'occupancy_rate'        => (float) $current_stats['occupancy_rate'],
                'available_tables'      => $this->get_available_table_count( $location_id, $date ),
                'pending_confirmations' => (int) $current_stats['pending'],
                'comparison'            => array(
                    'bookings_change' => $this->calculate_percentage_change( $previous['total_bookings'], $current_stats['total_bookings'] ),
                    'revenue_change'  => $this->calculate_percentage_change( $previous['revenue'], $current_stats['revenue'] ),
                    'trend'           => $current_stats['total_bookings'] >= $previous['total_bookings'] ? 'up' : 'down',
                ),
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

            $range      = $this->resolve_period_range( $period );
            $current    = $this->aggregate_range_stats( $location_id, $range['start'], $range['end'] );
            $comparison = $this->aggregate_range_stats( $location_id, $range['compare_start'], $range['compare_end'] );

            return array(
                'bookings_count'   => (int) $current['total_bookings'],
                'bookings_change'  => $this->calculate_percentage_change( $comparison['total_bookings'], $current['total_bookings'] ),
                'revenue_amount'   => (float) $current['revenue'],
                'revenue_change'   => $this->calculate_percentage_change( $comparison['revenue'], $current['revenue'] ),
                'revenue_currency' => $current['currency'],
                'occupancy_rate'   => (float) $current['occupancy_rate'],
                'occupancy_change' => $this->calculate_percentage_change( $comparison['occupancy_rate'], $current['occupancy_rate'] ),
                'pending_count'    => (int) $current['pending'],
                'pending_change'   => $this->calculate_percentage_change( $comparison['pending'], $current['pending'] ),
                'pending_badge'    => $current['pending'] > 0 ? __( 'Review Required', 'restaurant-booking' ) : '',
            );
        }

        /**
         * Generate booking trend data for Chart.js visualisations.
         *
         * @param int         $location_id Location identifier.
         * @param string      $period      Period key (7d, 30d, 90d).
         * @param string|null $end_date    Optional end date.
         *
         * @return array
         */
        public function get_booking_trends( $location_id, $period = '7d', $end_date = null ) {
            $location_id = $this->normalize_location_id( $location_id );
            $days        = $this->period_to_days( $period );
            $series      = $this->build_daily_series( $location_id, $days, $end_date );

            return array(
                'labels'   => wp_list_pluck( $series, 'label' ),
                'datasets' => array(
                    array(
                        'label'           => __( 'Bookings', 'restaurant-booking' ),
                        'data'            => wp_list_pluck( $series, 'total_bookings' ),
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
         * Provide dashboard chart data structure used by AJAX endpoint.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Period key.
         *
         * @return array
         */
        public function get_chart_data( $location_id, $period = '7d' ) {
            $location_id = $this->normalize_location_id( $location_id );
            $days        = $this->period_to_days( $period );
            $series      = $this->build_daily_series( $location_id, $days );

            return array(
                'bookingTrends' => array(
                    'labels'    => wp_list_pluck( $series, 'label' ),
                    'total'     => wp_list_pluck( $series, 'total_bookings' ),
                    'confirmed' => wp_list_pluck( $series, 'confirmed' ),
                    'pending'   => wp_list_pluck( $series, 'pending' ),
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

            if ( ! $this->booking_model || ! method_exists( $this->booking_model, 'get_todays_bookings' ) ) {
                return array();
            }

            $bookings = $this->booking_model->get_todays_bookings( $location_id, $date );
            $schedule = array();

            foreach ( (array) $bookings as $booking ) {
                $schedule[] = array(
                    'id'            => isset( $booking->id ) ? (int) $booking->id : 0,
                    'customer_name' => isset( $booking->customer_name ) ? $booking->customer_name : '',
                    'booking_time'  => isset( $booking->booking_time ) ? $booking->booking_time : '',
                    'party_size'    => isset( $booking->party_size ) ? (int) $booking->party_size : 0,
                    'table_number'  => isset( $booking->table_number ) ? $booking->table_number : '',
                    'status'        => isset( $booking->status ) ? $booking->status : '',
                    'total_amount'  => isset( $booking->total_amount ) ? (float) $booking->total_amount : 0.0,
                    'notes'         => isset( $booking->special_requests ) ? $booking->special_requests : '',
                );
            }

            return $schedule;
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
            $stats       = $this->query_daily_stats( $location_id, $date );

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

            if ( $stats['occupancy_rate'] >= 90 ) {
                $alerts[] = array(
                    'type'       => 'info',
                    'message'    => __( 'High occupancy expected today. Consider enabling the waitlist.', 'restaurant-booking' ),
                    'action_url' => '',
                );
            }

            if ( 0 === $this->get_available_table_count( $location_id, $date ) ) {
                $alerts[] = array(
                    'type'       => 'info',
                    'message'    => __( 'All tables are allocated for today. Review schedule for possible adjustments.', 'restaurant-booking' ),
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
            $valid  = array( 'today', 'week', 'month', 'now' );
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
            $end = gmdate( 'Y-m-d' );

            switch ( $period ) {
                case 'week':
                    return array(
                        'start'         => gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $end ) ) ),
                        'end'           => $end,
                        'compare_start' => gmdate( 'Y-m-d', strtotime( '-13 days', strtotime( $end ) ) ),
                        'compare_end'   => gmdate( 'Y-m-d', strtotime( '-7 days', strtotime( $end ) ) ),
                    );
                case 'month':
                    return array(
                        'start'         => gmdate( 'Y-m-d', strtotime( '-29 days', strtotime( $end ) ) ),
                        'end'           => $end,
                        'compare_start' => gmdate( 'Y-m-d', strtotime( '-59 days', strtotime( $end ) ) ),
                        'compare_end'   => gmdate( 'Y-m-d', strtotime( '-30 days', strtotime( $end ) ) ),
                    );
                case 'now':
                case 'today':
                default:
                    return array(
                        'start'         => $end,
                        'end'           => $end,
                        'compare_start' => gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $end ) ) ),
                        'compare_end'   => gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $end ) ) ),
                    );
            }
        }

        /**
         * Aggregate stats across a date range.
         *
         * @param int    $location_id Location identifier.
         * @param string $start       Range start (Y-m-d).
         * @param string $end         Range end (Y-m-d).
         *
         * @return array
         */
        private function aggregate_range_stats( $location_id, $start, $end ) {
            $start_ts = strtotime( $start );
            $end_ts   = strtotime( $end );

            if ( false === $start_ts || false === $end_ts || $start_ts > $end_ts ) {
                return $this->empty_daily_stats();
            }

            $aggregate = $this->empty_daily_stats();
            $days      = 0;

            for ( $ts = $start_ts; $ts <= $end_ts; $ts += DAY_IN_SECONDS ) {
                $date  = gmdate( 'Y-m-d', $ts );
                $stats = $this->query_daily_stats( $location_id, $date );

                $aggregate['total_bookings'] += (int) $stats['total_bookings'];
                $aggregate['confirmed']      += (int) $stats['confirmed'];
                $aggregate['pending']        += (int) $stats['pending'];
                $aggregate['cancelled']      += (int) $stats['cancelled'];
                $aggregate['total_guests']   += (int) $stats['total_guests'];
                $aggregate['revenue']        += (float) $stats['revenue'];
                $aggregate['currency']        = $stats['currency'];

                $aggregate['occupancy_rate'] += (float) $stats['occupancy_rate'];
                $days++;
            }

            if ( $days > 0 ) {
                $aggregate['occupancy_rate'] = round( $aggregate['occupancy_rate'] / $days, 1 );
            }

            return $aggregate;
        }

        /**
         * Build a daily statistics series for chart rendering.
         *
         * @param int         $location_id Location identifier.
         * @param int         $days        Number of days.
         * @param string|null $end_date    Optional end date.
         *
         * @return array<int,array>
         */
        private function build_daily_series( $location_id, $days, $end_date = null ) {
            $end_date = $this->normalize_date( $end_date );
            $series   = array();

            for ( $offset = $days - 1; $offset >= 0; $offset-- ) {
                $date  = gmdate( 'Y-m-d', strtotime( '-' . $offset . ' days', strtotime( $end_date ) ) );
                $stats = $this->query_daily_stats( $location_id, $date );

                $series[] = array(
                    'date'           => $date,
                    'label'          => gmdate( 'M j', strtotime( $date ) ),
                    'total_bookings' => (int) $stats['total_bookings'],
                    'confirmed'      => (int) $stats['confirmed'],
                    'pending'        => (int) $stats['pending'],
                    'revenue'        => (float) $stats['revenue'],
                    'guests'         => (int) $stats['total_guests'],
                );
            }

            return $series;
        }

        /**
         * Query aggregated booking statistics for a specific date.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date string.
         *
         * @return array
         */
        private function query_daily_stats( $location_id, $date ) {
            if ( $this->booking_model && method_exists( $this->booking_model, 'get_location_stats' ) ) {
                $results = $this->booking_model->get_location_stats( $location_id, $date );

                if ( is_array( $results ) && ! empty( $results ) ) {
                    return array_merge( $this->empty_daily_stats(), $results );
                }
            }

            return $this->empty_daily_stats();
        }

        /**
         * Provide default booking statistics when data is unavailable.
         *
         * @return array
         */
        private function empty_daily_stats() {
            return array(
                'total_bookings' => 0,
                'confirmed'      => 0,
                'pending'        => 0,
                'cancelled'      => 0,
                'total_guests'   => 0,
                'revenue'        => 0.0,
                'currency'       => class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'get_currency_symbol' ) ? RB_Booking::get_currency_symbol() : '$',
                'occupancy_rate' => 0.0,
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
                            return ! isset( $table['status'] ) || 'available' === $table['status'];
                        }
                    );

                    return count( $available );
                }
            }

            return 0;
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
            $stats       = $this->get_dashboard_stats( $location_id, $date );
            $trends      = $this->get_chart_data( $location_id );

            $lines   = array();
            $lines[] = 'Metric,Value';
            $lines[] = sprintf( 'Bookings,%d', $stats['todays_bookings'] );
            $lines[] = sprintf( 'Revenue,%s%0.2f', $stats['currency'], $stats['todays_revenue'] );
            $lines[] = sprintf( 'Occupancy,%s%%', $stats['occupancy_rate'] );
            $lines[] = sprintf( 'Pending,%d', $stats['pending_confirmations'] );
            $lines[] = '';
            $lines[] = 'Date,Total Bookings,Confirmed,Pending';

            $trend_data = isset( $trends['bookingTrends'] ) ? $trends['bookingTrends'] : array();
            $labels     = isset( $trend_data['labels'] ) ? $trend_data['labels'] : array();
            $total      = isset( $trend_data['total'] ) ? $trend_data['total'] : array();
            $confirmed  = isset( $trend_data['confirmed'] ) ? $trend_data['confirmed'] : array();
            $pending    = isset( $trend_data['pending'] ) ? $trend_data['pending'] : array();

            foreach ( $labels as $index => $label ) {
                $lines[] = sprintf(
                    '%s,%d,%d,%d',
                    $label,
                    isset( $total[ $index ] ) ? (int) $total[ $index ] : 0,
                    isset( $confirmed[ $index ] ) ? (int) $confirmed[ $index ] : 0,
                    isset( $pending[ $index ] ) ? (int) $pending[ $index ] : 0
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
