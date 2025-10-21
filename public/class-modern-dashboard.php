<?php
/**
 * Modern Restaurant Booking Manager - Portal Dashboard Integration (Phase 4)
 *
 * Provides the restaurant manager dashboard shortcode replacement by intercepting
 * portal requests, rendering the redesigned dashboard template, and exposing
 * AJAX endpoints for real-time statistics, charts, and schedule data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Modern_Dashboard' ) ) {

    class RB_Modern_Dashboard {

        /**
         * Analytics service instance.
         *
         * @var RB_Analytics|null
         */
        protected $analytics;

        /**
         * Cached current user context.
         *
         * @var array
         */
        protected $current_user = array();

        /**
         * Flag to prevent duplicate asset registration.
         *
         * @var bool
         */
        protected $assets_enqueued = false;

        /**
         * Default chart period for shortcode embeds.
         *
         * @var string
         */
        protected $chart_default_period = '7d';

        /**
         * Register WordPress hooks.
         */
        public function __construct() {
            if ( class_exists( 'RB_Analytics' ) ) {
                $this->analytics = RB_Analytics::get_instance();
            } else {
                $this->analytics = null;
            }

            add_action( 'init', array( $this, 'maybe_render_dashboard' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );

            add_action( 'wp_ajax_rb_get_dashboard_stats', array( $this, 'get_dashboard_stats' ) );
            add_action( 'wp_ajax_rb_update_stat_period', array( $this, 'update_stat_period' ) );
            add_action( 'wp_ajax_rb_get_dashboard_chart_data', array( $this, 'get_chart_data' ) );
            add_action( 'wp_ajax_rb_get_todays_schedule', array( $this, 'get_todays_schedule' ) );

            add_action( 'wp_ajax_nopriv_rb_get_dashboard_stats', array( $this, 'unauthorized_response' ) );
            add_action( 'wp_ajax_nopriv_rb_update_stat_period', array( $this, 'unauthorized_response' ) );
            add_action( 'wp_ajax_nopriv_rb_get_dashboard_chart_data', array( $this, 'unauthorized_response' ) );
            add_action( 'wp_ajax_nopriv_rb_get_todays_schedule', array( $this, 'unauthorized_response' ) );
        }

        /**
         * Render the dashboard template when the rb_portal=dashboard query var is detected.
         */
        public function maybe_render_dashboard() {
            if ( ! $this->is_dashboard_request() ) {
                return;
            }

            $this->render_dashboard();
            exit;
        }

        /**
         * Enqueue dashboard-specific assets.
         */
        public function enqueue_dashboard_assets() {
            if ( ! $this->is_dashboard_request() && ! $this->is_embed_context() ) {
                return;
            }

            $this->enqueue_portal_assets();
        }

        /**
         * Register dashboard assets for reuse across embeds.
         */
        protected function enqueue_portal_assets() {
            if ( $this->assets_enqueued ) {
                return;
            }

            $this->assets_enqueued = true;

            $version  = defined( 'RB_PLUGIN_VERSION' ) ? RB_PLUGIN_VERSION : '1.0.0';
            $base_url = plugin_dir_url( __FILE__ ) . '../';

            wp_enqueue_style(
                'rb-design-system',
                $base_url . 'assets/css/design-system.css',
                array(),
                $version
            );

            wp_enqueue_style(
                'rb-components',
                $base_url . 'assets/css/components.css',
                array( 'rb-design-system' ),
                $version
            );

            wp_enqueue_style(
                'rb-animations',
                $base_url . 'assets/css/animations.css',
                array( 'rb-design-system' ),
                $version
            );

            wp_enqueue_style(
                'rb-portal-dashboard',
                $base_url . 'assets/css/portal-dashboard.css',
                array( 'rb-design-system', 'rb-components', 'rb-animations' ),
                $version
            );

            wp_enqueue_style(
                'rb-portal-dashboard-mobile',
                $base_url . 'assets/css/portal-dashboard-mobile.css',
                array( 'rb-portal-dashboard' ),
                $version
            );

            wp_enqueue_script(
                'chart-js',
                'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
                array(),
                '3.9.1',
                true
            );

            wp_enqueue_script(
                'rb-dashboard-charts',
                $base_url . 'assets/js/dashboard-charts.js',
                array( 'chart-js' ),
                $version,
                true
            );

            wp_enqueue_script(
                'rb-theme-manager',
                $base_url . 'assets/js/theme-manager.js',
                array(),
                $version,
                true
            );

            wp_enqueue_script(
                'rb-portal-dashboard',
                $base_url . 'assets/js/portal-dashboard.js',
                array( 'rb-dashboard-charts', 'rb-theme-manager' ),
                $version,
                true
            );

            wp_enqueue_script(
                'rb-mobile-dashboard',
                $base_url . 'assets/js/mobile-dashboard.js',
                array( 'rb-portal-dashboard' ),
                $version,
                true
            );

            wp_localize_script(
                'rb-portal-dashboard',
                'rbDashboard',
                array(
                    'ajax_url'          => admin_url( 'admin-ajax.php' ),
                    'nonce'             => wp_create_nonce( 'rb_dashboard_nonce' ),
                    'current_location'  => $this->get_current_location(),
                    'calendar_url'      => $this->build_portal_url( 'calendar' ),
                    'tables_url'        => $this->build_portal_url( 'tables' ),
                    'reports_url'       => $this->build_portal_url( 'reports' ),
                    'settings_url'      => $this->build_portal_url( 'settings' ),
                    'chartDefaults'    => array(
                        'range' => $this->chart_default_period,
                    ),
                    'strings'           => array(
                        'loading'     => __( 'Loading...', 'restaurant-booking' ),
                        'error'       => __( 'Unable to load data.', 'restaurant-booking' ),
                        'no_data'     => __( 'No bookings', 'restaurant-booking' ),
                        'add_booking' => __( 'Add Booking', 'restaurant-booking' ),
                    ),
                )
            );

            wp_localize_script(
                'rb-mobile-dashboard',
                'rbMobileConfig',
                array(
                    'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                    'nonce'        => wp_create_nonce( 'rb_dashboard_nonce' ),
                    'serviceWorker'=> plugins_url( 'sw.js', RB_PLUGIN_FILE ),
                    'manifestUrl'  => plugins_url( 'manifest.json', RB_PLUGIN_FILE ),
                    'homeUrl'      => home_url( '/' ),
                    'strings'      => array(
                        'pullToRefresh' => __( 'Pull to refresh', 'restaurant-booking' ),
                        'refreshing'    => __( 'Refreshing dashboard…', 'restaurant-booking' ),
                        'refreshed'     => __( 'Dashboard updated', 'restaurant-booking' ),
                        'offline'       => __( 'You are offline. Some features may be limited.', 'restaurant-booking' ),
                        'online'        => __( 'Back online', 'restaurant-booking' ),
                        'installTitle'  => __( 'Install Restaurant Booking', 'restaurant-booking' ),
                        'installMessage'=> __( 'Add the manager portal to your home screen for quick access.', 'restaurant-booking' ),
                        'installAction' => __( 'Install', 'restaurant-booking' ),
                        'dismissAction' => __( 'Dismiss', 'restaurant-booking' ),
                        'confirmSuccess'=> __( 'Booking confirmed', 'restaurant-booking' ),
                        'cancelSuccess' => __( 'Booking cancelled', 'restaurant-booking' ),
                        'actionError'   => __( 'Unable to complete the action. Please try again.', 'restaurant-booking' ),
                    ),
                )
            );
        }

        /**
         * Render the portal dashboard template.
         */
        public function render_dashboard() {
            $session_manager = class_exists( 'RB_Portal_Session_Manager' ) ? new RB_Portal_Session_Manager() : null;

            if ( $session_manager && method_exists( $session_manager, 'is_logged_in' ) && ! $session_manager->is_logged_in() ) {
                wp_safe_redirect( home_url( '/portal' ) );
                exit;
            }

            $this->current_user = $this->resolve_current_user();
            $current_location   = $this->get_current_location();
            $locations          = $this->get_locations();
            $metric_periods     = $this->get_default_metric_periods();

            $initial_stats = array();
            foreach ( $metric_periods as $metric => $period ) {
                $initial_stats[ $metric ] = $this->get_metric_payload( $current_location, $metric, $period );
            }

            $schedule_data  = $this->get_schedule_payload( $current_location );
            $notifications  = $this->get_notifications( $current_location, wp_date( 'Y-m-d' ) );

            $this->enqueue_dashboard_assets();

            add_filter(
                'pre_get_document_title',
                static function () {
                    return __( 'Dashboard - Restaurant Manager', 'restaurant-booking' );
                }
            );

            $template = plugin_dir_path( __FILE__ ) . 'partials/portal-dashboard.php';
            if ( file_exists( $template ) ) {
                include $template;
            }
        }

        /**
         * Render analytics shortcode output.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_analytics_shortcode( $atts ) {
            $atts = shortcode_atts(
                array(
                    'location' => '',
                    'period'   => '7d',
                ),
                $atts,
                'restaurant_analytics'
            );

            $this->current_user = $this->resolve_current_user();

            $has_access = function_exists( 'restaurant_booking_user_can_manage' )
                ? restaurant_booking_user_can_manage()
                : current_user_can( 'manage_options' );

            if ( ! $has_access ) {
                if ( function_exists( 'restaurant_booking_render_permission_notice' ) ) {
                    return restaurant_booking_render_permission_notice( __( 'restaurant analytics', 'restaurant-booking' ) );
                }

                return '<div class="rb-alert rb-alert-warning">' . esc_html__( 'You do not have permission to view restaurant analytics.', 'restaurant-booking' ) . '</div>';
            }

            $location = sanitize_text_field( $atts['location'] );
            if ( '' !== $location ) {
                $this->current_user['location_id'] = $location;
            }

            $location_id = $this->get_current_location();

            $period = sanitize_key( $atts['period'] );
            if ( ! in_array( $period, array( '7d', '30d', '90d' ), true ) ) {
                $period = '7d';
            }
            $this->chart_default_period = $period;

            $this->enqueue_portal_assets();

            $metric_periods = $this->get_default_metric_periods();
            $initial_stats  = array();

            foreach ( $metric_periods as $metric => $default_period ) {
                $initial_stats[ $metric ] = $this->get_metric_payload( $location_id, $metric, $default_period );
            }

            $schedule_data = $this->get_schedule_payload( $location_id );
            $notifications = $this->get_notifications( $location_id, wp_date( 'Y-m-d' ) );
            $locations     = $this->get_locations();

            $user_context = $this->current_user;
            $user_name    = isset( $user_context['name'] ) ? $user_context['name'] : __( 'Manager', 'restaurant-booking' );

            $current_location = $location_id;

            ob_start();
            include plugin_dir_path( __FILE__ ) . 'partials/analytics-shortcode.php';
            return ob_get_clean();
        }

        /**
         * AJAX: Return aggregated dashboard stats for the current period.
         */
        public function get_dashboard_stats() {
            $this->verify_ajax_nonce();

            $location_id = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : $this->get_current_location();
            $period      = isset( $_POST['period'] ) ? sanitize_key( wp_unslash( $_POST['period'] ) ) : 'today';

            $metrics      = array_keys( $this->get_default_metric_periods() );
            $metrics_data = array();

            foreach ( $metrics as $metric ) {
                $metrics_data[ $metric ] = $this->get_metric_payload( $location_id, $metric, $period );
            }

            wp_send_json_success( $metrics_data );
        }

        /**
         * AJAX: Update a single metric based on selected period.
         */
        public function update_stat_period() {
            $this->verify_ajax_nonce();

            $metric      = isset( $_POST['metric'] ) ? sanitize_key( wp_unslash( $_POST['metric'] ) ) : '';
            $period      = isset( $_POST['period'] ) ? sanitize_key( wp_unslash( $_POST['period'] ) ) : 'today';
            $location_id = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : $this->get_current_location();

            $allowed_metrics = array( 'bookings', 'revenue', 'occupancy', 'pending' );
            if ( ! in_array( $metric, $allowed_metrics, true ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Unknown metric requested.', 'restaurant-booking' ),
                    ),
                    400
                );
            }

            $payload = $this->get_metric_payload( $location_id, $metric, $period );
            wp_send_json_success( $payload );
        }

        /**
         * AJAX: Return chart dataset.
         */
        public function get_chart_data() {
            $this->verify_ajax_nonce();

            $location_id = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : $this->get_current_location();
            $period      = isset( $_POST['period'] ) ? sanitize_key( wp_unslash( $_POST['period'] ) ) : '7d';

            $data = $this->fetch_chart_data( $location_id, $period );
            wp_send_json_success( $data );
        }

        /**
         * AJAX: Return today's schedule for the selected location.
         */
        public function get_todays_schedule() {
            $this->verify_ajax_nonce();

            $location_id = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : $this->get_current_location();
            $schedule    = $this->get_schedule_payload( $location_id );

            wp_send_json_success( $schedule );
        }

        /**
         * Send unauthorized response for non-authenticated requests.
         */
        public function unauthorized_response() {
            wp_send_json_error(
                array(
                    'message' => __( 'Authentication required.', 'restaurant-booking' ),
                ),
                401
            );
        }

        /**
         * Determine whether the request is targeting the manager dashboard.
         *
         * @return bool
         */
        protected function is_dashboard_request() {
            $view = function_exists( 'restaurant_booking_get_portal_view' )
                ? restaurant_booking_get_portal_view()
                : ( isset( $_GET['rb_portal'] ) ? sanitize_key( wp_unslash( $_GET['rb_portal'] ) ) : '' );

            return 'dashboard' === $view;
        }

        /**
         * Determine if the dashboard assets are needed for shortcode embeds.
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

            if ( has_shortcode( $post->post_content, 'restaurant_analytics' ) ) {
                return true;
            }

            return $this->page_has_portal_view( 'dashboard' );
        }

        /**
         * Check whether the portal shortcode is embedded with a specific view.
         *
         * @param string $view Expected view slug.
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
         * Resolve current user context.
         *
         * @return array
         */
        protected function resolve_current_user() {
            if ( ! empty( $this->current_user ) ) {
                return $this->current_user;
            }

            $session_manager = class_exists( 'RB_Portal_Session_Manager' ) ? new RB_Portal_Session_Manager() : null;
            if ( $session_manager && method_exists( $session_manager, 'get_current_user' ) ) {
                $user = $session_manager->get_current_user();
                if ( is_array( $user ) ) {
                    return $user;
                }
            }

            $wp_user = wp_get_current_user();
            if ( $wp_user instanceof WP_User && $wp_user->exists() ) {
                return array(
                    'name'        => $wp_user->display_name,
                    'email'       => $wp_user->user_email,
                    'location_id' => get_user_meta( $wp_user->ID, 'rb_preferred_location', true ),
                );
            }

            return array(
                'name'        => __( 'Manager', 'restaurant-booking' ),
                'email'       => '',
                'location_id' => '',
            );
        }

        /**
         * Get available restaurant locations.
         *
         * @return array
         */
        protected function get_locations() {
            if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_all_locations' ) ) {
                $locations = RB_Location::get_all_locations();
                if ( is_array( $locations ) ) {
                    return array_map(
                        static function ( $location ) {
                            return array(
                                'id'   => isset( $location->id ) ? (int) $location->id : 0,
                                'name' => isset( $location->name ) ? $location->name : __( 'Location', 'restaurant-booking' ),
                            );
                        },
                        $locations
                    );
                }
            }

            return array(
                array(
                    'id'   => 1,
                    'name' => __( 'Downtown Branch', 'restaurant-booking' ),
                ),
                array(
                    'id'   => 2,
                    'name' => __( 'Uptown Location', 'restaurant-booking' ),
                ),
                array(
                    'id'   => 3,
                    'name' => __( 'Mall Branch', 'restaurant-booking' ),
                ),
            );
        }

        /**
         * Compute the current location identifier.
         *
         * @return int
         */
        protected function get_current_location() {
            if ( ! empty( $this->current_user['location_id'] ) ) {
                return (int) $this->current_user['location_id'];
            }

            $session_manager = class_exists( 'RB_Portal_Session_Manager' ) ? new RB_Portal_Session_Manager() : null;
            if ( $session_manager && method_exists( $session_manager, 'get_current_user' ) ) {
                $user = $session_manager->get_current_user();
                if ( is_array( $user ) && ! empty( $user['location_id'] ) ) {
                    return (int) $user['location_id'];
                }
            }

            $locations = $this->get_locations();
            if ( ! empty( $locations ) ) {
                return (int) $locations[0]['id'];
            }

            return 0;
        }

        /**
         * Return default metric periods for initial render.
         *
         * @return array
         */
        protected function get_default_metric_periods() {
            return array(
                'bookings'  => 'today',
                'revenue'   => 'today',
                'occupancy' => 'today',
                'pending'   => 'today',
            );
        }

        /**
         * Prepare metric payload for the dashboard front-end.
         *
         * @param int    $location_id Location identifier.
         * @param string $metric      Metric key.
         * @param string $period      Selected period key.
         *
         * @return array
         */
        protected function get_metric_payload( $location_id, $metric, $period ) {
            $normalized_period = $this->normalize_period( $metric, $period );
            $stats             = $this->fetch_location_stats( $location_id, $normalized_period );
            $period_label      = $this->get_period_label( $period );

            switch ( $metric ) {
                case 'bookings':
                    return array(
                        'metric' => 'bookings',
                        'value'  => isset( $stats['bookings_count'] ) ? (int) $stats['bookings_count'] : 0,
                        'label'  => $this->get_metric_label( $metric, $period ),
                        'change' => array(
                            'percentage' => isset( $stats['bookings_change'] ) ? (float) $stats['bookings_change'] : 0,
                            'period'     => $period_label,
                        ),
                    );
                case 'revenue':
                    return array(
                        'metric' => 'revenue',
                        'value'  => isset( $stats['revenue_amount'] ) ? (float) $stats['revenue_amount'] : 0,
                        'label'  => $this->get_metric_label( $metric, $period ),
                        'prefix' => isset( $stats['revenue_currency'] ) ? $stats['revenue_currency'] : '$',
                        'change' => array(
                            'percentage' => isset( $stats['revenue_change'] ) ? (float) $stats['revenue_change'] : 0,
                            'period'     => $period_label,
                        ),
                    );
                case 'occupancy':
                    return array(
                        'metric' => 'occupancy',
                        'value'  => isset( $stats['occupancy_rate'] ) ? (float) $stats['occupancy_rate'] : 0,
                        'label'  => $this->get_metric_label( $metric, $period ),
                        'suffix' => '%',
                        'change' => array(
                            'percentage' => isset( $stats['occupancy_change'] ) ? (float) $stats['occupancy_change'] : 0,
                            'period'     => $period_label,
                        ),
                    );
                case 'pending':
                default:
                    return array(
                        'metric' => 'pending',
                        'value'  => isset( $stats['pending_count'] ) ? (int) $stats['pending_count'] : 0,
                        'label'  => $this->get_metric_label( $metric, $period ),
                        'badge'  => isset( $stats['pending_badge'] ) ? $stats['pending_badge'] : '',
                        'change' => array(
                            'percentage' => isset( $stats['pending_change'] ) ? (float) $stats['pending_change'] : 0,
                            'period'     => $period_label,
                        ),
                    );
            }
        }

        /**
         * Retrieve analytics stats for a given location and period or return fallback data.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Period key.
         *
         * @return array
         */
        protected function fetch_location_stats( $location_id, $period ) {
            if ( $this->analytics && method_exists( $this->analytics, 'get_location_stats' ) ) {
                $stats = $this->analytics->get_location_stats( $location_id, $period );
                if ( is_array( $stats ) ) {
                    return $stats;
                }
            }

            $currency = class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'get_currency_symbol' ) ? RB_Booking::get_currency_symbol() : '$';

            return array(
                'bookings_count'   => 0,
                'bookings_change'  => 0,
                'revenue_amount'   => 0,
                'revenue_change'   => 0,
                'revenue_currency' => $currency,
                'occupancy_rate'   => 0,
                'occupancy_change' => 0,
                'pending_count'    => 0,
                'pending_change'   => 0,
                'pending_badge'    => '',
            );
        }

        /**
         * Create fallback chart data when analytics service is unavailable.
         *
         * @param int    $location_id Location identifier.
         * @param string $period      Chart period.
         *
         * @return array
         */
        protected function fetch_chart_data( $location_id, $period ) {
            if ( $this->analytics && method_exists( $this->analytics, 'get_chart_data' ) ) {
                $data = $this->analytics->get_chart_data( $location_id, $period );
                if ( is_array( $data ) ) {
                    return $data;
                }
            }

            return array(
                'bookingTrends' => array(
                    'labels'    => array(),
                    'total'     => array(),
                    'confirmed' => array(),
                    'pending'   => array(),
                ),
            );
        }

        /**
         * Format bookings into schedule payload.
         *
         * @param int $location_id Location identifier.
         *
         * @return array
         */
        protected function get_schedule_payload( $location_id ) {
            $bookings = array();

            if ( $this->analytics && method_exists( $this->analytics, 'get_todays_schedule' ) ) {
                $bookings = $this->analytics->get_todays_schedule( $location_id, wp_date( 'Y-m-d' ) );
            }

            if ( empty( $bookings ) && class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'get_bookings_by_date' ) ) {
                $bookings = RB_Booking::get_bookings_by_date( $location_id, wp_date( 'Y-m-d' ) );
            }

            return $this->format_schedule_data( $bookings );
        }

        /**
         * Format schedule data into time slots.
         *
         * @param array $bookings Raw booking rows.
         *
         * @return array
         */
        protected function format_schedule_data( $bookings ) {
            $grouped = array();
            foreach ( $bookings as $booking ) {
                $record = is_array( $booking ) ? (object) $booking : $booking;
                $time_value = isset( $record->booking_time ) ? $record->booking_time : '';
                $time       = $time_value ? wp_date( 'H:i', strtotime( $time_value ) ) : '00:00';
                if ( ! isset( $grouped[ $time ] ) ) {
                    $grouped[ $time ] = array();
                }
                $grouped[ $time ][] = $record;
            }

            $time_slots = array();
            for ( $hour = 17; $hour <= 22; $hour++ ) {
                $time       = sprintf( '%02d:00', $hour );
                $time_label = wp_date( 'g:i A', strtotime( $time ) );
                $slot       = isset( $grouped[ $time ] ) ? $grouped[ $time ] : array();

                $time_slots[] = array(
                    'time'      => $time,
                    'timeLabel' => $time_label,
                    'bookings'  => array_map( array( $this, 'format_booking_item' ), $slot ),
                );
            }

            $total_bookings = count( $bookings );
            $expected_total = 0;
            foreach ( $bookings as $booking ) {
                $record = is_array( $booking ) ? (object) $booking : $booking;
                if ( isset( $record->total_amount ) ) {
                    $expected_total += (float) $record->total_amount;
                } elseif ( isset( $record->estimated_total ) ) {
                    $expected_total += (float) $record->estimated_total;
                } elseif ( isset( $record->total ) ) {
                    $expected_total += (float) $record->total;
                }
            }

            $currency_symbol = class_exists( 'RB_Booking' ) && method_exists( 'RB_Booking', 'get_currency_symbol' ) ? RB_Booking::get_currency_symbol() : '$';

            return array(
                'dateLabel' => wp_date( 'F j, Y' ),
                'timeSlots' => $time_slots,
                'summary'   => array(
                    'totalBookings'          => $total_bookings,
                    'expectedRevenue'        => $currency_symbol . number_format_i18n( $expected_total, 0 ),
                    'expectedRevenueFormatted' => $currency_symbol . number_format_i18n( $expected_total, 0 ),
                ),
            );
        }

        /**
         * Format a single booking entry.
         *
         * @param stdClass $booking Booking record.
         *
         * @return array
         */
        protected function format_booking_item( $booking ) {
            $record = is_array( $booking ) ? (object) $booking : $booking;
            $status = isset( $record->status ) ? $record->status : '';
            $party  = isset( $record->party_size ) ? (int) $record->party_size : 0;
            $table  = '';

            if ( isset( $record->table_number ) && $record->table_number ) {
                $table = $record->table_number;
            } elseif ( isset( $record->table_name ) ) {
                $table = $record->table_name;
            }

            if ( empty( $table ) ) {
                $table = __( 'TBD', 'restaurant-booking' );
            }

            $details = sprintf( __( 'Table %1$s • %2$d people', 'restaurant-booking' ), $table, $party );

            return array(
                'id'          => isset( $record->id ) ? (int) $record->id : 0,
                'customerName'=> isset( $record->customer_name ) ? $record->customer_name : __( 'Guest', 'restaurant-booking' ),
                'details'     => $details,
                'status'      => $status ? ucfirst( $status ) : '',
                'statusClass' => 'rb-status-' . sanitize_html_class( strtolower( $status ? $status : 'unknown' ) ),
            );
        }

        /**
         * Normalize period values for analytics.
         *
         * @param string $metric Metric name.
         * @param string $period Period key.
         *
         * @return string
         */
        protected function normalize_period( $metric, $period ) {
            if ( 'occupancy' === $metric && 'now' === $period ) {
                return 'today';
            }

            return $period;
        }

        /**
         * Build a translated metric label based on period.
         *
         * @param string $metric Metric key.
         * @param string $period Period key.
         *
         * @return string
         */
        protected function get_metric_label( $metric, $period ) {
            switch ( $metric ) {
                case 'bookings':
                    return ( 'today' === $period ) ? __( "Today's Bookings", 'restaurant-booking' ) : __( 'Total Bookings', 'restaurant-booking' );
                case 'revenue':
                    return ( 'today' === $period ) ? __( "Today's Revenue", 'restaurant-booking' ) : __( 'Revenue', 'restaurant-booking' );
                case 'occupancy':
                    return ( 'now' === $period ) ? __( 'Current Occupancy', 'restaurant-booking' ) : __( 'Table Occupancy', 'restaurant-booking' );
                case 'pending':
                default:
                    return __( 'Pending Approvals', 'restaurant-booking' );
            }
        }

        /**
         * Translate period key to human-readable comparison text.
         *
         * @param string $period Period key.
         *
         * @return string
         */
        protected function get_period_label( $period ) {
            switch ( $period ) {
                case 'today':
                    return __( 'since yesterday', 'restaurant-booking' );
                case 'week':
                    return __( 'since last week', 'restaurant-booking' );
                case 'month':
                    return __( 'since last month', 'restaurant-booking' );
                case 'now':
                    return __( 'vs. last hour', 'restaurant-booking' );
                default:
                    return __( 'previous period', 'restaurant-booking' );
            }
        }

        /**
         * Resolve chart period length.
         *
         * @param string $period Period key.
         *
         * @return int
         */
        protected function resolve_period_length( $period ) {
            switch ( $period ) {
                case '30d':
                    return 30;
                case '90d':
                    return 90;
                case '7d':
                default:
                    return 7;
            }
        }

        /**
         * Build portal URL helper.
         *
         * @param string $view View identifier.
         *
         * @return string
         */
        protected function build_portal_url( $view ) {
            return add_query_arg( 'rb_portal', $view, home_url( '/' ) );
        }

        /**
         * Provide stub notifications for the UI.
         *
         * @return array
         */
        protected function get_notifications( $location_id, $date ) {
            if ( $this->analytics && method_exists( $this->analytics, 'get_dashboard_alerts' ) ) {
                $alerts        = $this->analytics->get_dashboard_alerts( $location_id, $date );
                $icon_mapping  = array(
                    'warning' => 'alert',
                    'error'   => 'alert',
                    'success' => 'check-circle',
                    'info'    => 'info',
                );
                $notifications = array();

                foreach ( (array) $alerts as $alert ) {
                    $type = isset( $alert['type'] ) ? $alert['type'] : 'info';
                    $notifications[] = array(
                        'icon'       => isset( $icon_mapping[ $type ] ) ? $icon_mapping[ $type ] : 'info',
                        'title'      => isset( $alert['message'] ) ? wp_strip_all_tags( $alert['message'] ) : '',
                        'meta'       => '',
                        'action_url' => isset( $alert['action_url'] ) ? $alert['action_url'] : '',
                    );
                }

                return $notifications;
            }

            return array();
        }

        /**
         * Verify AJAX nonce value.
         */
        protected function verify_ajax_nonce() {
            check_ajax_referer( 'rb_dashboard_nonce', 'nonce' );
        }
    }

}
