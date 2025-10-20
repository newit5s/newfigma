<?php
/**
 * Modern admin integration for the Restaurant Booking plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Modern_Admin' ) ) {

    class RB_Modern_Admin {

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

            add_action( 'wp_ajax_rb_admin_get_dashboard', array( $this, 'ajax_get_dashboard' ) );
            add_action( 'wp_ajax_rb_admin_get_bookings', array( $this, 'ajax_get_bookings' ) );
            add_action( 'wp_ajax_rb_admin_get_locations', array( $this, 'ajax_get_locations' ) );
        }

        public function add_admin_pages() {
            add_menu_page(
                __( 'Restaurant Booking', 'restaurant-booking' ),
                __( 'Bookings', 'restaurant-booking' ),
                'manage_options',
                'rb-dashboard',
                array( $this, 'render_dashboard' ),
                'dashicons-calendar-alt',
                3
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Dashboard', 'restaurant-booking' ),
                __( 'Dashboard', 'restaurant-booking' ),
                'manage_options',
                'rb-dashboard',
                array( $this, 'render_dashboard' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Bookings', 'restaurant-booking' ),
                __( 'Bookings', 'restaurant-booking' ),
                'manage_options',
                'rb-bookings',
                array( $this, 'render_bookings' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Locations', 'restaurant-booking' ),
                __( 'Locations', 'restaurant-booking' ),
                'manage_options',
                'rb-locations',
                array( $this, 'render_locations' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Settings', 'restaurant-booking' ),
                __( 'Settings', 'restaurant-booking' ),
                'manage_options',
                'rb-settings',
                array( $this, 'render_settings' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Reports', 'restaurant-booking' ),
                __( 'Reports', 'restaurant-booking' ),
                'manage_options',
                'rb-reports',
                array( $this, 'render_reports' )
            );
        }

        public function enqueue_admin_assets( $hook ) {
            if ( strpos( $hook, 'rb-' ) === false ) {
                return;
            }

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
                'rb-modern-admin',
                $base_url . 'assets/css/modern-admin.css',
                array( 'rb-design-system', 'rb-components', 'rb-animations' ),
                $version
            );

            wp_enqueue_script(
                'rb-theme-manager',
                $base_url . 'assets/js/theme-manager.js',
                array(),
                $version,
                true
            );

            wp_enqueue_script(
                'rb-modern-admin',
                $base_url . 'assets/js/modern-admin.js',
                array( 'jquery', 'rb-theme-manager' ),
                $version,
                true
            );

            wp_localize_script(
                'rb-modern-admin',
                'rbAdmin',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'rb_admin_nonce' ),
                    'strings'  => array(
                        'loading' => __( 'Loading…', 'restaurant-booking' ),
                        'error'   => __( 'Error loading data', 'restaurant-booking' ),
                    ),
                )
            );
        }

        public function render_dashboard() {
            $this->include_partial( 'admin-dashboard.php' );
        }

        public function render_bookings() {
            $this->include_partial( 'bookings-table.php' );
        }

        public function render_locations() {
            $this->include_partial( 'locations-management.php' );
        }

        public function render_settings() {
            $this->include_partial( 'settings-panel.php' );
        }

        public function render_reports() {
            $this->include_partial( 'reports-analytics.php' );
        }

        public function ajax_get_dashboard() {
            check_ajax_referer( 'rb_admin_nonce', 'nonce' );

            try {
                $locations = RB_Location::get_all_locations();
                $data      = array(
                    'locations' => array_map( array( $this, 'format_location_stats' ), $locations ),
                );

                wp_send_json_success( $data );
            } catch ( Exception $exception ) {
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    500
                );
            }
        }

        public function ajax_get_bookings() {
            check_ajax_referer( 'rb_admin_nonce', 'nonce' );

            $location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
            $status   = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
            $page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );

            try {
                $bookings = RB_Booking::get_admin_bookings( $location, $status, $page );
                wp_send_json_success( $bookings );
            } catch ( Exception $exception ) {
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    500
                );
            }
        }

        public function ajax_get_locations() {
            check_ajax_referer( 'rb_admin_nonce', 'nonce' );

            $locations = RB_Location::get_all_locations();

            wp_send_json_success(
                array(
                    'locations' => array_map(
                        static function ( $location ) {
                            return array(
                                'id'   => $location->id,
                                'name' => $location->name,
                            );
                        },
                        $locations
                    ),
                )
            );
        }

        protected function include_partial( $file ) {
            $path = plugin_dir_path( __FILE__ ) . 'partials/' . $file;

            if ( file_exists( $path ) ) {
                include $path;
            } else {
                printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'Admin template missing.', 'restaurant-booking' ) );
            }
        }

        protected function format_location_stats( $location ) {
            $today     = current_time( 'Y-m-d' );
            $yesterday = gmdate( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );

            $today_bookings      = RB_Booking::count_by_date_and_location( $today, $location->id );
            $yesterday_bookings  = RB_Booking::count_by_date_and_location( $yesterday, $location->id );
            $bookings_trend      = $yesterday_bookings > 0 ? round( ( ( $today_bookings - $yesterday_bookings ) / max( 1, $yesterday_bookings ) ) * 100 ) : 0;
            $today_revenue       = RB_Booking::sum_revenue_by_date_and_location( $today, $location->id );
            $yesterday_revenue   = RB_Booking::sum_revenue_by_date_and_location( $yesterday, $location->id );
            $revenue_trend       = $yesterday_revenue > 0 ? round( ( ( $today_revenue - $yesterday_revenue ) / max( 1, $yesterday_revenue ) ) * 100 ) : 0;
            $occupancy           = RB_Table::get_occupancy_rate( $location->id );
            $previous_occupancy  = RB_Table::get_occupancy_rate( $location->id, $yesterday );
            $occupancy_delta     = $occupancy - $previous_occupancy;
            $tables_count        = RB_Table::count_by_location( $location->id );

            return array(
                'id'   => $location->id,
                'name' => $location->name,
                'stats' => array(
                    'bookings'        => $today_bookings,
                    'bookings_trend'  => $bookings_trend,
                    'revenue'         => '$' . number_format_i18n( $today_revenue ),
                    'revenue_trend'   => $revenue_trend,
                    'occupancy'       => $occupancy,
                    'occupancy_trend' => $occupancy_delta,
                    'tables'          => $tables_count,
                ),
            );
        }
    }
}
