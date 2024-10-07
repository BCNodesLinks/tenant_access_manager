<?php
// includes/shortcodes.php

/**
 * Tenant Access Manager - Shortcodes
 *
 * This file defines shortcodes used in the Tenant Access Manager plugin.
 * It includes the email entry form shortcode, which handles email submissions
 * and triggers Customer.io transactional emails with a 'portal_' prefix.
 */

/**
 * Email Entry Form Shortcode
 *
 * This shortcode displays an email entry form and handles form submissions.
 * Upon successful submission, it triggers a 'portal_email_submitted' transactional email in Customer.io.
 *
 * Usage: [tam_email_form]
 *
 * @return string HTML content for the email entry form.
 */
function tam_email_entry_form() {
    $output = '';

    // Check if the form is submitted and verify the nonce for security
    if ( isset( $_POST['tam_email_entry_form_nonce'] ) && wp_verify_nonce( $_POST['tam_email_entry_form_nonce'], 'tam_email_entry_form_action' ) ) {
        if ( isset( $_POST['tam_user_email'] ) && is_email( $_POST['tam_user_email'] ) ) {
            $email = sanitize_email( $_POST['tam_user_email'] );
            $token = bin2hex( random_bytes( 32 ) );
            $expiration = time() + 24 * HOUR_IN_SECONDS;
            $data = array(
                'email'      => $email,
                'expiration' => $expiration,
            );

            // Log token generation and transient setting for debugging
            error_log( 'Generated token: ' . $token . ' for email: ' . $email );
            if ( set_transient( 'tam_email_token_' . $token, $data, 24 * HOUR_IN_SECONDS ) ) {
                error_log( 'Transient set for token: ' . $token );
            } else {
                error_log( 'Failed to set transient for token: ' . $token );
            }

            // Generate the confirmation link
            $confirm_link = add_query_arg( array( 'tam_confirm_email' => $token ), site_url( '/login/' ) );

            // Define your transactional template ID using the constant
            $transactional_template_id = TAM_CUSTOMERIO_TRANSACTIONAL_TEMPLATE_ID;

            // Data to pass to the transactional email template
            $email_data = array(
                'confirmation_url' => esc_url( $confirm_link ),
                'timestamp'        => time(),
            );

            // Send transactional email
            tam_send_transactional_email( $email, $transactional_template_id, $email_data );

            // Inform the user that a confirmation link has been sent
            $output .= '<p>If your email is registered, you will receive a confirmation link shortly.</p>';
        } else {
            $output .= '<p>Please enter a valid email address.</p>';
        }
    }

    // Display the email entry form
    $output .= '<form method="post">';
    $output .= wp_nonce_field( 'tam_email_entry_form_action', 'tam_email_entry_form_nonce', true, false );
    $output .= '<label for="tam_user_email">Enter your email:</label><br/>';
    $output .= '<input type="email" name="tam_user_email" required /><br/>';
    $output .= '<input type="submit" value="Submit" />';
    $output .= '</form>';

    return $output;
}
add_shortcode( 'tam_email_form', 'tam_email_entry_form' );

/**
 * Logout Button Shortcode
 */
function tam_logout_button_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'text'  => 'Logout',
        'class' => 'tam-logout-button',
    ), $atts, 'tam_logout_button' );

    // Get the current URL without query parameters
    $current_url = home_url( add_query_arg( null, null ) );

    // Generate a logout URL with nonce
    $logout_url = add_query_arg( array(
        'tam_logout'        => '1',
        'tam_logout_nonce'  => wp_create_nonce( 'tam_logout_action' ),
    ), $current_url );

    // Create the logout button HTML
    $html = '<a href="' . esc_url( $logout_url ) . '" class="' . esc_attr( $atts['class'] ) . '">' . esc_html( $atts['text'] ) . '</a>';

    return $html;
}
add_shortcode( 'tam_logout_button', 'tam_logout_button_shortcode' );

/**
 * Tenant Logo Shortcode
 */
function tam_tenant_logo_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'size'  => 'full',
        'class' => 'tam-tenant-logo',
    ), $atts, 'tam_tenant_logo' );

    $logo_url = tam_get_authenticated_tenant_logo_url( $atts['size'] );
    if ( $logo_url ) {
        $html = '<img src="' . esc_url( $logo_url ) . '" class="' . esc_attr( $atts['class'] ) . '" alt="Tenant Logo" />';
        return $html;
    } else {
        // Return an empty string or a placeholder
        return '';
    }
}
add_shortcode( 'tam_tenant_logo', 'tam_tenant_logo_shortcode' );
