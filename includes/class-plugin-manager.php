<?php
/**
 * Plugin Manager - orchestrates plugin functionality.
 */

if ( ! class_exists( 'Restaurant_Booking_Plugin_Manager' ) ) {

    class Restaurant_Booking_Plugin_Manager {

        /**
         * Singleton instance.
         *
         * @var Restaurant_Booking_Plugin_Manager|null
         */
        protected static $instance = null;

        /**
         * Loader instance.
         *
         * @var Restaurant_Booking_Plugin_Loader
         */
        protected $loader;

        /**
         * Flag to ensure public components bootstrap once.
         *
         * @var bool
         */
        protected $public_bootstrapped = false;

        /**
         * Tracks whether the admin components have been initialised.
         *
         * @var bool
         */
        protected $admin_bootstrapped = false;

        /**
         * Booking manager instance.
         *
         * @var RB_Modern_Booking_Manager|null
         */
        protected $booking_manager = null;

        /**
         * Dashboard instance.
         *
         * @var RB_Modern_Dashboard|null
         */
        protected $dashboard = null;

        /**
         * Table manager instance.
         *
         * @var RB_Modern_Table_Manager|null
         */
        protected $table_manager = null;

        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function __construct() {
            $this->loader = new Restaurant_Booking_Plugin_Loader();
            $this->load_dependencies();
            $this->define_hooks();
        }

        protected function load_dependencies() {
            $required_files = array(
                'includes/class-plugin-activator.php',
                'includes/class-plugin-deactivator.php',
                'includes/traits/trait-rb-asset-loader.php',
                'includes/models/class-booking.php',
                'includes/models/class-location.php',
                'includes/models/class-table.php',
                'includes/models/class-customer.php',
                'includes/services/class-analytics-service.php',
                'includes/services/class-rb-analytics.php',
                'includes/services/class-calendar-service.php',
                'includes/services/class-notification-service.php',
                'includes/database/schema.php',
            );

            foreach ( $required_files as $file ) {
                $path = RESTAURANT_BOOKING_PATH . $file;

                if ( file_exists( $path ) ) {
                    require_once $path;
                } else {
                    $this->register_missing_file_notice( $file );
                }
            }

            $admin_file = RESTAURANT_BOOKING_PATH . 'admin/class-modern-admin.php';
            if ( file_exists( $admin_file ) ) {
                require_once $admin_file;
            }

            $public_files = array(
                'public/class-modern-booking-widget.php',
                'public/class-modern-portal-auth.php',
                'public/class-modern-dashboard.php',
                'public/class-modern-booking-manager.php',
                'public/class-modern-table-manager.php',
            );

            foreach ( $public_files as $file ) {
                $path = RESTAURANT_BOOKING_PATH . $file;

                if ( file_exists( $path ) ) {
                    require_once $path;
                } else {
                    $this->register_missing_file_notice( $file );
                }
            }
        }

        protected function define_hooks() {
            if ( did_action( 'plugins_loaded' ) ) {
                $this->load_textdomain();
            } else {
                $this->loader->add_action( 'plugins_loaded', $this, 'load_textdomain' );
            }

            $this->loader->add_action( 'init', $this, 'bootstrap_public_components' );
            $this->loader->add_action( 'admin_init', $this, 'bootstrap_admin_components' );
        }

        /**
         * Load plugin translations.
         */
        public function load_textdomain() {
            load_plugin_textdomain(
                'restaurant-booking',
                false,
                dirname( RESTAURANT_BOOKING_BASENAME ) . '/languages/'
            );
        }

        /**
         * Register an admin notice when a required file is missing.
         *
         * @param string $file Missing file path relative to the plugin root.
         */
        protected function register_missing_file_notice( $file ) {
            error_log( sprintf( 'Restaurant Booking Error: Missing required file - %s', $file ) );

            add_action(
                'admin_notices',
                function () use ( $file ) {
                    printf(
                        '<div class="notice notice-error"><p>%s</p></div>',
                        sprintf(
                            /* translators: %s: Missing file name. */
                            __( 'Restaurant Booking: Missing file %s. Please reinstall the plugin.', 'restaurant-booking' ),
                            esc_html( basename( $file ) )
                        )
                    );
                }
            );
        }

        public function bootstrap_public_components() {
            if ( $this->public_bootstrapped ) {
                return;
            }

            new RB_Modern_Booking_Widget();
            $this->booking_manager = new RB_Modern_Booking_Manager();
            $this->dashboard       = new RB_Modern_Dashboard();
            $this->table_manager   = new RB_Modern_Table_Manager();
            new RB_Modern_Portal_Auth();

            $this->register_public_shortcodes();

            $this->public_bootstrapped = true;
        }

        protected function register_public_shortcodes() {
            if ( $this->booking_manager && method_exists( $this->booking_manager, 'render_calendar_shortcode' ) ) {
                add_shortcode( 'booking_calendar', array( $this->booking_manager, 'render_calendar_shortcode' ) );
            }

            if ( $this->dashboard && method_exists( $this->dashboard, 'render_analytics_shortcode' ) ) {
                add_shortcode( 'restaurant_analytics', array( $this->dashboard, 'render_analytics_shortcode' ) );
            }

            if ( $this->table_manager && method_exists( $this->table_manager, 'render_floor_plan_shortcode' ) ) {
                add_shortcode( 'table_floor_plan', array( $this->table_manager, 'render_floor_plan_shortcode' ) );
            }
        }

        public function bootstrap_admin_components() {
            if ( $this->admin_bootstrapped ) {
                return;
            }

            if ( ! is_admin() ) {
                return;
            }

            if ( class_exists( 'RB_Modern_Admin' ) ) {
                new RB_Modern_Admin();
                $this->admin_bootstrapped = true;
            }
        }

        public function run() {
            $this->loader->run();
        }
    }
}
