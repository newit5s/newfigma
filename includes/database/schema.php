<?php
/**
 * Database schema definitions.
 */

if ( ! function_exists( 'restaurant_booking_get_schema_definitions' ) ) {

    function restaurant_booking_get_schema_definitions() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $bookings_table  = $wpdb->prefix . 'restaurant_bookings';
        $tables_table    = $wpdb->prefix . 'restaurant_tables';
        $customers_table = $wpdb->prefix . 'restaurant_customers';

        $schemas = array();

        $schemas[] = "CREATE TABLE {$bookings_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            location_id bigint(20) unsigned NOT NULL DEFAULT 0,
            booking_datetime datetime NOT NULL,
            party_size int(11) NOT NULL DEFAULT 2,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        $schemas[] = "CREATE TABLE {$tables_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(100) NOT NULL,
            capacity int(11) NOT NULL DEFAULT 2,
            status varchar(20) NOT NULL DEFAULT 'available',
            layout longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        $schemas[] = "CREATE TABLE {$customers_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(190) NOT NULL,
            phone varchar(30) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY email (email)
        ) {$charset_collate};";

        return $schemas;
    }
}
