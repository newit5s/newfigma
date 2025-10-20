<?php
/**
 * Portal Login Template
 *
 * Renders the modern portal login interface using Phase 1 design tokens.
 * Variables expected from the shortcode context:
 * - $redirect_url        : URL to redirect to after successful login.
 * - $forgot_password_url : URL to the password reset flow.
 * - $logo_text           : Primary heading text.
 * - $subtitle            : Supporting subtitle text.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$redirect_attr        = ! empty( $redirect_url ) ? esc_attr( $redirect_url ) : '';
$forgot_password_link = ! empty( $forgot_password_url ) ? esc_url( $forgot_password_url ) : '';
$logo_text            = ! empty( $logo_text ) ? $logo_text : __( 'Restaurant Manager', 'restaurant-booking' );
$subtitle             = ! empty( $subtitle ) ? $subtitle : __( 'Staff Portal', 'restaurant-booking' );
?>
<div class="rb-portal-login-wrapper">
    <div class="rb-portal-login-container">
        <div class="rb-portal-login-card" aria-labelledby="rb-portal-heading">
            <div class="rb-portal-logo">
                <div class="rb-logo-icon" aria-hidden="true">🍽️</div>
                <h1 class="rb-logo-text" id="rb-portal-heading">
                    <?php echo esc_html( $logo_text ); ?>
                </h1>
                <p class="rb-logo-subtitle"><?php echo esc_html( $subtitle ); ?></p>
            </div>

            <form class="rb-portal-login-form" id="rb-portal-login" data-redirect="<?php echo $redirect_attr; ?>" novalidate>
                <div class="rb-form-group">
                    <label class="rb-label" for="username"><?php esc_html_e( 'Username', 'restaurant-booking' ); ?></label>
                    <input type="text" class="rb-input rb-input-lg" id="username" name="username" autocomplete="username" required />
                    <span class="rb-error-message" id="username-error" role="alert" aria-hidden="true"></span>
                </div>

                <div class="rb-form-group">
                    <label class="rb-label" for="password"><?php esc_html_e( 'Password', 'restaurant-booking' ); ?></label>
                    <div class="rb-input-group">
                        <input type="password" class="rb-input rb-input-lg" id="password" name="password" autocomplete="current-password" required />
                        <button type="button" class="rb-input-addon rb-password-toggle" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'restaurant-booking' ); ?>" aria-pressed="false">
                            <svg class="rb-eye-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 5c-7.633 0-11 6.998-11 6.998s3.367 7.002 11 7.002 11-7.002 11-7.002S19.633 5 12 5zm0 12.003a5 5 0 1 1 0-10.005 5 5 0 0 1 0 10.005zm0-2.5a2.5 2.5 0 1 0 0-5.002 2.5 2.5 0 0 0 0 5.002z" />
                            </svg>
                        </button>
                    </div>
                    <span class="rb-error-message" id="password-error" role="alert" aria-hidden="true"></span>
                </div>

                <div class="rb-form-group rb-form-checkbox">
                    <label class="rb-checkbox-label" for="remember-me">
                        <input type="checkbox" class="rb-checkbox" id="remember-me" name="remember" value="1" />
                        <span class="rb-checkbox-custom" aria-hidden="true"></span>
                        <?php esc_html_e( 'Remember me', 'restaurant-booking' ); ?>
                    </label>
                    <a href="<?php echo esc_url( $forgot_password_link ? $forgot_password_link : '#' ); ?>" class="rb-link" id="forgot-password">
                        <?php esc_html_e( 'Forgot your password?', 'restaurant-booking' ); ?>
                    </a>
                </div>

                <button type="submit" class="rb-btn rb-btn-primary rb-btn-lg rb-btn-block" id="login-submit" aria-busy="false">
                    <span class="rb-btn-text"><?php esc_html_e( 'Sign In', 'restaurant-booking' ); ?></span>
                    <div class="rb-btn-loading" style="display: none;" aria-hidden="true">
                        <div class="rb-loading-spinner"></div>
                    </div>
                </button>
            </form>

            <div class="rb-alert rb-alert-error" id="login-error" role="alert" aria-hidden="true">
                <div class="rb-alert-content">
                    <span class="rb-alert-message"></span>
                </div>
            </div>
        </div>

        <div class="rb-portal-bg" aria-hidden="true">
            <div class="rb-bg-pattern"></div>
        </div>
    </div>
</div>
