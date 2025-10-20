<?php
/**
 * Location model placeholder.
 */

if ( ! class_exists( 'RB_Location' ) ) {

    class RB_Location {

        public $id = 0;
        public $name = '';

        public function __construct( $id = 0, $name = '' ) {
            $this->id   = absint( $id );
            $this->name = $name;
        }

        public static function get_all_locations() {
            return array(
                new self( 1, __( 'Downtown', 'restaurant-booking' ) ),
            );
        }
    }
}
