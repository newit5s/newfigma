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

        /**
         * Cached configuration describing how the settings lockdown behaves.
         *
         * @var array|null
         */
        protected $settings_lockdown_config = null;

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
            add_action( 'admin_menu', array( $this, 'remove_restricted_settings_menu' ), 99 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
            add_action( 'admin_init', array( $this, 'maybe_block_settings_access' ), 5 );
            add_action( 'admin_init', array( $this, 'register_settings' ), 20 );
            add_filter( 'user_has_cap', array( $this, 'maybe_grant_testing_manage_capability' ), 20, 4 );

            add_action( 'wp_ajax_rb_admin_get_dashboard', array( $this, 'ajax_get_dashboard' ) );
            add_action( 'wp_ajax_rb_admin_get_bookings', array( $this, 'ajax_get_bookings' ) );
            add_action( 'wp_ajax_rb_admin_get_locations', array( $this, 'ajax_get_locations' ) );
            add_action( 'wp_ajax_rb_admin_save_location', array( $this, 'ajax_save_location' ) );
            add_action( 'wp_ajax_rb_admin_delete_location', array( $this, 'ajax_delete_location' ) );
        }

        public function register_settings() {
            if ( $this->settings_registered ) {
                return;
            }

            $this->settings_registered = true;

            if ( ! function_exists( 'register_setting' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ( ! function_exists( 'add_settings_section' ) ) {
                require_once ABSPATH . 'wp-admin/includes/template.php';
            }

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

        /**
         * Remove the settings menu entry for restricted users.
         */
        public function remove_restricted_settings_menu() {
            if ( ! $this->is_settings_lockdown_enabled() ) {
                return;
            }

            if ( ! $this->is_settings_access_restricted() ) {
                return;
            }

            $settings_slug = function_exists( 'restaurant_booking_get_settings_page_slug' )
                ? restaurant_booking_get_settings_page_slug()
                : 'restaurant-booking-settings';

            remove_submenu_page( 'rb-dashboard', $settings_slug );
            remove_submenu_page( 'options-general.php', $settings_slug );
        }

        /**
         * Block direct access to the settings screen when restricted.
         */
        public function maybe_block_settings_access() {
            if ( ! is_admin() ) {
                return;
            }

            if ( ! $this->is_settings_lockdown_enabled() ) {
                return;
            }

            if ( wp_doing_ajax() ) {
                return;
            }

            if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return;
            }

            $requested_page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            $settings_slug = function_exists( 'restaurant_booking_get_settings_page_slug' )
                ? restaurant_booking_get_settings_page_slug()
                : 'restaurant-booking-settings';

            if ( $requested_page !== $settings_slug ) {
                return;
            }

            if ( ! $this->is_settings_access_restricted() ) {
                return;
            }

            $message = apply_filters(
                'restaurant_booking_restricted_settings_message',
                __( 'Restaurant Booking settings are temporarily unavailable while internal testing is in progress.', 'restaurant-booking' )
            );

            wp_die(
                wp_kses_post( $message ),
                esc_html__( 'Access restricted', 'restaurant-booking' ),
                array(
                    'response'  => 403,
                    'back_link' => true,
                )
            );
        }

        /**
         * Determine whether the current user should be blocked from accessing settings.
         *
         * @return bool
         */
        protected function is_settings_access_restricted() {
            if ( ! $this->is_settings_lockdown_enabled() ) {
                return false;
            }

            if ( ! function_exists( 'wp_get_current_user' ) ) {
                return false;
            }

            $user = wp_get_current_user();

            if ( ! $user instanceof WP_User || 0 === $user->ID ) {
                return false;
            }

            $config = $this->get_settings_lockdown_config();

            $blocked_roles = apply_filters( 'restaurant_booking_restricted_settings_roles', $config['blocked_roles'] );
            $blocked_roles = $this->normalize_config_list( $blocked_roles, 'sanitize_key' );

            $blocked_user_ids = apply_filters( 'restaurant_booking_restricted_settings_user_ids', $config['blocked_user_ids'] );
            $blocked_user_ids = $this->normalize_config_list(
                $blocked_user_ids,
                'intval',
                function ( $value ) {
                    return $value > 0;
                }
            );

            $restricted = false;

            if ( ! empty( $config['block_super_admins'] ) && function_exists( 'is_super_admin' ) && is_super_admin( $user->ID ) ) {
                $restricted = true;
            }

            $testing_overrides   = $this->get_testing_access_overrides();
            $is_testing_override = $this->user_matches_testing_override( $user, $testing_overrides );

            if ( $is_testing_override ) {
                $restricted = false;
            } else {
                if ( ! $restricted && ! empty( $blocked_roles ) && is_array( $user->roles ) ) {
                    $sanitized_roles = array_map( 'sanitize_key', (array) $user->roles );
                    $restricted      = (bool) array_intersect( $blocked_roles, $sanitized_roles );
                }

                if ( ! $restricted && ! empty( $blocked_user_ids ) ) {
                    $restricted = in_array( (int) $user->ID, $blocked_user_ids, true );
                }
            }

            return (bool) apply_filters( 'restaurant_booking_is_settings_access_restricted', $restricted, $user );
        }

        /**
         * Grant the manage capability to testing accounts while administrators are blocked.
         *
         * Ensures that QA users defined via the restaurant_booking_testing_allowed_* filters
         * keep access to the settings screen even if their role normally lacks the
         * `manage_bookings` capability.
         *
         * @param array   $allcaps All the capabilities of the user.
         * @param array   $caps    Required primitive capabilities for the requested capability.
         * @param array   $args    Arguments that accompany the capability check.
         * @param WP_User $user    User object being evaluated.
         *
         * @return array
         */
        public function maybe_grant_testing_manage_capability( $allcaps, $caps, $args, $user ) {
            if ( empty( $args ) || ! isset( $args[0] ) ) {
                return $allcaps;
            }

            $requested_cap = $args[0];
            if ( ! $user instanceof WP_User ) {
                return $allcaps;
            }

            $user_id = (int) $user->ID;

            if ( $user_id <= 0 ) {
                return $allcaps;
            }

            $testing_overrides = $this->get_testing_access_overrides();

            if ( empty( $testing_overrides['roles'] ) && empty( $testing_overrides['user_ids'] ) ) {
                return $allcaps;
            }

            $target_capabilities = array( 'manage_bookings' );

            if ( function_exists( 'restaurant_booking_get_manage_capability' ) ) {
                $manage_capability = restaurant_booking_get_manage_capability();

                if (
                    ! empty( $manage_capability )
                    && 'manage_options' !== $manage_capability
                    && ! in_array( $manage_capability, $target_capabilities, true )
                ) {
                    $target_capabilities[] = $manage_capability;
                }
            }

            if ( ! in_array( $requested_cap, $target_capabilities, true ) ) {
                return $allcaps;
            }

            if ( ! $this->user_matches_testing_override( $user, $testing_overrides ) ) {
                return $allcaps;
            }

            foreach ( $target_capabilities as $capability ) {
                $allcaps[ $capability ] = true;
            }

            return $allcaps;
        }

        /**
         * Retrieve sanitized testing override configuration.
         *
         * @return array{
         *     roles: string[],
         *     user_ids: int[],
         * }
         */
        protected function get_testing_access_overrides() {
            $config = $this->get_settings_lockdown_config();

            $roles = apply_filters( 'restaurant_booking_testing_allowed_roles', $config['testing_roles'] );
            $roles = $this->normalize_config_list( $roles, 'sanitize_key' );

            $user_ids = apply_filters( 'restaurant_booking_testing_allowed_user_ids', $config['testing_user_ids'] );
            $user_ids = $this->normalize_config_list(
                $user_ids,
                'intval',
                function ( $value ) {
                    return $value > 0;
                }
            );

            return array(
                'roles'    => array_values( array_unique( $roles ) ),
                'user_ids' => array_values( array_unique( $user_ids ) ),
            );
        }

        /**
         * Determine whether the current environment should enforce the settings lockdown.
         *
         * @return bool
         */
        protected function is_settings_lockdown_enabled() {
            $config  = $this->get_settings_lockdown_config();
            $enabled = ! empty( $config['enabled'] );

            return (bool) apply_filters( 'restaurant_booking_settings_lockdown_enabled', $enabled, $config );
        }

        /**
         * Retrieve consolidated lockdown configuration from options, environment, and filters.
         *
         * @return array{
         *     enabled: bool,
         *     blocked_roles: string[],
         *     blocked_user_ids: int[],
         *     testing_roles: string[],
         *     testing_user_ids: int[],
         *     block_super_admins: bool,
         * }
         */
        protected function get_settings_lockdown_config() {
            if ( null !== $this->settings_lockdown_config ) {
                return $this->settings_lockdown_config;
            }

            $config = array(
                'enabled'            => false,
                'blocked_roles'      => array(),
                'blocked_user_ids'   => array(),
                'testing_roles'      => array(),
                'testing_user_ids'   => array(),
                'block_super_admins' => false,
            );

            if ( function_exists( 'get_option' ) ) {
                $option = get_option( 'restaurant_booking_settings_lockdown', null );

                if ( is_array( $option ) ) {
                    if ( array_key_exists( 'enabled', $option ) ) {
                        $config['enabled'] = $this->normalize_boolean_flag( $option['enabled'], $config['enabled'] );
                    }

                    if ( array_key_exists( 'blocked_roles', $option ) ) {
                        $config['blocked_roles'] = $this->normalize_config_list( $option['blocked_roles'], 'sanitize_key' );
                    }

                    if ( array_key_exists( 'blocked_user_ids', $option ) ) {
                        $config['blocked_user_ids'] = $this->normalize_config_list(
                            $option['blocked_user_ids'],
                            'intval',
                            function ( $value ) {
                                return $value > 0;
                            }
                        );
                    }

                    if ( array_key_exists( 'testing_roles', $option ) ) {
                        $config['testing_roles'] = $this->normalize_config_list( $option['testing_roles'], 'sanitize_key' );
                    }

                    if ( array_key_exists( 'testing_user_ids', $option ) ) {
                        $config['testing_user_ids'] = $this->normalize_config_list(
                            $option['testing_user_ids'],
                            'intval',
                            function ( $value ) {
                                return $value > 0;
                            }
                        );
                    }

                    if ( array_key_exists( 'block_super_admins', $option ) ) {
                        $config['block_super_admins'] = $this->normalize_boolean_flag( $option['block_super_admins'], $config['block_super_admins'] );
                    }
                }
            }

            $config['blocked_roles'] = $this->merge_config_lists(
                $config['blocked_roles'],
                array(
                    defined( 'RESTAURANT_BOOKING_BLOCKED_SETTINGS_ROLES' ) ? RESTAURANT_BOOKING_BLOCKED_SETTINGS_ROLES : null,
                    $this->get_environment_value( array( 'RESTAURANT_BOOKING_BLOCKED_SETTINGS_ROLES', 'RB_BLOCKED_SETTINGS_ROLES' ) ),
                ),
                'sanitize_key'
            );

            $config['blocked_user_ids'] = $this->merge_config_lists(
                $config['blocked_user_ids'],
                array(
                    defined( 'RESTAURANT_BOOKING_BLOCKED_SETTINGS_USERS' ) ? RESTAURANT_BOOKING_BLOCKED_SETTINGS_USERS : null,
                    $this->get_environment_value( array( 'RESTAURANT_BOOKING_BLOCKED_SETTINGS_USERS', 'RB_BLOCKED_SETTINGS_USERS' ) ),
                ),
                'intval',
                function ( $value ) {
                    return $value > 0;
                }
            );

            $config['testing_roles'] = $this->merge_config_lists(
                $config['testing_roles'],
                array(
                    defined( 'RESTAURANT_BOOKING_TESTING_ALLOWED_ROLES' ) ? RESTAURANT_BOOKING_TESTING_ALLOWED_ROLES : null,
                    $this->get_environment_value( array( 'RESTAURANT_BOOKING_TESTING_ALLOWED_ROLES', 'RB_TESTING_ALLOWED_ROLES' ) ),
                ),
                'sanitize_key'
            );

            $config['testing_user_ids'] = $this->merge_config_lists(
                $config['testing_user_ids'],
                array(
                    defined( 'RESTAURANT_BOOKING_TESTING_ALLOWED_USER_IDS' ) ? RESTAURANT_BOOKING_TESTING_ALLOWED_USER_IDS : ( defined( 'RESTAURANT_BOOKING_TESTING_ALLOWED_USERS' ) ? RESTAURANT_BOOKING_TESTING_ALLOWED_USERS : null ),
                    $this->get_environment_value( array( 'RESTAURANT_BOOKING_TESTING_ALLOWED_USER_IDS', 'RESTAURANT_BOOKING_TESTING_ALLOWED_USERS', 'RB_TESTING_ALLOWED_USER_IDS', 'RB_TESTING_ALLOWED_USERS' ) ),
                ),
                'intval',
                function ( $value ) {
                    return $value > 0;
                }
            );

            if ( defined( 'RESTAURANT_BOOKING_SETTINGS_LOCKDOWN_ENABLED' ) ) {
                $config['enabled'] = $this->normalize_boolean_flag( RESTAURANT_BOOKING_SETTINGS_LOCKDOWN_ENABLED, $config['enabled'] );
            }

            $env_enabled = $this->get_environment_value( array( 'RESTAURANT_BOOKING_SETTINGS_LOCKDOWN_ENABLED', 'RB_SETTINGS_LOCKDOWN_ENABLED' ) );
            if ( null !== $env_enabled ) {
                $config['enabled'] = $this->normalize_boolean_flag( $env_enabled, $config['enabled'] );
            }

            if ( defined( 'RESTAURANT_BOOKING_DISABLE_SETTINGS_LOCKDOWN' ) ) {
                if ( $this->normalize_boolean_flag( RESTAURANT_BOOKING_DISABLE_SETTINGS_LOCKDOWN, false ) ) {
                    $config['enabled'] = false;
                }
            }

            $env_disable = $this->get_environment_value( array( 'RESTAURANT_BOOKING_DISABLE_SETTINGS_LOCKDOWN', 'RB_DISABLE_SETTINGS_LOCKDOWN' ) );
            if ( null !== $env_disable && $this->normalize_boolean_flag( $env_disable, false ) ) {
                $config['enabled'] = false;
            }

            if ( defined( 'RESTAURANT_BOOKING_BLOCK_SUPER_ADMINS' ) ) {
                $config['block_super_admins'] = $this->normalize_boolean_flag( RESTAURANT_BOOKING_BLOCK_SUPER_ADMINS, $config['block_super_admins'] );
            }

            $env_block_super_admins = $this->get_environment_value( array( 'RESTAURANT_BOOKING_BLOCK_SUPER_ADMINS', 'RB_BLOCK_SUPER_ADMINS' ) );
            if ( null !== $env_block_super_admins ) {
                $config['block_super_admins'] = $this->normalize_boolean_flag( $env_block_super_admins, $config['block_super_admins'] );
            }

            $config = apply_filters( 'restaurant_booking_settings_lockdown_config', $config );

            $config['blocked_roles'] = $this->normalize_config_list( $config['blocked_roles'], 'sanitize_key' );
            $config['blocked_user_ids'] = $this->normalize_config_list(
                $config['blocked_user_ids'],
                'intval',
                function ( $value ) {
                    return $value > 0;
                }
            );
            $config['testing_roles'] = $this->normalize_config_list( $config['testing_roles'], 'sanitize_key' );
            $config['testing_user_ids'] = $this->normalize_config_list(
                $config['testing_user_ids'],
                'intval',
                function ( $value ) {
                    return $value > 0;
                }
            );
            $config['block_super_admins'] = $this->normalize_boolean_flag( $config['block_super_admins'], true );
            $config['enabled']            = $this->normalize_boolean_flag( $config['enabled'], true );

            $this->settings_lockdown_config = $config;

            return $this->settings_lockdown_config;
        }

        /**
         * Determine whether the given user is whitelisted for settings access overrides.
         *
         * @param WP_User $user       WordPress user object.
         * @param array   $overrides  Override configuration with `roles` and `user_ids` keys.
         *
         * @return bool
         */
        protected function user_matches_testing_override( $user, $overrides ) {
            if ( ! $user instanceof WP_User ) {
                return false;
            }

            $user_id = (int) $user->ID;

            if ( $user_id > 0 && ! empty( $overrides['user_ids'] ) && in_array( $user_id, $overrides['user_ids'], true ) ) {
                return true;
            }

            if ( empty( $overrides['roles'] ) || ! is_array( $user->roles ) ) {
                return false;
            }

            $sanitized_roles = array_map( 'sanitize_key', (array) $user->roles );

            return ! empty( array_intersect( $overrides['roles'], $sanitized_roles ) );
        }

        /**
         * Normalize a list-like value into a sanitized array.
         *
         * @param mixed    $source    Input configuration.
         * @param callable $sanitize  Sanitization callback applied to each value.
         * @param callable $validator Optional validator returning true for allowed values.
         *
         * @return array
         */
        protected function normalize_config_list( $source, $sanitize, $validator = null ) {
            $values = array();

            if ( null === $source ) {
                return $values;
            }

            if ( is_string( $source ) ) {
                $source = trim( $source );

                if ( '' === $source ) {
                    return $values;
                }

                if ( function_exists( 'wp_parse_list' ) ) {
                    $source = wp_parse_list( $source );
                } else {
                    $source = preg_split( '/[\s,]+/', $source );
                }
            } elseif ( is_object( $source ) ) {
                $source = (array) $source;
            } elseif ( ! is_array( $source ) ) {
                $source = array( $source );
            }

            foreach ( $source as $item ) {
                if ( is_string( $item ) ) {
                    $item = trim( $item );
                }

                if ( '' === $item || null === $item ) {
                    continue;
                }

                $sanitized = call_user_func( $sanitize, $item );

                if ( '' === $sanitized || null === $sanitized ) {
                    continue;
                }

                if ( null !== $validator && ! call_user_func( $validator, $sanitized ) ) {
                    continue;
                }

                $values[] = $sanitized;
            }

            return $values;
        }

        /**
         * Merge multiple list-like sources into a sanitized array.
         *
         * @param array    $base      Base array to extend.
         * @param array    $sources   Additional values to merge.
         * @param callable $sanitize  Sanitization callback.
         * @param callable $validator Optional validator callback.
         *
         * @return array
         */
        protected function merge_config_lists( array $base, array $sources, $sanitize, $validator = null ) {
            $merged = $base;

            foreach ( $sources as $source ) {
                if ( null === $source || '' === $source ) {
                    continue;
                }

                $merged = array_merge( $merged, $this->normalize_config_list( $source, $sanitize, $validator ) );
            }

            if ( empty( $merged ) ) {
                return array();
            }

            return array_values( array_unique( $merged ) );
        }

        /**
         * Convert loose truthy/falsey configuration values into booleans.
         *
         * @param mixed $value   Raw value from options, constants, or environment.
         * @param bool  $default Default when the value cannot be interpreted.
         *
         * @return bool
         */
        protected function normalize_boolean_flag( $value, $default = true ) {
            if ( is_bool( $value ) ) {
                return $value;
            }

            if ( is_numeric( $value ) ) {
                return (bool) (int) $value;
            }

            if ( is_string( $value ) ) {
                $normalized = strtolower( trim( $value ) );

                if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
                    return true;
                }

                if ( in_array( $normalized, array( '0', 'false', 'no', 'off' ), true ) ) {
                    return false;
                }
            }

            return (bool) $default;
        }

        /**
         * Retrieve the first available environment variable value for the provided keys.
         *
         * @param string[] $keys Environment variable names ordered by priority.
         *
         * @return string|null
         */
        protected function get_environment_value( array $keys ) {
            foreach ( $keys as $key ) {
                if ( empty( $key ) ) {
                    continue;
                }

                $value = getenv( $key );

                if ( false !== $value && '' !== $value ) {
                    return $value;
                }
            }

            return null;
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
                        'items'      => isset( $results['items'] ) ? $results['items'] : array(),
                        'pagination' => isset( $results['pagination'] ) ? $results['pagination'] : array(),
                        'summary'    => isset( $results['summary'] ) ? $results['summary'] : array(),
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

                $payload = $this->compile_locations_directory();

                wp_send_json_success( $payload );
            } catch ( Exception $exception ) {
                error_log( 'RB Admin AJAX Error [ajax_get_locations]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        public function ajax_save_location() {
            try {
                $this->verify_admin_ajax_request();

                $location_id = absint( $_POST['id'] ?? 0 );

                $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

                if ( '' === $name ) {
                    throw new Exception( __( 'Location name is required.', 'restaurant-booking' ), 400 );
                }

                $status_raw        = isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'active';
                $waitlist_raw      = isset( $_POST['waitlist_enabled'] ) ? wp_unslash( $_POST['waitlist_enabled'] ) : '';
                $capacity_provided = isset( $_POST['capacity'] );

                $data = array(
                    'name'            => $name,
                    'email'           => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
                    'phone'           => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
                    'address'         => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
                    'hours_weekday'   => isset( $_POST['hours_weekday'] ) ? sanitize_text_field( wp_unslash( $_POST['hours_weekday'] ) ) : '',
                    'hours_weekend'   => isset( $_POST['hours_weekend'] ) ? sanitize_text_field( wp_unslash( $_POST['hours_weekend'] ) ) : '',
                    'waitlist_enabled'=> in_array( strtolower( (string) $waitlist_raw ), array( '1', 'true', 'yes', 'on' ), true ),
                    'status'          => sanitize_key( $status_raw ),
                );

                if ( $capacity_provided ) {
                    $data['capacity'] = max( 0, (int) wp_unslash( $_POST['capacity'] ) );
                } elseif ( $location_id > 0 && method_exists( 'RB_Location', 'get_location' ) ) {
                    $existing = RB_Location::get_location( $location_id );
                    if ( $existing && isset( $existing->capacity ) ) {
                        $data['capacity'] = (int) $existing->capacity;
                    }
                }

                if ( $location_id > 0 ) {
                    $location = RB_Location::update_location( $location_id, $data );
                } else {
                    $location = RB_Location::create_location( $data );
                }

                if ( ! $location ) {
                    throw new Exception( __( 'Unable to save location details.', 'restaurant-booking' ), 500 );
                }

                $directory = $this->compile_locations_directory( true );
                $payload   = array_merge(
                    $directory,
                    array(
                        'location' => $this->format_location_response( $location ),
                    )
                );

                wp_send_json_success( $payload );
            } catch ( Exception $exception ) {
                error_log( 'RB Admin AJAX Error [ajax_save_location]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        public function ajax_delete_location() {
            try {
                $this->verify_admin_ajax_request();

                $location_id = absint( $_POST['id'] ?? 0 );

                if ( $location_id <= 0 ) {
                    throw new Exception( __( 'Missing location identifier.', 'restaurant-booking' ), 400 );
                }

                $deleted = RB_Location::delete_location( $location_id );

                if ( ! $deleted ) {
                    throw new Exception( __( 'Failed to delete the location.', 'restaurant-booking' ), 500 );
                }

                $payload = $this->compile_locations_directory( true );
                $payload['deleted'] = $location_id;

                wp_send_json_success( $payload );
            } catch ( Exception $exception ) {
                error_log( 'RB Admin AJAX Error [ajax_delete_location]: ' . $exception->getMessage() );

                $status = $exception->getCode() ?: 400;
                wp_send_json_error(
                    array( 'message' => $exception->getMessage() ),
                    $status
                );
            }
        }

        protected function compile_locations_directory( $include_dashboard = false ) {
            $locations           = RB_Location::get_all_locations();
            $formatted_locations = array();
            $dashboard_locations = array();

            if ( ! empty( $locations ) ) {
                foreach ( $locations as $location ) {
                    $formatted_locations[] = $this->format_location_response( $location );

                    if ( $include_dashboard ) {
                        $dashboard_locations[] = $this->format_location_stats( $location );
                    }
                }
            }

            $payload = array(
                'locations' => $formatted_locations,
                'summary'   => $this->build_locations_summary( $formatted_locations ),
            );

            if ( $include_dashboard ) {
                $payload['dashboard'] = array(
                    'locations' => $dashboard_locations,
                    'summary'   => $this->build_dashboard_summary( $dashboard_locations ),
                );
            }

            return $payload;
        }

        protected function format_location_response( $location ) {
            if ( is_array( $location ) ) {
                $location = (object) $location;
            }

            $location_id = isset( $location->id ) ? (int) $location->id : 0;
            $declared_capacity = isset( $location->capacity ) ? (int) $location->capacity : 0;

            $layout      = array();
            $table_count = 0;
            $table_seats = 0;

            if ( $location_id > 0 && class_exists( 'RB_Table' ) ) {
                if ( method_exists( 'RB_Table', 'get_layout' ) ) {
                    $layout = RB_Table::get_layout( $location_id );
                } elseif ( method_exists( 'RB_Table', 'get_tables_by_location' ) ) {
                    $layout = RB_Table::get_tables_by_location( $location_id );
                } elseif ( method_exists( 'RB_Table', 'get_tables' ) ) {
                    $layout = RB_Table::get_tables( $location_id );
                }

                if ( ! empty( $layout ) && is_array( $layout ) ) {
                    foreach ( $layout as $table ) {
                        $table_count++;

                        if ( is_object( $table ) ) {
                            $table_seats += isset( $table->capacity ) ? (int) $table->capacity : 0;
                        } elseif ( is_array( $table ) ) {
                            $table_seats += isset( $table['capacity'] ) ? (int) $table['capacity'] : 0;
                        }
                    }
                }
            }

            $status = isset( $location->status ) ? sanitize_key( $location->status ) : 'active';

            return array(
                'id'                 => $location_id,
                'name'               => isset( $location->name ) ? $location->name : '',
                'email'              => isset( $location->email ) ? $location->email : '',
                'phone'              => isset( $location->phone ) ? $location->phone : '',
                'address'            => isset( $location->address ) ? $location->address : '',
                'status'             => $status,
                'hours_weekday'      => isset( $location->hours_weekday ) ? $location->hours_weekday : '',
                'hours_weekend'      => isset( $location->hours_weekend ) ? $location->hours_weekend : '',
                'waitlist_enabled'   => ! empty( $location->waitlist_enabled ),
                'tables'             => $table_count,
                'capacity'           => $table_seats > 0 ? $table_seats : $declared_capacity,
                'declared_capacity'  => $declared_capacity,
                'table_capacity'     => $table_seats,
            );
        }

        protected function build_locations_summary( $locations ) {
            $summary = array(
                'total_locations'   => 0,
                'open_locations'    => 0,
                'closed_locations'  => 0,
                'total_tables'      => 0,
                'total_seats'       => 0,
                'waitlist_enabled'  => 0,
            );

            if ( empty( $locations ) ) {
                return apply_filters( 'rb_admin_locations_summary', $summary, $locations );
            }

            $closed_statuses = apply_filters( 'rb_admin_closed_location_statuses', array( 'closed', 'inactive', 'archived' ) );

            foreach ( $locations as $location ) {
                $summary['total_locations']++;

                $status = isset( $location['status'] ) ? sanitize_key( $location['status'] ) : 'active';

                if ( in_array( $status, (array) $closed_statuses, true ) ) {
                    $summary['closed_locations']++;
                } else {
                    $summary['open_locations']++;
                }

                $summary['total_tables'] += isset( $location['tables'] ) ? (int) $location['tables'] : 0;
                $summary['total_seats']  += isset( $location['capacity'] ) ? (int) $location['capacity'] : 0;

                if ( ! empty( $location['waitlist_enabled'] ) ) {
                    $summary['waitlist_enabled']++;
                }
            }

            return apply_filters( 'rb_admin_locations_summary', $summary, $locations );
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
