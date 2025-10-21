<?php
/**
 * Asset Loader Trait
 *
 * Provides shared helpers for enqueueing the Modern Restaurant Booking
 * design system assets while reducing duplication across classes.
 *
 * @package RestaurantBooking
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! trait_exists( 'RB_Asset_Loader' ) ) {

    trait RB_Asset_Loader {

        /**
         * Tracks whether the core assets have already been enqueued.
         *
         * @var bool
         */
        protected $assets_enqueued = false;

        /**
         * Enqueue the shared design system assets used throughout the plugin.
         *
         * @param string $context Context identifier (admin, portal, widget, etc.).
         */
        protected function enqueue_core_assets( $context = 'default' ) {
            if ( $this->assets_enqueued ) {
                return;
            }

            $this->assets_enqueued = true;

            $version  = defined( 'RB_PLUGIN_VERSION' ) ? RB_PLUGIN_VERSION : '1.0.0';
            $base_url = defined( 'RESTAURANT_BOOKING_URL' ) ? RESTAURANT_BOOKING_URL : plugin_dir_url( dirname( __DIR__ ) . '/' );

            // Core styles.
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

            // Theme manager (must be available before dependent scripts).
            wp_enqueue_script(
                'rb-theme-manager',
                $base_url . 'assets/js/theme-manager.js',
                array(),
                $version,
                true
            );

            /**
             * Allow additional logic to run after the shared assets load.
             *
             * @param string $context Context identifier provided to the method.
             * @param string $base_url Base plugin URL.
             * @param string $version  Asset version string.
             */
            do_action( 'rb_after_core_assets', $context, $base_url, $version );
        }

        /**
         * Localize AJAX data for a given script handle.
         *
         * @param string $handle       Script handle to localize.
         * @param string $object_name  JavaScript object name.
         * @param array  $additional   Additional data to merge into the payload.
         * @param string $nonce_action Nonce action name.
         * @param string $nonce_key    Array key that should store the nonce.
         * @param string $ajax_key     Array key that should store the AJAX URL.
         */
        protected function localize_ajax_data( $handle, $object_name, $additional = array(), $nonce_action = 'rb_nonce', $nonce_key = 'nonce', $ajax_key = 'ajax_url' ) {
            $data = array(
                $ajax_key  => admin_url( 'admin-ajax.php' ),
                $nonce_key => wp_create_nonce( $nonce_action ),
            );

            if ( ! empty( $additional ) ) {
                $data = array_merge( $data, $additional );
            }

            wp_localize_script( $handle, $object_name, $data );
        }

        /**
         * Register the Chart.js dependency when required.
         */
        protected function enqueue_chartjs() {
            static $chartjs_loaded = false;

            if ( $chartjs_loaded ) {
                return;
            }

            $chartjs_loaded = true;

            wp_enqueue_script(
                'chart-js',
                'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
                array(),
                '3.9.1',
                true
            );
        }

        /**
         * Enqueue a context specific stylesheet from the assets directory.
         *
         * @param string $context      Context name (e.g. portal-dashboard).
         * @param array  $dependencies Optional extra dependencies.
         */
        protected function enqueue_context_css( $context, $dependencies = array() ) {
            $version  = defined( 'RB_PLUGIN_VERSION' ) ? RB_PLUGIN_VERSION : '1.0.0';
            $base_url = defined( 'RESTAURANT_BOOKING_URL' ) ? RESTAURANT_BOOKING_URL : plugin_dir_url( dirname( __DIR__ ) . '/' );

            $dependencies = array_unique( array_merge( array( 'rb-design-system', 'rb-components', 'rb-animations' ), $dependencies ) );

            wp_enqueue_style(
                'rb-' . $context,
                $base_url . 'assets/css/' . $context . '.css',
                $dependencies,
                $version
            );
        }

        /**
         * Enqueue a context specific script from the assets directory.
         *
         * @param string $context      Context name.
         * @param array  $dependencies Optional extra dependencies.
         */
        protected function enqueue_context_js( $context, $dependencies = array() ) {
            $version  = defined( 'RB_PLUGIN_VERSION' ) ? RB_PLUGIN_VERSION : '1.0.0';
            $base_url = defined( 'RESTAURANT_BOOKING_URL' ) ? RESTAURANT_BOOKING_URL : plugin_dir_url( dirname( __DIR__ ) . '/' );

            if ( ! in_array( 'rb-theme-manager', $dependencies, true ) ) {
                $dependencies[] = 'rb-theme-manager';
            }

            wp_enqueue_script(
                'rb-' . $context,
                $base_url . 'assets/js/' . $context . '.js',
                $dependencies,
                $version,
                true
            );
        }
    }
}
