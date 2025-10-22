<?php
/**
 * Modern booking management integration (Phase 5)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Modern_Booking_Manager' ) ) {

    class RB_Modern_Booking_Manager {

        use RB_Asset_Loader;

        /**
         * Cached current user context.
         *
         * @var array
         */
        public $current_user = array();

        /**
         * AJAX nonce handle.
         *
         * @var string
         */
        protected $ajax_nonce = 'rb_booking_management_nonce';

        /**
         * Constructor.
         */
        public function __construct() {
            add_action( 'init', array( $this, 'maybe_render_management' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

            add_filter( 'rb_portal_render_view', array( $this, 'render_portal_view' ), 10, 4 );

            add_action( 'wp_ajax_rb_get_bookings_list', array( $this, 'ajax_get_bookings_list' ) );
            add_action( 'wp_ajax_rb_get_calendar_data', array( $this, 'ajax_get_calendar_data' ) );
            add_action( 'wp_ajax_rb_update_booking_status', array( $this, 'ajax_update_booking_status' ) );
            add_action( 'wp_ajax_rb_delete_booking', array( $this, 'ajax_delete_booking' ) );
            add_action( 'wp_ajax_rb_bulk_update_bookings', array( $this, 'ajax_bulk_update_bookings' ) );
            add_action( 'wp_ajax_rb_send_bulk_reminders', array( $this, 'ajax_send_bulk_reminders' ) );
            add_action( 'wp_ajax_rb_export_bookings', array( $this, 'ajax_export_bookings' ) );
        }

        /**
         * Render booking management page when requested via query var.
         */
        public function maybe_render_management() {
            if ( ! $this->is_management_request() ) {
                return;
            }

            $this->current_user = $this->resolve_current_user();

            if ( ! $this->is_user_authenticated() ) {
                $this->redirect_to_portal_login();
                exit;
            }

            $this->enqueue_assets();

            add_filter(
                'pre_get_document_title',
                static function () {
                    return __( 'Booking Management – Restaurant Manager', 'restaurant-booking' );
                }
            );

            $template = plugin_dir_path( __FILE__ ) . 'partials/booking-management.php';
            if ( file_exists( $template ) ) {
                include $template;
            }

            exit;
        }

        /**
         * Enqueue styles and scripts when the management view is active.
         */
        public function enqueue_assets() {
            if ( ! $this->is_management_request() && ! $this->is_embed_context() ) {
                return;
            }

            $this->enqueue_management_assets();
        }

        /**
         * Enqueue assets for booking management views and shortcodes.
         */
        protected function enqueue_management_assets() {
            if ( $this->assets_enqueued ) {
                return;
            }

            $this->enqueue_core_assets( 'booking-management' );
            $this->enqueue_context_css( 'booking-management' );
            $this->enqueue_context_js( 'booking-management' );

            $defaults = array(
                'date_from' => wp_date( 'Y-m-d' ),
                'date_to'   => wp_date( 'Y-m-d', strtotime( '+7 days' ) ),
                'location'  => $this->get_current_location(),
            );

            $this->localize_ajax_data(
                'rb-booking-management',
                'rbBookingManagement',
                array(
                    'current_location' => $this->get_current_location(),
                    'defaults'         => $defaults,
                    'strings'          => array(
                        'loading'               => __( 'Loading bookings…', 'restaurant-booking' ),
                        'error'                 => __( 'Something went wrong. Please try again.', 'restaurant-booking' ),
                        'confirm_delete'        => __( 'Are you sure you want to delete this booking?', 'restaurant-booking' ),
                        'confirm_bulk_cancel'   => __( 'Cancel the selected bookings?', 'restaurant-booking' ),
                        'no_bookings_selected'  => __( 'No bookings selected.', 'restaurant-booking' ),
                        'success_updated'       => __( 'Booking updated successfully.', 'restaurant-booking' ),
                        'success_deleted'       => __( 'Booking deleted.', 'restaurant-booking' ),
                        'reminders_sent'        => __( 'Reminder emails sent successfully.', 'restaurant-booking' ),
                    ),
                ),
                $this->ajax_nonce
            );
        }

        /**
         * Allow embedding the management UI via portal shortcode view.
         *
         * @param string $content Existing content.
         * @param string $view    Requested view identifier.
         * @param array  $atts    Shortcode attributes.
         * @param mixed  $auth    Portal auth instance.
         *
         * @return string
         */
        public function render_portal_view( $content, $view, $atts, $auth ) {
            if ( 'bookings' !== $view ) {
                return $content;
            }

            $this->current_user = $this->resolve_current_user();
            if ( ! $this->is_user_authenticated() ) {
                $login_url = home_url( '/portal/' );

                $message = sprintf(
                    /* translators: %s: URL to the portal login page. */
                    __( 'Please <a href="%s">sign in to the restaurant portal</a> to manage bookings.', 'restaurant-booking' ),
                    esc_url( $login_url )
                );

                return wp_kses(
                    '<div class="rb-alert rb-alert-info">' . $message . '</div>',
                    array(
                        'div' => array( 'class' => array() ),
                        'a'   => array( 'href' => array() ),
                    )
                );
            }

            $this->enqueue_management_assets();

            ob_start();

            $is_embed = true;
            $template = plugin_dir_path( __FILE__ ) . 'partials/booking-management.php';
            if ( file_exists( $template ) ) {
                include $template;
            }

            return ob_get_clean();
        }

        /**
         * Render the booking calendar shortcode.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_calendar_shortcode( $atts ) {
            $atts = shortcode_atts(
                array(
                    'location' => '',
                ),
                $atts,
                'booking_calendar'
            );

            $this->current_user = $this->resolve_current_user();

            if ( ! restaurant_booking_user_can_manage() ) {
                if ( function_exists( 'restaurant_booking_render_permission_notice' ) ) {
                    return restaurant_booking_render_permission_notice( __( 'booking calendar', 'restaurant-booking' ) );
                }

                return '<div class="rb-alert rb-alert-warning">' . esc_html__( 'You do not have permission to view the booking calendar.', 'restaurant-booking' ) . '</div>';
            }

            $location = sanitize_text_field( $atts['location'] );
            if ( '' !== $location ) {
                $this->current_user['location_id'] = $location;
            }

            $this->enqueue_management_assets();

            ob_start();

            echo '<div class="rb-booking-calendar-shortcode" data-rb-booking-calendar="1">';
            $template = plugin_dir_path( __FILE__ ) . 'partials/booking-calendar-view.php';
            if ( file_exists( $template ) ) {
                include $template;
            }
            echo '</div>';

            return ob_get_clean();
        }

        /**
         * AJAX: fetch bookings for table view.
         */
        public function ajax_get_bookings_list() {
            try {
                $this->verify_ajax_request();
                $this->ensure_user_can_manage();

                $page      = max( 1, (int) filter_input( INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT ) );
                $page_size = max( 1, (int) filter_input( INPUT_POST, 'page_size', FILTER_SANITIZE_NUMBER_INT ) );
                $sort_by   = sanitize_text_field( wp_unslash( $_POST['sort_by'] ?? 'booking_datetime' ) );
                $sort_order = sanitize_text_field( wp_unslash( $_POST['sort_order'] ?? 'desc' ) );

                $filters = array(
                    'date_from' => $this->read_filter_value( 'dateFrom', 'date_from' ),
                    'date_to'   => $this->read_filter_value( 'dateTo', 'date_to' ),
                    'status'    => sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ),
                    'location'  => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
                    'search'    => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
                );

                $results = $this->query_bookings( $filters, $page, $page_size, $sort_by, $sort_order );

                $bookings   = isset( $results['bookings'] ) ? $results['bookings'] : array();
                $total      = isset( $results['total_items'] ) ? (int) $results['total_items'] : count( $bookings );
                $total_page = isset( $results['total_pages'] ) ? max( 1, (int) $results['total_pages'] ) : max( 1, (int) ceil( $total / $page_size ) );
                $current    = isset( $results['current_page'] ) ? max( 1, (int) $results['current_page'] ) : $page;

                $formatted = array();
                foreach ( $bookings as $booking ) {
                    $formatted[] = $this->format_booking_record( $booking );
                }

                $payload = array(
                    'bookings'   => $formatted,
                    'pagination' => array(
                        'current_page' => $current,
                        'total_pages'  => $total_page,
                        'total'        => $total,
                        'start'        => $total ? ( ( ( $current - 1 ) * $page_size ) + 1 ) : 0,
                        'end'          => $total ? min( $total, $current * $page_size ) : 0,
                    ),
                );

                wp_send_json_success( $payload );
            } catch ( Exception $exception ) {
                error_log( 'RB Booking Manager AJAX Error [ajax_get_bookings_list]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        /**
         * AJAX: fetch calendar dataset.
         */
        public function ajax_get_calendar_data() {
            try {
                $this->verify_ajax_request();
                $this->ensure_user_can_manage();

                $month = max( 1, min( 12, (int) filter_input( INPUT_POST, 'month', FILTER_SANITIZE_NUMBER_INT ) ) );
                $year  = (int) filter_input( INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT );
                if ( ! $year ) {
                    $year = (int) wp_date( 'Y' );
                }
                $view = sanitize_text_field( wp_unslash( $_POST['view'] ?? 'month' ) );

                $filters = array(
                    'status'   => sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ),
                    'location' => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
                    'search'   => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
                    'date_from' => $this->read_filter_value( 'dateFrom', 'date_from' ),
                    'date_to'   => $this->read_filter_value( 'dateTo', 'date_to' ),
                );

                $calendar = $this->query_calendar( $month, $year, $view, $filters );
                wp_send_json_success( $calendar );
            } catch ( Exception $exception ) {
                error_log( 'RB Booking Manager AJAX Error [ajax_get_calendar_data]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        /**
         * AJAX: update booking status.
         */
        public function ajax_update_booking_status() {
            try {
                $this->verify_ajax_request();
                $this->ensure_user_can_manage();

                if ( ! class_exists( 'RB_Booking' ) ) {
                    throw new Exception( __( 'Booking service unavailable.', 'restaurant-booking' ), 501 );
                }

                $booking_id = (int) filter_input( INPUT_POST, 'booking_id', FILTER_SANITIZE_NUMBER_INT );
                $status     = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

                if ( ! $booking_id || ! $status ) {
                    throw new Exception( __( 'Invalid booking data.', 'restaurant-booking' ), 400 );
                }

                $booking = new RB_Booking( $booking_id );
                if ( ! method_exists( $booking, 'exists' ) || ! $booking->exists() ) {
                    throw new Exception( __( 'Booking not found.', 'restaurant-booking' ), 404 );
                }

                $result = method_exists( $booking, 'update_status' ) ? $booking->update_status( $status ) : false;
                if ( ! $result ) {
                    throw new Exception( __( 'Unable to update booking status.', 'restaurant-booking' ), 500 );
                }

                if ( 'confirmed' === $status && method_exists( $booking, 'send_confirmation_email' ) ) {
                    $booking->send_confirmation_email();
                } elseif ( 'cancelled' === $status && method_exists( $booking, 'send_cancellation_email' ) ) {
                    $booking->send_cancellation_email();
                }

                $record = method_exists( $booking, 'get_data' ) ? $booking->get_data() : $booking;

                if ( class_exists( 'RB_Logger' ) && method_exists( 'RB_Logger', 'log' ) ) {
                    RB_Logger::log(
                        'booking_status_changed',
                        array(
                            'booking_id' => $booking_id,
                            'status'     => $status,
                            'user_id'    => $this->current_user['id'] ?? get_current_user_id(),
                        )
                    );
                }

                wp_send_json_success(
                    array(
                        'message' => __( 'Booking status updated.', 'restaurant-booking' ),
                        'booking' => $this->format_booking_record( $record ),
                    )
                );
            } catch ( Exception $exception ) {
                error_log( 'RB Booking Manager AJAX Error [ajax_update_booking_status]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        /**
         * AJAX: delete booking.
         */
        public function ajax_delete_booking() {
            try {
                $this->verify_ajax_request();
                $this->ensure_user_can_manage();

                if ( ! class_exists( 'RB_Booking' ) ) {
                    throw new Exception( __( 'Booking service unavailable.', 'restaurant-booking' ), 501 );
                }

                $booking_id = (int) filter_input( INPUT_POST, 'booking_id', FILTER_SANITIZE_NUMBER_INT );
                if ( ! $booking_id ) {
                    throw new Exception( __( 'Invalid booking identifier.', 'restaurant-booking' ), 400 );
                }

                $booking = new RB_Booking( $booking_id );
                if ( ! method_exists( $booking, 'exists' ) || ! $booking->exists() ) {
                    throw new Exception( __( 'Booking not found.', 'restaurant-booking' ), 404 );
                }

                $result = method_exists( $booking, 'delete' ) ? $booking->delete() : false;
                if ( ! $result ) {
                    throw new Exception( __( 'Unable to delete booking.', 'restaurant-booking' ), 500 );
                }

                if ( class_exists( 'RB_Logger' ) && method_exists( 'RB_Logger', 'log' ) ) {
                    RB_Logger::log(
                        'booking_deleted',
                        array(
                            'booking_id' => $booking_id,
                            'user_id'    => $this->current_user['id'] ?? get_current_user_id(),
                        )
                    );
                }

                wp_send_json_success( array( 'message' => __( 'Booking deleted.', 'restaurant-booking' ) ) );
            } catch ( Exception $exception ) {
                error_log( 'RB Booking Manager AJAX Error [ajax_delete_booking]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        /**
         * AJAX: bulk status update.
         */
        public function ajax_bulk_update_bookings() {
            $errors = array();

            try {
                $this->verify_ajax_request();
                $this->ensure_user_can_manage();

                if ( ! class_exists( 'RB_Booking' ) ) {
                    throw new Exception( __( 'Booking service unavailable.', 'restaurant-booking' ), 501 );
                }

                $ids    = isset( $_POST['booking_ids'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['booking_ids'] ) ) ) : array();
                $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

                $ids = array_filter( array_map( 'absint', $ids ) );
                if ( empty( $ids ) || ! $status ) {
                    throw new Exception( __( 'No bookings selected.', 'restaurant-booking' ), 400 );
                }

                $updated = 0;

                foreach ( $ids as $booking_id ) {
                    $booking = new RB_Booking( $booking_id );
                    if ( ! method_exists( $booking, 'exists' ) || ! $booking->exists() ) {
                        $errors[] = sprintf( __( 'Booking #%d not found.', 'restaurant-booking' ), $booking_id );
                        continue;
                    }

                    $result = method_exists( $booking, 'update_status' ) ? $booking->update_status( $status ) : false;
                    if ( ! $result ) {
                        $errors[] = sprintf( __( 'Failed to update booking #%d.', 'restaurant-booking' ), $booking_id );
                        continue;
                    }

                    if ( 'confirmed' === $status && method_exists( $booking, 'send_confirmation_email' ) ) {
                        $booking->send_confirmation_email();
                    }
                    if ( 'cancelled' === $status && method_exists( $booking, 'send_cancellation_email' ) ) {
                        $booking->send_cancellation_email();
                    }

                    $updated++;
                }

                if ( class_exists( 'RB_Logger' ) && method_exists( 'RB_Logger', 'log' ) ) {
                    RB_Logger::log(
                        'bulk_booking_update',
                        array(
                            'booking_ids' => $ids,
                            'status'      => $status,
                            'user_id'     => $this->current_user['id'] ?? get_current_user_id(),
                        )
                    );
                }

                if ( 0 === $updated ) {
                    throw new Exception( __( 'No bookings were updated.', 'restaurant-booking' ), 400 );
                }

                $message = sprintf(
                    _n( '%d booking updated.', '%d bookings updated.', $updated, 'restaurant-booking' ),
                    $updated
                );

                if ( ! empty( $errors ) ) {
                    $message .= ' ' . __( 'Some bookings could not be updated.', 'restaurant-booking' );
                }

                wp_send_json_success( array( 'message' => $message, 'errors' => $errors ) );
            } catch ( Exception $exception ) {
                error_log( 'RB Booking Manager AJAX Error [ajax_bulk_update_bookings]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array(
                        'message' => $exception->getMessage(),
                        'errors'  => $errors,
                    ),
                    $status
                );
            }
        }

        /**
         * AJAX: send bulk reminders.
         */
        public function ajax_send_bulk_reminders() {
            $errors = array();

            try {
                $this->verify_ajax_request();
                $this->ensure_user_can_manage();

                if ( ! class_exists( 'RB_Booking' ) ) {
                    throw new Exception( __( 'Booking service unavailable.', 'restaurant-booking' ), 501 );
                }

                $ids = isset( $_POST['booking_ids'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['booking_ids'] ) ) ) : array();
                $ids = array_filter( array_map( 'absint', $ids ) );

                if ( empty( $ids ) ) {
                    throw new Exception( __( 'No bookings selected.', 'restaurant-booking' ), 400 );
                }

                $sent = 0;

                foreach ( $ids as $booking_id ) {
                    $booking = new RB_Booking( $booking_id );
                    if ( ! method_exists( $booking, 'exists' ) || ! $booking->exists() ) {
                        $errors[] = sprintf( __( 'Booking #%d not found.', 'restaurant-booking' ), $booking_id );
                        continue;
                    }

                    if ( method_exists( $booking, 'get_status' ) && 'confirmed' !== $booking->get_status() ) {
                        $errors[] = sprintf( __( 'Booking #%d is not confirmed.', 'restaurant-booking' ), $booking_id );
                        continue;
                    }

                    $success = method_exists( $booking, 'send_reminder_email' ) ? $booking->send_reminder_email() : false;
                    if ( $success ) {
                        $sent++;
                    } else {
                        $errors[] = sprintf( __( 'Failed to send reminder for booking #%d.', 'restaurant-booking' ), $booking_id );
                    }
                }

                if ( class_exists( 'RB_Logger' ) && method_exists( 'RB_Logger', 'log' ) ) {
                    RB_Logger::log(
                        'bulk_reminder_sent',
                        array(
                            'booking_ids' => $ids,
                            'sent'        => $sent,
                            'user_id'     => $this->current_user['id'] ?? get_current_user_id(),
                        )
                    );
                }

                if ( 0 === $sent ) {
                    throw new Exception( __( 'No reminder emails were sent.', 'restaurant-booking' ), 400 );
                }

                $message = sprintf(
                    _n( '%d reminder sent.', '%d reminders sent.', $sent, 'restaurant-booking' ),
                    $sent
                );

                if ( ! empty( $errors ) ) {
                    $message .= ' ' . __( 'Some reminders could not be delivered.', 'restaurant-booking' );
                }

                wp_send_json_success( array( 'message' => $message, 'errors' => $errors ) );
            } catch ( Exception $exception ) {
                error_log( 'RB Booking Manager AJAX Error [ajax_send_bulk_reminders]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array(
                        'message' => $exception->getMessage(),
                        'errors'  => $errors,
                    ),
                    $status
                );
            }
        }

        /**
         * AJAX: export bookings to CSV.
         */
        public function ajax_export_bookings() {
            try {
                $this->verify_ajax_request();
                $this->ensure_user_can_manage();

                if ( ! class_exists( 'RB_Booking' ) ) {
                    throw new Exception( __( 'Booking service unavailable.', 'restaurant-booking' ), 501 );
                }

                $filters = array(
                    'date_from' => $this->read_filter_value( 'dateFrom', 'date_from' ),
                    'date_to'   => $this->read_filter_value( 'dateTo', 'date_to' ),
                    'status'    => sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ),
                    'location'  => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
                    'search'    => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
                );

                if ( ! method_exists( 'RB_Booking', 'get_bookings_for_export' ) ) {
                    throw new Exception( __( 'Export not available.', 'restaurant-booking' ), 501 );
                }

                $records = RB_Booking::get_bookings_for_export( $filters );
                $csv     = $this->generate_csv( $records );

                nocache_headers();
                header( 'Content-Type: text/csv; charset=UTF-8' );
                header( 'Content-Disposition: attachment; filename="bookings-export-' . gmdate( 'Y-m-d' ) . '.csv"' );
                header( 'Content-Length: ' . strlen( $csv ) );

                echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                exit;
            } catch ( Exception $exception ) {
                error_log( 'RB Booking Manager AJAX Error [ajax_export_bookings]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        /**
         * Query bookings with filters.
         */
        protected function query_bookings( $filters, $page, $page_size, $sort_by, $sort_order ) {
            if ( class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'get_bookings_with_filters' ) ) {
                return RB_Booking::get_bookings_with_filters( $filters, $page, $page_size, $sort_by, $sort_order );
            }

            /**
             * Allow third parties to provide booking data.
             */
            $fallback = apply_filters( 'rb_booking_manager_fetch_bookings', null, $filters, $page, $page_size, $sort_by, $sort_order );
            if ( null !== $fallback ) {
                return $fallback;
            }

            return array(
                'bookings'     => array(),
                'total_items'  => 0,
                'total_pages'  => 1,
                'current_page' => 1,
                'page_size'    => $page_size,
            );
        }

        /**
         * Query calendar dataset.
         */
        protected function query_calendar( $month, $year, $view, $filters ) {
            if ( class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'get_calendar_data' ) ) {
                return RB_Booking::get_calendar_data( $month, $year, $view, $filters );
            }

            return apply_filters( 'rb_booking_manager_calendar_data', array(), $month, $year, $view, $filters );
        }

        /**
         * Format booking record for JSON output.
         *
         * @param array|object $booking Booking data.
         *
         * @return array
         */
        protected function format_booking_record( $booking ) {
            $object = is_array( $booking ) ? (object) $booking : $booking;

            return array(
                'id'              => isset( $object->id ) ? $object->id : 0,
                'customer_name'   => isset( $object->customer_name ) ? $object->customer_name : '',
                'email'           => isset( $object->email ) ? $object->email : '',
                'phone'           => isset( $object->phone ) ? $object->phone : '',
                'booking_date'    => isset( $object->booking_date ) ? $object->booking_date : '',
                'booking_time'    => isset( $object->booking_time ) ? $object->booking_time : '',
                'party_size'      => isset( $object->party_size ) ? $object->party_size : '',
                'table_number'    => isset( $object->table_number ) ? $object->table_number : '',
                'status'          => isset( $object->status ) ? $object->status : 'pending',
                'special_requests'=> isset( $object->special_requests ) ? $object->special_requests : '',
                'location_name'   => isset( $object->location_name ) ? $object->location_name : '',
            );
        }

        /**
         * Generate CSV export string.
         *
         * @param array $records Booking list.
         *
         * @return string
         */
        protected function generate_csv( $records ) {
            $handle = fopen( 'php://temp', 'w+' );
            if ( ! $handle ) {
                return '';
            }

            fputcsv( $handle, array( 'ID', 'Customer', 'Email', 'Phone', 'Date', 'Time', 'Party Size', 'Table', 'Status', 'Location', 'Requests', 'Created' ) );

            if ( is_array( $records ) || $records instanceof Traversable ) {
                foreach ( $records as $record ) {
                    $entry = $this->format_booking_record( $record );
                    fputcsv(
                        $handle,
                        array(
                            $entry['id'],
                            $entry['customer_name'],
                            $entry['email'],
                            $entry['phone'],
                            $entry['booking_date'],
                            $entry['booking_time'],
                            $entry['party_size'],
                            $entry['table_number'],
                            $entry['status'],
                            $entry['location_name'],
                            $entry['special_requests'],
                            isset( $record->created_at ) ? $record->created_at : ( $record['created_at'] ?? '' ),
                        )
                    );
                }
            }

            rewind( $handle );
            $csv = stream_get_contents( $handle );
            fclose( $handle );

            return $csv;
        }

        /**
         * Verify AJAX nonce value.
         */
        protected function verify_ajax_request() {
            if ( ! check_ajax_referer( $this->ajax_nonce, 'nonce', false ) ) {
                throw new Exception( __( 'Security check failed. Please refresh and try again.', 'restaurant-booking' ), 403 );
            }

            $this->current_user = $this->resolve_current_user();
        }

        /**
         * Ensure current user can manage bookings.
         *
         * @throws Exception When the user lacks permission.
         */
        protected function ensure_user_can_manage() {
            $allowed = function_exists( 'restaurant_booking_user_can_manage' )
                ? restaurant_booking_user_can_manage()
                : current_user_can( 'manage_options' );
            $allowed = apply_filters( 'rb_booking_manager_user_can', $allowed, $this->current_user );

            if ( ! $allowed ) {
                throw new Exception( __( 'You do not have permission to manage bookings.', 'restaurant-booking' ), 403 );
            }
        }

        /**
         * Resolve current user context.
         *
         * @return array
         */
        protected function resolve_current_user() {
            if ( ! empty( $this->current_user ) ) {
                return $this->current_user;
            }

            $session_manager = class_exists( 'RB_Portal_Session_Manager' ) ? new RB_Portal_Session_Manager() : null;
            if ( $session_manager && method_exists( $session_manager, 'is_logged_in' ) && $session_manager->is_logged_in() ) {
                $user = method_exists( $session_manager, 'get_current_user' ) ? $session_manager->get_current_user() : array();
                if ( is_array( $user ) ) {
                    $this->current_user = $user;
                    return $this->current_user;
                }
            }

            $wp_user = wp_get_current_user();
            if ( $wp_user instanceof WP_User && $wp_user->exists() ) {
                $this->current_user = array(
                    'id'    => $wp_user->ID,
                    'name'  => $wp_user->display_name ? $wp_user->display_name : $wp_user->user_login,
                    'email' => $wp_user->user_email,
                );
            }

            return $this->current_user;
        }

        /**
         * Determine management request.
         *
         * @return bool
         */
        protected function is_management_request() {
            $view = function_exists( 'restaurant_booking_get_portal_view' )
                ? restaurant_booking_get_portal_view()
                : ( isset( $_GET['rb_portal'] ) ? sanitize_key( wp_unslash( $_GET['rb_portal'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            return 'bookings' === $view;
        }

        /**
         * Whether output is being embedded through shortcode filter.
         *
         * @return bool
         */
        protected function is_embed_context() {
            if ( did_action( 'rb_portal_render_view' ) > 0 ) {
                return true;
            }

            if ( ! is_singular() ) {
                return false;
            }

            global $post;

            if ( ! $post instanceof WP_Post ) {
                return false;
            }

            if ( has_shortcode( $post->post_content, 'booking_calendar' ) ) {
                return true;
            }

            return $this->page_has_portal_view( 'bookings' );
        }

        /**
         * Determine if the current post embeds the portal shortcode for a given view.
         *
         * @param string $view Requested portal view slug.
         *
         * @return bool
         */
        protected function page_has_portal_view( $view ) {
            if ( ! is_singular() ) {
                return false;
            }

            global $post;

            if ( ! $post instanceof WP_Post ) {
                return false;
            }

            if ( ! has_shortcode( $post->post_content, 'modern_booking_portal' ) ) {
                return false;
            }

            $shortcode_regex = get_shortcode_regex( array( 'modern_booking_portal' ) );
            if ( empty( $shortcode_regex ) ) {
                return false;
            }

            preg_match_all( '/'. $shortcode_regex .'/s', $post->post_content, $matches, PREG_SET_ORDER );

            foreach ( $matches as $shortcode ) {
                $atts = shortcode_parse_atts( $shortcode[3] );
                $requested_view = isset( $atts['view'] ) ? sanitize_key( $atts['view'] ) : 'login';

                if ( $requested_view === $view ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Ensure user is authenticated.
         *
         * @return bool
         */
        protected function is_user_authenticated() {
            if ( ! empty( $this->current_user ) ) {
                return true;
            }

            $session_manager = class_exists( 'RB_Portal_Session_Manager' ) ? new RB_Portal_Session_Manager() : null;
            if ( $session_manager && method_exists( $session_manager, 'is_logged_in' ) ) {
                return (bool) $session_manager->is_logged_in();
            }

            return is_user_logged_in();
        }

        /**
         * Redirect to portal login if authentication fails.
         */
        protected function redirect_to_portal_login() {
            $login_url = home_url( '/portal' );
            wp_safe_redirect( $login_url );
        }

        /**
         * Read filter value from POST with support for camelCase and snake_case keys.
         *
         * @param string $primary Primary key.
         * @param string $fallback Fallback key.
         *
         * @return string
         */
        protected function read_filter_value( $primary, $fallback ) {
            $value = $_POST[ $primary ] ?? $_POST[ $fallback ] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return sanitize_text_field( wp_unslash( $value ) );
        }

        /**
         * Determine current location identifier.
         *
         * @return int|string
         */
        public function get_current_location() {
            if ( isset( $this->current_user['location_id'] ) ) {
                return $this->current_user['location_id'];
            }

            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_all_locations' ) ) {
                $locations = RB_Location::get_all_locations();
                if ( ! empty( $locations ) ) {
                    $first = reset( $locations );
                    if ( isset( $first->id ) ) {
                        return $first->id;
                    }
                }
            }

            return '';
        }
    }

}
