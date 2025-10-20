<?php
/**
 * Modern Restaurant Booking Manager - Portal Authentication Integration
 *
 * Registers the modern portal login shortcode, enqueues assets, and bridges
 * the AJAX authentication flow with the existing session management hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Modern_Portal_Auth' ) ) {

    class RB_Modern_Portal_Auth {

        /**
         * Shortcode identifier for the portal login view.
         *
         * @var string
         */
        protected $shortcode = 'modern_booking_portal';

        /**
         * Register hooks.
         */
        public function __construct() {
            add_shortcode( $this->shortcode, array( $this, 'render_portal_entry' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

            add_action( 'wp_ajax_rb_portal_login', array( $this, 'handle_login' ) );
            add_action( 'wp_ajax_nopriv_rb_portal_login', array( $this, 'handle_login' ) );

            add_action( 'wp_ajax_rb_portal_check_session', array( $this, 'check_session' ) );
            add_action( 'wp_ajax_nopriv_rb_portal_check_session', array( $this, 'check_session' ) );

            add_action( 'wp_ajax_rb_portal_logout', array( $this, 'handle_logout' ) );
        }

        /**
         * Render the portal entry point (login view for Phase 3).
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_portal_entry( $atts ) {
            $atts = shortcode_atts(
                array(
                    'view'                 => 'login',
                    'redirect'             => '',
                    'forgot_password_url'  => '',
                    'logo_text'            => __( 'Restaurant Manager', 'restaurant-booking' ),
                    'subtitle'             => __( 'Staff Portal', 'restaurant-booking' ),
                ),
                $atts,
                $this->shortcode
            );

            $view = sanitize_key( $atts['view'] );
            if ( 'login' !== $view ) {
                /**
                 * Filter to render alternate portal views.
                 *
                 * @param string $view_identifier Requested view identifier.
                 * @param array  $atts            Shortcode attributes.
                 * @param self   $auth            Current instance.
                 */
                $alternate = apply_filters( 'rb_portal_render_view', '', $view, $atts, $this );
                if ( is_string( $alternate ) && '' !== $alternate ) {
                    return $alternate;
                }
            }

            $redirect_url        = $this->resolve_redirect_url( $atts['redirect'] );
            $forgot_password_url = $this->resolve_forgot_password_url( $atts['forgot_password_url'] );

            $logo_text = sanitize_text_field( $atts['logo_text'] );
            $subtitle  = sanitize_text_field( $atts['subtitle'] );

            $context = apply_filters(
                'rb_portal_login_view_data',
                array(
                    'redirect_url'        => $redirect_url,
                    'forgot_password_url' => $forgot_password_url,
                    'logo_text'           => $logo_text,
                    'subtitle'            => $subtitle,
                ),
                $atts
            );

            $redirect_url        = isset( $context['redirect_url'] ) ? $context['redirect_url'] : $redirect_url;
            $forgot_password_url = isset( $context['forgot_password_url'] ) ? $context['forgot_password_url'] : $forgot_password_url;
            $logo_text           = isset( $context['logo_text'] ) ? $context['logo_text'] : $logo_text;
            $subtitle            = isset( $context['subtitle'] ) ? $context['subtitle'] : $subtitle;

            ob_start();

            $partial = $this->get_partial_path( 'portal-login.php' );
            if ( file_exists( $partial ) ) {
                include $partial;
            }

            return ob_get_clean();
        }

        /**
         * Enqueue login assets when the shortcode is present on the current post.
         */
        public function enqueue_assets() {
            if ( ! $this->is_shortcode_present() ) {
                return;
            }

            $version  = defined( 'RB_PLUGIN_VERSION' ) ? RB_PLUGIN_VERSION : '1.0.0';
            $base_dir = plugin_dir_url( __FILE__ ) . '../';

            wp_enqueue_style(
                'rb-design-system',
                $base_dir . 'assets/css/design-system.css',
                array(),
                $version
            );

            wp_enqueue_style(
                'rb-components',
                $base_dir . 'assets/css/components.css',
                array( 'rb-design-system' ),
                $version
            );

            wp_enqueue_style(
                'rb-portal-auth',
                $base_dir . 'assets/css/portal-auth.css',
                array( 'rb-design-system', 'rb-components' ),
                $version
            );

            wp_enqueue_script(
                'rb-portal-auth',
                $base_dir . 'assets/js/portal-auth.js',
                array(),
                $version,
                true
            );

            $strings = array(
                'requiredUsername'   => __( 'Enter your username.', 'restaurant-booking' ),
                'requiredPassword'   => __( 'Enter your password.', 'restaurant-booking' ),
                'invalidCredentials' => __( 'The username or password is incorrect.', 'restaurant-booking' ),
                'genericError'       => __( 'Unable to sign you in right now. Please try again.', 'restaurant-booking' ),
                'networkError'       => __( 'Network error. Check your connection and retry.', 'restaurant-booking' ),
                'success'            => __( 'Signed in successfully. Redirecting…', 'restaurant-booking' ),
                'submitLabel'        => __( 'Sign In', 'restaurant-booking' ),
            );

            $localized = array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'rb_portal_auth' ),
                'redirectUrl'   => $this->get_default_redirect_url(),
                'checkSession'  => true,
                'strings'       => $strings,
            );

            wp_localize_script( 'rb-portal-auth', 'rbPortalAuth', $localized );
        }

        /**
         * Handle AJAX login submission.
         */
        public function handle_login() {
            $this->verify_nonce();

            $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
            $password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
            $remember = ! empty( $_POST['remember'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['remember'] ) );
            $redirect = isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : '';

            if ( empty( $username ) || empty( $password ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Please provide both username and password.', 'restaurant-booking' ),
                    ),
                    400
                );
            }

            $auth_result = apply_filters( 'rb_portal_authenticate', null, $username, $password, $remember, $_POST );

            if ( is_wp_error( $auth_result ) ) {
                wp_send_json_error(
                    array(
                        'message' => $auth_result->get_error_message(),
                    ),
                    401
                );
            }

            if ( $auth_result instanceof WP_User ) {
                $this->maybe_set_current_user( $auth_result, $remember );
                $this->send_login_success( $redirect, $auth_result );
            }

            $credentials = array(
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => $remember,
            );

            $user = wp_signon( $credentials, false );
            if ( is_wp_error( $user ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'The username or password is incorrect.', 'restaurant-booking' ),
                    ),
                    401
                );
            }

            $this->send_login_success( $redirect, $user );
        }

        /**
         * Check if a session is already active.
         */
        public function check_session() {
            $this->verify_nonce();

            $session = apply_filters( 'rb_portal_check_session', null );
            if ( is_wp_error( $session ) ) {
                wp_send_json_error(
                    array(
                        'message' => $session->get_error_message(),
                    ),
                    400
                );
            }

            if ( is_array( $session ) && isset( $session['active'] ) ) {
                wp_send_json_success( $session );
            }

            if ( is_user_logged_in() ) {
                wp_send_json_success(
                    array(
                        'active'        => true,
                        'redirect_url'  => $this->get_default_redirect_url(),
                        'user_id'       => get_current_user_id(),
                    )
                );
            }

            wp_send_json_success(
                array(
                    'active' => false,
                )
            );
        }

        /**
         * Destroy the current portal session.
         */
        public function handle_logout() {
            $this->verify_nonce();

            /**
             * Allow custom logout logic (e.g., custom session manager cleanup).
             */
            do_action( 'rb_portal_before_logout' );

            wp_logout();

            do_action( 'rb_portal_after_logout' );

            wp_send_json_success(
                array(
                    'redirect_url' => apply_filters( 'rb_portal_logout_redirect', home_url() ),
                )
            );
        }

        /**
         * Ensure AJAX requests include a valid nonce.
         */
        protected function verify_nonce() {
            $valid = check_ajax_referer( 'rb_portal_auth', 'nonce', false );
            if ( ! $valid ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Authentication token has expired. Refresh and try again.', 'restaurant-booking' ),
                    ),
                    403
                );
            }
        }

        /**
         * Send a successful login response.
         *
         * @param string   $redirect Requested redirect.
         * @param WP_User  $user     Authenticated user.
         */
        protected function send_login_success( $redirect, $user ) {
            do_action( 'rb_portal_login_success', $user );

            $redirect_url = $this->validate_redirect( $redirect );

            wp_send_json_success(
                array(
                    'redirect_url' => $redirect_url,
                    'message'      => __( 'Signed in successfully. Redirecting…', 'restaurant-booking' ),
                )
            );
        }

        /**
         * Safely set the current user if a custom authentication handler returned a user object.
         *
         * @param WP_User $user     Authenticated user.
         * @param bool    $remember Remember me preference.
         */
        protected function maybe_set_current_user( $user, $remember ) {
            if ( ! $user instanceof WP_User ) {
                return;
            }

            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, $remember );
        }

        /**
         * Determine if the shortcode is present in the current request.
         *
         * @return bool
         */
        protected function is_shortcode_present() {
            if ( ! is_singular() ) {
                return false;
            }

            global $post;

            if ( ! $post instanceof WP_Post ) {
                return false;
            }

            return has_shortcode( $post->post_content, $this->shortcode );
        }

        /**
         * Get the absolute path to a portal partial.
         *
         * @param string $file Partial filename.
         *
         * @return string
         */
        protected function get_partial_path( $file ) {
            return plugin_dir_path( __FILE__ ) . 'partials/' . $file;
        }

        /**
         * Compute the default redirect destination after login.
         *
         * @return string
         */
        protected function get_default_redirect_url() {
            $default = apply_filters( 'rb_portal_dashboard_url', home_url( '/portal-dashboard/' ) );
            return esc_url_raw( $default );
        }

        /**
         * Resolve redirect URL preference with safety checks.
         *
         * @param string $requested Requested redirect URL.
         *
         * @return string
         */
        protected function resolve_redirect_url( $requested ) {
            if ( empty( $requested ) ) {
                return $this->get_default_redirect_url();
            }

            $requested = wp_unslash( $requested );

            return $this->validate_redirect( $requested );
        }

        /**
         * Resolve forgot password URL preference with fallback.
         *
         * @param string $requested Requested URL.
         *
         * @return string
         */
        protected function resolve_forgot_password_url( $requested ) {
            if ( ! empty( $requested ) ) {
                return esc_url_raw( wp_unslash( $requested ) );
            }

            $default = apply_filters( 'rb_portal_forgot_password_url', wp_lostpassword_url() );

            return esc_url_raw( $default );
        }

        /**
         * Validate and sanitize a redirect URL.
         *
         * @param string $redirect Requested redirect URL.
         *
         * @return string
         */
        protected function validate_redirect( $redirect ) {
            $default = $this->get_default_redirect_url();
            $redirect = wp_validate_redirect( $redirect, $default );

            return $redirect ? esc_url_raw( $redirect ) : $default;
        }
    }
}
