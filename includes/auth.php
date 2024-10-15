<?php
// includes/auth.php

/**
 * Tenant Access Manager - Authentication Handlers
 *
 * This file handles email confirmation and user logout functionalities,
 * including setting authentication cookies and tracking events with Customer.io.
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
 * authenticates the user by setting a secure cookie,
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
            error_log( "[TAM_DEBUG] Token valid. Email: {$data['email']}" );

            // Delete the transient as it's no longer needed to prevent reuse
            delete_transient( 'tam_email_token_' . $token );

            // Check if the token has expired
            if ( time() > $data['expiration'] ) {
                error_log( "[TAM_DEBUG] Token has expired: {$token}" );
                echo '<p>' . __( 'Token has expired. Please request a new confirmation email.', 'tenant-access-manager' ) . '</p>';
                return;
            }

            // Extract email from the token data
            $email = $data['email'];

            // Try to retrieve tenant ID based on email address
            $tenant_id = tam_get_tenant_by_email( $email );

            if ( ! $tenant_id ) {
                // If no tenant found via email, try to retrieve tenant ID based on email domain
                if ( strpos( $email, '@' ) !== false ) {
                    list( $user, $domain ) = explode( '@', $email );
                    $tenant_id = tam_get_tenant_by_domain( $domain );
                } else {
                    error_log( "[TAM_DEBUG] Invalid email format: {$email}" );
                    echo '<p>' . __( 'Invalid email format.', 'tenant-access-manager' ) . '</p>';
                    return;
                }
            }

            if ( $tenant_id ) {
                // Retrieve Tenant Name based on Tenant ID
                $tenant_name = tam_get_tenant_name( $tenant_id );

                // Generate a secure authentication token with user and tenant information
                $token_data = json_encode( array(
                    'email'        => $email,
                    'tenant_id'    => $tenant_id,
                    'tenant_name'  => $tenant_name,
                    'timestamp'    => time(),
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

                error_log( "[TAM_DEBUG] User authenticated. Email: {$email}, Tenant ID: {$tenant_id}, Tenant Name: {$tenant_name}" );

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
                // Log and display an error if no tenant is found for the email or domain
                error_log( "[TAM_DEBUG] No tenant found for email: {$email}" );
                echo '<p>' . __( 'No tenant found for your email.', 'tenant-access-manager' ) . '</p>';
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
                    'tenant_id'    => $tenant_id,
                    'tenant_name'  => $tenant_name,
                    'timestamp'    => time(),
                ) );
            }

            // Redirect the user to the login page after successful logout
            $login_page = get_page_by_path( 'login' );
            if ( $login_page ) {
                wp_redirect( get_permalink( $login_page->ID ) );
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
 * Validate User Authentication
 *
 * This function validates the user's authentication token and retrieves user data.
 *
 * @return array|false Returns user data array if valid, false otherwise.
 */
function tam_validate_user_authentication() {
    if ( isset( $_COOKIE['tam_user_token'] ) ) {
        $auth_token = $_COOKIE['tam_user_token'];
        $decoded    = base64_decode( $auth_token );

        if ( $decoded ) {
            error_log( "[TAM_DEBUG] Decoded authentication token successfully." );

            // Ensure the token contains both data and signature
            if ( strpos( $decoded, '::' ) !== false ) {
                list( $token_data, $signature ) = explode( '::', $decoded );

                // Verify the signature
                $expected_signature = hash_hmac( 'sha256', $token_data, TAM_SECRET_KEY );
                if ( hash_equals( $expected_signature, $signature ) ) {
                    error_log( "[TAM_DEBUG] Authentication token signature verified." );

                    $data = json_decode( $token_data, true );

                    if ( is_array( $data ) && isset( $data['email'], $data['tenant_id'], $data['tenant_name'] ) ) {
                        $email     = $data['email'];
                        $tenant_id = intval( $data['tenant_id'] );

                        // Re-validate the user's email against the tenant's allowed emails or domains
                        $access_type = get_post_meta( $tenant_id, '_tam_tenant_access_type', true );
                        if ( 'email' === $access_type ) {
                            $allowed_emails = get_post_meta( $tenant_id, '_tam_tenant_emails', false );
                            if ( in_array( strtolower( $email ), array_map( 'strtolower', $allowed_emails ), true ) ) {
                                return $data;
                            } else {
                                error_log( "[TAM_DEBUG] Email {$email} not allowed for Tenant ID {$tenant_id}." );
                                return false;
                            }
                        } else {
                            if ( strpos( $email, '@' ) !== false ) {
                                list( $user, $domain ) = explode( '@', $email );
                                $allowed_domains = get_post_meta( $tenant_id, '_tam_tenant_domains', false );
                                if ( in_array( strtolower( $domain ), array_map( 'strtolower', $allowed_domains ), true ) ) {
                                    return $data;
                                } else {
                                    error_log( "[TAM_DEBUG] Domain {$domain} not allowed for Tenant ID {$tenant_id}." );
                                    return false;
                                }
                            } else {
                                error_log( "[TAM_DEBUG] Invalid email format: {$email}" );
                                return false;
                            }
                        }
                    } else {
                        error_log( "[TAM_DEBUG] Authentication data missing required fields." );
                    }
                } else {
                    error_log( "[TAM_DEBUG] Authentication token signature mismatch." );
                }
            } else {
                error_log( "[TAM_DEBUG] Authentication token format invalid. Missing '::' separator." );
            }
        } else {
            error_log( "[TAM_DEBUG] Failed to decode authentication token." );
        }
    } else {
        error_log( "[TAM_DEBUG] Authentication cookie 'tam_user_token' not found." );
    }

    return false;
}
