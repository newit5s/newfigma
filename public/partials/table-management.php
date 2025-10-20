<?php
/**
 * Table management template (Phase 6)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$manager      = isset( $this ) ? $this : null;
$current_user = isset( $manager->current_user ) ? $manager->current_user : array();
$user_name    = isset( $current_user['name'] ) ? $current_user['name'] : __( 'Manager', 'restaurant-booking' );
$user_initials = strtoupper( mb_substr( wp_strip_all_tags( $user_name ), 0, 2, 'UTF-8' ) );

$current_loc = isset( $manager ) && method_exists( $manager, 'get_current_location' )
    ? $manager->get_current_location()
    : '';
$tables = array();
if ( isset( $manager ) && method_exists( $manager, 'get_tables_for_location' ) ) {
    $tables = $manager->get_tables_for_location( $current_loc );
}

$locations = array();
if ( class_exists( 'RB_Location' ) && method_exists( 'RB_Location', 'get_all_locations' ) ) {
    $raw_locations = RB_Location::get_all_locations();
    if ( is_array( $raw_locations ) || $raw_locations instanceof Traversable ) {
        foreach ( $raw_locations as $location ) {
            $locations[] = array(
                'id'   => isset( $location->id ) ? $location->id : 0,
                'name' => isset( $location->name ) ? $location->name : __( 'Location', 'restaurant-booking' ),
            );
        }
    }
}
if ( empty( $locations ) ) {
    $locations[] = array(
        'id'   => 0,
        'name' => __( 'Main Dining Room', 'restaurant-booking' ),
    );
}

$is_embed = isset( $is_embed ) ? (bool) $is_embed : false;
?>
<?php if ( ! $is_embed ) : ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'rb-table-management-page' ); ?>>
<?php endif; ?>

<div class="rb-table-management" data-table-management>
    <div class="rb-table-header">
        <div class="rb-header-title">
            <h1 class="rb-page-title"><?php esc_html_e( 'Table Management', 'restaurant-booking' ); ?></h1>
            <div class="rb-location-info">
                <span class="rb-location-name"><?php echo esc_html( $locations[0]['name'] ); ?></span>
                <span class="rb-table-count"><?php echo esc_html( sprintf( __( '%1$s tables • %2$s seats', 'restaurant-booking' ), count( $tables ), array_sum( wp_list_pluck( $tables, 'capacity' ) ) ) ); ?></span>
            </div>
        </div>
        <div class="rb-header-actions">
            <div class="rb-user-menu">
                <div class="rb-user-avatar"><?php echo esc_html( $user_initials ); ?></div>
                <span class="rb-user-name"><?php echo esc_html( $user_name ); ?></span>
            </div>
            <div class="rb-table-tabs" role="tablist">
                <button type="button" class="rb-tab-btn rb-active" data-tab="floor-plan" role="tab" aria-selected="true"><?php esc_html_e( 'Floor Plan', 'restaurant-booking' ); ?></button>
                <button type="button" class="rb-tab-btn" data-tab="table-list" role="tab" aria-selected="false"><?php esc_html_e( 'Table List', 'restaurant-booking' ); ?></button>
                <button type="button" class="rb-tab-btn" data-tab="analytics" role="tab" aria-selected="false"><?php esc_html_e( 'Analytics', 'restaurant-booking' ); ?></button>
            </div>
        </div>
    </div>

    <div class="rb-table-layout">
        <section class="rb-floor-plan-card rb-tab-panel rb-active" data-tab="floor-plan">
            <div class="rb-floor-plan-toolbar">
                <div>
                    <h2 class="rb-section-title"><?php esc_html_e( 'Visual Floor Plan', 'restaurant-booking' ); ?></h2>
                    <p class="rb-page-subtitle"><?php esc_html_e( 'Drag tables to reposition, duplicate for seasonal layouts, and quickly toggle availability.', 'restaurant-booking' ); ?></p>
                </div>
                <div class="rb-toolbar-actions">
                    <button type="button" class="rb-btn rb-btn-outline" id="reset-layout">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4a8 8 0 00-7.88 6.7A1 1 0 005.1 12H7a1 1 0 00.99-.86 6 6 0 0110.41-3.45L16 10h6V4l-2.35 2.35z"/></svg>
                        <?php esc_html_e( 'Reset Layout', 'restaurant-booking' ); ?>
                    </button>
                    <button type="button" class="rb-btn rb-btn-outline" id="add-table">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        <?php esc_html_e( 'Add Table', 'restaurant-booking' ); ?>
                    </button>
                    <button type="button" class="rb-btn rb-btn-primary" id="save-layout">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 3H5a2 2 0 00-2 2v14c0 1.1.89 2 2 2h14a2 2 0 002-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        <?php esc_html_e( 'Save Layout', 'restaurant-booking' ); ?>
                    </button>
                </div>
            </div>
            <div class="rb-floor-plan-canvas" data-floor-canvas role="application" aria-label="<?php esc_attr_e( 'Table floor plan editor', 'restaurant-booking' ); ?>">
                <div class="rb-floor-plan-grid" aria-hidden="true"></div>
                <?php foreach ( $tables as $table ) :
                    $label = isset( $table['label'] ) ? $table['label'] : ( $table['name'] ?? __( 'Table', 'restaurant-booking' ) );
                    $capacity = isset( $table['capacity'] ) ? (int) $table['capacity'] : 0;
                    $status = isset( $table['status'] ) ? $table['status'] : 'available';
                    $shape = isset( $table['shape'] ) ? $table['shape'] : 'rectangle';
                    $left = isset( $table['position_x'] ) ? (int) $table['position_x'] : 120;
                    $top  = isset( $table['position_y'] ) ? (int) $table['position_y'] : 120;
                    ?>
                    <button type="button" class="rb-floor-table" data-table-id="<?php echo esc_attr( $table['id'] ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-shape="<?php echo esc_attr( $shape ); ?>" style="left: <?php echo esc_attr( $left ); ?>px; top: <?php echo esc_attr( $top ); ?>px;">
                        <span class="rb-table-label"><?php echo esc_html( $label ); ?></span>
                        <span class="rb-table-capacity"><?php echo esc_html( sprintf( _n( '%d seat', '%d seats', $capacity, 'restaurant-booking' ), $capacity ) ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="rb-page-subtitle" style="margin-top: var(--rb-space-4);">
                <?php esc_html_e( 'Status Legend: Green — Available, Yellow — Reserved, Red — Occupied, Blue — Cleaning', 'restaurant-booking' ); ?>
            </div>
        </section>

        <aside class="rb-table-properties" data-table-properties aria-live="polite">
            <header>
                <div>
                    <h2 class="rb-page-title"><?php esc_html_e( 'Select a table', 'restaurant-booking' ); ?></h2>
                    <p class="rb-page-subtitle"><?php esc_html_e( 'Click a table on the floor plan to view its details and quick actions.', 'restaurant-booking' ); ?></p>
                </div>
            </header>
        </aside>
    </div>

    <section class="rb-table-list-card rb-tab-panel" data-tab="table-list" aria-hidden="true">
        <div class="rb-table-list-header">
            <h2 class="rb-section-title"><?php esc_html_e( 'Table Overview', 'restaurant-booking' ); ?></h2>
            <p class="rb-page-subtitle"><?php esc_html_e( 'Review seating capacity, status, and location metadata in list format.', 'restaurant-booking' ); ?></p>
        </div>
        <div class="rb-table-responsive">
            <table class="rb-table-list">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Table', 'restaurant-booking' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Capacity', 'restaurant-booking' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Status', 'restaurant-booking' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Position', 'restaurant-booking' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Shape', 'restaurant-booking' ); ?></th>
                        <th scope="col" class="rb-text-right"><?php esc_html_e( 'Actions', 'restaurant-booking' ); ?></th>
                    </tr>
                </thead>
                <tbody data-table-list>
                <?php if ( empty( $tables ) ) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e( 'No tables have been configured for this location yet.', 'restaurant-booking' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $tables as $table ) :
                        $label = isset( $table['label'] ) ? $table['label'] : ( $table['name'] ?? __( 'Table', 'restaurant-booking' ) );
                        $capacity = isset( $table['capacity'] ) ? (int) $table['capacity'] : 0;
                        $status = isset( $table['status'] ) ? $table['status'] : 'available';
                        $position = sprintf( '%d, %d', (int) ( $table['position_x'] ?? 0 ), (int) ( $table['position_y'] ?? 0 ) );
                        $shape = isset( $table['shape'] ) ? $table['shape'] : 'rectangle';
                        ?>
                        <tr data-table-row="<?php echo esc_attr( $table['id'] ); ?>">
                            <td><?php echo esc_html( $label ); ?></td>
                            <td><?php echo esc_html( $capacity ); ?></td>
                            <td><span class="rb-table-status-badge <?php echo esc_attr( 'rb-status-' . $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
                            <td><?php echo esc_html( $position ); ?></td>
                            <td><?php echo esc_html( ucfirst( $shape ) ); ?></td>
                            <td class="rb-text-right">
                                <button type="button" class="rb-btn rb-btn-xs rb-btn-outline" data-action="focus" data-table="<?php echo esc_attr( $table['id'] ); ?>"><?php esc_html_e( 'Focus', 'restaurant-booking' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rb-table-analytics-card rb-tab-panel" data-tab="analytics" aria-hidden="true">
        <h2 class="rb-section-title"><?php esc_html_e( 'Operational Analytics', 'restaurant-booking' ); ?></h2>
        <p class="rb-page-subtitle"><?php esc_html_e( 'Track seat availability, occupied tables, and average occupancy rates.', 'restaurant-booking' ); ?></p>
        <div data-table-analytics>
            <div class="rb-analytics-grid">
                <div class="rb-analytics-card">
                    <div class="rb-summary-label"><?php esc_html_e( 'Total tables', 'restaurant-booking' ); ?></div>
                    <div class="rb-analytics-value"><?php echo esc_html( count( $tables ) ); ?></div>
                </div>
                <div class="rb-analytics-card">
                    <div class="rb-summary-label"><?php esc_html_e( 'Total seats', 'restaurant-booking' ); ?></div>
                    <div class="rb-analytics-value"><?php echo esc_html( array_sum( wp_list_pluck( $tables, 'capacity' ) ) ); ?></div>
                </div>
                <div class="rb-analytics-card">
                    <div class="rb-summary-label"><?php esc_html_e( 'Reserved tables', 'restaurant-booking' ); ?></div>
                    <div class="rb-analytics-value"><?php echo esc_html( count( array_filter( $tables, static function( $table ) { return ( $table['status'] ?? 'available' ) === 'reserved'; } ) ) ); ?></div>
                </div>
                <div class="rb-analytics-card">
                    <div class="rb-summary-label"><?php esc_html_e( 'Occupied tables', 'restaurant-booking' ); ?></div>
                    <div class="rb-analytics-value"><?php echo esc_html( count( array_filter( $tables, static function( $table ) { return ( $table['status'] ?? 'available' ) === 'occupied'; } ) ) ); ?></div>
                </div>
            </div>
        </div>
    </section>

    <div class="rb-toast-region" data-table-notice aria-live="polite" aria-atomic="true"></div>
</div>

<?php if ( ! $is_embed ) : ?>
<?php wp_footer(); ?>
</body>
</html>
<?php endif; ?>
