<?php
/**
 * Notification service placeholder.
 */

if ( ! class_exists( 'RB_Notification_Service' ) ) {

    class RB_Notification_Service {

        public function send_booking_confirmation( $booking_id ) {
            return true;
        }

        public function send_booking_reminder( $booking_id ) {
            return true;
        }
    }
}
