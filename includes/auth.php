<?php
// includes/auth.php

/**
 * Tenant Access Manager - Authentication Handlers
 *
 * This file handles email confirmation and user logout functionalities,
 * including setting authentication cookies and tracking events with Customer.io.
 */

/**
 * Handle Email Confirmation
 *
 * This function processes the email confirmation link, validates the token,
 * authenticates the user by setting a secure cookie, and tracks the event with Customer.io.
 */
function tam_handle_email_confirmation() {
    if ( isset( $_GET['tam_confirm_email'] ) ) {
        $token = sanitize_text_field( $_GET['tam_confirm_email'] );
        
        // Log the token handling
        error_log( 'Handling email confirmation for token: ' . $token );
        
        // Retrieve token data from transient
        $data = get_transient( 'tam_email_token_' . $token );
        if ( $data ) {
            error_log( 'Token valid. Email: ' . $data['email'] );
            
            // Delete the transient as it's no longer needed
            delete_transient( 'tam_email_token_' . $token );
            
            // Check if the token has expired
            if ( time() > $data['expiration'] ) {
                echo '<p>' . __( 'Token has expired. Please request a new confirmation email.', 'tenant-access-manager' ) . '</p>';
                return;
            }
            
            $email = $data['email'];
            list( $user, $domain ) = explode( '@', $email );
            $tenant_id = tam_get_tenant_by_domain( $domain );
            
            if ( $tenant_id ) {
                // Generate a secure authentication token
                $token_data = json_encode( array(
                    'email'      => $email,
                    'tenant_id'  => $tenant_id,
                    'timestamp'  => time(),
                ) );
                $signature = hash_hmac( 'sha256', $token_data, TAM_SECRET_KEY );
                $auth_token = base64_encode( $token_data . '::' . $signature );
                
                // Set the authentication cookie
                setcookie( 'tam_user_token', $auth_token, array(
                    'expires'  => time() + 3600 * 24 * 30, // 30 days
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ) );
                
                error_log( 'User authenticated. Email: ' . $email . ', Tenant ID: ' . $tenant_id );
                
                // Track the email confirmation event with Customer.io
                tam_track_customerio_event( $email, 'email_confirmed', array(
                    'tenant_id' => $tenant_id,
                    'timestamp' => time(),
                ) );
                
                // Redirect the user to the portal page
                wp_redirect( site_url( '/portal/' ) );
                exit;
            } else {
                error_log( 'No tenant found for domain: ' . $domain );
                echo '<p>' . __( 'No tenant found for your email domain.', 'tenant-access-manager' ) . '</p>';
            }
        } else {
            error_log( 'Invalid or expired token: ' . $token );
            echo '<p>' . __( 'Invalid or expired token.', 'tenant-access-manager' ) . '</p>';
        }
    }
}
add_action( 'template_redirect', 'tam_handle_email_confirmation' );

/**
 * Logout Handler
 *
 * This function handles user logout by clearing the authentication cookie
 * and tracking the logout event with Customer.io.
 */
function tam_handle_logout() {
    if ( isset( $_GET['tam_logout'] ) ) {
        // Verify nonce for security
        if ( isset( $_GET['tam_logout_nonce'] ) && wp_verify_nonce( $_GET['tam_logout_nonce'], 'tam_logout_action' ) ) {

            // Retrieve user data before clearing the cookie
            $auth_data = tam_validate_user_authentication();
            if ( $auth_data ) {
                $email = $auth_data['email'];
                $tenant_id = $auth_data['tenant_id'];
                $customer_id = md5( strtolower( $email ) );
            }

            // Clear the authentication cookie
            setcookie( 'tam_user_token', '', time() - 3600, '/', COOKIE_DOMAIN, is_ssl(), true );

            // Track logout event with Customer.io
            if ( isset( $email ) && isset( $tenant_id ) ) {
                tam_track_customerio_event( $email, 'user_logged_out', array(
                    'tenant_id' => $tenant_id,
                    'timestamp' => time(),
                ) );
            }

            // Redirect to the login page after logout
            $login_page = get_page_by_path( 'login' );
            if ( $login_page ) {
                wp_redirect( get_permalink( $login_page->ID ) );
                exit;
            } else {
                wp_die( __( 'Login page not found.', 'tenant-access-manager' ) );
            }
        } else {
            wp_die( __( 'Invalid logout request.', 'tenant-access-manager' ) );
        }
    }
}
add_action( 'init', 'tam_handle_logout' );
