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
            require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-activator.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/class-plugin-deactivator.php';

            require_once RESTAURANT_BOOKING_PATH . 'includes/models/class-booking.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/models/class-location.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/models/class-table.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/models/class-customer.php';

            require_once RESTAURANT_BOOKING_PATH . 'includes/services/class-analytics-service.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/services/class-rb-analytics.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/services/class-calendar-service.php';
            require_once RESTAURANT_BOOKING_PATH . 'includes/services/class-notification-service.php';

            require_once RESTAURANT_BOOKING_PATH . 'includes/database/schema.php';

            if ( file_exists( RESTAURANT_BOOKING_PATH . 'admin/class-modern-admin.php' ) ) {
                require_once RESTAURANT_BOOKING_PATH . 'admin/class-modern-admin.php';
            }

            require_once RESTAURANT_BOOKING_PATH . 'public/class-modern-booking-widget.php';
            require_once RESTAURANT_BOOKING_PATH . 'public/class-modern-booking-manager.php';
            require_once RESTAURANT_BOOKING_PATH . 'public/class-modern-dashboard.php';
            require_once RESTAURANT_BOOKING_PATH . 'public/class-modern-table-manager.php';
            require_once RESTAURANT_BOOKING_PATH . 'public/class-modern-portal-auth.php';
        }

        protected function define_hooks() {
            $this->loader->add_action( 'init', $this, 'bootstrap_public_components' );
            $this->loader->add_action( 'admin_init', $this, 'bootstrap_admin_components' );
        }

        public function bootstrap_public_components() {
            if ( $this->public_bootstrapped ) {
                return;
            }

            new RB_Modern_Booking_Widget();
            new RB_Modern_Booking_Manager();
            new RB_Modern_Dashboard();
            new RB_Modern_Table_Manager();
            new RB_Modern_Portal_Auth();

            $this->public_bootstrapped = true;
        }

        public function bootstrap_admin_components() {
            if ( ! is_admin() ) {
                return;
            }

            if ( class_exists( 'RB_Modern_Admin' ) ) {
                new RB_Modern_Admin();
            }
        }

        public function run() {
            $this->loader->run();
        }
    }
}
