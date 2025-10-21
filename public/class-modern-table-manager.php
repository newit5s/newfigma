<?php
/**
 * Modern table & customer management integration (Phase 6)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Modern_Table_Manager' ) ) {

    class RB_Modern_Table_Manager {

        /**
         * Current portal user context.
         *
         * @var array
         */
        public $current_user = array();

        /**
         * AJAX nonce handle.
         *
         * @var string
         */
        protected $ajax_nonce = 'rb_table_manager_nonce';

        /**
         * Cached customer records.
         *
         * @var array
         */
        protected $customer_cache = array();

        /**
         * Constructor.
         */
        public function __construct() {
            add_action( 'init', array( $this, 'maybe_render_view' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

            add_filter( 'rb_portal_render_view', array( $this, 'render_portal_view' ), 10, 4 );

            add_action( 'wp_ajax_rb_get_tables_layout', array( $this, 'ajax_get_tables_layout' ) );
            add_action( 'wp_ajax_rb_save_table_layout', array( $this, 'ajax_save_table_layout' ) );
            add_action( 'wp_ajax_rb_get_customers', array( $this, 'ajax_get_customers' ) );
            add_action( 'wp_ajax_rb_update_customer_status', array( $this, 'ajax_update_customer_status' ) );

            add_action( 'wp_ajax_nopriv_rb_get_tables_layout', array( $this, 'unauthorized_response' ) );
            add_action( 'wp_ajax_nopriv_rb_save_table_layout', array( $this, 'unauthorized_response' ) );
            add_action( 'wp_ajax_nopriv_rb_get_customers', array( $this, 'unauthorized_response' ) );
            add_action( 'wp_ajax_nopriv_rb_update_customer_status', array( $this, 'unauthorized_response' ) );
        }

        /**
         * Render requested portal view directly.
         */
        public function maybe_render_view() {
            if ( ! $this->is_direct_request() ) {
                return;
            }

            $view = $this->get_requested_view();

            $this->current_user = $this->resolve_current_user();
            if ( ! $this->is_user_authenticated() ) {
                $this->redirect_to_portal_login();
                exit;
            }

            $this->enqueue_assets();

            add_filter(
                'pre_get_document_title',
                static function () use ( $view ) {
                    $title = 'customers' === $view ? __( 'Customer Management – Restaurant Manager', 'restaurant-booking' ) : __( 'Table Management – Restaurant Manager', 'restaurant-booking' );
                    return $title;
                }
            );

            $template = 'customers' === $view ? 'customer-profiles.php' : 'table-management.php';
            $template_path = plugin_dir_path( __FILE__ ) . 'partials/' . $template;
            if ( file_exists( $template_path ) ) {
                include $template_path;
            }

            exit;
        }

        /**
         * Enqueue assets for table & customer management views.
         */
        public function enqueue_assets() {
            if ( ! $this->should_load_assets() ) {
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
                'rb-table-management',
                $base_url . 'assets/css/table-management.css',
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
                'rb-table-management',
                $base_url . 'assets/js/table-management.js',
                array( 'rb-theme-manager' ),
                $version,
                true
            );

            wp_enqueue_script(
                'rb-customer-management',
                $base_url . 'assets/js/customer-management.js',
                array( 'rb-theme-manager' ),
                $version,
                true
            );

            $location_id = $this->get_current_location();
            $tables      = $this->get_tables_for_location( $location_id );
            $customers   = $this->get_customers_for_segment( 'all' );

            wp_localize_script(
                'rb-table-management',
                'rbTableManager',
                array(
                    'ajax_url'          => admin_url( 'admin-ajax.php' ),
                    'nonce'             => wp_create_nonce( $this->ajax_nonce ),
                    'current_location'  => $location_id,
                    'tables'            => $tables,
                    'strings'           => array(
                        'seats'           => __( 'seats', 'restaurant-booking' ),
                        'no_tables'       => __( 'No tables defined yet.', 'restaurant-booking' ),
                        'focus'           => __( 'Focus', 'restaurant-booking' ),
                        'total_tables'    => __( 'Total tables', 'restaurant-booking' ),
                        'total_capacity'  => __( 'Total seats', 'restaurant-booking' ),
                        'occupancy'       => __( 'Occupied', 'restaurant-booking' ),
                        'occupancy_rate'  => __( 'Occupancy rate', 'restaurant-booking' ),
                        'layout_saved'    => __( 'Layout saved successfully.', 'restaurant-booking' ),
                        'layout_reset'    => __( 'Layout reset to last saved version.', 'restaurant-booking' ),
                        'table_added'     => __( 'Table added to layout.', 'restaurant-booking' ),
                        'table_deleted'   => __( 'Table removed from layout.', 'restaurant-booking' ),
                        'table_duplicated'=> __( 'Table duplicated.', 'restaurant-booking' ),
                        'table_rotated'   => __( 'Table rotated.', 'restaurant-booking' ),
                        'status_updated'  => __( 'Table status updated.', 'restaurant-booking' ),
                        'save_failed'     => __( 'Unable to save layout.', 'restaurant-booking' ),
                        'missing_ajax'    => __( 'AJAX endpoint unavailable.', 'restaurant-booking' ),
                        'duplicate'       => __( 'Duplicate', 'restaurant-booking' ),
                        'rotate'          => __( 'Rotate', 'restaurant-booking' ),
                        'delete'          => __( 'Delete', 'restaurant-booking' ),
                        'toggle_status'   => __( 'Toggle status', 'restaurant-booking' ),
                        'table_properties'=> __( 'Update table attributes and seating details.', 'restaurant-booking' ),
                        'select_table_hint'=> __( 'Choose a table from the floor plan to edit its details.', 'restaurant-booking' ),
                        'table'           => __( 'Table', 'restaurant-booking' ),
                        'statuses'        => array(
                            'available' => __( 'Available', 'restaurant-booking' ),
                            'reserved'  => __( 'Reserved', 'restaurant-booking' ),
                            'occupied'  => __( 'Occupied', 'restaurant-booking' ),
                            'cleaning'  => __( 'Cleaning', 'restaurant-booking' ),
                        ),
                        'capacity'        => __( 'Capacity', 'restaurant-booking' ),
                        'status'          => __( 'Status', 'restaurant-booking' ),
                        'position'        => __( 'Position', 'restaurant-booking' ),
                        'shape'           => __( 'Shape', 'restaurant-booking' ),
                        'table_properties_title' => __( 'Table properties', 'restaurant-booking' ),
                        'no_table_selected'      => __( 'No table selected', 'restaurant-booking' ),
                    ),
                )
            );

            wp_localize_script(
                'rb-customer-management',
                'rbCustomerManager',
                array(
                    'ajax_url'  => admin_url( 'admin-ajax.php' ),
                    'nonce'     => wp_create_nonce( $this->ajax_nonce ),
                    'customers' => $customers,
                    'strings'   => array(
                        'add_customer'       => __( 'Launching add customer flow…', 'restaurant-booking' ),
                        'export_started'     => __( 'Preparing customer export…', 'restaurant-booking' ),
                        'import_started'     => __( 'Upload a CSV file to import customers.', 'restaurant-booking' ),
                        'no_customers'       => __( 'No customers match the filters.', 'restaurant-booking' ),
                        'select_customer'    => __( 'Select a customer', 'restaurant-booking' ),
                        'select_customer_hint'=> __( 'Choose a customer from the list to view their history.', 'restaurant-booking' ),
                        'recent_bookings'    => __( 'Recent bookings', 'restaurant-booking' ),
                        'notes_preferences'  => __( 'Notes & preferences', 'restaurant-booking' ),
                        'total_visits'       => __( 'Total visits', 'restaurant-booking' ),
                        'total_spent'        => __( 'Total spent', 'restaurant-booking' ),
                        'avg_party_size'     => __( 'Avg party size', 'restaurant-booking' ),
                        'last_visit'         => __( 'Last visit', 'restaurant-booking' ),
                        'no_history'         => __( 'No bookings recorded yet.', 'restaurant-booking' ),
                        'staff_notes'        => __( 'Staff notes', 'restaurant-booking' ),
                        'preferences'        => __( 'Preferences', 'restaurant-booking' ),
                        'no_notes'           => __( 'No notes added yet.', 'restaurant-booking' ),
                        'status_updated'     => __( 'Customer status updated.', 'restaurant-booking' ),
                        'update_failed'      => __( 'Unable to update status.', 'restaurant-booking' ),
                        'mark_vip'           => __( 'Mark VIP', 'restaurant-booking' ),
                        'mark_regular'       => __( 'Mark Regular', 'restaurant-booking' ),
                        'mark_blacklist'     => __( 'Add to Blacklist', 'restaurant-booking' ),
                        'add_note'           => __( 'Add Note', 'restaurant-booking' ),
                        'note_prompt'        => __( 'Open note editor modal.', 'restaurant-booking' ),
                        'table'              => __( 'Table', 'restaurant-booking' ),
                        'party'              => __( 'Party', 'restaurant-booking' ),
                        'statuses'           => array(
                            'vip'       => __( 'VIP', 'restaurant-booking' ),
                            'regular'   => __( 'Regular', 'restaurant-booking' ),
                            'blacklist' => __( 'Blacklist', 'restaurant-booking' ),
                        ),
                    ),
                )
            );
        }

        /**
         * Render via shortcode interception.
         *
         * @param string $content Existing content.
         * @param string $view    Requested view identifier.
         * @param array  $atts    Shortcode attributes.
         * @param mixed  $auth    Portal auth instance.
         *
         * @return string
         */
        public function render_portal_view( $content, $view, $atts, $auth ) {
            if ( ! in_array( $view, array( 'tables', 'customers' ), true ) ) {
                return $content;
            }

            $this->current_user = $this->resolve_current_user();
            if ( ! $this->is_user_authenticated() ) {
                return $content;
            }

            $this->enqueue_assets();

            ob_start();
            $is_embed = true;
            $template = 'customers' === $view ? 'customer-profiles.php' : 'table-management.php';
            $template_path = plugin_dir_path( __FILE__ ) . 'partials/' . $template;
            if ( file_exists( $template_path ) ) {
                include $template_path;
            }

            return ob_get_clean();
        }

        /**
         * AJAX: return tables for a location.
         */
        public function ajax_get_tables_layout() {
            $this->verify_ajax_request();
            $this->ensure_user_can_manage();

            $location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
            $tables   = $this->get_tables_for_location( $location );

            wp_send_json_success( array( 'tables' => $tables ) );
        }

        /**
         * AJAX: persist table layout.
         */
        public function ajax_save_table_layout() {
            $this->verify_ajax_request();
            $this->ensure_user_can_manage();

            $location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
            $payload  = wp_unslash( $_POST['tables'] ?? '[]' );
            $tables   = json_decode( $payload, true );

            if ( ! is_array( $tables ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid table data supplied.', 'restaurant-booking' ) ), 400 );
            }

            $persisted = false;

            if ( class_exists( 'RB_Table' ) ) {
                if ( method_exists( 'RB_Table', 'update_table_layout' ) ) {
                    $persisted = (bool) RB_Table::update_table_layout( $location, $tables );
                } elseif ( method_exists( 'RB_Table', 'update_table' ) ) {
                    foreach ( $tables as $table ) {
                        $id = isset( $table['id'] ) ? $table['id'] : 0;
                        if ( ! $id ) {
                            continue;
                        }
                        RB_Table::update_table( $id, $table );
                        $persisted = true;
                    }
                }
            }

            wp_send_json_success(
                array(
                    'persisted' => $persisted,
                )
            );
        }

        /**
         * AJAX: return customer listing.
         */
        public function ajax_get_customers() {
            $this->verify_ajax_request();
            $this->ensure_user_can_manage();

            $segment   = sanitize_key( wp_unslash( $_POST['segment'] ?? 'all' ) );
            $customers = $this->get_customers_for_segment( $segment );

            wp_send_json_success( array( 'customers' => $customers ) );
        }

        /**
         * AJAX: update customer status.
         */
        public function ajax_update_customer_status() {
            $this->verify_ajax_request();
            $this->ensure_user_can_manage();

            $customer_id = absint( $_POST['customer_id'] ?? 0 );
            $status      = sanitize_key( wp_unslash( $_POST['status'] ?? 'regular' ) );

            if ( ! $customer_id ) {
                wp_send_json_error( array( 'message' => __( 'Missing customer identifier.', 'restaurant-booking' ) ), 400 );
            }

            if ( class_exists( 'RB_Customer' ) ) {
                if ( method_exists( 'RB_Customer', 'update_status' ) ) {
                    RB_Customer::update_status( $customer_id, $status );
                } elseif ( method_exists( 'RB_Customer', 'update_customer' ) ) {
                    RB_Customer::update_customer( $customer_id, array( 'status' => $status ) );
                }
            }

            wp_send_json_success();
        }

        /**
         * Unauthorized response for unauthenticated AJAX requests.
         */
        public function unauthorized_response() {
            wp_send_json_error( array( 'message' => __( 'Authentication required.', 'restaurant-booking' ) ), 401 );
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

        /**
         * Fetch tables for a given location.
         *
         * @param int|string $location Location identifier.
         *
         * @return array
         */
        public function get_tables_for_location( $location ) {
            $tables = array();

            if ( class_exists( 'RB_Table' ) ) {
                if ( method_exists( 'RB_Table', 'get_tables_by_location' ) ) {
                    $records = RB_Table::get_tables_by_location( $location );
                } elseif ( method_exists( 'RB_Table', 'get_tables' ) ) {
                    $records = RB_Table::get_tables( $location );
                } else {
                    $records = array();
                }

                if ( ! empty( $records ) && ( is_array( $records ) || $records instanceof Traversable ) ) {
                    foreach ( $records as $record ) {
                        $tables[] = $this->normalize_table_record( $record );
                    }
                }
            }

            if ( empty( $tables ) ) {
                $tables = $this->get_default_tables();
            }

            return $tables;
        }

        /**
         * Return customers filtered by segment.
         *
         * @param string $segment Segment identifier.
         *
         * @return array
         */
        public function get_customers_for_segment( $segment = 'all' ) {
            $customers = $this->get_all_customers();

            if ( 'all' === $segment ) {
                return $customers;
            }

            return array_values(
                array_filter(
                    $customers,
                    static function ( $customer ) use ( $segment ) {
                        return isset( $customer['status'] ) && $segment === $customer['status'];
                    }
                )
            );
        }

        /**
         * Retrieve all customer records from cache or source.
         *
         * @return array
         */
        protected function get_all_customers() {
            if ( ! empty( $this->customer_cache ) ) {
                return $this->customer_cache;
            }

            $customers = array();

            if ( class_exists( 'RB_Customer' ) ) {
                if ( method_exists( 'RB_Customer', 'get_all' ) ) {
                    $records = RB_Customer::get_all();
                } elseif ( method_exists( 'RB_Customer', 'get_customers' ) ) {
                    $records = RB_Customer::get_customers();
                } else {
                    $records = array();
                }

                if ( ! empty( $records ) && ( is_array( $records ) || $records instanceof Traversable ) ) {
                    foreach ( $records as $record ) {
                        $customers[] = $this->normalize_customer_record( $record );
                    }
                }
            }

            if ( empty( $customers ) ) {
                $customers = $this->get_default_customers();
            }

            $this->customer_cache = $customers;

            return $this->customer_cache;
        }

        /**
         * Normalize table record into array structure for front-end usage.
         *
         * @param mixed $record Table record.
         *
         * @return array
         */
        protected function normalize_table_record( $record ) {
            $record = (array) $record;

            return array(
                'id'          => $record['id'] ?? ( $record['table_id'] ?? uniqid( 'table_', true ) ),
                'name'        => $record['name'] ?? ( $record['label'] ?? __( 'Table', 'restaurant-booking' ) ),
                'label'       => $record['label'] ?? ( $record['name'] ?? __( 'Table', 'restaurant-booking' ) ),
                'capacity'    => (int) ( $record['capacity'] ?? ( $record['seats'] ?? 0 ) ),
                'status'      => $record['status'] ?? 'available',
                'shape'       => $record['shape'] ?? 'rectangle',
                'position_x'  => (int) ( $record['position_x'] ?? ( $record['x'] ?? 120 ) ),
                'position_y'  => (int) ( $record['position_y'] ?? ( $record['y'] ?? 120 ) ),
                'width'       => isset( $record['width'] ) ? (int) $record['width'] : null,
                'height'      => isset( $record['height'] ) ? (int) $record['height'] : null,
                'rotation'    => isset( $record['rotation'] ) ? (int) $record['rotation'] : 0,
            );
        }

        /**
         * Normalize customer record.
         *
         * @param mixed $record Customer record.
         *
         * @return array
         */
        protected function normalize_customer_record( $record ) {
            $record = (array) $record;
            $name   = $record['name'] ?? ( $record['full_name'] ?? __( 'Guest', 'restaurant-booking' ) );

            return array(
                'id'             => $record['id'] ?? ( $record['customer_id'] ?? uniqid( 'customer_', true ) ),
                'name'           => $name,
                'initials'       => $record['initials'] ?? strtoupper( mb_substr( wp_strip_all_tags( $name ), 0, 2, 'UTF-8' ) ),
                'email'          => $record['email'] ?? '',
                'phone'          => $record['phone'] ?? '',
                'status'         => $record['status'] ?? 'regular',
                'total_visits'   => (int) ( $record['total_visits'] ?? ( $record['visits'] ?? 0 ) ),
                'total_spent'    => $record['total_spent'] ?? ( $record['lifetime_value'] ?? 0 ),
                'avg_party_size' => $record['avg_party_size'] ?? ( $record['average_party'] ?? 0 ),
                'last_visit'     => $record['last_visit'] ?? ( $record['last_booking'] ?? __( 'Never', 'restaurant-booking' ) ),
                'notes'          => $record['notes'] ?? '',
                'preferences'    => isset( $record['preferences'] ) && is_array( $record['preferences'] ) ? $record['preferences'] : array(),
                'history'        => isset( $record['history'] ) && is_array( $record['history'] ) ? $record['history'] : array(),
                'tags'           => isset( $record['tags'] ) && is_array( $record['tags'] ) ? $record['tags'] : array(),
            );
        }

        /**
         * Provide fallback tables when backend data is unavailable.
         *
         * @return array
         */
        protected function get_default_tables() {
            return array(
                array(
                    'id'         => 1,
                    'label'      => 'T1',
                    'name'       => 'Table 1',
                    'capacity'   => 4,
                    'status'     => 'available',
                    'shape'      => 'rectangle',
                    'position_x' => 120,
                    'position_y' => 80,
                ),
                array(
                    'id'         => 2,
                    'label'      => 'T2',
                    'name'       => 'Table 2',
                    'capacity'   => 2,
                    'status'     => 'occupied',
                    'shape'      => 'round',
                    'position_x' => 280,
                    'position_y' => 120,
                ),
                array(
                    'id'         => 3,
                    'label'      => 'T3',
                    'name'       => 'Booth 3',
                    'capacity'   => 6,
                    'status'     => 'reserved',
                    'shape'      => 'rectangle',
                    'position_x' => 160,
                    'position_y' => 260,
                ),
                array(
                    'id'         => 4,
                    'label'      => 'T4',
                    'name'       => 'Table 4',
                    'capacity'   => 8,
                    'status'     => 'available',
                    'shape'      => 'rectangle',
                    'position_x' => 360,
                    'position_y' => 260,
                ),
            );
        }

        /**
         * Provide fallback customer dataset.
         *
         * @return array
         */
        protected function get_default_customers() {
            return array(
                array(
                    'id'             => 101,
                    'name'           => 'John Doe',
                    'initials'       => 'JD',
                    'email'          => 'john.doe@example.com',
                    'phone'          => '+1 234 567 8900',
                    'status'         => 'vip',
                    'total_visits'   => 24,
                    'total_spent'    => '$2,400',
                    'avg_party_size' => 3.2,
                    'last_visit'     => 'March 10, 2024',
                    'notes'          => __( 'Prefers window seating and Rioja red wine.', 'restaurant-booking' ),
                    'preferences'    => array( __( 'Window table', 'restaurant-booking' ), __( 'Notify sommelier on arrival', 'restaurant-booking' ) ),
                    'tags'           => array( 'VIP', 'Wine Club' ),
                    'history'        => array(
                        array(
                            'date'       => '2024-03-10',
                            'location'   => __( 'Downtown', 'restaurant-booking' ),
                            'table'      => 'T1',
                            'party_size' => 4,
                            'status'     => __( 'Completed', 'restaurant-booking' ),
                        ),
                        array(
                            'date'       => '2024-02-18',
                            'location'   => __( 'Downtown', 'restaurant-booking' ),
                            'table'      => 'T3',
                            'party_size' => 2,
                            'status'     => __( 'Completed', 'restaurant-booking' ),
                        ),
                    ),
                ),
                array(
                    'id'             => 102,
                    'name'           => 'Sarah Wilson',
                    'initials'       => 'SW',
                    'email'          => 'sarah.wilson@example.com',
                    'phone'          => '+1 234 567 8901',
                    'status'         => 'regular',
                    'total_visits'   => 8,
                    'total_spent'    => '$640',
                    'avg_party_size' => 3,
                    'last_visit'     => 'February 28, 2024',
                    'notes'          => __( 'Allergic to shellfish.', 'restaurant-booking' ),
                    'preferences'    => array( __( 'Booth seating', 'restaurant-booking' ) ),
                    'tags'           => array( 'Loyalty' ),
                    'history'        => array(
                        array(
                            'date'       => '2024-02-28',
                            'location'   => __( 'Downtown', 'restaurant-booking' ),
                            'table'      => 'T5',
                            'party_size' => 3,
                            'status'     => __( 'Completed', 'restaurant-booking' ),
                        ),
                    ),
                ),
                array(
                    'id'             => 103,
                    'name'           => 'Michael Brown',
                    'initials'       => 'MB',
                    'email'          => 'michael.brown@example.com',
                    'phone'          => '+1 234 567 8902',
                    'status'         => 'blacklist',
                    'total_visits'   => 3,
                    'total_spent'    => '$180',
                    'avg_party_size' => 2,
                    'last_visit'     => 'January 14, 2024',
                    'notes'          => __( 'Marked as no-show twice in a row.', 'restaurant-booking' ),
                    'preferences'    => array(),
                    'tags'           => array( 'Watchlist' ),
                    'history'        => array(
                        array(
                            'date'       => '2024-01-14',
                            'location'   => __( 'Downtown', 'restaurant-booking' ),
                            'table'      => 'T2',
                            'party_size' => 2,
                            'status'     => __( 'No-show', 'restaurant-booking' ),
                        ),
                    ),
                ),
            );
        }

        /**
         * Verify AJAX nonce.
         */
        protected function verify_ajax_request() {
            check_ajax_referer( $this->ajax_nonce, 'nonce' );
            $this->current_user = $this->resolve_current_user();
        }

        /**
         * Ensure user has permissions.
         */
        protected function ensure_user_can_manage() {
            $allowed = current_user_can( 'manage_options' );
            $allowed = apply_filters( 'rb_table_manager_user_can', $allowed, $this->current_user );

            if ( ! $allowed ) {
                wp_send_json_error( array( 'message' => __( 'You do not have permission to manage tables or customers.', 'restaurant-booking' ) ), 403 );
            }
        }

        /**
         * Resolve current user.
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
         * Whether current request is for tables/customers view.
         *
         * @return bool
         */
        protected function is_direct_request() {
            if ( ! isset( $_GET['rb_portal'] ) ) {
                return false;
            }

            $view = sanitize_key( wp_unslash( $_GET['rb_portal'] ) );
            return in_array( $view, array( 'tables', 'customers' ), true );
        }

        /**
         * Requested view slug.
         *
         * @return string
         */
        protected function get_requested_view() {
            $view = isset( $_GET['rb_portal'] ) ? sanitize_key( wp_unslash( $_GET['rb_portal'] ) ) : 'tables';
            return in_array( $view, array( 'tables', 'customers' ), true ) ? $view : 'tables';
        }

        /**
         * Whether output is being embedded via shortcode.
         *
         * @return bool
         */
        protected function is_embed_context() {
            return did_action( 'rb_portal_render_view' ) > 0;
        }

        /**
         * Determine if assets should load.
         *
         * @return bool
         */
        protected function should_load_assets() {
            return $this->is_direct_request() || $this->is_embed_context();
        }

        /**
         * Ensure the current user is authenticated.
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
         * Redirect to login when authentication fails.
         */
        protected function redirect_to_portal_login() {
            $login_url = home_url( '/portal' );
            wp_safe_redirect( $login_url );
        }
    }

}
