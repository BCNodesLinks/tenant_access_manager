<?php
/**
 * Tenant Access Manager - Authentication Handlers
 *
 * This file handles email confirmation and user logout functionalities,
 * including generating secure login links and tracking events with Customer.io.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Include Customer.io Integration
 *
 * Ensures that all Customer.io related functions are available.
 */
require_once plugin_dir_path( __FILE__ ) . 'customerio.php';

/**
 * Handle Email Confirmation
 *
 * Processes the email confirmation link, validates the token,
 * authenticates the user by logging them in,
 * updates the customer's profile with tenant information,
 * and tracks the 'email_confirmed' event with Customer.io.
 */
function tam_handle_email_confirmation() {
    if ( isset( $_GET['tam_confirm_email'] ) ) {
        // Sanitize the token to prevent malicious input
        $token = sanitize_text_field( $_GET['tam_confirm_email'] );

        // Log the token handling for debugging purposes
        error_log( "[TAM_DEBUG] Handling email confirmation for token: {$token}" );

        // Retrieve token data from transient storage
        $data = get_transient( 'tam_email_token_' . $token );
        if ( $data ) {
            error_log( "[TAM_DEBUG] Token valid for user ID: {$data['user_id']}" );

            // Delete the transient as it's no longer needed to prevent reuse
            delete_transient( 'tam_email_token_' . $token );

            // Check if the token has expired
            if ( time() > $data['expiration'] ) {
                error_log( "[TAM_DEBUG] Token has expired: {$token}" );
                echo '<p>' . __( 'Token has expired. Please request a new confirmation email.', 'tenant-access-manager' ) . '</p>';
                return;
            }

            // Retrieve user by ID
            $user_id = $data['user_id'];
            $user = get_user_by( 'id', $user_id );

            if ( $user ) {
                // Log the user in
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id );

                // Retrieve tenant information
                $email        = $user->user_email;
                $tenant_id    = get_user_meta( $user_id, 'tenant_id', true );
                $tenant_name  = tam_get_tenant_name( $tenant_id );

                /**
                 * Update the customer's profile with Tenant ID and Tenant Name in Customer.io.
                 */
                tam_update_customerio_profile( $email, $tenant_id, $tenant_name );

                /**
                 * Track the 'email_confirmed' event with Customer.io as an identified event.
                 * This associates the event with the user's email address in Customer.io.
                 */
                tam_track_customerio_event( $email, 'email_confirmed', array(
                    'tenant_id'    => $tenant_id,
                    'tenant_name'  => $tenant_name,
                    'timestamp'    => time(),
                ) );

                // Redirect the authenticated user to the portal page
                wp_redirect( site_url( '/portal/' ) );
                exit;
            } else {
                // Log and display an error if the user is not found
                error_log( "[TAM_DEBUG] User not found for user ID: {$user_id}" );
                echo '<p>' . __( 'User not found.', 'tenant-access-manager' ) . '</p>';
            }
        } else {
            // Log and display an error if the token is invalid or expired
            error_log( "[TAM_DEBUG] Invalid or expired token: {$token}" );
            echo '<p>' . __( 'Invalid or expired token.', 'tenant-access-manager' ) . '</p>';
        }
    }
}
add_action( 'template_redirect', 'tam_handle_email_confirmation' );

/**
 * Logout Handler
 *
 * Handles user logout by clearing the authentication cookies
 * and tracking the logout event with Customer.io.
 */
function tam_handle_logout() {
    if ( isset( $_GET['tam_logout'] ) ) {
        // Verify nonce to ensure the logout request is legitimate
        if ( isset( $_GET['tam_logout_nonce'] ) && wp_verify_nonce( $_GET['tam_logout_nonce'], 'tam_logout_action' ) ) {

            // Retrieve user data before logging out
            if ( is_user_logged_in() ) {
                $user_id     = get_current_user_id();
                $email       = wp_get_current_user()->user_email;
                $tenant_id   = get_user_meta( $user_id, 'tenant_id', true );
                $tenant_name = tam_get_tenant_name( $tenant_id );
            }

            // Log the user out
            wp_logout();

            /**
             * Track the 'user_logged_out' event with Customer.io as an identified event.
             * This associates the logout event with the user's email address in Customer.io.
             */
            if ( isset( $email ) && isset( $tenant_id ) && isset( $tenant_name ) ) {
                tam_track_customerio_event( $email, 'user_logged_out', array(
                    'tenant_id'    => $tenant_id,
                    'tenant_name'  => $tenant_name,
                    'timestamp'    => time(),
                ) );
            }

            // Redirect the user to the login page after successful logout with auto_logout parameter
            $login_page = get_page_by_path( 'login' );
            if ( $login_page ) {
                //$redirect_url = add_query_arg( 'auto_logout', '1', get_permalink( $login_page->ID ) );
                wp_redirect( $redirect_url );
                exit;
            } else {
                // Display an error if the login page is not found
                error_log( "[TAM_DEBUG] Login page not found during logout." );
                wp_die( __( 'Login page not found.', 'tenant-access-manager' ) );
            }
        } else {
            // Display an error if the logout request is invalid
            error_log( "[TAM_DEBUG] Invalid logout request detected." );
            wp_die( __( 'Invalid logout request.', 'tenant-access-manager' ) );
        }
    }
}
add_action( 'init', 'tam_handle_logout' );

/**
 * Get Current User Tenant Data
 *
 * This function retrieves the tenant data for the currently logged-in user.
 *
 * @return array|false Returns user data array if logged in, false otherwise.
 */
function tam_get_current_user_tenant_data() {
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $email = wp_get_current_user()->user_email;
        $tenant_id = get_user_meta( $user_id, 'tenant_id', true );
        $tenant_name = tam_get_tenant_name( $tenant_id );

        return array(
            'email'       => $email,
            'tenant_id'   => $tenant_id,
            'tenant_name' => $tenant_name,
        );
    }

    return false;
}
?>
