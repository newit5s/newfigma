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
            if ( function_exists( 'restaurant_booking_resolve_manage_capability' ) ) {
                $capability = restaurant_booking_resolve_manage_capability();
            } elseif ( function_exists( 'restaurant_booking_get_manage_capability' ) ) {
                $capability = restaurant_booking_get_manage_capability();
            } else {
                $capability = 'manage_options';
            }

            add_menu_page(
                __( 'Restaurant Booking', 'restaurant-booking' ),
                __( 'Bookings', 'restaurant-booking' ),
                $capability,
                'rb-dashboard',
                array( $this, 'render_dashboard' ),
                'dashicons-calendar-alt',
                3
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Dashboard', 'restaurant-booking' ),
                __( 'Dashboard', 'restaurant-booking' ),
                $capability,
                'rb-dashboard',
                array( $this, 'render_dashboard' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Bookings', 'restaurant-booking' ),
                __( 'Bookings', 'restaurant-booking' ),
                $capability,
                'rb-bookings',
                array( $this, 'render_bookings' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Locations', 'restaurant-booking' ),
                __( 'Locations', 'restaurant-booking' ),
                $capability,
                'rb-locations',
                array( $this, 'render_locations' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Settings', 'restaurant-booking' ),
                __( 'Settings', 'restaurant-booking' ),
                $capability,
                'rb-settings',
                array( $this, 'render_settings' )
            );

            add_submenu_page(
                'rb-dashboard',
                __( 'Reports', 'restaurant-booking' ),
                __( 'Reports', 'restaurant-booking' ),
                $capability,
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

            $currency_symbol = apply_filters( 'rb_booking_currency', '$', 0 );
            $currency_code   = apply_filters( 'rb_booking_currency_code', 'USD', 0 );

            $badge_counts = apply_filters(
                'rb_admin_menu_badge_counts',
                array(
                    'rb-bookings' => $this->get_pending_badge_count(),
                )
            );

            wp_localize_script(
                'rb-modern-admin',
                'rbAdmin',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'rb_admin_nonce' ),
                    'strings'  => array(
                        'loading'         => __( 'Loading…', 'restaurant-booking' ),
                        'error'           => __( 'Error loading data', 'restaurant-booking' ),
                        'bookings'        => __( 'Bookings', 'restaurant-booking' ),
                        'revenue'         => __( 'Revenue', 'restaurant-booking' ),
                        'occupancy'       => __( 'Occupancy', 'restaurant-booking' ),
                        'tables'          => __( 'Tables', 'restaurant-booking' ),
                        'tablesHelp'      => __( 'Total tables in this location', 'restaurant-booking' ),
                        'pending'         => __( 'Pending', 'restaurant-booking' ),
                        'pendingHelp'     => __( 'Awaiting confirmation', 'restaurant-booking' ),
                        'emptyBookings'   => __( 'No bookings match the current filters.', 'restaurant-booking' ),
                        'locationsEmpty'  => __( 'No locations available yet.', 'restaurant-booking' ),
                        'settingsSaved'   => __( 'Settings saved successfully.', 'restaurant-booking' ),
                        'settingsReset'   => __( 'Settings restored to defaults.', 'restaurant-booking' ),
                        'locationSaved'   => __( 'Location details saved.', 'restaurant-booking' ),
                        'locationReset'   => __( 'Location form reset.', 'restaurant-booking' ),
                        'peakTime'        => __( 'Peak dining time', 'restaurant-booking' ),
                        'sentiment'       => __( 'Guest sentiment', 'restaurant-booking' ),
                    ),
                    'bookings' => array(
                        'perPage'      => 20,
                        'statusLabels' => array(
                            'pending'   => __( 'Pending', 'restaurant-booking' ),
                            'confirmed' => __( 'Confirmed', 'restaurant-booking' ),
                            'completed' => __( 'Completed', 'restaurant-booking' ),
                            'cancelled' => __( 'Cancelled', 'restaurant-booking' ),
                        ),
                    ),
                    'currency' => array(
                        'code'   => $currency_code,
                        'symbol' => $currency_symbol,
                    ),
                    'badges'  => array_map( 'intval', $badge_counts ),
                    'reports' => array(
                        'defaultRange' => 30,
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
                $formatted = array_map( array( $this, 'format_location_stats' ), $locations );

                wp_send_json_success(
                    array(
                        'locations' => $formatted,
                        'summary'   => $this->build_dashboard_summary( $formatted ),
                    )
                );
            } catch ( Exception $exception ) {
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    500
                );
            }
        }

        public function ajax_get_bookings() {
            check_ajax_referer( 'rb_admin_nonce', 'nonce' );

            $location  = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
            $status    = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
            $page      = max( 1, (int) ( $_POST['page'] ?? 1 ) );
            $search    = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
            $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
            $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );
            $per_page  = isset( $_POST['per_page'] ) ? max( 1, min( 100, (int) $_POST['per_page'] ) ) : 20;
            $sort_by   = sanitize_key( wp_unslash( $_POST['sort_by'] ?? '' ) );
            $sort_order = sanitize_key( wp_unslash( $_POST['sort_order'] ?? '' ) );

            $args = array(
                'search'    => $search,
                'date_from' => $date_from,
                'date_to'   => $date_to,
                'per_page'  => $per_page,
            );

            if ( ! empty( $sort_by ) ) {
                $args['sort_by'] = $sort_by;
            }

            if ( ! empty( $sort_order ) ) {
                $args['sort_order'] = $sort_order;
            }

            try {
                $bookings = RB_Booking::get_admin_bookings( $location, $status, $page, $args );
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

            try {
                $locations = RB_Location::get_all_locations();

                $formatted      = array();
                $total_tables   = 0;
                $total_seats    = 0;
                $open_locations = 0;

                foreach ( $locations as $location ) {
                    $tables = class_exists( 'RB_Table' ) ? RB_Table::count_by_location( $location->id ) : 0;

                    $formatted[] = array(
                        'id'       => $location->id,
                        'name'     => $location->name,
                        'address'  => isset( $location->address ) ? $location->address : '',
                        'phone'    => isset( $location->phone ) ? $location->phone : '',
                        'email'    => isset( $location->email ) ? $location->email : '',
                        'capacity' => isset( $location->capacity ) ? (int) $location->capacity : 0,
                        'status'   => isset( $location->status ) ? $location->status : 'active',
                        'tables'   => $tables,
                    );

                    $total_tables += $tables;
                    $total_seats  += isset( $location->capacity ) ? (int) $location->capacity : 0;

                    if ( empty( $location->status ) || 'inactive' !== $location->status ) {
                        $open_locations++;
                    }
                }

                wp_send_json_success(
                    array(
                        'locations' => $formatted,
                        'summary'   => array(
                            'total_tables'   => $total_tables,
                            'total_seats'    => $total_seats,
                            'open_locations' => $open_locations,
                        ),
                    )
                );
            } catch ( Exception $exception ) {
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    500
                );
            }
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

            $today_bookings     = RB_Booking::count_by_date_and_location( $today, $location->id );
            $yesterday_bookings = RB_Booking::count_by_date_and_location( $yesterday, $location->id );
            $bookings_trend     = $yesterday_bookings > 0 ? round( ( ( $today_bookings - $yesterday_bookings ) / max( 1, $yesterday_bookings ) ) * 100 ) : 0;
            $today_revenue      = RB_Booking::sum_revenue_by_date_and_location( $today, $location->id );
            $yesterday_revenue  = RB_Booking::sum_revenue_by_date_and_location( $yesterday, $location->id );
            $revenue_trend      = $yesterday_revenue > 0 ? round( ( ( $today_revenue - $yesterday_revenue ) / max( 1, $yesterday_revenue ) ) * 100 ) : 0;

            $tables_class_exists    = class_exists( 'RB_Table' );
            $occupancy_method       = $tables_class_exists && method_exists( 'RB_Table', 'get_occupancy_rate' );
            $table_count_method     = $tables_class_exists && method_exists( 'RB_Table', 'count_by_location' );
            $occupancy              = $occupancy_method ? (float) RB_Table::get_occupancy_rate( $location->id ) : 0.0;
            $previous_occupancy     = $occupancy_method ? (float) RB_Table::get_occupancy_rate( $location->id, $yesterday ) : 0.0;
            $occupancy_delta        = $occupancy - $previous_occupancy;
            $tables_count           = $table_count_method ? (int) RB_Table::count_by_location( $location->id ) : 0;
            $pending_total          = method_exists( 'RB_Booking', 'count_by_status' ) ? RB_Booking::count_by_status( 'pending', $location->id ) : 0;

            return array(
                'id'      => $location->id,
                'name'    => $location->name,
                'address' => isset( $location->address ) ? $location->address : '',
                'phone'   => isset( $location->phone ) ? $location->phone : '',
                'email'   => isset( $location->email ) ? $location->email : '',
                'status'  => isset( $location->status ) ? $location->status : 'active',
                'stats'   => array(
                    'bookings'        => $today_bookings,
                    'bookings_trend'  => $bookings_trend,
                    'revenue'         => $this->format_currency( $today_revenue ),
                    'revenue_value'   => (float) $today_revenue,
                    'revenue_trend'   => $revenue_trend,
                    'occupancy'       => $occupancy,
                    'occupancy_trend' => $occupancy_delta,
                    'tables'          => $tables_count,
                    'pending'         => $pending_total,
                ),
            );
        }

        protected function build_dashboard_summary( $locations ) {
            $summary = array(
                'total_bookings'         => 0,
                'total_revenue'          => 0.0,
                'total_revenue_formatted'=> $this->format_currency( 0 ),
                'average_occupancy'      => 0.0,
                'bookings_change'        => 0.0,
                'revenue_change'         => 0.0,
                'occupancy_change'       => 0.0,
                'pending_total'          => 0,
                'top_location'           => '',
                'top_location_bookings'  => 0,
                'peak_time'              => '',
                'peak_time_label'        => '',
                'sentiment_score'        => '',
                'recommendation'         => '',
            );

            if ( empty( $locations ) ) {
                return apply_filters( 'rb_admin_dashboard_summary', $summary, $locations );
            }

            $location_count      = count( $locations );
            $occupancy_sum       = 0.0;
            $bookings_trend_sum  = 0.0;
            $revenue_trend_sum   = 0.0;
            $occupancy_trend_sum = 0.0;

            foreach ( $locations as $location ) {
                $stats = isset( $location['stats'] ) ? $location['stats'] : array();

                $bookings = isset( $stats['bookings'] ) ? (int) $stats['bookings'] : 0;
                $summary['total_bookings'] += $bookings;

                $revenue_value = isset( $stats['revenue_value'] ) ? (float) $stats['revenue_value'] : 0.0;
                $summary['total_revenue'] += $revenue_value;

                $pending = isset( $stats['pending'] ) ? (int) $stats['pending'] : 0;
                $summary['pending_total'] += $pending;

                $occupancy = isset( $stats['occupancy'] ) ? (float) $stats['occupancy'] : 0.0;
                $occupancy_sum += $occupancy;

                $bookings_trend_sum  += isset( $stats['bookings_trend'] ) ? (float) $stats['bookings_trend'] : 0.0;
                $revenue_trend_sum   += isset( $stats['revenue_trend'] ) ? (float) $stats['revenue_trend'] : 0.0;
                $occupancy_trend_sum += isset( $stats['occupancy_trend'] ) ? (float) $stats['occupancy_trend'] : 0.0;

                if ( $bookings > $summary['top_location_bookings'] ) {
                    $summary['top_location_bookings'] = $bookings;
                    $summary['top_location']          = isset( $location['name'] ) ? $location['name'] : '';
                }
            }

            if ( $location_count > 0 ) {
                $summary['average_occupancy'] = round( $occupancy_sum / $location_count, 1 );
                $summary['bookings_change']   = round( $bookings_trend_sum / $location_count, 1 );
                $summary['revenue_change']    = round( $revenue_trend_sum / $location_count, 1 );
                $summary['occupancy_change']  = round( $occupancy_trend_sum / $location_count, 1 );
            }

            $summary['total_revenue_formatted'] = $this->format_currency( $summary['total_revenue'] );

            return apply_filters( 'rb_admin_dashboard_summary', $summary, $locations );
        }

        protected function get_pending_badge_count() {
            if ( ! method_exists( 'RB_Booking', 'count_by_status' ) ) {
                return 0;
            }

            return (int) RB_Booking::count_by_status( 'pending' );
        }

        protected function format_currency( $amount ) {
            $amount   = (float) $amount;
            $decimals = (int) apply_filters( 'rb_booking_currency_decimals', 2 );
            $symbol   = apply_filters( 'rb_booking_currency', '$', 0 );
            $position = apply_filters( 'rb_booking_currency_position', 'left' );

            $formatted = number_format_i18n( $amount, $decimals );

            switch ( $position ) {
                case 'left_space':
                    return trim( $symbol . ' ' . $formatted );
                case 'right':
                    return trim( $formatted . $symbol );
                case 'right_space':
                    return trim( $formatted . ' ' . $symbol );
                case 'left':
                default:
                    return trim( $symbol . $formatted );
            }
        }
    }
}
