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
 * Conditional Logging Function
 *
 * Logs messages only if TAM_DEBUG is enabled.
 *
 * @param string $message The message to log.
 */
function tam_log( $message ) {
    if ( defined( 'TAM_DEBUG' ) && TAM_DEBUG ) {
        error_log( '[TAM_DEBUG] ' . $message );
    }
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
        tam_log( 'Customer.io Initialization Error: CUSTOMERIO_SITE_ID and CUSTOMERIO_API_KEY must be defined.' );
        return null;
    }

    try {
        // Initialize the Customer.io client with Site API Key and Site ID
        $client = new \Customerio\Client( $api_key, $site_id, [ 'region' => $region ] );

        // Set App API Key for transactional emails if defined
        if ( ! empty( $app_key ) ) {
            $client->setAppAPIKey( $app_key );
        }

        tam_log( 'Customer.io client initialized successfully.' );
        return $client;
    } catch ( Exception $e ) {
        tam_log( 'Customer.io Initialization Exception: ' . $e->getMessage() );
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
        tam_log( 'Customer.io client not available. Cannot update profile.' );
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

        tam_log( "Updated Customer.io profile for {$email} with Tenant ID: {$tenant_id} and Tenant Name: {$tenant_name}. Response: " . print_r( $response, true ) );
    } catch ( Exception $e ) {
        tam_log( 'Customer.io Profile Update Error: ' . $e->getMessage() );
    }
}

/**
 * Track Customer.io Event
 *
 * Tracks an event for a customer, identified by email or anonymous ID.
 *
 * @param string      $email         The customer's email address.
 * @param string      $event_name    The name of the event.
 * @param array       $data          Additional data for the event.
 * @param bool        $anonymous     Whether the event is anonymous.
 * @param string|null $anonymous_id  The anonymous ID if the event is anonymous.
 */
function tam_track_customerio_event( $email, $event_name, $data = array(), $anonymous = false, $anonymous_id = null ) {
    $client = tam_get_customerio_client();
    if ( ! $client ) {
        tam_log( 'Customer.io client not available. Cannot track event.' );
        return;
    }

    try {
        // Prepend 'portal_' to the event name
        $prefixed_event_name = 'portal_' . $event_name;

        if ( ! $anonymous ) {
            if ( ! is_email( $email ) ) {
                tam_log( 'Customer.io Tracking Error: Invalid email provided for identified event.' );
                return;
            }

            // Track identified event using the customer's email
            $response = $client->customers->event( array(
                'id'   => $email, // Using email as identifier
                'name' => $prefixed_event_name,
                'data' => $data,
            ) );

            tam_log( "Tracked identified event '{$prefixed_event_name}' for {$email} with data: " . json_encode( $data ) . ". Response: " . print_r( $response, true ) );
        } else {
            if ( empty( $anonymous_id ) ) {
                tam_log( 'Customer.io Tracking Error: anonymous_id is required for anonymous events.' );
                return;
            }

            // Track anonymous event with a unique anonymous_id
            $response = $client->events->anonymous( array(
                'name'         => $prefixed_event_name,
                'data'         => $data,
                'anonymous_id' => $anonymous_id,
            ) );

            tam_log( "Tracked anonymous event '{$prefixed_event_name}' with anonymous_id '{$anonymous_id}' and data: " . json_encode( $data ) . ". Response: " . print_r( $response, true ) );
        }
    } catch ( Exception $e ) {
        tam_log( 'Customer.io Tracking Error: ' . $e->getMessage() );
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
        tam_log( 'Customer.io client not available. Cannot send transactional email.' );
        return;
    }

    try {
        // Check if 'send' endpoint is initialized
        if ( ! method_exists( $client, 'send' ) ) {
            tam_log( 'Customer.io Send endpoint is not initialized.' );
            return;
        }

        if ( ! is_email( $email ) ) {
            tam_log( 'Customer.io Transactional Email Error: Invalid email address.' );
            return;
        }

        tam_log("attempttosend");

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
        tam_log( 'Transactional Email Payload: ' . json_encode( $payload ) );

        // Send transactional email
        $response = $client->send()->email( $payload );

        // Log the response for debugging
        tam_log( "Sent transactional email using template '{$template_id}' to {$email} with data: " . json_encode( $data ) );
        tam_log( "Transactional Email Response: " . print_r( $response, true ) );
    } catch ( Exception $e ) {
        tam_log( 'Customer.io Transactional Email Error: ' . $e->getMessage() );
    }
}

/**
 * Send Transactional Email via Customer.io (Alternative Method)
 *
 * This function is an alternative method in case you need to specify an anonymous ID.
 *
 * @param string      $email         The recipient's email address.
 * @param string      $template_id    The ID of the transactional email template.
 * @param array       $data          Additional data to populate the email template.
 * @param string      $anonymous_id   The anonymous ID for the event.
 */
function tam_send_transactional_email_anonymous( $email, $template_id, $data = array(), $anonymous_id ) {
    $client = tam_get_customerio_client();
    if ( ! $client ) {
        tam_log( 'Customer.io client not available. Cannot send transactional email.' );
        return;
    }

    try {
        // Check if 'send' endpoint is initialized
        if ( ! method_exists( $client, 'send' ) ) {
            tam_log( 'Customer.io Send endpoint is not initialized.' );
            return;
        }

        if ( ! is_email( $email ) ) {
            tam_log( 'Customer.io Transactional Email Error: Invalid email address.' );
            return;
        }

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
        tam_log( 'Transactional Email Payload (Anonymous): ' . json_encode( $payload ) );

        // Send transactional email
        $response = $client->send()->email( $payload );

        // Log the response for debugging
        tam_log( "Sent transactional email (Anonymous) using template '{$template_id}' to {$email} with data: " . json_encode( $data ) );
        tam_log( "Transactional Email Response: " . print_r( $response, true ) );
    } catch ( Exception $e ) {
        tam_log( 'Customer.io Transactional Email Error: ' . $e->getMessage() );
    }
}
