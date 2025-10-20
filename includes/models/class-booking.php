<?php
/**
 * Booking model placeholder implementation.
 */

if ( ! class_exists( 'RB_Booking' ) ) {

    class RB_Booking {

        protected $id;

        public function __construct( $id = 0 ) {
            $this->id = absint( $id );
        }

        public static function get_admin_bookings( $location = '', $status = '', $page = 1 ) {
            return array(
                'items'      => array(),
                'pagination' => array(
                    'current_page' => (int) $page,
                    'total_pages'  => 0,
                    'total_items'  => 0,
                ),
            );
        }

        public static function count_by_date_and_location( $date, $location_id ) {
            return 0;
        }

        public static function sum_revenue_by_date_and_location( $date, $location_id ) {
            return 0;
        }

        public static function get_recent_for_portal( $location_id, $limit = 5 ) {
            return array();
        }

        public static function query( $args = array() ) {
            return array(
                'items' => array(),
                'total' => 0,
            );
        }

        public function update_status( $status ) {
            return true;
        }

        public function delete() {
            return true;
        }
    }
}
