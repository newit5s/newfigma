<?php
/**
 * Analytics service placeholder.
 */

if ( ! class_exists( 'RB_Analytics_Service' ) ) {

    class RB_Analytics_Service {

        public function get_dashboard_metrics( $location_id = 0 ) {
            return array(
                'bookings'  => 0,
                'revenue'   => 0,
                'occupancy' => 0,
            );
        }
    }
}
