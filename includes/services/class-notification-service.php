<?php
/**
 * Notification delivery service.
 *
 * Responsible for transactional booking emails including confirmation,
 * reminders, and cancellations.
 *
 * @package RestaurantBooking\Services
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RB_Notification_Service' ) ) {

    class RB_Notification_Service {

        /**
         * Option key storing hashed booking tokens.
         */
        const TOKENS_OPTION = 'rb_booking_tokens';

        /**
         * Default lifetime for booking tokens (in seconds).
         *
         * @var int
         */
        protected $token_ttl = DAY_IN_SECONDS * 2;

        /**
         * Send a booking confirmation email.
         *
         * @param int|array|RB_Booking $booking Booking identifier or payload.
         *
         * @return bool
         */
        public function send_booking_confirmation( $booking ) {
            $data = $this->resolve_booking( $booking );

            if ( empty( $data ) || empty( $data['customer_email'] ) || ! is_email( $data['customer_email'] ) ) {
                return false;
            }

            $token       = $this->generate_token( $data['id'], 'confirmation' );
            $confirm_url = $this->build_confirmation_url( $data, $token );

            $subject = sprintf(
                /* translators: %s: Restaurant name. */
                __( 'Your reservation at %s', 'restaurant-booking' ),
                $this->get_restaurant_name()
            );
            $subject = apply_filters( 'rb_confirmation_email_subject', $subject, $data, $token );

            $body = $this->render_email_template(
                array(
                    'heading'      => __( 'Reservation Confirmed', 'restaurant-booking' ),
                    'intro'        => $this->get_confirmation_intro(),
                    'booking'      => $data,
                    'token'        => $token,
                    'cta'          => array(
                        'label' => __( 'Confirm Reservation', 'restaurant-booking' ),
                        'url'   => $confirm_url,
                    ),
                    'footer'       => $this->get_footer_text(),
                    'type'         => 'confirmation',
                    'extra_markup' => $this->format_special_requests( $data ),
                )
            );

            $body = apply_filters( 'rb_confirmation_email_body', $body, $data, $token, $confirm_url );

            $headers = $this->prepare_headers();

            $sent = $this->send_mail( $data['customer_email'], $subject, $body, $headers );

            if ( $sent ) {
                $this->store_token( $data['id'], 'confirmation', $token );

                /**
                 * Fires after the booking confirmation email is sent.
                 *
                 * @since 2.0.0
                 *
                 * @param array  $data        Booking data payload.
                 * @param string $token       Generated confirmation token.
                 * @param string $confirm_url Confirmation link sent to the guest.
                 */
                do_action( 'rb_confirmation_email_sent', $data, $token, $confirm_url );
            }

            return $sent;
        }

        /**
         * Send a booking reminder email.
         *
         * @param int|array|RB_Booking $booking Booking identifier or payload.
         *
         * @return bool
         */
        public function send_booking_reminder( $booking ) {
            $data = $this->resolve_booking( $booking );

            if ( empty( $data ) || empty( $data['customer_email'] ) || ! is_email( $data['customer_email'] ) ) {
                return false;
            }

            $subject = sprintf(
                /* translators: %s: Restaurant name. */
                __( 'Reminder: your reservation at %s', 'restaurant-booking' ),
                $this->get_restaurant_name()
            );
            $subject = apply_filters( 'rb_reminder_email_subject', $subject, $data );

            $body = $this->render_email_template(
                array(
                    'heading'      => __( 'See you soon!', 'restaurant-booking' ),
                    'intro'        => sprintf(
                        /* translators: %s: Formatted reservation date. */
                        __( 'This is a friendly reminder about your upcoming reservation on %s.', 'restaurant-booking' ),
                        $this->format_booking_datetime( $data )
                    ),
                    'booking'      => $data,
                    'footer'       => $this->get_footer_text(),
                    'type'         => 'reminder',
                    'extra_markup' => $this->format_special_requests( $data ),
                )
            );

            $body = apply_filters( 'rb_reminder_email_body', $body, $data );

            $headers = $this->prepare_headers();

            $sent = $this->send_mail( $data['customer_email'], $subject, $body, $headers );

            if ( $sent ) {
                /**
                 * Fires after the booking reminder email is sent.
                 *
                 * @since 2.0.0
                 *
                 * @param array $data Booking data payload.
                 */
                do_action( 'rb_reminder_email_sent', $data );
            }

            return $sent;
        }

        /**
         * Send a booking cancellation email.
         *
         * @param int|array|RB_Booking $booking Booking identifier or payload.
         *
         * @return bool
         */
        public function send_booking_cancellation( $booking ) {
            $data = $this->resolve_booking( $booking );

            if ( empty( $data ) || empty( $data['customer_email'] ) || ! is_email( $data['customer_email'] ) ) {
                return false;
            }

            $subject = sprintf(
                /* translators: %s: Restaurant name. */
                __( 'Your reservation at %s has been cancelled', 'restaurant-booking' ),
                $this->get_restaurant_name()
            );
            $subject = apply_filters( 'rb_cancellation_email_subject', $subject, $data );

            $body = $this->render_email_template(
                array(
                    'heading'      => __( 'Reservation Cancelled', 'restaurant-booking' ),
                    'intro'        => __( 'We are sorry to see you go. Your reservation has been cancelled as requested.', 'restaurant-booking' ),
                    'booking'      => $data,
                    'footer'       => $this->get_footer_text(),
                    'type'         => 'cancellation',
                    'extra_markup' => $this->format_special_requests( $data ),
                )
            );

            $body = apply_filters( 'rb_cancellation_email_body', $body, $data );

            $headers = $this->prepare_headers();

            $sent = $this->send_mail( $data['customer_email'], $subject, $body, $headers );

            if ( $sent ) {
                /**
                 * Fires after a cancellation email is dispatched.
                 *
                 * @since 2.0.0
                 *
                 * @param array $data Booking data payload.
                 */
                do_action( 'rb_cancellation_email_sent', $data );
            }

            return $sent;
        }

        /**
         * Resolve booking payload from various inputs.
         *
         * @param int|array|RB_Booking $booking Booking input.
         *
         * @return array
         */
        protected function resolve_booking( $booking ) {
            if ( is_array( $booking ) ) {
                return $booking;
            }

            if ( $booking instanceof RB_Booking ) {
                return $booking->get_data();
            }

            if ( is_numeric( $booking ) ) {
                $booking_id = absint( $booking );

                if ( class_exists( 'RB_Booking' ) && $booking_id > 0 ) {
                    $model = new RB_Booking( $booking_id );
                    if ( method_exists( $model, 'get_data' ) ) {
                        return $model->get_data();
                    }
                }
            }

            return array();
        }

        /**
         * Prepare email headers.
         *
         * @param array $headers Custom headers.
         *
         * @return array
         */
        protected function prepare_headers( $headers = array() ) {
            $defaults = array( 'Content-Type: text/html; charset=UTF-8' );

            $from_email = apply_filters( 'rb_notification_from_email', get_option( 'admin_email' ) );
            $from_name  = apply_filters( 'rb_notification_from_name', $this->get_restaurant_name() );

            if ( $from_email && is_email( $from_email ) ) {
                $defaults[] = sprintf( 'From: %s <%s>', $from_name ? $from_name : get_bloginfo( 'name' ), $from_email );
            }

            $headers = array_merge( $defaults, $headers );

            /**
             * Filter the outgoing email headers for plugin notifications.
             *
             * @since 2.0.0
             *
             * @param array $headers Email headers.
             */
            return apply_filters( 'rb_notification_email_headers', $headers );
        }

        /**
         * Generate a secure token for the booking.
         *
         * @param int    $booking_id Booking identifier.
         * @param string $type       Token type.
         *
         * @return string
         */
        protected function generate_token( $booking_id, $type ) {
            $booking_id = absint( $booking_id );

            if ( $booking_id <= 0 ) {
                return '';
            }

            $length = apply_filters( 'rb_booking_token_length', 32, $type, $booking_id );
            $token  = wp_generate_password( $length, false, false );

            return apply_filters( 'rb_booking_token_generated', $token, $type, $booking_id );
        }

        /**
         * Persist token hash to the database for future validation.
         *
         * @param int    $booking_id Booking identifier.
         * @param string $type       Token type.
         * @param string $token      Raw token string.
         */
        protected function store_token( $booking_id, $type, $token ) {
            $booking_id = absint( $booking_id );
            $type       = sanitize_key( $type );
            $token      = (string) $token;

            if ( $booking_id <= 0 || '' === $type || '' === $token ) {
                return;
            }

            $hash = $this->hash_token( $token, $booking_id, $type );

            $tokens = get_option( self::TOKENS_OPTION, array() );
            if ( ! is_array( $tokens ) ) {
                $tokens = array();
            }

            if ( ! isset( $tokens[ $type ] ) || ! is_array( $tokens[ $type ] ) ) {
                $tokens[ $type ] = array();
            }

            $expires = time() + (int) apply_filters( 'rb_booking_token_ttl', $this->token_ttl, $type, $booking_id );

            $tokens[ $type ][ $booking_id ] = array(
                'hash'        => $hash,
                'generated_at'=> time(),
                'expires'     => $expires,
            );

            update_option( self::TOKENS_OPTION, $tokens, false );
        }

        /**
         * Produce a confirmation URL containing the token payload.
         *
         * @param array  $booking Booking data array.
         * @param string $token   Raw token.
         *
         * @return string
         */
        protected function build_confirmation_url( $booking, $token ) {
            $args = array(
                'rb-confirm' => rawurlencode( $token ),
                'booking'    => isset( $booking['id'] ) ? absint( $booking['id'] ) : 0,
            );

            $url = add_query_arg( $args, home_url( '/' ) );

            /**
             * Filter the confirmation URL sent to the guest.
             *
             * @since 2.0.0
             *
             * @param string $url     Confirmation URL.
             * @param array  $booking Booking data.
             * @param string $token   Token string.
             */
            return apply_filters( 'rb_booking_confirmation_url', $url, $booking, $token );
        }

        /**
         * Format the booking date/time for display.
         *
         * @param array $booking Booking data payload.
         *
         * @return string
         */
        protected function format_booking_datetime( $booking ) {
            $date = isset( $booking['booking_date'] ) ? $booking['booking_date'] : '';
            $time = isset( $booking['booking_time'] ) ? $booking['booking_time'] : '';

            if ( $date && $time ) {
                $timestamp = strtotime( $date . ' ' . $time );
            } elseif ( $date ) {
                $timestamp = strtotime( $date );
            } else {
                $timestamp = false;
            }

            if ( false === $timestamp ) {
                return trim( $date . ' ' . $time );
            }

            $format = sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'g:i a' ) );

            return wp_date( $format, $timestamp );
        }

        /**
         * Retrieve the restaurant name from settings.
         *
         * @return string
         */
        protected function get_restaurant_name() {
            if ( function_exists( 'restaurant_booking_get_setting' ) ) {
                $name = restaurant_booking_get_setting( 'restaurant_name', '' );
                if ( $name ) {
                    return $name;
                }
            }

            return get_bloginfo( 'name' );
        }

        /**
         * Retrieve the intro text for confirmation emails.
         *
         * @return string
         */
        protected function get_confirmation_intro() {
            if ( function_exists( 'restaurant_booking_get_setting' ) ) {
                $template = restaurant_booking_get_setting( 'confirmation_template', '' );
                if ( $template ) {
                    return wp_kses_post( $template );
                }
            }

            return __( 'Thank you for choosing us. We look forward to hosting you!', 'restaurant-booking' );
        }

        /**
         * Compose the standard email footer.
         *
         * @return string
         */
        protected function get_footer_text() {
            $phone = get_option( 'admin_phone', '' );
            $email = apply_filters( 'rb_support_email', get_option( 'admin_email' ) );

            $parts = array();

            if ( $phone ) {
                $parts[] = sprintf(
                    /* translators: %s: Phone number. */
                    __( 'Need help? Call us at %s.', 'restaurant-booking' ),
                    esc_html( $phone )
                );
            }

            if ( $email && is_email( $email ) ) {
                $parts[] = sprintf(
                    /* translators: %s: Support email. */
                    __( 'You can also reach us at %s.', 'restaurant-booking' ),
                    esc_html( $email )
                );
            }

            $footer = implode( ' ', $parts );

            if ( '' === $footer ) {
                $footer = __( 'We look forward to serving you.', 'restaurant-booking' );
            }

            return $footer;
        }

        /**
         * Convert special requests into formatted markup.
         *
         * @param array $booking Booking payload.
         *
         * @return string
         */
        protected function format_special_requests( $booking ) {
            if ( empty( $booking['special_requests'] ) ) {
                return '';
            }

            $label = __( 'Special Requests', 'restaurant-booking' );
            $text  = wp_kses_post( $booking['special_requests'] );

            $markup  = '<div style="margin-top:24px;">';
            $markup .= '<strong style="display:block;margin-bottom:6px;font-size:14px;line-height:20px;color:#111827;">' . esc_html( $label ) . '</strong>';
            $markup .= '<div style="font-size:14px;line-height:20px;color:#374151;">' . wpautop( $text ) . '</div>';
            $markup .= '</div>';

            return $markup;
        }

        /**
         * Render the email markup.
         *
         * @param array $args Template arguments.
         *
         * @return string
         */
        protected function render_email_template( $args ) {
            $defaults = array(
                'heading'      => '',
                'intro'        => '',
                'booking'      => array(),
                'footer'       => '',
                'cta'          => array(),
                'token'        => '',
                'type'         => '',
                'extra_markup' => '',
            );

            $args    = wp_parse_args( $args, $defaults );
            $booking = $args['booking'];

            $details = $this->prepare_booking_details( $booking );

            ob_start();
            ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo esc_html( $args['heading'] ); ?></title>
</head>
<body style="margin:0;padding:32px;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,'Noto Sans',sans-serif;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;box-shadow:0 12px 30px rgba(15,23,42,0.08);overflow:hidden;">
        <div style="padding:32px 32px 24px;border-bottom:1px solid #e5e7eb;background:linear-gradient(135deg,#111827,#1f2937);color:#ffffff;">
            <h1 style="margin:0;font-size:24px;line-height:32px;font-weight:600;">
                <?php echo esc_html( $args['heading'] ); ?>
            </h1>
            <div style="margin:12px 0 0;font-size:16px;line-height:24px;color:rgba(255,255,255,0.82);">
                <?php echo wp_kses_post( wpautop( $args['intro'] ) ); ?>
            </div>
        </div>
        <div style="padding:28px 32px 32px;">
            <table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 24px;">
                <tbody>
                    <?php foreach ( $details as $label => $value ) : ?>
                        <tr>
                            <th style="text-align:left;padding:8px 12px 8px 0;font-size:14px;line-height:20px;color:#6b7280;font-weight:500;white-space:nowrap;">
                                <?php echo esc_html( $label ); ?>
                            </th>
                            <td style="padding:8px 0;font-size:15px;line-height:22px;color:#111827;">
                                <?php echo esc_html( $value ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( ! empty( $args['extra_markup'] ) ) : ?>
                <?php echo $args['extra_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <?php if ( ! empty( $args['cta']['url'] ) && ! empty( $args['cta']['label'] ) ) : ?>
                <div style="margin-top:28px;text-align:center;">
                    <a href="<?php echo esc_url( $args['cta']['url'] ); ?>" style="display:inline-block;padding:14px 32px;border-radius:9999px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#ffffff;font-weight:600;font-size:15px;text-decoration:none;box-shadow:0 12px 24px rgba(37,99,235,0.28);">
                        <?php echo esc_html( $args['cta']['label'] ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div style="padding:24px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:13px;line-height:20px;color:#6b7280;">
            <?php echo wp_kses_post( wpautop( $args['footer'] ) ); ?>
        </div>
    </div>
</body>
</html>
<?php
            $markup = ob_get_clean();

            /**
             * Filter the rendered email markup before sending.
             *
             * @since 2.0.0
             *
             * @param string $markup Rendered HTML markup.
             * @param array  $args   Template arguments.
             */
            return apply_filters( 'rb_notification_email_template', $markup, $args );
        }

        /**
         * Prepare the table of booking details.
         *
         * @param array $booking Booking payload.
         *
         * @return array<string,string>
         */
        protected function prepare_booking_details( $booking ) {
            $details = array();

            $details[ __( 'Reservation Code', 'restaurant-booking' ) ] = isset( $booking['id'] ) && $booking['id']
                ? '#' . absint( $booking['id'] )
                : __( 'Pending', 'restaurant-booking' );

            $details[ __( 'Guest', 'restaurant-booking' ) ] = isset( $booking['customer_name'] ) && $booking['customer_name']
                ? $booking['customer_name']
                : __( 'Valued Guest', 'restaurant-booking' );

            if ( isset( $booking['customer_email'] ) && $booking['customer_email'] ) {
                $details[ __( 'Email', 'restaurant-booking' ) ] = $booking['customer_email'];
            }

            if ( isset( $booking['customer_phone'] ) && $booking['customer_phone'] ) {
                $details[ __( 'Phone', 'restaurant-booking' ) ] = $booking['customer_phone'];
            }

            $details[ __( 'Date & Time', 'restaurant-booking' ) ] = $this->format_booking_datetime( $booking );

            if ( isset( $booking['party_size'] ) && $booking['party_size'] ) {
                $details[ __( 'Party Size', 'restaurant-booking' ) ] = (string) max( 1, (int) $booking['party_size'] );
            }

            $location_name = '';

            if ( isset( $booking['location_name'] ) && $booking['location_name'] ) {
                $location_name = $booking['location_name'];
            } elseif ( isset( $booking['location_id'] ) && $booking['location_id'] && class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_location' ) ) {
                $location = RB_Location::get_location( (int) $booking['location_id'] );
                if ( $location && ! empty( $location->name ) ) {
                    $location_name = $location->name;
                }
            }

            if ( $location_name ) {
                $details[ __( 'Location', 'restaurant-booking' ) ] = $location_name;
            }

            return apply_filters( 'rb_notification_booking_details', $details, $booking );
        }

        /**
         * Hash a token value using a stable algorithm.
         *
         * @param string $token      Raw token string.
         * @param int    $booking_id Booking identifier.
         * @param string $type       Token type.
         *
         * @return string
         */
        protected function hash_token( $token, $booking_id, $type ) {
            $salt = wp_salt( 'rb_booking_token' );

            return hash_hmac( 'sha256', $token . '|' . $booking_id . '|' . $type, $salt );
        }

        /**
         * Wrapper around wp_mail() to simplify testing.
         *
         * @param string       $to      Recipient address.
         * @param string       $subject Email subject.
         * @param string       $body    Email body.
         * @param string|array $headers Headers.
         *
         * @return bool
         */
        protected function send_mail( $to, $subject, $body, $headers ) {
            if ( ! function_exists( 'wp_mail' ) ) {
                return false;
            }

            return wp_mail( $to, $subject, $body, $headers );
        }
    }
}
