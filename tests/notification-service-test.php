<?php
require __DIR__ . '/helpers/wp-stubs.php';
require dirname( __DIR__ ) . '/restaurant-booking-manager.php';
require dirname( __DIR__ ) . '/includes/services/class-notification-service.php';

function assert_true( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

function assert_equals( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        throw new RuntimeException( $message . ' Expected: ' . var_export( $expected, true ) . ' Actual: ' . var_export( $actual, true ) );
    }
}

function assert_contains( $needle, $haystack, $message ) {
    if ( false === strpos( $haystack, $needle ) ) {
        throw new RuntimeException( $message . ' Missing fragment: ' . $needle );
    }
}

function assert_not_equals( $expected, $actual, $message ) {
    if ( $expected === $actual ) {
        throw new RuntimeException( $message );
    }
}

function reset_email_log() {
    $GLOBALS['wp_sent_emails'] = array();
}

function get_last_email() {
    $emails = $GLOBALS['wp_sent_emails'];

    return end( $emails );
}

function reset_filters() {
    remove_all_filters( 'rb_confirmation_email_body' );
    remove_all_filters( 'rb_booking_token_generated' );
    remove_all_filters( 'rb_booking_settings' );
}

function test_booking_settings_filter_alias() {
    reset_filters();
    $GLOBALS['wp_options'] = array();

    add_filter(
        'rb_booking_settings',
        function ( $settings ) {
            $settings['buffer_time'] = 45;

            return $settings;
        }
    );

    $settings = restaurant_booking_get_settings();

    assert_equals( 45, $settings['buffer_time'], 'rb_booking_settings filter should adjust settings.' );
}

function test_confirmation_email_delivery() {
    reset_filters();
    $GLOBALS['wp_options'] = array();
    reset_email_log();

    update_option(
        'restaurant_booking_settings',
        array(
            'restaurant_name'       => 'Test Bistro',
            'confirmation_template' => "Xin cảm ơn quý khách! Chúng tôi sẽ giữ bàn trong 15 phút.",
        )
    );
    update_option( 'admin_email', 'owner@example.com' );
    update_option( 'date_format', 'F j, Y' );
    update_option( 'time_format', 'g:i a' );

    $captured_token = null;
    $filtered_body  = false;

    add_filter(
        'rb_booking_token_generated',
        function ( $token ) use ( &$captured_token ) {
            $captured_token = $token;

            return $token;
        },
        10,
        3
    );

    add_filter(
        'rb_confirmation_email_body',
        function ( $body, $data, $token, $confirm_url ) use ( &$filtered_body ) {
            $filtered_body = true;

            return $body . '\n<!-- Filtered -->';
        },
        10,
        4
    );

    $booking = array(
        'id'               => 321,
        'customer_email'   => 'guest@example.com',
        'customer_name'    => 'Jane Doe',
        'customer_phone'   => '+123456789',
        'booking_date'     => '2024-06-01',
        'booking_time'     => '19:30',
        'party_size'       => 4,
        'special_requests' => 'Chỗ ngồi gần cửa sổ',
        'location_name'    => 'Downtown',
    );

    $service = new RB_Notification_Service();

    $sent = $service->send_booking_confirmation( $booking );

    assert_true( $sent, 'Confirmation email should be marked as sent.' );
    assert_true( $filtered_body, 'rb_confirmation_email_body filter should run.' );
    assert_true( ! empty( $captured_token ), 'Booking confirmation should generate a token.' );

    $email = get_last_email();

    assert_equals( 'guest@example.com', $email['to'], 'Confirmation email should target customer email.' );
    assert_equals( 'Your reservation at Test Bistro', $email['subject'], 'Confirmation subject should include restaurant name.' );
    assert_true( in_array( 'Content-Type: text/html; charset=UTF-8', $email['headers'], true ), 'Headers should define HTML content type.' );

    assert_contains( 'Reservation Confirmed', $email['message'], 'Email body should include heading.' );
    assert_contains( 'Xin cảm ơn quý khách!', $email['message'], 'Email body should render confirmation template.' );
    assert_contains( 'Confirm Reservation', $email['message'], 'Email body should include CTA label.' );
    assert_contains( 'rb-confirm=', $email['message'], 'Email body should include confirmation URL with token.' );
    assert_contains( 'Special Requests', $email['message'], 'Email body should include special requests label.' );
    assert_contains( '<!-- Filtered -->', $email['message'], 'Email body should include filter output.' );

    $stored_tokens = get_option( RB_Notification_Service::TOKENS_OPTION );
    assert_true( isset( $stored_tokens['confirmation'][321] ), 'Confirmation token should be stored.' );

    $token_record = $stored_tokens['confirmation'][321];
    $expected_hash = hash_hmac( 'sha256', $captured_token . '|321|confirmation', wp_salt( 'rb_booking_token' ) );

    assert_equals( $expected_hash, $token_record['hash'], 'Stored token hash should match expected value.' );
    assert_not_equals( $captured_token, $token_record['hash'], 'Stored token should be hashed.' );
    assert_true( $token_record['expires'] > $token_record['generated_at'], 'Token should have an expiration timestamp.' );
}

function test_reminder_email_delivery() {
    reset_filters();
    reset_email_log();

    update_option(
        'restaurant_booking_settings',
        array(
            'restaurant_name' => 'Test Bistro',
        )
    );

    $booking = array(
        'id'             => 321,
        'customer_email' => 'guest@example.com',
        'customer_name'  => 'Jane Doe',
        'booking_date'   => '2024-06-01',
        'booking_time'   => '19:30',
        'party_size'     => 4,
    );

    $service = new RB_Notification_Service();
    $sent    = $service->send_booking_reminder( $booking );

    assert_true( $sent, 'Reminder email should be sent successfully.' );

    $email = get_last_email();

    assert_equals( 'Reminder: your reservation at Test Bistro', $email['subject'], 'Reminder subject should be localized.' );
    assert_contains( 'See you soon!', $email['message'], 'Reminder email should include heading text.' );
    assert_contains( 'upcoming reservation on', $email['message'], 'Reminder email should include intro text.' );
}

function test_cancellation_email_delivery() {
    reset_filters();
    reset_email_log();

    update_option(
        'restaurant_booking_settings',
        array(
            'restaurant_name' => 'Test Bistro',
        )
    );

    $booking = array(
        'id'             => 321,
        'customer_email' => 'guest@example.com',
        'customer_name'  => 'Jane Doe',
        'booking_date'   => '2024-06-01',
        'booking_time'   => '19:30',
        'party_size'     => 4,
    );

    $service = new RB_Notification_Service();
    $sent    = $service->send_booking_cancellation( $booking );

    assert_true( $sent, 'Cancellation email should be sent successfully.' );

    $email = get_last_email();

    assert_equals( 'Your reservation at Test Bistro has been cancelled', $email['subject'], 'Cancellation subject should be localized.' );
    assert_contains( 'Reservation Cancelled', $email['message'], 'Cancellation email should include heading text.' );
    assert_contains( 'Your reservation has been cancelled as requested.', $email['message'], 'Cancellation email should include localized intro.' );
}

try {
    test_booking_settings_filter_alias();
    test_confirmation_email_delivery();
    test_reminder_email_delivery();
    test_cancellation_email_delivery();

    echo "All notification service tests passed.\n";
} catch ( Throwable $e ) {
    echo 'Test failure: ' . $e->getMessage() . "\n";
    exit( 1 );
}
