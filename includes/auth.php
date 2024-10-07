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
 * Processes the email confirmation link, validates the token,
 * authenticates the user by setting a secure cookie,
 * updates the customer's profile with tenant information,
 * and tracks the 'email_confirmed' event with Customer.io.
 */
function tam_handle_email_confirmation() {
    if ( isset( $_GET['tam_confirm_email'] ) ) {
        // Sanitize the token to prevent malicious input
        $token = sanitize_text_field( $_GET['tam_confirm_email'] );

        // Log the token handling for debugging purposes
        error_log( 'Handling email confirmation for token: ' . $token );

        // Retrieve token data from transient storage
        $data = get_transient( 'tam_email_token_' . $token );
        if ( $data ) {
            error_log( 'Token valid. Email: ' . $data['email'] );

            // Delete the transient as it's no longer needed to prevent reuse
            delete_transient( 'tam_email_token_' . $token );

            // Check if the token has expired
            if ( time() > $data['expiration'] ) {
                echo '<p>' . __( 'Token has expired. Please request a new confirmation email.', 'tenant-access-manager' ) . '</p>';
                return;
            }

            // Extract email and domain from the token data
            $email = $data['email'];
            if ( strpos( $email, '@' ) !== false ) {
                list( $user, $domain ) = explode( '@', $email );
            } else {
                error_log( 'Invalid email format in token data: ' . $email );
                echo '<p>' . __( 'Invalid email format.', 'tenant-access-manager' ) . '</p>';
                return;
            }

            // Retrieve tenant ID based on the email domain
            $tenant_id = tam_get_tenant_by_domain( $domain );

            if ( $tenant_id ) {
                // Retrieve Tenant Name based on Tenant ID
                $tenant_name = tam_get_tenant_name( $tenant_id );

                // Generate a secure authentication token with user and tenant information
                $token_data = json_encode( array(
                    'email'       => $email,
                    'tenant_id'   => $tenant_id,
                    'tenant_name' => $tenant_name,
                    'timestamp'   => time(),
                ) );
                $signature = hash_hmac( 'sha256', $token_data, TAM_SECRET_KEY );
                $auth_token = base64_encode( $token_data . '::' . $signature );

                // Set the authentication cookie with secure parameters
                setcookie( 'tam_user_token', $auth_token, array(
                    'expires'  => time() + 3600 * 24 * 30, // 30 days
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ) );

                error_log( 'User authenticated. Email: ' . $email . ', Tenant ID: ' . $tenant_id . ', Tenant Name: ' . $tenant_name );

                /**
                 * Update the customer's profile with Tenant ID and Tenant Name in Customer.io.
                 */
                tam_update_customerio_profile( $email, $tenant_id, $tenant_name );

                /**
                 * Track the 'email_confirmed' event with Customer.io as an identified event.
                 * This associates the event with the user's email address in Customer.io.
                 */
                tam_track_customerio_event( $email, 'email_confirmed', array(
                    'tenant_id'   => $tenant_id,
                    'tenant_name' => $tenant_name,
                    'timestamp'   => time(),
                ) );

                // Redirect the authenticated user to the portal page
                wp_redirect( site_url( '/portal/' ) );
                exit;
            } else {
                // Log and display an error if no tenant is found for the email domain
                error_log( 'No tenant found for domain: ' . $domain );
                echo '<p>' . __( 'No tenant found for your email domain.', 'tenant-access-manager' ) . '</p>';
            }
        } else {
            // Log and display an error if the token is invalid or expired
            error_log( 'Invalid or expired token: ' . $token );
            echo '<p>' . __( 'Invalid or expired token.', 'tenant-access-manager' ) . '</p>';
        }
    }
}
add_action( 'template_redirect', 'tam_handle_email_confirmation' );

/**
 * Logout Handler
 *
 * Handles user logout by clearing the authentication cookie
 * and tracking the logout event with Customer.io.
 */
function tam_handle_logout() {
    if ( isset( $_GET['tam_logout'] ) ) {
        // Verify nonce to ensure the logout request is legitimate
        if ( isset( $_GET['tam_logout_nonce'] ) && wp_verify_nonce( $_GET['tam_logout_nonce'], 'tam_logout_action' ) ) {

            // Retrieve user data before clearing the cookie
            $auth_data = tam_validate_user_authentication();
            if ( $auth_data ) {
                $email        = $auth_data['email'];
                $tenant_id    = $auth_data['tenant_id'];
                $tenant_name  = $auth_data['tenant_name'];
            }

            // Clear the authentication cookie by setting its expiration time in the past
            setcookie( 'tam_user_token', '', time() - 3600, '/', COOKIE_DOMAIN, is_ssl(), true );

            /**
             * Track the 'user_logged_out' event with Customer.io as an identified event.
             * This associates the logout event with the user's email address in Customer.io.
             */
            if ( isset( $email ) && isset( $tenant_id ) && isset( $tenant_name ) ) {
                tam_track_customerio_event( $email, 'user_logged_out', array(
                    'tenant_id'   => $tenant_id,
                    'tenant_name' => $tenant_name,
                    'timestamp'   => time(),
                ) );
            }

            // Redirect the user to the login page after successful logout
            $login_page = get_page_by_path( 'login' );
            if ( $login_page ) {
                wp_redirect( get_permalink( $login_page->ID ) );
                exit;
            } else {
                // Display an error if the login page is not found
                wp_die( __( 'Login page not found.', 'tenant-access-manager' ) );
            }
        } else {
            // Display an error if the logout request is invalid
            wp_die( __( 'Invalid logout request.', 'tenant-access-manager' ) );
        }
    }
}
add_action( 'init', 'tam_handle_logout' );

/**
 * Update Customer.io Customer Profile with Tenant Information
 *
 * This function updates the customer's profile in Customer.io with Tenant ID and Tenant Name.
 *
 * @param string $email        Customer's email address.
 * @param int    $tenant_id    Tenant ID associated with the customer.
 * @param string $tenant_name  Tenant Name associated with the customer.
 * @return void
 */
function tam_update_customerio_profile( $email, $tenant_id, $tenant_name ) {
    $client = tam_get_customerio_client();
    if ( $client ) {
        try {
            // Update the customer profile with Tenant ID and Tenant Name
            $client->customers->add( array(
                'email'        => $email,
                'tenant_id'    => $tenant_id,
                'tenant_name'  => $tenant_name,
                'updated_at'   => time(),
            ) );

            error_log( "Updated Customer.io profile for {$email} with Tenant ID: {$tenant_id} and Tenant Name: {$tenant_name}" );
        } catch ( Exception $e ) {
            error_log( 'Customer.io Profile Update Error: ' . $e->getMessage() );
        }
    } else {
        error_log( 'Customer.io client not available. Cannot update profile.' );
    }
}
