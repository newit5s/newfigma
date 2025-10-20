<?php
/**
 * Customer management template (Phase 6)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$manager      = isset( $this ) ? $this : null;
$current_user = isset( $manager->current_user ) ? $manager->current_user : array();
$user_name    = isset( $current_user['name'] ) ? $current_user['name'] : __( 'Manager', 'restaurant-booking' );
$user_initials = strtoupper( mb_substr( wp_strip_all_tags( $user_name ), 0, 2, 'UTF-8' ) );

$customers = array();
if ( isset( $manager ) && method_exists( $manager, 'get_customers_for_segment' ) ) {
    $customers = $manager->get_customers_for_segment( 'all' );
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
<body <?php body_class( 'rb-customer-management-page' ); ?>>
<?php endif; ?>

<div class="rb-customer-management">
    <header class="rb-customer-header-bar">
        <div class="rb-header-title">
            <h1 class="rb-page-title"><?php esc_html_e( 'Customer Management', 'restaurant-booking' ); ?></h1>
            <p class="rb-page-subtitle"><?php esc_html_e( 'Track visit history, manage VIP guests, and monitor blacklist statuses.', 'restaurant-booking' ); ?></p>
        </div>
        <div class="rb-header-actions">
            <div class="rb-user-menu">
                <div class="rb-user-avatar"><?php echo esc_html( $user_initials ); ?></div>
                <span class="rb-user-name"><?php echo esc_html( $user_name ); ?></span>
            </div>
            <div class="rb-header-buttons">
                <button type="button" class="rb-btn rb-btn-outline" id="import-customers">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20h14v-2H5m14-9h-4V3H9v6H5l7 7 7-7z"/></svg>
                    <?php esc_html_e( 'Import', 'restaurant-booking' ); ?>
                </button>
                <button type="button" class="rb-btn rb-btn-outline" id="export-customers">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20h14v-2H5m7-9l7-7H5l7 7z"/></svg>
                    <?php esc_html_e( 'Export', 'restaurant-booking' ); ?>
                </button>
                <button type="button" class="rb-btn rb-btn-primary" id="add-customer">
                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    <?php esc_html_e( 'Add Customer', 'restaurant-booking' ); ?>
                </button>
            </div>
        </div>
    </header>

    <div class="rb-customer-layout">
        <aside class="rb-customer-panel">
            <div class="rb-customer-filter-tabs" role="tablist">
                <button type="button" class="rb-pill rb-active" data-segment="all" role="tab" aria-selected="true"><?php esc_html_e( 'All', 'restaurant-booking' ); ?></button>
                <button type="button" class="rb-pill" data-segment="vip" role="tab" aria-selected="false"><?php esc_html_e( 'VIP', 'restaurant-booking' ); ?></button>
                <button type="button" class="rb-pill" data-segment="regular" role="tab" aria-selected="false"><?php esc_html_e( 'Regular', 'restaurant-booking' ); ?></button>
                <button type="button" class="rb-pill" data-segment="blacklist" role="tab" aria-selected="false"><?php esc_html_e( 'Blacklist', 'restaurant-booking' ); ?></button>
            </div>
            <div class="rb-customer-search">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16a6.471 6.471 0 004.23-1.57l.27.27v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="search" id="customer-search" class="rb-input" placeholder="<?php esc_attr_e( 'Search by name, email, or phone…', 'restaurant-booking' ); ?>" />
            </div>
            <div class="rb-customer-list" data-customer-list>
                <?php if ( empty( $customers ) ) : ?>
                    <div class="rb-empty-state"><?php esc_html_e( 'No customer profiles found yet.', 'restaurant-booking' ); ?></div>
                <?php else : ?>
                    <?php foreach ( $customers as $customer ) :
                        $initials = isset( $customer['initials'] ) ? $customer['initials'] : strtoupper( mb_substr( $customer['name'], 0, 2 ) );
                        $status   = isset( $customer['status'] ) ? $customer['status'] : 'regular';
                        ?>
                        <article class="rb-customer-item" data-customer="<?php echo esc_attr( $customer['id'] ); ?>">
                            <div class="rb-customer-avatar"><?php echo esc_html( $initials ); ?></div>
                            <div class="rb-customer-meta">
                                <span class="rb-customer-name"><?php echo esc_html( $customer['name'] ); ?></span>
                                <span class="rb-text-muted"><?php echo esc_html( $customer['email'] ); ?></span>
                                <div class="rb-customer-tags">
                                    <span class="rb-badge <?php echo 'vip' === $status ? 'rb-badge-success' : ( 'blacklist' === $status ? 'rb-badge-error' : 'rb-badge-muted' ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section class="rb-customer-detail-card" data-customer-detail aria-live="polite">
            <div class="rb-empty-state">
                <h2><?php esc_html_e( 'Select a customer', 'restaurant-booking' ); ?></h2>
                <p><?php esc_html_e( 'Choose a profile from the list to review booking history and preferences.', 'restaurant-booking' ); ?></p>
            </div>
        </section>
    </div>

    <div class="rb-toast-region" data-customer-notice aria-live="polite" aria-atomic="true"></div>
</div>

<?php if ( ! $is_embed ) : ?>
<?php wp_footer(); ?>
</body>
</html>
<?php endif; ?>
