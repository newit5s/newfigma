<?php
/**
 * Database schema definitions.
 */

if ( ! function_exists( 'restaurant_booking_get_schema_definitions' ) ) {

    function restaurant_booking_get_schema_definitions() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $bookings_table  = $wpdb->prefix . 'rb_bookings';
        $tables_table    = $wpdb->prefix . 'rb_tables';
        $customers_table = $wpdb->prefix . 'rb_customers';
        $locations_table = $wpdb->prefix . 'rb_locations';

        $schemas = array();

        $schemas[] = "CREATE TABLE {$bookings_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            location_id bigint(20) unsigned NOT NULL DEFAULT 0,
            table_id bigint(20) unsigned NOT NULL DEFAULT 0,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            booking_datetime datetime NOT NULL,
            party_size int(11) NOT NULL DEFAULT 2,
            status varchar(20) NOT NULL DEFAULT 'pending',
            source varchar(50) NOT NULL DEFAULT 'online',
            total_amount decimal(12,2) NOT NULL DEFAULT 0,
            special_requests text NULL,
            customer_name varchar(190) NOT NULL DEFAULT '',
            customer_email varchar(190) NOT NULL DEFAULT '',
            customer_phone varchar(50) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY location_id (location_id),
            KEY booking_date (booking_date),
            KEY booking_datetime (booking_datetime),
            KEY customer_id (customer_id),
            KEY created_at (created_at),
            KEY location_date (location_id, booking_date)
        ) {$charset_collate};";

        $schemas[] = "CREATE TABLE {$tables_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL DEFAULT 0,
            table_number varchar(50) NOT NULL DEFAULT '',
            capacity int(11) NOT NULL DEFAULT 2,
            status varchar(20) NOT NULL DEFAULT 'available',
            position_x int(11) NOT NULL DEFAULT 0,
            position_y int(11) NOT NULL DEFAULT 0,
            shape varchar(20) NOT NULL DEFAULT 'rectangle',
            width int(11) NOT NULL DEFAULT 120,
            height int(11) NOT NULL DEFAULT 120,
            rotation int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY location_table (location_id, table_number),
            KEY location_id (location_id),
            KEY status (status)
        ) {$charset_collate};";

        $schemas[] = "CREATE TABLE {$customers_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(190) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'regular',
            notes longtext NULL,
            preferences longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY phone (phone),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $schemas[] = "CREATE TABLE {$locations_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            address varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            capacity int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";

        return $schemas;
    }
}
