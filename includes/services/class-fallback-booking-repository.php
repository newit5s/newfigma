<?php
/**
 * Fallback booking repository used when custom database tables
 * are unavailable. Stores booking data in a WordPress option so
 * the front-end booking form and management dashboard remain
 * functional in demo environments.
 *
 * @package RestaurantBooking\Services
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Fallback_Booking_Repository' ) ) {

    class RB_Fallback_Booking_Repository {

        /**
         * Option key storing fallback bookings.
         */
        const OPTION_KEY = 'rb_fallback_bookings';

        /**
         * Option key storing incremental identifier.
         */
        const INDEX_OPTION_KEY = 'rb_fallback_bookings_index';

        /**
         * Singleton instance.
         *
         * @var self|null
         */
        protected static $instance = null;

        /**
         * Cached bookings array.
         *
         * @var array|null
         */
        protected $cache = null;

        /**
         * Retrieve singleton instance.
         *
         * @return self
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Add a booking record to fallback storage.
         *
         * @param array $data Booking payload.
         *
         * @return array Created booking data.
         */
        public function add_booking( $data ) {
            $bookings = $this->get_all_bookings();

            $id = $this->generate_booking_id();
            $now = current_time( 'mysql' );

            $first_name = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
            $last_name  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
            $full_name  = trim( $first_name . ' ' . $last_name );

            $booking_date = $this->sanitize_date( $data['date'] ?? '' );
            $booking_time = $this->sanitize_time( $data['time'] ?? '' );

            $location_id   = isset( $data['location_id'] ) ? absint( $data['location_id'] ) : 0;
            $location_name = $this->resolve_location_name( $location_id );

            $party_size = isset( $data['party_size'] ) ? max( 1, (int) $data['party_size'] ) : 2;

            $booking = array(
                'id'              => $id,
                'status'          => 'pending',
                'booking_date'    => $booking_date,
                'booking_time'    => $booking_time,
                'party_size'      => $party_size,
                'location_id'     => $location_id,
                'location_name'   => $location_name,
                'customer_name'   => $full_name,
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'customer_email'  => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
                'customer_phone'  => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
                'special_requests'=> isset( $data['special_requests'] ) ? wp_kses_post( $data['special_requests'] ) : '',
                'table_number'    => '',
                'created_at'      => $now,
                'updated_at'      => $now,
                'total_amount'    => 0.0,
                'source'          => 'fallback',
            );

            $bookings[ $id ] = $booking;
            $this->save_bookings( $bookings );

            return $booking;
        }

        /**
         * Retrieve a single fallback booking.
         *
         * @param int $booking_id Booking identifier.
         *
         * @return array|null
         */
        public function get_booking( $booking_id ) {
            $bookings = $this->get_all_bookings();

            $booking_id = absint( $booking_id );

            return isset( $bookings[ $booking_id ] ) ? $bookings[ $booking_id ] : null;
        }

        /**
         * Determine whether a fallback booking exists.
         *
         * @param int $booking_id Booking identifier.
         *
         * @return bool
         */
        public function booking_exists( $booking_id ) {
            return null !== $this->get_booking( $booking_id );
        }

        /**
         * Update booking status for fallback data.
         *
         * @param int    $booking_id Booking identifier.
         * @param string $status     New status.
         *
         * @return bool
         */
        public function update_status( $booking_id, $status ) {
            $status  = sanitize_key( $status );
            $booking = $this->get_booking( $booking_id );

            if ( ! $booking ) {
                return false;
            }

            $bookings = $this->get_all_bookings();

            $booking['status']     = $status ? $status : $booking['status'];
            $booking['updated_at'] = current_time( 'mysql' );

            $bookings[ $booking_id ] = $booking;
            $this->save_bookings( $bookings );

            return true;
        }

        /**
         * Delete a fallback booking.
         *
         * @param int $booking_id Booking identifier.
         *
         * @return bool
         */
        public function delete_booking( $booking_id ) {
            $bookings = $this->get_all_bookings();
            $booking_id = absint( $booking_id );

            if ( ! isset( $bookings[ $booking_id ] ) ) {
                return false;
            }

            unset( $bookings[ $booking_id ] );
            $this->save_bookings( $bookings );

            return true;
        }

        /**
         * Fetch bookings using management style filters.
         *
         * @param array  $filters    Filters array.
         * @param int    $page       Page number.
         * @param int    $page_size  Items per page.
         * @param string $sort_by    Sort field.
         * @param string $sort_order Sort order.
         *
         * @return array
         */
        public function get_bookings( $filters, $page, $page_size, $sort_by, $sort_order ) {
            $records = array_values( $this->filter_bookings( $filters ) );

            $sort_by    = sanitize_key( $sort_by );
            $sort_order = strtolower( sanitize_key( $sort_order ) );
            $sort_order = 'asc' === $sort_order ? 'asc' : 'desc';

            $records = $this->sort_bookings( $records, $sort_by, $sort_order );

            $total      = count( $records );
            $page_size  = max( 1, (int) $page_size );
            $total_page = max( 1, (int) ceil( $total / $page_size ) );
            $page       = max( 1, min( $total_page, (int) $page ) );

            $offset  = ( $page - 1 ) * $page_size;
            $results = array_slice( $records, $offset, $page_size );

            return array(
                'bookings'     => $results,
                'total_items'  => $total,
                'total_pages'  => $total_page,
                'current_page' => $page,
                'page_size'    => $page_size,
                'summary'      => $this->build_summary( $records ),
            );
        }

        /**
         * Retrieve bookings grouped by date for calendar view.
         *
         * @param int   $month   Month value.
         * @param int   $year    Year value.
         * @param array $filters Additional filters.
         *
         * @return array
         */
        public function get_calendar_data( $month, $year, $filters = array() ) {
            $month = max( 1, min( 12, (int) $month ) );
            $year  = max( 1970, (int) $year );

            $start = gmdate( 'Y-m-d', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
            $end   = gmdate( 'Y-m-d', strtotime( $start . ' +1 month -1 day' ) );

            $filters = wp_parse_args(
                $filters,
                array(
                    'date_from' => $start,
                    'date_to'   => $end,
                )
            );

            $records = $this->filter_bookings( $filters );

            $grouped = array();

            foreach ( $records as $booking ) {
                $date_key = $booking['booking_date'];

                if ( ! isset( $grouped[ $date_key ] ) ) {
                    $grouped[ $date_key ] = array(
                        'bookings' => array(),
                        'count'    => 0,
                    );
                }

                $grouped[ $date_key ]['count']++;
                $grouped[ $date_key ]['bookings'][] = array(
                    'id'            => $booking['id'],
                    'customer_name' => $booking['customer_name'],
                    'time'          => $booking['booking_time'],
                    'status'        => $booking['status'],
                    'party_size'    => $booking['party_size'],
                    'table_number'  => $booking['table_number'],
                );
            }

            return $grouped;
        }

        /**
         * Fetch bookings on a specific date.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Date in Y-m-d format.
         *
         * @return array
         */
        public function get_bookings_by_date( $location_id, $date ) {
            $date = $this->sanitize_date( $date );

            $filters = array(
                'location'  => $location_id,
                'date_from' => $date,
                'date_to'   => $date,
            );

            return array_values( $this->filter_bookings( $filters ) );
        }

        /**
         * Count bookings for a date/location combination.
         *
         * @param string $date        Date string.
         * @param int    $location_id Location identifier.
         *
         * @return int
         */
        public function count_by_date_and_location( $date, $location_id ) {
            return count( $this->get_bookings_by_date( $location_id, $date ) );
        }

        /**
         * Count bookings by status.
         *
         * @param string $status      Booking status.
         * @param int    $location_id Optional location identifier.
         *
         * @return int
         */
        public function count_by_status( $status, $location_id = 0 ) {
            $status = sanitize_key( $status );
            $filters = array(
                'status'   => $status,
                'location' => $location_id,
            );

            return count( $this->filter_bookings( $filters ) );
        }

        /**
         * Fetch recent bookings for dashboard widgets.
         *
         * @param int $location_id Location identifier.
         * @param int $limit       Number of records.
         *
         * @return array
         */
        public function get_recent( $location_id, $limit = 5 ) {
            $filters = array(
                'location' => $location_id,
            );

            $records = array_values( $this->filter_bookings( $filters ) );
            $records = $this->sort_bookings( $records, 'booking_datetime', 'desc' );

            return array_slice( $records, 0, max( 1, (int) $limit ) );
        }

        /**
         * Build availability slots for the booking widget.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Target date.
         * @param int    $party_size  Party size.
         *
         * @return array
         */
        public function get_time_slots( $location_id, $date, $party_size = 2 ) {
            $date       = $this->sanitize_date( $date );
            $party_size = max( 1, (int) $party_size );

            $existing = $this->get_bookings_by_date( $location_id, $date );
            $capacity = $this->resolve_location_capacity( $location_id );
            $slot_max = max( 1, (int) floor( $capacity / max( $party_size, 2 ) ) );
            $slot_max = max( $slot_max, 4 );

            $time_format = get_option( 'time_format', 'g:i A' );

            $available_slots    = array();
            $alternative_slots  = array();
            $base_slots         = $this->generate_time_slots( $date );

            foreach ( $base_slots as $slot_time => $timestamp ) {
                $bookings_for_slot = array_filter(
                    $existing,
                    function ( $booking ) use ( $slot_time ) {
                        if ( ! isset( $booking['booking_time'] ) ) {
                            return false;
                        }

                        return $booking['booking_time'] === $slot_time && 'cancelled' !== $booking['status'];
                    }
                );

                $remaining = max( 0, $slot_max - count( $bookings_for_slot ) );
                $label     = wp_date( $time_format, $timestamp );

                $available_slots[] = array(
                    'time'               => $slot_time,
                    'value'              => $slot_time,
                    'label'              => $label,
                    'status'             => $remaining > 0 ? __( 'Available', 'restaurant-booking' ) : __( 'Fully Booked', 'restaurant-booking' ),
                    'available'          => $remaining > 0,
                    'available_capacity' => $remaining,
                );

                if ( $remaining > 0 ) {
                    $alternative_slots[] = array(
                        'time'  => $slot_time,
                        'value' => $slot_time,
                        'label' => $label,
                    );
                }
            }

            $alternative_slots = array_slice( $alternative_slots, 0, 3 );

            return array(
                'date'              => $date,
                'available_slots'   => $available_slots,
                'alternative_slots' => $alternative_slots,
            );
        }

        /**
         * Provide aggregated metrics for a location/date combination.
         *
         * @param int    $location_id Location identifier.
         * @param string $date        Target date.
         *
         * @return array
         */
        public function get_location_stats( $location_id, $date ) {
            $date     = $this->sanitize_date( $date );
            $records  = $this->get_bookings_by_date( $location_id, $date );
            $currency = apply_filters( 'rb_booking_currency', '$', $location_id );

            $totals = array(
                'total_bookings' => 0,
                'confirmed'      => 0,
                'pending'        => 0,
                'cancelled'      => 0,
                'total_guests'   => 0,
                'revenue'        => 0.0,
            );

            foreach ( $records as $booking ) {
                $totals['total_bookings']++;
                $totals['total_guests'] += isset( $booking['party_size'] ) ? (int) $booking['party_size'] : 0;
                $totals['revenue']      += isset( $booking['total_amount'] ) ? (float) $booking['total_amount'] : 0.0;

                $status = isset( $booking['status'] ) ? $booking['status'] : 'pending';
                if ( isset( $totals[ $status ] ) ) {
                    $totals[ $status ]++;
                }
            }

            list( $available_tables, $used_tables, $occupancy ) = $this->calculate_table_metrics( $records, $location_id );

            return array(
                'total_bookings'  => $totals['total_bookings'],
                'confirmed'       => $totals['confirmed'],
                'pending'         => $totals['pending'],
                'cancelled'       => $totals['cancelled'],
                'total_guests'    => $totals['total_guests'],
                'revenue'         => $totals['revenue'],
                'currency'        => $currency,
                'occupancy_rate'  => $occupancy,
                'available_tables'=> $available_tables,
                'used_tables'     => $used_tables,
            );
        }

        /**
         * Retrieve all stored fallback bookings.
         *
         * @return array
         */
        protected function get_all_bookings() {
            if ( null !== $this->cache ) {
                return $this->cache;
            }

            $stored = get_option( self::OPTION_KEY, array() );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            if ( empty( $stored ) ) {
                $stored = $this->seed_demo_data();
            }

            $this->cache = $stored;

            return $this->cache;
        }

        /**
         * Persist fallback bookings and clear cache.
         *
         * @param array $bookings Booking dataset.
         */
        protected function save_bookings( $bookings ) {
            if ( ! is_array( $bookings ) ) {
                $bookings = array();
            }

            $this->cache = $bookings;
            update_option( self::OPTION_KEY, $bookings, false );
        }

        /**
         * Filter stored bookings based on filters.
         *
         * @param array $filters Filters array.
         *
         * @return array
         */
        protected function filter_bookings( $filters ) {
            $bookings = $this->get_all_bookings();

            if ( empty( $bookings ) ) {
                return array();
            }

            $filters = wp_parse_args(
                (array) $filters,
                array(
                    'status'    => '',
                    'location'  => 0,
                    'date_from' => '',
                    'date_to'   => '',
                    'search'    => '',
                )
            );

            $status     = sanitize_key( $filters['status'] );
            $location   = absint( $filters['location'] );
            $date_from  = $filters['date_from'] ? $this->sanitize_date( $filters['date_from'] ) : '';
            $date_to    = $filters['date_to'] ? $this->sanitize_date( $filters['date_to'] ) : '';
            $search_raw = isset( $filters['search'] ) ? wp_strip_all_tags( $filters['search'] ) : '';
            $search     = $search_raw ? strtolower( $search_raw ) : '';

            $result = array_filter(
                $bookings,
                function ( $booking ) use ( $status, $location, $date_from, $date_to, $search ) {
                    if ( $status && ( ! isset( $booking['status'] ) || $booking['status'] !== $status ) ) {
                        return false;
                    }

                    if ( $location && (int) ( $booking['location_id'] ?? 0 ) !== $location ) {
                        return false;
                    }

                    $booking_date = isset( $booking['booking_date'] ) ? $booking['booking_date'] : '';

                    if ( $date_from && $booking_date < $date_from ) {
                        return false;
                    }

                    if ( $date_to && $booking_date > $date_to ) {
                        return false;
                    }

                    if ( $search ) {
                        $haystack = strtolower( implode( ' ', array(
                            $booking['customer_name'] ?? '',
                            $booking['customer_email'] ?? '',
                            $booking['customer_phone'] ?? '',
                        ) ) );

                        if ( false === strpos( $haystack, $search ) ) {
                            return false;
                        }
                    }

                    return true;
                }
            );

            return $result;
        }

        /**
         * Sort bookings dataset.
         *
         * @param array  $records    Booking list.
         * @param string $sort_by    Sort field.
         * @param string $sort_order Sort direction.
         *
         * @return array
         */
        protected function sort_bookings( $records, $sort_by, $sort_order ) {
            if ( empty( $records ) ) {
                return array();
            }

            $sort_by = $sort_by ? $sort_by : 'booking_datetime';

            usort(
                $records,
                function ( $left, $right ) use ( $sort_by, $sort_order ) {
                    $left_value  = $this->resolve_sort_value( $left, $sort_by );
                    $right_value = $this->resolve_sort_value( $right, $sort_by );

                    if ( $left_value === $right_value ) {
                        return 0;
                    }

                    if ( 'asc' === $sort_order ) {
                        return ( $left_value < $right_value ) ? -1 : 1;
                    }

                    return ( $left_value > $right_value ) ? -1 : 1;
                }
            );

            return $records;
        }

        /**
         * Resolve comparison value for sorting.
         *
         * @param array  $booking Booking array.
         * @param string $sort_by Sort key.
         *
         * @return mixed
         */
        protected function resolve_sort_value( $booking, $sort_by ) {
            switch ( $sort_by ) {
                case 'booking_datetime':
                    $date = isset( $booking['booking_date'] ) ? $booking['booking_date'] : '';
                    $time = isset( $booking['booking_time'] ) ? $booking['booking_time'] : '00:00';

                    return strtotime( $date . ' ' . $time );
                case 'party_size':
                    return isset( $booking['party_size'] ) ? (int) $booking['party_size'] : 0;
                case 'customer_name':
                    return strtolower( $booking['customer_name'] ?? '' );
                case 'status':
                    return strtolower( $booking['status'] ?? '' );
                case 'table_number':
                    return strtolower( $booking['table_number'] ?? '' );
                default:
                    return strtolower( $booking[ $sort_by ] ?? '' );
            }
        }

        /**
         * Build summary payload for booking table output.
         *
         * @param array $records Filtered bookings.
         *
         * @return array
         */
        protected function build_summary( $records ) {
            if ( empty( $records ) ) {
                return array(
                    'total_bookings'     => 0,
                    'total_revenue'      => 0.0,
                    'revenue_total'      => 0.0,
                    'total_guests'       => 0,
                    'average_party_size' => 0.0,
                    'bookings_change'    => 0.0,
                    'revenue_change'     => 0.0,
                    'party_size_change'  => 0.0,
                    'pending_total'      => 0,
                    'status_counts'      => array(
                        'pending'   => 0,
                        'confirmed' => 0,
                        'completed' => 0,
                        'cancelled' => 0,
                    ),
                );
            }

            $total         = count( $records );
            $total_guests  = 0;
            $status_counts = array(
                'pending'   => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
            );

            foreach ( $records as $booking ) {
                $total_guests += isset( $booking['party_size'] ) ? (int) $booking['party_size'] : 0;

                $status = isset( $booking['status'] ) ? $booking['status'] : 'pending';
                if ( isset( $status_counts[ $status ] ) ) {
                    $status_counts[ $status ]++;
                }
            }

            return array(
                'total_bookings'     => $total,
                'total_revenue'      => 0.0,
                'revenue_total'      => 0.0,
                'total_guests'       => $total_guests,
                'average_party_size' => $total ? round( $total_guests / $total, 1 ) : 0.0,
                'bookings_change'    => 0.0,
                'revenue_change'     => 0.0,
                'party_size_change'  => 0.0,
                'pending_total'      => $status_counts['pending'],
                'status_counts'      => $status_counts,
            );
        }

        /**
         * Generate 30 minute slots between 17:00 and 21:00.
         *
         * @param string $date Date string.
         *
         * @return array<string,int> Map of slot time to timestamp.
         */
        protected function generate_time_slots( $date ) {
            $slots = array();

            $start = strtotime( $date . ' 17:00:00' );
            $end   = strtotime( $date . ' 21:00:00' );

            if ( ! $start || ! $end ) {
                return $slots;
            }

            for ( $time = $start; $time <= $end; $time += 30 * MINUTE_IN_SECONDS ) {
                $slots[ gmdate( 'H:i', $time ) ] = $time;
            }

            return $slots;
        }

        /**
         * Resolve location display name.
         *
         * @param int $location_id Location identifier.
         *
         * @return string
         */
        protected function resolve_location_name( $location_id ) {
            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_location' ) ) {
                $location = RB_Location::get_location( $location_id );

                if ( $location && isset( $location->name ) && $location->name ) {
                    return $location->name;
                }
            }

            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_all_locations' ) ) {
                $locations = RB_Location::get_all_locations();
                if ( ! empty( $locations ) ) {
                    $first = is_array( $locations ) ? reset( $locations ) : null;
                    if ( $first && isset( $first->name ) && $first->name ) {
                        return $first->name;
                    }
                }
            }

            return __( 'Main Dining Room', 'restaurant-booking' );
        }

        /**
         * Resolve seating capacity for a location.
         *
         * @param int $location_id Location identifier.
         *
         * @return int
         */
        protected function resolve_location_capacity( $location_id ) {
            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_location' ) ) {
                $location = RB_Location::get_location( $location_id );
                if ( $location && isset( $location->capacity ) && $location->capacity ) {
                    return (int) $location->capacity;
                }
            }

            return 48;
        }

        /**
         * Seed demo bookings when persistent storage is empty.
         *
         * @return array
         */
        protected function seed_demo_data() {
            $locations = $this->get_seed_location_names();
            $today     = gmdate( 'Y-m-d' );

            $dataset = array();

            $dataset[1] = $this->build_seed_booking( 1, array(
                'status'        => 'confirmed',
                'date'          => $today,
                'time'          => '18:00',
                'location_id'   => 1,
                'table_id'      => 101,
                'table_number'  => 'T1',
                'party_size'    => 4,
                'total_amount'  => 220.00,
                'first_name'    => 'Ava',
                'last_name'     => 'Carter',
                'email'         => 'ava.carter@example.com',
                'phone'         => '+1 555-0101',
                'special_requests' => __( 'Celebrating anniversary – add candles to dessert.', 'restaurant-booking' ),
            ), $locations );

            $dataset[2] = $this->build_seed_booking( 2, array(
                'status'        => 'pending',
                'date'          => $today,
                'time'          => '20:15',
                'location_id'   => 1,
                'table_id'      => 102,
                'table_number'  => 'T2',
                'party_size'    => 2,
                'total_amount'  => 120.00,
                'first_name'    => 'Sarah',
                'last_name'     => 'Wilson',
                'email'         => 'sarah.wilson@example.com',
                'phone'         => '+1 555-0102',
            ), $locations );

            $dataset[3] = $this->build_seed_booking( 3, array(
                'status'        => 'completed',
                'date'          => gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) ),
                'time'          => '19:30',
                'location_id'   => 1,
                'table_id'      => 103,
                'table_number'  => 'T3',
                'party_size'    => 5,
                'total_amount'  => 310.00,
                'first_name'    => 'Daniel',
                'last_name'     => 'Lee',
                'email'         => 'daniel.lee@example.com',
                'phone'         => '+1 555-0106',
            ), $locations );

            $dataset[4] = $this->build_seed_booking( 4, array(
                'status'        => 'cancelled',
                'date'          => gmdate( 'Y-m-d', strtotime( '-2 days', strtotime( $today ) ) ),
                'time'          => '21:00',
                'location_id'   => 1,
                'table_id'      => 104,
                'table_number'  => 'T4',
                'party_size'    => 6,
                'total_amount'  => 0.0,
                'first_name'    => 'Emily',
                'last_name'     => 'Stone',
                'email'         => 'emily.stone@example.com',
                'phone'         => '+1 555-0105',
            ), $locations );

            $dataset[5] = $this->build_seed_booking( 5, array(
                'status'        => 'confirmed',
                'date'          => $today,
                'time'          => '19:00',
                'location_id'   => 2,
                'table_id'      => 201,
                'table_number'  => 'R1',
                'party_size'    => 3,
                'total_amount'  => 180.00,
                'first_name'    => 'Liam',
                'last_name'     => 'Nguyen',
                'email'         => 'liam.nguyen@example.com',
                'phone'         => '+1 555-0103',
            ), $locations );

            $dataset[6] = $this->build_seed_booking( 6, array(
                'status'        => 'confirmed',
                'date'          => gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $today ) ) ),
                'time'          => '18:30',
                'location_id'   => 2,
                'table_id'      => 202,
                'table_number'  => 'R2',
                'party_size'    => 2,
                'total_amount'  => 140.00,
                'first_name'    => 'Maya',
                'last_name'     => 'Cole',
                'email'         => 'maya.cole@example.com',
                'phone'         => '+1 555-0107',
            ), $locations );

            $dataset[7] = $this->build_seed_booking( 7, array(
                'status'        => 'cancelled',
                'date'          => gmdate( 'Y-m-d', strtotime( '-3 days', strtotime( $today ) ) ),
                'time'          => '20:45',
                'location_id'   => 2,
                'table_id'      => 203,
                'table_number'  => 'Lounge',
                'party_size'    => 4,
                'total_amount'  => 0.0,
                'first_name'    => 'Olivia',
                'last_name'     => 'Martin',
                'email'         => 'olivia.martin@example.com',
                'phone'         => '+1 555-0108',
            ), $locations );

            $dataset[8] = $this->build_seed_booking( 8, array(
                'status'        => 'confirmed',
                'date'          => gmdate( 'Y-m-d', strtotime( '+3 days', strtotime( $today ) ) ),
                'time'          => '19:15',
                'location_id'   => 3,
                'table_id'      => 301,
                'table_number'  => 'Loft A',
                'party_size'    => 10,
                'total_amount'  => 650.00,
                'first_name'    => 'Noah',
                'last_name'     => 'Garcia',
                'email'         => 'noah.garcia@example.com',
                'phone'         => '+1 555-0104',
                'special_requests' => __( 'Corporate tasting menu with projector setup.', 'restaurant-booking' ),
            ), $locations );

            $dataset[9] = $this->build_seed_booking( 9, array(
                'status'        => 'completed',
                'date'          => gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) ),
                'time'          => '18:45',
                'location_id'   => 3,
                'table_id'      => 302,
                'table_number'  => 'Loft B',
                'party_size'    => 8,
                'total_amount'  => 520.00,
                'first_name'    => 'Priya',
                'last_name'     => 'Sharma',
                'email'         => 'priya.sharma@example.com',
                'phone'         => '+1 555-0109',
            ), $locations );

            $dataset[10] = $this->build_seed_booking( 10, array(
                'status'        => 'confirmed',
                'date'          => gmdate( 'Y-m-d', strtotime( '+5 days', strtotime( $today ) ) ),
                'time'          => '17:30',
                'location_id'   => 1,
                'table_id'      => 101,
                'table_number'  => 'T1',
                'party_size'    => 3,
                'total_amount'  => 165.00,
                'first_name'    => 'Grace',
                'last_name'     => 'Bennett',
                'email'         => 'grace.bennett@example.com',
                'phone'         => '+1 555-0110',
            ), $locations );

            $this->save_bookings( $dataset );

            $next_index = max( array_keys( $dataset ) ) + 1;
            update_option( self::INDEX_OPTION_KEY, $next_index, false );

            return $dataset;
        }

        /**
         * Build a seed booking record merging defaults with overrides.
         *
         * @param int   $id         Booking identifier.
         * @param array $overrides  Booking data overrides.
         * @param array $locations  Map of location names keyed by identifier.
         *
         * @return array
         */
        protected function build_seed_booking( $id, $overrides, $locations ) {
            $date = isset( $overrides['date'] ) ? $this->sanitize_date( $overrides['date'] ) : gmdate( 'Y-m-d' );
            $time = isset( $overrides['time'] ) ? $this->sanitize_time( $overrides['time'] ) : '18:00';

            $datetime = $date . ' ' . $time . ':00';
            $location_id = isset( $overrides['location_id'] ) ? absint( $overrides['location_id'] ) : 1;
            $location_name = isset( $overrides['location_name'] )
                ? $overrides['location_name']
                : ( isset( $locations[ $location_id ] ) ? $locations[ $location_id ] : __( 'Main Dining Room', 'restaurant-booking' ) );

            $first_name = isset( $overrides['first_name'] ) ? sanitize_text_field( $overrides['first_name'] ) : '';
            $last_name  = isset( $overrides['last_name'] ) ? sanitize_text_field( $overrides['last_name'] ) : '';
            $full_name  = trim( $first_name . ' ' . $last_name );

            return array(
                'id'              => (int) $id,
                'status'          => isset( $overrides['status'] ) ? sanitize_key( $overrides['status'] ) : 'confirmed',
                'booking_date'    => $date,
                'booking_time'    => $time,
                'booking_datetime'=> $datetime,
                'party_size'      => isset( $overrides['party_size'] ) ? (int) $overrides['party_size'] : 2,
                'location_id'     => $location_id,
                'location_name'   => $location_name,
                'customer_name'   => $full_name ? $full_name : __( 'Guest', 'restaurant-booking' ),
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'customer_email'  => isset( $overrides['email'] ) ? sanitize_email( $overrides['email'] ) : '',
                'customer_phone'  => isset( $overrides['phone'] ) ? sanitize_text_field( $overrides['phone'] ) : '',
                'special_requests'=> isset( $overrides['special_requests'] ) ? wp_kses_post( $overrides['special_requests'] ) : '',
                'table_id'        => isset( $overrides['table_id'] ) ? (int) $overrides['table_id'] : 0,
                'table_number'    => isset( $overrides['table_number'] ) ? $overrides['table_number'] : '',
                'created_at'      => gmdate( 'Y-m-d H:i:s', strtotime( $datetime . ' -6 hours' ) ),
                'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
                'total_amount'    => isset( $overrides['total_amount'] ) ? (float) $overrides['total_amount'] : 0.0,
                'source'          => isset( $overrides['source'] ) ? sanitize_key( $overrides['source'] ) : 'demo',
            );
        }

        /**
         * Resolve location names for seed data.
         *
         * @return array
         */
        protected function get_seed_location_names() {
            $names = array();

            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_all_locations' ) ) {
                $locations = RB_Location::get_all_locations();
                foreach ( (array) $locations as $location ) {
                    if ( isset( $location->id ) && isset( $location->name ) ) {
                        $names[ (int) $location->id ] = $location->name;
                    }
                }
            }

            if ( empty( $names ) ) {
                $names[1] = __( 'Main Dining Room', 'restaurant-booking' );
            }

            return $names;
        }

        /**
         * Calculate table metrics for occupancy and availability.
         *
         * @param array $records     Booking dataset.
         * @param int   $location_id Location identifier.
         *
         * @return array Array containing available tables, used tables, and occupancy percentage.
         */
        protected function calculate_table_metrics( $records, $location_id ) {
            $available_tables = 0;
            $used_tables      = array();

            if ( class_exists( 'RB_Table' ) ) {
                $tables = RB_Table::get_tables_by_location( $location_id );

                if ( $tables instanceof Traversable ) {
                    $tables = iterator_to_array( $tables, false );
                }

                if ( is_array( $tables ) ) {
                    $available_tables = count( $tables );
                }
            }

            foreach ( $records as $booking ) {
                if ( isset( $booking['status'] ) && 'cancelled' === $booking['status'] ) {
                    continue;
                }

                if ( isset( $booking['table_id'] ) && $booking['table_id'] ) {
                    $used_tables[ $booking['table_id'] ] = true;
                } elseif ( ! empty( $booking['table_number'] ) ) {
                    $used_tables[ $booking['table_number'] ] = true;
                }
            }

            $used_count = count( $used_tables );
            $occupancy  = $available_tables > 0 ? min( 100.0, round( ( $used_count / $available_tables ) * 100, 1 ) ) : 0.0;

            return array( $available_tables, $used_count, $occupancy );
        }

        /**
         * Sanitize date string.
         *
         * @param string $date Date string.
         *
         * @return string
         */
        protected function sanitize_date( $date ) {
            if ( ! $date ) {
                return gmdate( 'Y-m-d' );
            }

            $timestamp = strtotime( $date );

            if ( ! $timestamp ) {
                return gmdate( 'Y-m-d' );
            }

            return gmdate( 'Y-m-d', $timestamp );
        }

        /**
         * Sanitize time string.
         *
         * @param string $time Time string.
         *
         * @return string
         */
        protected function sanitize_time( $time ) {
            if ( ! $time ) {
                return '18:00';
            }

            if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
                return $time;
            }

            $timestamp = strtotime( $time );

            if ( ! $timestamp ) {
                return '18:00';
            }

            return gmdate( 'H:i', $timestamp );
        }

        /**
         * Generate incrementing booking identifier.
         *
         * @return int
         */
        protected function generate_booking_id() {
            $next_id = (int) get_option( self::INDEX_OPTION_KEY, 1 );
            $id      = $next_id > 0 ? $next_id : 1;

            update_option( self::INDEX_OPTION_KEY, $id + 1, false );

            return $id;
        }
    }
}
