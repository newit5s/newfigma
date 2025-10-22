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
         * Option key storing fallback layouts when database tables are unavailable.
         */
        const FALLBACK_LAYOUT_OPTION = 'rb_fallback_tables_layout';

        /**
         * Cached fallback layout dataset.
         *
         * @var array|null
         */
        protected static $fallback_layout_cache = null;

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
                        'shape'      => isset( $table->shape ) ? $table->shape : 'rectangle',
                        'width'      => isset( $table->width ) ? (int) $table->width : 120,
                        'height'     => isset( $table->height ) ? (int) $table->height : 120,
                        'rotation'   => isset( $table->rotation ) ? (int) $table->rotation : 0,
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
                    'SELECT id, location_id, table_number, capacity, status, position_x, position_y, shape, width, height, rotation FROM ' . $table . ' WHERE location_id = %d ORDER BY table_number ASC',
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
         * Create a table record.
         *
         * @param array $data Table payload.
         *
         * @return int Table identifier.
         */
        public static function create_table( $data ) {
            global $wpdb;

            $table_name = self::get_table_name();

            $location_id = isset( $data['location_id'] ) ? $data['location_id'] : ( $data['location'] ?? 0 );
            $normalized  = self::normalize_table_data( $data, $location_id );

            if ( empty( $normalized ) ) {
                return 0;
            }

            if ( self::table_exists( $table_name ) ) {
                $formats = self::get_table_column_formats();
                $now     = current_time( 'mysql', true );

                $insert = array_merge(
                    $normalized,
                    array(
                        'created_at' => $now,
                        'updated_at' => $now,
                    )
                );

                $insert_formats = array();
                foreach ( array_keys( $insert ) as $column ) {
                    if ( isset( $formats[ $column ] ) ) {
                        $insert_formats[] = $formats[ $column ];
                    }
                }

                $result = $wpdb->insert( $table_name, $insert, $insert_formats );

                if ( false === $result ) {
                    return 0;
                }

                self::$fallback_layout_cache = null;

                return (int) $wpdb->insert_id;
            }

            $layouts = self::load_fallback_layouts();
            $id      = self::generate_fallback_table_id( isset( $layouts[ $normalized['location_id'] ] ) ? $layouts[ $normalized['location_id'] ] : array() );

            $normalized['id'] = $id;

            if ( ! isset( $layouts[ $normalized['location_id'] ] ) || ! is_array( $layouts[ $normalized['location_id'] ] ) ) {
                $layouts[ $normalized['location_id'] ] = array();
            }

            $layouts[ $normalized['location_id'] ][] = $normalized;

            self::save_fallback_layouts( $layouts );

            return (int) $id;
        }

        /**
         * Update a table record.
         *
         * @param int   $table_id Table identifier.
         * @param array $data     Table payload.
         *
         * @return bool
         */
        public static function update_table( $table_id, $data ) {
            global $wpdb;

            $table_id = absint( $table_id );

            if ( $table_id <= 0 ) {
                return false;
            }

            $table_name = self::get_table_name();

            $location_id = isset( $data['location_id'] ) ? $data['location_id'] : ( $data['location'] ?? 0 );
            $normalized  = self::normalize_table_data( $data, $location_id );

            if ( empty( $normalized ) ) {
                return false;
            }

            if ( self::table_exists( $table_name ) ) {
                $formats = self::get_table_column_formats();
                $normalized['updated_at'] = current_time( 'mysql', true );

                $update_formats = array();
                foreach ( array_keys( $normalized ) as $column ) {
                    if ( isset( $formats[ $column ] ) ) {
                        $update_formats[] = $formats[ $column ];
                    }
                }

                $result = $wpdb->update(
                    $table_name,
                    $normalized,
                    array( 'id' => $table_id ),
                    $update_formats,
                    array( '%d' )
                );

                self::$fallback_layout_cache = null;

                return false !== $result;
            }

            $layouts = self::load_fallback_layouts();

            foreach ( $layouts as $location => &$tables ) {
                if ( ! is_array( $tables ) ) {
                    continue;
                }

                foreach ( $tables as $index => $table ) {
                    if ( isset( $table['id'] ) && (int) $table['id'] === $table_id ) {
                        $normalized['id']          = $table_id;
                        $normalized['location_id'] = isset( $table['location_id'] ) ? (int) $table['location_id'] : $normalized['location_id'];
                        $tables[ $index ]          = $normalized;
                        self::save_fallback_layouts( $layouts );

                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Delete a table record.
         *
         * @param int $table_id Table identifier.
         *
         * @return bool
         */
        public static function delete_table( $table_id ) {
            global $wpdb;

            $table_id = absint( $table_id );

            if ( $table_id <= 0 ) {
                return false;
            }

            $table = self::get_table_name();

            if ( self::table_exists( $table ) ) {
                $deleted = $wpdb->delete( $table, array( 'id' => $table_id ), array( '%d' ) );

                if ( false === $deleted ) {
                    return false;
                }

                self::$fallback_layout_cache = null;

                return true;
            }

            $layouts = self::load_fallback_layouts();

            foreach ( $layouts as $location => &$tables ) {
                if ( ! is_array( $tables ) ) {
                    continue;
                }

                foreach ( $tables as $index => $table ) {
                    if ( isset( $table['id'] ) && (int) $table['id'] === $table_id ) {
                        unset( $tables[ $index ] );
                        $tables = array_values( $tables );
                        self::save_fallback_layouts( $layouts );

                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Remove all tables for a location.
         *
         * @param int $location_id Location identifier.
         */
        public static function delete_tables_by_location( $location_id ) {
            global $wpdb;

            $location_id = absint( $location_id );

            if ( $location_id <= 0 ) {
                return;
            }

            $table = self::get_table_name();

            if ( self::table_exists( $table ) ) {
                $wpdb->delete( $table, array( 'location_id' => $location_id ), array( '%d' ) );
                self::$fallback_layout_cache = null;

                return;
            }

            $layouts = self::load_fallback_layouts();

            if ( isset( $layouts[ $location_id ] ) ) {
                unset( $layouts[ $location_id ] );
                self::save_fallback_layouts( $layouts );
            }
        }

        /**
         * Persist a table layout for a location.
         *
         * @param int   $location_id Location identifier.
         * @param array $tables      Table layout payload.
         *
         * @return bool
         */
        public static function update_table_layout( $location_id, $tables ) {
            $location_id = absint( $location_id );

            if ( $location_id <= 0 || ! is_array( $tables ) ) {
                return false;
            }

            $table_name = self::get_table_name();

            if ( self::table_exists( $table_name ) ) {
                $existing    = self::get_tables_by_location( $location_id );
                $existing_ids = array();

                if ( $existing ) {
                    foreach ( $existing as $record ) {
                        if ( isset( $record->id ) ) {
                            $existing_ids[] = (int) $record->id;
                        }
                    }
                }

                $processed = array();

                foreach ( $tables as $table ) {
                    $normalized = self::normalize_table_data( $table, $location_id );

                    if ( empty( $normalized ) ) {
                        continue;
                    }

                    $table_id = isset( $table['id'] ) ? (int) $table['id'] : 0;

                    if ( $table_id > 0 && in_array( $table_id, $existing_ids, true ) ) {
                        self::update_table( $table_id, $normalized );
                        $processed[] = $table_id;
                    } else {
                        $new_id = self::create_table( $normalized );
                        if ( $new_id ) {
                            $processed[] = $new_id;
                        }
                    }
                }

                $to_delete = array_diff( $existing_ids, $processed );

                foreach ( $to_delete as $delete_id ) {
                    self::delete_table( $delete_id );
                }

                return true;
            }

            $layouts         = self::load_fallback_layouts();
            $normalized_list = array();
            $existing_layout = isset( $layouts[ $location_id ] ) ? $layouts[ $location_id ] : array();
            $existing_lookup = array();

            foreach ( $existing_layout as $entry ) {
                if ( isset( $entry['id'] ) ) {
                    $existing_lookup[ (int) $entry['id'] ] = $entry;
                }
            }

            foreach ( $tables as $table ) {
                $normalized = self::normalize_table_data( $table, $location_id );

                if ( empty( $normalized ) ) {
                    continue;
                }

                $table_id = isset( $table['id'] ) ? (int) $table['id'] : 0;

                if ( $table_id > 0 && isset( $existing_lookup[ $table_id ] ) ) {
                    $normalized['id'] = $table_id;
                } else {
                    $normalized['id'] = self::generate_fallback_table_id( $normalized_list ? array_values( $normalized_list ) : $existing_layout );
                }

                $normalized_list[ $normalized['id'] ] = $normalized;
            }

            $layouts[ $location_id ] = array_values( $normalized_list );
            self::save_fallback_layouts( $layouts );

            return true;
        }

        /**
         * Retrieve fallback tables for a location when no database table exists.
         *
         * @param int $location_id Location identifier.
         *
         * @return array
         */
        protected static function get_fallback_tables_for_location( $location_id ) {
            $layouts     = self::load_fallback_layouts();
            $location_id = absint( $location_id );

            if ( isset( $layouts[ $location_id ] ) ) {
                return self::convert_fallback_tables_to_objects( $layouts[ $location_id ], $location_id );
            }

            $default = self::get_default_fallback_table_map();

            if ( isset( $default[ $location_id ] ) ) {
                return $default[ $location_id ];
            }

            return isset( $default[0] ) ? $default[0] : array();
        }

        /**
         * Build fallback table dataset shared across locations.
         *
         * @return array
         */
        protected static function get_default_fallback_table_map() {
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
                        'shape'       => 'rectangle',
                        'width'       => 120,
                        'height'      => 120,
                        'rotation'    => 0,
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
                        'shape'       => 'rectangle',
                        'width'       => 120,
                        'height'      => 120,
                        'rotation'    => 0,
                    ) ),
                    self::to_object( array(
                        'id'          => 102,
                        'location_id' => 1,
                        'table_number'=> 'T2',
                        'capacity'    => 2,
                        'status'      => 'occupied',
                        'position_x'  => 240,
                        'position_y'  => 120,
                        'shape'       => 'rectangle',
                        'width'       => 110,
                        'height'      => 110,
                        'rotation'    => 0,
                    ) ),
                    self::to_object( array(
                        'id'          => 103,
                        'location_id' => 1,
                        'table_number'=> 'T3',
                        'capacity'    => 6,
                        'status'      => 'reserved',
                        'position_x'  => 360,
                        'position_y'  => 200,
                        'shape'       => 'round',
                        'width'       => 140,
                        'height'      => 140,
                        'rotation'    => 0,
                    ) ),
                    self::to_object( array(
                        'id'          => 104,
                        'location_id' => 1,
                        'table_number'=> 'T4',
                        'capacity'    => 8,
                        'status'      => 'available',
                        'position_x'  => 480,
                        'position_y'  => 260,
                        'shape'       => 'rectangle',
                        'width'       => 160,
                        'height'      => 120,
                        'rotation'    => 0,
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
                        'shape'       => 'rectangle',
                        'width'       => 120,
                        'height'      => 110,
                        'rotation'    => 0,
                    ) ),
                    self::to_object( array(
                        'id'          => 202,
                        'location_id' => 2,
                        'table_number'=> 'R2',
                        'capacity'    => 2,
                        'status'      => 'available',
                        'position_x'  => 300,
                        'position_y'  => 140,
                        'shape'       => 'round',
                        'width'       => 110,
                        'height'      => 110,
                        'rotation'    => 0,
                    ) ),
                    self::to_object( array(
                        'id'          => 203,
                        'location_id' => 2,
                        'table_number'=> 'Lounge',
                        'capacity'    => 6,
                        'status'      => 'occupied',
                        'position_x'  => 420,
                        'position_y'  => 220,
                        'shape'       => 'rectangle',
                        'width'       => 160,
                        'height'      => 120,
                        'rotation'    => 0,
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
                        'shape'       => 'rectangle',
                        'width'       => 200,
                        'height'      => 120,
                        'rotation'    => 0,
                    ) ),
                    self::to_object( array(
                        'id'          => 302,
                        'location_id' => 3,
                        'table_number'=> 'Loft B',
                        'capacity'    => 8,
                        'status'      => 'reserved',
                        'position_x'  => 320,
                        'position_y'  => 210,
                        'shape'       => 'round',
                        'width'       => 150,
                        'height'      => 150,
                        'rotation'    => 0,
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
         * Normalise table payload for persistence.
         *
         * @param array $table       Table payload.
         * @param int   $location_id Location identifier.
         *
         * @return array
         */
        protected static function normalize_table_data( $table, $location_id ) {
            $location_id = absint( $location_id );

            if ( $location_id <= 0 ) {
                return array();
            }

            $table  = (array) $table;
            $number = '';

            if ( isset( $table['table_number'] ) && '' !== $table['table_number'] ) {
                $number = sanitize_text_field( $table['table_number'] );
            } elseif ( isset( $table['label'] ) && '' !== $table['label'] ) {
                $number = sanitize_text_field( $table['label'] );
            } elseif ( isset( $table['name'] ) && '' !== $table['name'] ) {
                $number = sanitize_text_field( $table['name'] );
            }

            if ( '' === $number ) {
                $number = sprintf( 'Table %d-%s', $location_id, substr( uniqid(), -4 ) );
            }

            $shape = isset( $table['shape'] ) ? sanitize_text_field( $table['shape'] ) : 'rectangle';
            $shape = $shape ? $shape : 'rectangle';

            return array(
                'location_id'  => $location_id,
                'table_number' => $number,
                'capacity'     => max( 1, (int) ( $table['capacity'] ?? 0 ) ),
                'status'       => sanitize_key( $table['status'] ?? 'available' ),
                'position_x'   => isset( $table['position_x'] ) ? (int) $table['position_x'] : 0,
                'position_y'   => isset( $table['position_y'] ) ? (int) $table['position_y'] : 0,
                'shape'        => $shape,
                'width'        => isset( $table['width'] ) ? (int) $table['width'] : 120,
                'height'       => isset( $table['height'] ) ? (int) $table['height'] : 120,
                'rotation'     => isset( $table['rotation'] ) ? (int) $table['rotation'] : 0,
            );
        }

        /**
         * Retrieve column formats for database persistence.
         *
         * @return array
         */
        protected static function get_table_column_formats() {
            return array(
                'location_id'  => '%d',
                'table_number' => '%s',
                'capacity'     => '%d',
                'status'       => '%s',
                'position_x'   => '%d',
                'position_y'   => '%d',
                'shape'        => '%s',
                'width'        => '%d',
                'height'       => '%d',
                'rotation'     => '%d',
                'created_at'   => '%s',
                'updated_at'   => '%s',
            );
        }

        /**
         * Load stored fallback layouts.
         *
         * @return array
         */
        protected static function load_fallback_layouts() {
            if ( null !== self::$fallback_layout_cache ) {
                return self::$fallback_layout_cache;
            }

            $stored = get_option( self::FALLBACK_LAYOUT_OPTION, array() );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            self::$fallback_layout_cache = $stored;

            return self::$fallback_layout_cache;
        }

        /**
         * Persist fallback layouts.
         *
         * @param array $layouts Layout dataset.
         */
        protected static function save_fallback_layouts( $layouts ) {
            self::$fallback_layout_cache = $layouts;
            update_option( self::FALLBACK_LAYOUT_OPTION, $layouts, false );
        }

        /**
         * Generate a fallback table identifier.
         *
         * @param array $tables Existing tables for the location.
         *
         * @return int
         */
        protected static function generate_fallback_table_id( $tables ) {
            if ( empty( $tables ) ) {
                return 1;
            }

            $ids = array();

            foreach ( $tables as $table ) {
                if ( isset( $table['id'] ) ) {
                    $ids[] = (int) $table['id'];
                }
            }

            if ( empty( $ids ) ) {
                return 1;
            }

            return max( $ids ) + 1;
        }

        /**
         * Convert fallback array data to objects.
         *
         * @param array $tables      Table arrays.
         * @param int   $location_id Location identifier.
         *
         * @return array
         */
        protected static function convert_fallback_tables_to_objects( $tables, $location_id ) {
            $objects = array();

            foreach ( (array) $tables as $table ) {
                if ( ! isset( $table['location_id'] ) ) {
                    $table['location_id'] = $location_id;
                }

                $objects[] = self::to_object( $table );
            }

            if ( empty( $objects ) ) {
                $default = self::get_default_fallback_table_map();

                if ( isset( $default[ $location_id ] ) ) {
                    return $default[ $location_id ];
                }

                return isset( $default[0] ) ? $default[0] : array();
            }

            return $objects;
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
                'shape'        => 'rectangle',
                'width'        => 120,
                'height'       => 120,
                'rotation'     => 0,
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

            $table = trim( $table );

            if ( '' === $table ) {
                return false;
            }

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
