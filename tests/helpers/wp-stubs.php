<?php
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['wp_filters']     = array();
$GLOBALS['wp_options']     = array();
$GLOBALS['wp_sent_emails'] = array();
$GLOBALS['wp_last_redirect'] = null;

function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
    global $wp_filters;

    if ( ! isset( $wp_filters[ $tag ] ) ) {
        $wp_filters[ $tag ] = array();
    }

    if ( ! isset( $wp_filters[ $tag ][ $priority ] ) ) {
        $wp_filters[ $tag ][ $priority ] = array();
    }

    $wp_filters[ $tag ][ $priority ][] = array(
        'function'      => $function_to_add,
        'accepted_args' => $accepted_args,
    );

    return true;
}

function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
    return add_filter( $tag, $function_to_add, $priority, $accepted_args );
}

function remove_all_filters( $tag, $priority = false ) {
    global $wp_filters;

    if ( ! isset( $wp_filters[ $tag ] ) ) {
        return;
    }

    if ( false === $priority ) {
        unset( $wp_filters[ $tag ] );

        return;
    }

    unset( $wp_filters[ $tag ][ $priority ] );
}

function _wp_sort_hooks( $hooks ) {
    ksort( $hooks );

    return $hooks;
}

function apply_filters( $tag, $value ) {
    global $wp_filters;

    $args  = func_get_args();
    $value = $args[1];

    if ( ! isset( $wp_filters[ $tag ] ) ) {
        return $value;
    }

    $hooks = _wp_sort_hooks( $wp_filters[ $tag ] );

    foreach ( $hooks as $callbacks ) {
        foreach ( $callbacks as $callback ) {
            $function      = $callback['function'];
            $accepted_args = $callback['accepted_args'];
            $callback_args = array_slice( $args, 1, $accepted_args );
            $value         = call_user_func_array( $function, $callback_args );
        }
    }

    return $value;
}

function do_action( $tag ) {
    global $wp_filters;

    $args = func_get_args();

    if ( ! isset( $wp_filters[ $tag ] ) ) {
        return;
    }

    $hooks = _wp_sort_hooks( $wp_filters[ $tag ] );
    $args  = array_slice( $args, 1 );

    foreach ( $hooks as $callbacks ) {
        foreach ( $callbacks as $callback ) {
            $function      = $callback['function'];
            $accepted_args = $callback['accepted_args'];
            $callback_args = array_slice( $args, 0, $accepted_args );
            call_user_func_array( $function, $callback_args );
        }
    }
}

function __( $text, $domain = null ) {
    return $text;
}

function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
    return esc_html( $text );
}

function esc_html__( $text, $domain = null ) {
    return __( $text, $domain );
}

function esc_url( $url ) {
    return filter_var( $url, FILTER_SANITIZE_URL );
}

function wp_nonce_field() {}

function wp_verify_nonce( $nonce, $action ) {
    return true;
}

function wp_create_nonce( $action = -1 ) {
    return 'nonce';
}

function wp_safe_redirect( $location, $status = 302 ) {
    $GLOBALS['wp_last_redirect'] = $location;

    return true;
}

function sanitize_text_field( $text ) {
    return trim( strip_tags( $text ) );
}

function sanitize_key( $key ) {
    $key = strtolower( $key );
    $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

    return $key;
}

function sanitize_email( $email ) {
    return filter_var( $email, FILTER_SANITIZE_EMAIL );
}

function wp_kses_post( $content ) {
    return $content;
}

function wpautop( $pee ) {
    $pee = trim( $pee );

    if ( '' === $pee ) {
        return '';
    }

    $pee = str_replace( array( "\r\n", "\r" ), "\n", $pee );
    $paragraphs = preg_split( '/\n\n+/', $pee );
    $pee = '';

    foreach ( $paragraphs as $paragraph ) {
        $pee .= '<p>' . str_replace( "\n", '<br />', trim( $paragraph ) ) . '</p>';
    }

    return $pee;
}

function wp_parse_args( $args, $defaults = array() ) {
    if ( is_object( $args ) ) {
        $args = get_object_vars( $args );
    }

    if ( ! is_array( $args ) ) {
        $args = array();
    }

    return array_merge( $defaults, $args );
}

function absint( $maybeint ) {
    return abs( intval( $maybeint ) );
}

function is_email( $email ) {
    return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
    return substr( bin2hex( random_bytes( $length ) ), 0, $length );
}

function wp_salt( $scheme = 'auth' ) {
    return 'salt-' . $scheme;
}

function wp_mail( $to, $subject, $message, $headers = '' ) {
    $GLOBALS['wp_sent_emails'][] = array(
        'to'      => $to,
        'subject' => $subject,
        'message' => $message,
        'headers' => $headers,
    );

    return true;
}

function get_option( $option, $default = false ) {
    return array_key_exists( $option, $GLOBALS['wp_options'] ) ? $GLOBALS['wp_options'][ $option ] : $default;
}

function update_option( $option, $value, $autoload = null ) {
    $GLOBALS['wp_options'][ $option ] = $value;

    return true;
}

function add_option( $option, $value ) {
    if ( array_key_exists( $option, $GLOBALS['wp_options'] ) ) {
        return false;
    }

    $GLOBALS['wp_options'][ $option ] = $value;

    return true;
}

function delete_option( $option ) {
    unset( $GLOBALS['wp_options'][ $option ] );
}

function get_bloginfo( $show = '', $filter = 'raw' ) {
    switch ( $show ) {
        case 'charset':
            return 'UTF-8';
        case 'name':
        default:
            return 'Modern Restaurant';
    }
}

function bloginfo( $show = '' ) {
    echo esc_html( get_bloginfo( $show ) );
}

function home_url( $path = '' ) {
    return 'https://example.com' . $path;
}

function add_query_arg( $args, $url ) {
    $parsed = parse_url( $url );
    $query  = array();

    if ( isset( $parsed['query'] ) ) {
        parse_str( $parsed['query'], $query );
    }

    if ( is_array( $args ) ) {
        $query = array_merge( $query, $args );
    }

    $scheme   = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
    $host     = isset( $parsed['host'] ) ? $parsed['host'] : '';
    $port     = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
    $path     = isset( $parsed['path'] ) ? $parsed['path'] : '';
    $fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

    $query_string = http_build_query( $query );

    return $scheme . $host . $port . $path . ( $query_string ? '?' . $query_string : '' ) . $fragment;
}

function language_attributes() {
    echo 'lang="en"';
}

function wp_date( $format, $timestamp ) {
    return gmdate( $format, $timestamp );
}

function current_time( $type, $gmt = 0 ) {
    return gmdate( 'Y-m-d H:i:s' );
}

function plugin_dir_path( $file ) {
    return rtrim( dirname( $file ), '/' ) . '/';
}

function plugin_dir_url( $file ) {
    return 'https://example.com/wp-content/plugins/restaurant-booking/';
}

function plugin_basename( $file ) {
    return basename( dirname( $file ) ) . '/' . basename( $file );
}

function load_plugin_textdomain() {
    return true;
}

function register_activation_hook( $file, $callback ) {}

function register_deactivation_hook( $file, $callback ) {}

function register_uninstall_hook( $file, $callback ) {}

function is_admin() {
    return false;
}

function admin_url( $path = '' ) {
    return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
}

function settings_fields() {}

function do_settings_sections() {}

function submit_button() {}

function wp_enqueue_style() {}

function wp_enqueue_script() {}

function wp_register_style() {}

function wp_register_script() {}

function wp_localize_script() {}

function wp_add_inline_style() {}

function current_user_can() {
    return true;
}

function wp_die( $message ) {
    throw new RuntimeException( $message );
}

function wp_get_current_user() {
    return (object) array( 'ID' => 1 );
}

function get_transient( $transient ) {
    return false;
}

function set_transient( $transient, $value, $expiration = 0 ) {
    return true;
}

function delete_transient( $transient ) {
    return true;
}

function wp_schedule_single_event() {}

function wp_next_scheduled() {
    return false;
}

function wp_unschedule_event() {}

function trailingslashit( $string ) {
    return rtrim( $string, "/\\" ) . '/';
}

function maybe_serialize( $data ) {
    return serialize( $data );
}

function maybe_unserialize( $data ) {
    if ( is_string( $data ) ) {
        $result = @unserialize( $data );
        if ( false !== $result || 'b:0;' === $data ) {
            return $result;
        }
    }

    return $data;
}

function number_format_i18n( $number, $decimals = 0 ) {
    return number_format( $number, $decimals );
}

function wp_json_encode( $data ) {
    return json_encode( $data );
}

function wp_send_json( $response ) {
    echo wp_json_encode( $response );
}

function wp_send_json_success( $data = null ) {
    wp_send_json( array( 'success' => true, 'data' => $data ) );
}

function wp_send_json_error( $data = null ) {
    wp_send_json( array( 'success' => false, 'data' => $data ) );
}

function rest_url( $path = '' ) {
    return 'https://example.com/wp-json/' . ltrim( $path, '/' );
}

function wp_list_pluck( $input_list, $field, $index_key = null ) {
    $output = array();

    foreach ( $input_list as $item ) {
        if ( is_object( $item ) ) {
            $item = get_object_vars( $item );
        }

        if ( ! isset( $item[ $field ] ) ) {
            continue;
        }

        if ( null === $index_key ) {
            $output[] = $item[ $field ];
        } else {
            $output[ $item[ $index_key ] ] = $item[ $field ];
        }
    }

    return $output;
}

function wp_redirect( $location, $status = 302 ) {
    $GLOBALS['wp_last_redirect'] = $location;

    return true;
}

function wp_cache_get() {
    return false;
}

function wp_cache_set() {}

function wp_cache_delete() {}
