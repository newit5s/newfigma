<?php
/**
 * Plugin Loader
 */

if ( ! class_exists( 'Restaurant_Booking_Plugin_Loader' ) ) {

    class Restaurant_Booking_Plugin_Loader {

        /**
         * Registered WordPress actions.
         *
         * @var array
         */
        protected $actions = array();

        /**
         * Registered WordPress filters.
         *
         * @var array
         */
        protected $filters = array();

        /**
         * Registered shortcodes.
         *
         * @var array
         */
        protected $shortcodes = array();

        public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
            $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
        }

        public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
            $this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
        }

        public function add_shortcode( $tag, $component, $callback ) {
            $this->shortcodes[] = compact( 'tag', 'component', 'callback' );
        }

        public function do_actions() {
            foreach ( $this->actions as $hook ) {
                add_action(
                    $hook['hook'],
                    array( $hook['component'], $hook['callback'] ),
                    $hook['priority'],
                    $hook['accepted_args']
                );
            }
        }

        public function do_filters() {
            foreach ( $this->filters as $hook ) {
                add_filter(
                    $hook['hook'],
                    array( $hook['component'], $hook['callback'] ),
                    $hook['priority'],
                    $hook['accepted_args']
                );
            }
        }

        public function do_shortcodes() {
            foreach ( $this->shortcodes as $shortcode ) {
                add_shortcode(
                    $shortcode['tag'],
                    array( $shortcode['component'], $shortcode['callback'] )
                );
            }
        }

        public function run() {
            $this->do_actions();
            $this->do_filters();
            $this->do_shortcodes();
        }
    }
}
