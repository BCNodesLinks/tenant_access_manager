<?php
/**
 * Customer.io Integration for Tenant Access Manager (TAM) Plugin
 *
 * Handles initialization and interactions with the Customer.io API.
 *
 * @package TenantAccessManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Initialize Customer.io Client
 *
 * Retrieves API credentials from constants and initializes the Customer.io client.
 *
 * @return \Customerio\Client|null The initialized Customer.io client or null on failure.
 */
function tam_get_customerio_client() {
    // Retrieve constants from wp-config.php
    $site_id    = defined( 'CUSTOMERIO_SITE_ID' ) ? CUSTOMERIO_SITE_ID : '';
    $api_key    = defined( 'CUSTOMERIO_API_KEY' ) ? CUSTOMERIO_API_KEY : '';
    $app_key    = defined( 'CUSTOMERIO_APP_KEY' ) ? CUSTOMERIO_APP_KEY : '';
    $region     = defined( 'CUSTOMERIO_REGION' ) ? CUSTOMERIO_REGION : 'eu';

    // Validate required credentials
    if ( empty( $site_id ) || empty( $api_key ) ) {
        error_log( "[TAM_DEBUG] Customer.io Initialization Error: CUSTOMERIO_SITE_ID and CUSTOMERIO_API_KEY must be defined." );
        return null;
    }

    try {
        // Initialize the Customer.io client with Site API Key and Site ID
        $client = new \Customerio\Client( $api_key, $site_id, [ 'region' => $region ] );

        // Set App API Key for transactional emails if defined
        if ( ! empty( $app_key ) ) {
            $client->setAppAPIKey( $app_key );
        }

        return $client;
    } catch ( Exception $e ) {
        error_log( "[TAM_DEBUG] Customer.io Initialization Exception: " . $e->getMessage() );
        return null;
    }
}

/**
 * Update Customer.io Profile
 *
 * Updates the customer profile with tenant information.
 *
 * @param string $email       The customer's email address.
 * @param int    $tenant_id   The tenant's ID.
 * @param string $tenant_name The tenant's name.
 */
function tam_update_customerio_profile( $email, $tenant_id, $tenant_name ) {
    $client = tam_get_customerio_client();
    if ( ! $client ) {
        error_log( "[TAM_DEBUG] Customer.io client not available. Cannot update profile." );
        return;
    }

    try {
        // Update the customer profile with 'id', Tenant ID, and Tenant Name
        $response = $client->customers->add( array(
            'id'          => $email, // Using email as the unique identifier
            'email'       => $email,
            'tenant_id'   => $tenant_id,
            'tenant_name' => $tenant_name,
            'updated_at'  => time(),
        ) );

        error_log( "[TAM_DEBUG] Updated Customer.io profile for {$email} with Tenant ID: {$tenant_id} and Tenant Name: {$tenant_name}. Response: " . print_r( $response, true ) );
    } catch ( Exception $e ) {
        error_log( "[TAM_DEBUG] Customer.io Profile Update Error: " . $e->getMessage() );
    }
}

/**
 * Track Customer.io Event
 *
 * Tracks an event for a customer, identified by email.
 *
 * @param string $email         The customer's email address.
 * @param string $event_name    The name of the event.
 * @param array  $data          Additional data for the event.
 */
function tam_track_customerio_event( $email, $event_name, $data = array() ) {
    $client = tam_get_customerio_client();
    if ( ! $client ) {
        error_log( "[TAM_DEBUG] Customer.io client not available. Cannot track event." );
        return;
    }

    try {
        // Prepend 'portal_' to the event name
        $prefixed_event_name = 'portal_' . $event_name;
        error_log( "[TAM_DEBUG] Preparing to send event '{$prefixed_event_name}' for Email: {$email}" );

        if ( ! is_email( $email ) ) {
            error_log( "[TAM_DEBUG] Customer.io Tracking Error: Invalid email provided for identified event." );
            return;
        }

        // Track identified event using the customer's email
        $response = $client->customers->event( array(
            'id'   => $email, // Using email as identifier
            'name' => $prefixed_event_name,
            'data' => $data,
        ) );

        error_log( "[TAM_DEBUG] Tracked identified event '{$prefixed_event_name}' for {$email} with data: " . json_encode( $data ) . ". Response: " . print_r( $response, true ) );
    } catch ( Exception $e ) {
        error_log( "[TAM_DEBUG] Customer.io Tracking Error: " . $e->getMessage() );
    }
}

/**
 * Send Transactional Email via Customer.io
 *
 * Sends a transactional email using a specified template.
 *
 * @param string $email       The recipient's email address.
 * @param string $template_id The ID of the transactional email template.
 * @param array  $data        Additional data to populate the email template.
 */
function tam_send_transactional_email( $email, $template_id, $data = array() ) {
    $client = tam_get_customerio_client();
    if ( ! $client ) {
        error_log( "[TAM_DEBUG] Customer.io client not available. Cannot send transactional email." );
        return;
    }

    try {
        // Check if 'send' endpoint is initialized
        if ( ! method_exists( $client, 'send' ) ) {
            error_log( "[TAM_DEBUG] Customer.io Send endpoint is not initialized." );
            return;
        }

        if ( ! is_email( $email ) ) {
            error_log( "[TAM_DEBUG] Customer.io Transactional Email Error: Invalid email address." );
            return;
        }

        error_log( "[TAM_DEBUG] Attempting to send transactional email using template ID '{$template_id}' to {$email}." );

        // Prepare the payload for transactional email with 'identifiers'
        $payload = array(
            'to'                       => $email,
            'transactional_message_id' => $template_id,
            'message_data'             => $data,
            'identifiers'              => array(
                'email' => $email, // Associate the email with the customer
            ),
        );

        // Log the payload being sent
        error_log( "[TAM_DEBUG] Transactional Email Payload: " . json_encode( $payload ) );

        // Send transactional email
        $response = $client->send()->email( $payload );

        // Log the response for debugging
        error_log( "[TAM_DEBUG] Sent transactional email using template '{$template_id}' to {$email} with data: " . json_encode( $data ) );
        error_log( "[TAM_DEBUG] Transactional Email Response: " . print_r( $response, true ) );
    } catch ( Exception $e ) {
        error_log( "[TAM_DEBUG] Customer.io Transactional Email Error: " . $e->getMessage() );
    }
}
?>
