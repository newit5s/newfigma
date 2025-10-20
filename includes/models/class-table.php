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

            if ( ! self::table_exists( $table ) ) {
                return 0;
            }

            $sql = self::prepare(
                'SELECT COUNT(*) FROM ' . $table . ' WHERE location_id = %d',
                array( absint( $location_id ) )
            );

            return (int) $wpdb->get_var( $sql );
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

            if ( ! self::table_exists( $table ) ) {
                return array();
            }

            $sql = self::prepare(
                'SELECT id, location_id, table_number, capacity, status, position_x, position_y FROM ' . $table . ' WHERE location_id = %d ORDER BY table_number ASC',
                array( absint( $location_id ) )
            );

            $records = $wpdb->get_results( $sql );

            return $records ? $records : array();
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
