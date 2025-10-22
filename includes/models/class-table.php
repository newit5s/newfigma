<?php
/**
 * Table model implementation.
 *
 * @package RestaurantBooking\Models
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Table' ) ) {

    /**
     * Table data helpers.
     */
    class RB_Table {

        /**
         * Return table occupancy rate for a location.
         *
         * @param int         $location_id Location identifier.
         * @param string|null $date        Target date (Y-m-d).
         *
         * @return float
         */
        public static function get_occupancy_rate( $location_id, $date = null ) {
            $location_id = absint( $location_id );

            if ( $location_id <= 0 ) {
                return 0.0;
            }

            $total_tables = self::count_by_location( $location_id );
            if ( $total_tables <= 0 ) {
                return 0.0;
            }

            $date     = self::normalize_date( $date );
            $occupied = array();

            if ( class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'get_bookings_by_date' ) ) {
                $bookings = RB_Booking::get_bookings_by_date( $location_id, $date );

                foreach ( $bookings as $booking ) {
                    $table_id = 0;

                    if ( isset( $booking['table_id'] ) && $booking['table_id'] ) {
                        $table_id = (int) $booking['table_id'];
                    } elseif ( isset( $booking['table_number'] ) && $booking['table_number'] ) {
                        $table_id = absint( preg_replace( '/[^0-9]/', '', (string) $booking['table_number'] ) );
                    }

                    if ( $table_id > 0 ) {
                        $occupied[ $table_id ] = true;
                    }
                }
            }

            $occupied_count = count( $occupied );

            return min( 100.0, round( ( $occupied_count / $total_tables ) * 100, 1 ) );
        }

        /**
         * Count total tables for a location.
         *
         * @param int $location_id Location identifier.
         *
         * @return int
         */
        public static function count_by_location( $location_id ) {
            global $wpdb;

            $table = self::get_table_name();

            if ( self::table_exists( $table ) ) {
                $sql = self::prepare(
                    'SELECT COUNT(*) FROM ' . $table . ' WHERE location_id = %d',
                    array( absint( $location_id ) )
                );

                $count = $wpdb->get_var( $sql );

                if ( null !== $count ) {
                    return (int) $count;
                }
            }

            $fallback = self::get_fallback_tables_for_location( $location_id );

            return count( $fallback );
        }

        /**
         * Retrieve table layout meta for a location.
         *
         * @param int $location_id Location identifier.
         *
         * @return array
         */
        public static function get_layout( $location_id ) {
            $tables = self::get_tables_by_location( $location_id );

            $layout = array();

            if ( $tables ) {
                foreach ( $tables as $table ) {
                    $layout[] = array(
                        'id'         => isset( $table->id ) ? (int) $table->id : 0,
                        'number'     => isset( $table->table_number ) ? $table->table_number : '',
                        'capacity'   => isset( $table->capacity ) ? (int) $table->capacity : 0,
                        'status'     => isset( $table->status ) ? $table->status : 'available',
                        'position_x' => isset( $table->position_x ) ? (int) $table->position_x : 0,
                        'position_y' => isset( $table->position_y ) ? (int) $table->position_y : 0,
                    );
                }
            }

            return $layout;
        }

        /**
         * Retrieve tables for a location.
         *
         * @param int $location_id Location identifier.
         *
         * @return array
         */
        public static function get_tables( $location_id ) {
            return self::get_tables_by_location( $location_id );
        }

        /**
         * Retrieve tables for a location.
         *
         * @param int $location_id Location identifier.
         *
         * @return array
         */
        public static function get_tables_by_location( $location_id ) {
            global $wpdb;

            $table = self::get_table_name();

            if ( self::table_exists( $table ) ) {
                $sql = self::prepare(
                    'SELECT id, location_id, table_number, capacity, status, position_x, position_y FROM ' . $table . ' WHERE location_id = %d ORDER BY table_number ASC',
                    array( absint( $location_id ) )
                );

                $records = $wpdb->get_results( $sql );

                if ( $records ) {
                    return $records;
                }
            }

            return self::get_fallback_tables_for_location( $location_id );
        }

        /**
         * Retrieve fallback tables for a location when no database table exists.
         *
         * @param int $location_id Location identifier.
         *
         * @return array
         */
        protected static function get_fallback_tables_for_location( $location_id ) {
            $map = self::get_fallback_table_map();

            if ( isset( $map[ $location_id ] ) ) {
                return $map[ $location_id ];
            }

            // Provide at least one generic table so occupancy calculations have context.
            return $map[0];
        }

        /**
         * Build fallback table dataset shared across locations.
         *
         * @return array
         */
        protected static function get_fallback_table_map() {
            static $fallback = null;

            if ( null !== $fallback ) {
                return $fallback;
            }

            $fallback = array(
                0 => array(
                    self::to_object( array(
                        'id'          => 901,
                        'location_id' => 0,
                        'table_number'=> 'T1',
                        'capacity'    => 4,
                        'status'      => 'available',
                        'position_x'  => 120,
                        'position_y'  => 80,
                    ) ),
                ),
                1 => array(
                    self::to_object( array(
                        'id'          => 101,
                        'location_id' => 1,
                        'table_number'=> 'T1',
                        'capacity'    => 4,
                        'status'      => 'available',
                        'position_x'  => 120,
                        'position_y'  => 80,
                    ) ),
                    self::to_object( array(
                        'id'          => 102,
                        'location_id' => 1,
                        'table_number'=> 'T2',
                        'capacity'    => 2,
                        'status'      => 'occupied',
                        'position_x'  => 240,
                        'position_y'  => 120,
                    ) ),
                    self::to_object( array(
                        'id'          => 103,
                        'location_id' => 1,
                        'table_number'=> 'T3',
                        'capacity'    => 6,
                        'status'      => 'reserved',
                        'position_x'  => 360,
                        'position_y'  => 200,
                    ) ),
                    self::to_object( array(
                        'id'          => 104,
                        'location_id' => 1,
                        'table_number'=> 'T4',
                        'capacity'    => 8,
                        'status'      => 'available',
                        'position_x'  => 480,
                        'position_y'  => 260,
                    ) ),
                ),
                2 => array(
                    self::to_object( array(
                        'id'          => 201,
                        'location_id' => 2,
                        'table_number'=> 'R1',
                        'capacity'    => 4,
                        'status'      => 'available',
                        'position_x'  => 140,
                        'position_y'  => 90,
                    ) ),
                    self::to_object( array(
                        'id'          => 202,
                        'location_id' => 2,
                        'table_number'=> 'R2',
                        'capacity'    => 2,
                        'status'      => 'available',
                        'position_x'  => 300,
                        'position_y'  => 140,
                    ) ),
                    self::to_object( array(
                        'id'          => 203,
                        'location_id' => 2,
                        'table_number'=> 'Lounge',
                        'capacity'    => 6,
                        'status'      => 'occupied',
                        'position_x'  => 420,
                        'position_y'  => 220,
                    ) ),
                ),
                3 => array(
                    self::to_object( array(
                        'id'          => 301,
                        'location_id' => 3,
                        'table_number'=> 'Loft A',
                        'capacity'    => 10,
                        'status'      => 'available',
                        'position_x'  => 160,
                        'position_y'  => 110,
                    ) ),
                    self::to_object( array(
                        'id'          => 302,
                        'location_id' => 3,
                        'table_number'=> 'Loft B',
                        'capacity'    => 8,
                        'status'      => 'reserved',
                        'position_x'  => 320,
                        'position_y'  => 210,
                    ) ),
                ),
            );

            /**
             * Allow modification of the fallback table dataset.
             *
             * @since 2.0.0
             *
             * @param array $fallback Fallback table map keyed by location identifier.
             */
            $fallback = apply_filters( 'rb_fallback_tables', $fallback );

            return $fallback;
        }

        /**
         * Convert array data into a consistent object representation.
         *
         * @param array $table Table data.
         *
         * @return stdClass
         */
        protected static function to_object( $table ) {
            $defaults = array(
                'id'           => 0,
                'location_id'  => 0,
                'table_number' => '',
                'capacity'     => 0,
                'status'       => 'available',
                'position_x'   => 0,
                'position_y'   => 0,
            );

            return (object) array_merge( $defaults, (array) $table );
        }

        /**
         * Helper to normalise date strings.
         *
         * @param string|null $date Input date.
         *
         * @return string
         */
        protected static function normalize_date( $date ) {
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
         * Return qualified table name.
         *
         * @return string
         */
        protected static function get_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'rb_tables';
        }

        /**
         * Check if table exists.
         *
         * @param string $table Table name.
         *
         * @return bool
         */
        protected static function table_exists( $table ) {
            global $wpdb;

            $query = self::prepare( 'SHOW TABLES LIKE %s', array( $table ) );
            $found = $wpdb->get_var( $query );

            return $found === $table;
        }

        /**
         * Prepare SQL with optional parameters.
         *
         * @param string $sql    SQL statement.
         * @param array  $params Optional parameters.
         *
         * @return string
         */
        protected static function prepare( $sql, $params = array() ) {
            global $wpdb;

            if ( empty( $params ) ) {
                return $sql;
            }

            return $wpdb->prepare( $sql, $params );
        }
    }
}
