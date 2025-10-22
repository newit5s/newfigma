<?php
/**
 * Modern admin integration for the Restaurant Booking plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Modern_Admin' ) ) {

    class RB_Modern_Admin {

        use RB_Asset_Loader;

        /**
         * Cached plugin settings for rendering.
         *
         * @var array|null
         */
        protected $cached_settings = null;

        /**
         * Tracks whether settings registration has been performed.
         *
         * @var bool
         */
        protected $settings_registered = false;

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

            add_action( 'wp_ajax_rb_admin_get_dashboard', array( $this, 'ajax_get_dashboard' ) );
            add_action( 'wp_ajax_rb_admin_get_bookings', array( $this, 'ajax_get_bookings' ) );
            add_action( 'wp_ajax_rb_admin_get_locations', array( $this, 'ajax_get_locations' ) );

            $this->register_settings();
        }

        public function register_settings() {
            if ( $this->settings_registered ) {
                return;
            }

            $this->settings_registered = true;

            $default_settings = function_exists( 'restaurant_booking_get_default_settings' )
                ? restaurant_booking_get_default_settings()
                : array();

            $args = array(
                'type'    => 'array',
                'default' => $default_settings,
            );

            if ( function_exists( 'restaurant_booking_sanitize_settings' ) ) {
                $args['sanitize_callback'] = 'restaurant_booking_sanitize_settings';
            }

            register_setting( 'restaurant_booking_settings', 'restaurant_booking_settings', $args );

            add_settings_section(
                'restaurant_booking_settings_general',
                __( 'General settings', 'restaurant-booking' ),
                array( $this, 'render_general_settings_intro' ),
                'restaurant_booking_settings'
            );

            add_settings_field(
                'restaurant_booking_settings_restaurant_name',
                __( 'Restaurant name', 'restaurant-booking' ),
                array( $this, 'render_text_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_general',
                array(
                    'label_for'   => 'restaurant_booking_settings_restaurant_name',
                    'option_key'  => 'restaurant_name',
                    'placeholder' => __( 'e.g. Modern Bistro', 'restaurant-booking' ),
                    'description' => __( 'Used in confirmation emails and portal headers.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_default_currency',
                __( 'Default currency', 'restaurant-booking' ),
                array( $this, 'render_select_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_general',
                array(
                    'label_for'  => 'restaurant_booking_settings_default_currency',
                    'option_key' => 'default_currency',
                    'choices'    => array(
                        'USD' => __( 'US Dollar (USD)', 'restaurant-booking' ),
                        'EUR' => __( 'Euro (EUR)', 'restaurant-booking' ),
                        'GBP' => __( 'British Pound (GBP)', 'restaurant-booking' ),
                        'JPY' => __( 'Japanese Yen (JPY)', 'restaurant-booking' ),
                    ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_max_party_size',
                __( 'Maximum party size', 'restaurant-booking' ),
                array( $this, 'render_number_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_general',
                array(
                    'label_for'   => 'restaurant_booking_settings_max_party_size',
                    'option_key'  => 'max_party_size',
                    'min'         => 1,
                    'max'         => 30,
                    'description' => __( 'Largest group that can book online.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_buffer_time',
                __( 'Buffer time (minutes)', 'restaurant-booking' ),
                array( $this, 'render_number_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_general',
                array(
                    'label_for'   => 'restaurant_booking_settings_buffer_time',
                    'option_key'  => 'buffer_time',
                    'min'         => 0,
                    'max'         => 180,
                    'step'        => 5,
                    'description' => __( 'Minutes between bookings to reset tables.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_allow_walkins',
                __( 'Walk-in reservations', 'restaurant-booking' ),
                array( $this, 'render_checkbox_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_general',
                array(
                    'label_for'   => 'restaurant_booking_settings_allow_walkins',
                    'option_key'  => 'allow_walkins',
                    'label'       => __( 'Allow walk-in reservations', 'restaurant-booking' ),
                    'description' => __( 'When enabled, the floor plan reserves seats for guests without bookings.', 'restaurant-booking' ),
                )
            );

            add_settings_section(
                'restaurant_booking_settings_notifications',
                __( 'Notifications', 'restaurant-booking' ),
                array( $this, 'render_notifications_intro' ),
                'restaurant_booking_settings'
            );

            add_settings_field(
                'restaurant_booking_settings_reminder_hours',
                __( 'Reminder timing (hours)', 'restaurant-booking' ),
                array( $this, 'render_number_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_notifications',
                array(
                    'label_for'   => 'restaurant_booking_settings_reminder_hours',
                    'option_key'  => 'reminder_hours',
                    'min'         => 1,
                    'max'         => 168,
                    'description' => __( 'How many hours before arrival to send reminder messages.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_confirmation_template',
                __( 'Confirmation template', 'restaurant-booking' ),
                array( $this, 'render_textarea_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_notifications',
                array(
                    'label_for'   => 'restaurant_booking_settings_confirmation_template',
                    'option_key'  => 'confirmation_template',
                    'rows'        => 6,
                    'description' => __( 'Email body sent to guests when a booking is confirmed.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_send_sms',
                __( 'SMS reminders', 'restaurant-booking' ),
                array( $this, 'render_checkbox_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_notifications',
                array(
                    'label_for'   => 'restaurant_booking_settings_send_sms',
                    'option_key'  => 'send_sms',
                    'label'       => __( 'Send SMS reminder', 'restaurant-booking' ),
                    'description' => __( 'Requires a connected SMS provider.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_send_followup',
                __( 'Post-visit follow-up', 'restaurant-booking' ),
                array( $this, 'render_checkbox_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_notifications',
                array(
                    'label_for'   => 'restaurant_booking_settings_send_followup',
                    'option_key'  => 'send_followup',
                    'label'       => __( 'Send post-visit follow-up email', 'restaurant-booking' ),
                    'description' => __( 'Great for requesting reviews or feedback after the dining experience.', 'restaurant-booking' ),
                )
            );

            add_settings_section(
                'restaurant_booking_settings_advanced',
                __( 'Advanced options', 'restaurant-booking' ),
                array( $this, 'render_advanced_intro' ),
                'restaurant_booking_settings'
            );

            add_settings_field(
                'restaurant_booking_settings_auto_cancel_minutes',
                __( 'Auto-cancel no-shows (minutes)', 'restaurant-booking' ),
                array( $this, 'render_number_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_advanced',
                array(
                    'label_for'   => 'restaurant_booking_settings_auto_cancel_minutes',
                    'option_key'  => 'auto_cancel_minutes',
                    'min'         => 0,
                    'max'         => 240,
                    'step'        => 5,
                    'description' => __( 'Automatically release the table after this many minutes without arrival.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_hold_time_minutes',
                __( 'Hold time for walk-ins (minutes)', 'restaurant-booking' ),
                array( $this, 'render_number_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_advanced',
                array(
                    'label_for'   => 'restaurant_booking_settings_hold_time_minutes',
                    'option_key'  => 'hold_time_minutes',
                    'min'         => 0,
                    'max'         => 180,
                    'step'        => 5,
                    'description' => __( 'How long to hold a table for guests queued at the host stand.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_integrations',
                __( 'External integrations', 'restaurant-booking' ),
                array( $this, 'render_select_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_advanced',
                array(
                    'label_for'  => 'restaurant_booking_settings_integrations',
                    'option_key' => 'integrations',
                    'choices'    => array(
                        'zapier'   => __( 'Zapier', 'restaurant-booking' ),
                        'slack'    => __( 'Slack', 'restaurant-booking' ),
                        'teams'    => __( 'Microsoft Teams', 'restaurant-booking' ),
                        'webhooks' => __( 'Webhook callback', 'restaurant-booking' ),
                    ),
                    'multiple'    => true,
                    'size'        => 4,
                    'description' => __( 'Select integrations to enable for automation workflows.', 'restaurant-booking' ),
                )
            );

            add_settings_field(
                'restaurant_booking_settings_maintenance_mode',
                __( 'Maintenance mode', 'restaurant-booking' ),
                array( $this, 'render_checkbox_field' ),
                'restaurant_booking_settings',
                'restaurant_booking_settings_advanced',
                array(
                    'label_for'   => 'restaurant_booking_settings_maintenance_mode',
                    'option_key'  => 'maintenance_mode',
                    'label'       => __( 'Temporarily disable public bookings', 'restaurant-booking' ),
                    'description' => __( 'Useful during renovations or private events.', 'restaurant-booking' ),
                )
            );
        }

        public function render_general_settings_intro() {
            echo '<p>' . esc_html__( 'Configure the restaurant details and booking defaults shown across the manager tools.', 'restaurant-booking' ) . '</p>';
        }

        public function render_notifications_intro() {
            echo '<p>' . esc_html__( 'Control when confirmations and reminders are delivered to guests.', 'restaurant-booking' ) . '</p>';
        }

        public function render_advanced_intro() {
            echo '<p>' . esc_html__( 'Fine-tune automation rules and integration hooks for complex workflows.', 'restaurant-booking' ) . '</p>';
        }

        protected function get_settings_cache() {
            if ( null === $this->cached_settings ) {
                if ( function_exists( 'restaurant_booking_get_settings' ) ) {
                    $this->cached_settings = restaurant_booking_get_settings();
                } else {
                    $this->cached_settings = array();
                }
            }

            return $this->cached_settings;
        }

        protected function get_setting_value( $key, $default = '' ) {
            $settings = $this->get_settings_cache();

            if ( isset( $settings[ $key ] ) ) {
                return $settings[ $key ];
            }

            return $default;
        }

        public function render_text_field( $args ) {
            $key = isset( $args['option_key'] ) ? $args['option_key'] : '';
            $id  = isset( $args['label_for'] ) ? $args['label_for'] : $key;

            if ( empty( $key ) || empty( $id ) ) {
                return;
            }

            $value = $this->get_setting_value( $key, isset( $args['default'] ) ? $args['default'] : '' );
            $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';

            printf(
                '<input type="text" id="%1$s" name="restaurant_booking_settings[%2$s]" value="%3$s" class="regular-text" %4$s/>',
                esc_attr( $id ),
                esc_attr( $key ),
                esc_attr( $value ),
                $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''
            );

            if ( ! empty( $args['description'] ) ) {
                printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
            }
        }

        public function render_number_field( $args ) {
            $key = isset( $args['option_key'] ) ? $args['option_key'] : '';
            $id  = isset( $args['label_for'] ) ? $args['label_for'] : $key;

            if ( empty( $key ) || empty( $id ) ) {
                return;
            }

            $value = $this->get_setting_value( $key, isset( $args['default'] ) ? $args['default'] : 0 );

            $attributes = array();

            foreach ( array( 'min', 'max', 'step' ) as $attribute ) {
                if ( isset( $args[ $attribute ] ) && '' !== $args[ $attribute ] ) {
                    $attributes[] = sprintf( '%s="%s"', $attribute, esc_attr( $args[ $attribute ] ) );
                }
            }

            printf(
                '<input type="number" id="%1$s" name="restaurant_booking_settings[%2$s]" value="%3$s" class="small-text" %4$s/>',
                esc_attr( $id ),
                esc_attr( $key ),
                esc_attr( $value ),
                implode( ' ', $attributes )
            );

            if ( ! empty( $args['description'] ) ) {
                printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
            }
        }

        public function render_checkbox_field( $args ) {
            $key = isset( $args['option_key'] ) ? $args['option_key'] : '';
            $id  = isset( $args['label_for'] ) ? $args['label_for'] : $key;

            if ( empty( $key ) || empty( $id ) ) {
                return;
            }

            $value = $this->get_setting_value( $key, isset( $args['default'] ) ? $args['default'] : false );
            $label = isset( $args['label'] ) ? $args['label'] : '';

            echo '<label for="' . esc_attr( $id ) . '">';
            printf(
                '<input type="checkbox" id="%1$s" name="restaurant_booking_settings[%2$s]" value="1" %3$s />',
                esc_attr( $id ),
                esc_attr( $key ),
                checked( ! empty( $value ), true, false )
            );

            if ( $label ) {
                echo ' ' . esc_html( $label );
            }

            echo '</label>';

            if ( ! empty( $args['description'] ) ) {
                printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
            }
        }

        public function render_select_field( $args ) {
            $key = isset( $args['option_key'] ) ? $args['option_key'] : '';
            $id  = isset( $args['label_for'] ) ? $args['label_for'] : $key;
            $choices = isset( $args['choices'] ) && is_array( $args['choices'] ) ? $args['choices'] : array();

            if ( empty( $key ) || empty( $id ) ) {
                return;
            }

            $multiple = ! empty( $args['multiple'] );
            $value    = $this->get_setting_value( $key, isset( $args['default'] ) ? $args['default'] : ( $multiple ? array() : '' ) );

            $name = sprintf( 'restaurant_booking_settings[%s]%s', $key, $multiple ? '[]' : '' );

            $attributes = $multiple ? ' multiple="multiple"' : '';
            if ( $multiple && ! empty( $args['size'] ) ) {
                $attributes .= ' size="' . esc_attr( (int) $args['size'] ) . '"';
            }

            printf(
                '<select id="%1$s" name="%2$s" %3$s>',
                esc_attr( $id ),
                esc_attr( $name ),
                $attributes
            );

            foreach ( $choices as $option_value => $label ) {
                $is_selected = $multiple
                    ? in_array( $option_value, (array) $value, true )
                    : (string) $value === (string) $option_value;

                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr( $option_value ),
                    selected( $is_selected, true, false ),
                    esc_html( $label )
                );
            }

            echo '</select>';

            if ( ! empty( $args['description'] ) ) {
                printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
            }
        }

        public function render_textarea_field( $args ) {
            $key = isset( $args['option_key'] ) ? $args['option_key'] : '';
            $id  = isset( $args['label_for'] ) ? $args['label_for'] : $key;

            if ( empty( $key ) || empty( $id ) ) {
                return;
            }

            $value = $this->get_setting_value( $key, isset( $args['default'] ) ? $args['default'] : '' );
            $rows  = isset( $args['rows'] ) ? (int) $args['rows'] : 5;

            printf(
                '<textarea id="%1$s" name="restaurant_booking_settings[%2$s]" rows="%3$d" class="large-text">%4$s</textarea>',
                esc_attr( $id ),
                esc_attr( $key ),
                max( 3, $rows ),
                esc_textarea( $value )
            );

            if ( ! empty( $args['description'] ) ) {
                printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
            }
        }

        public function add_admin_pages() {
            if ( function_exists( 'restaurant_booking_resolve_manage_capability' ) ) {
                $capability = restaurant_booking_resolve_manage_capability();
            } elseif ( function_exists( 'restaurant_booking_get_manage_capability' ) ) {
                $capability = restaurant_booking_get_manage_capability();
            } else {
                $capability = 'manage_options';
            }

            $settings_slug = function_exists( 'restaurant_booking_get_settings_page_slug' )
                ? restaurant_booking_get_settings_page_slug()
                : 'restaurant-booking-settings';

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
                $settings_slug,
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

            add_options_page(
                __( 'Restaurant Booking Settings', 'restaurant-booking' ),
                __( 'Restaurant Booking', 'restaurant-booking' ),
                $capability,
                $settings_slug,
                array( $this, 'render_settings' )
            );
        }

        public function enqueue_admin_assets( $hook ) {
            $settings_slug = function_exists( 'restaurant_booking_get_settings_page_slug' )
                ? restaurant_booking_get_settings_page_slug()
                : 'restaurant-booking-settings';

            if ( strpos( $hook, 'rb-' ) === false && strpos( $hook, $settings_slug ) === false ) {
                return;
            }

            $this->enqueue_core_assets( 'admin' );
            $this->enqueue_context_css( 'modern-admin' );
            $this->enqueue_context_js( 'modern-admin', array( 'jquery' ) );

            $currency_symbol = apply_filters( 'rb_booking_currency', '$', 0 );
            $currency_code   = apply_filters( 'rb_booking_currency_code', 'USD', 0 );

            $badge_counts = apply_filters(
                'rb_admin_menu_badge_counts',
                array(
                    'rb-bookings' => $this->get_pending_badge_count(),
                )
            );

            $this->localize_ajax_data(
                'rb-modern-admin',
                'rbAdmin',
                array(
                    'strings' => array(
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
                        'bufferSingular'  => __( '%s minute buffer', 'restaurant-booking' ),
                        'bufferPlural'    => __( '%s minutes buffer', 'restaurant-booking' ),
                        'guestSingular'   => __( '%s guest', 'restaurant-booking' ),
                        'guestPlural'     => __( '%s guests', 'restaurant-booking' ),
                        'reminderSingular' => __( '%s hour prior', 'restaurant-booking' ),
                        'reminderPlural'   => __( '%s hours prior', 'restaurant-booking' ),
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
                ),
                'rb_admin_nonce'
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
            try {
                $this->verify_admin_ajax_request();

                $locations = RB_Location::get_all_locations();
                $formatted = array_map( array( $this, 'format_location_stats' ), $locations );

                wp_send_json_success(
                    array(
                        'locations' => $formatted,
                        'summary'   => $this->build_dashboard_summary( $formatted ),
                    )
                );
            } catch ( Exception $exception ) {
                error_log( 'RB Admin AJAX Error [ajax_get_dashboard]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        public function ajax_get_bookings() {
            try {
                $this->verify_admin_ajax_request();

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

                $results = RB_Booking::get_bookings( $location, $status, $page, $args );

                wp_send_json_success(
                    array(
                        'items'      => $results['items'] ?? array(),
                        'total'      => $results['total'] ?? 0,
                        'totalPages' => $results['total_pages'] ?? 1,
                        'page'       => $page,
                    )
                );
            } catch ( Exception $exception ) {
                error_log( 'RB Admin AJAX Error [ajax_get_bookings]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        public function ajax_get_locations() {
            try {
                $this->verify_admin_ajax_request();

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
                error_log( 'RB Admin AJAX Error [ajax_get_locations]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        /**
         * Validate nonce and permissions for admin AJAX requests.
         *
         * @throws Exception When the request fails security or permission checks.
         */
        protected function verify_admin_ajax_request() {
            if ( ! check_ajax_referer( 'rb_admin_nonce', 'nonce', false ) ) {
                throw new Exception( __( 'Security check failed. Please refresh and try again.', 'restaurant-booking' ), 403 );
            }

            $allowed = function_exists( 'restaurant_booking_user_can_manage' )
                ? restaurant_booking_user_can_manage()
                : current_user_can( 'manage_options' );

            $allowed = apply_filters( 'rb_admin_user_can_manage', $allowed );

            if ( ! $allowed ) {
                throw new Exception( __( 'You do not have permission to perform this action.', 'restaurant-booking' ), 403 );
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
