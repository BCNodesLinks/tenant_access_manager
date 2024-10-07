<?php
// includes/customerio.php

use Customerio\Client;

/**
 * Tenant Access Manager - Customer.io Integration
 *
 * This file handles the integration with Customer.io, including tracking both
 * identified and anonymous events, as well as sending transactional emails.
 */

/**
 * Get the Customer.io Client
 *
 * Initializes and returns the Customer.io client using defined credentials.
 *
 * @return Client|false Returns the Customer.io client instance or false on failure.
 */
function tam_get_customerio_client() {
    static $client = null;

    if ( is_null( $client ) ) {
        // Ensure that the Customer.io API credentials are defined
        if ( defined( 'CUSTOMERIO_API_KEY' ) && defined( 'CUSTOMERIO_SITE_ID' ) ) {
            try {
                // Initialize the Customer.io client
                $client = new Client( CUSTOMERIO_SITE_ID, CUSTOMERIO_API_KEY );

                // If you have an App API Key, set it here
                if ( defined( 'CUSTOMERIO_APP_KEY' ) && ! empty( CUSTOMERIO_APP_KEY ) ) {
                    $client->setAppAPIKey( CUSTOMERIO_APP_KEY );
                }

                // Optionally, set the region if you're targeting the EU
                if ( defined( 'CUSTOMERIO_REGION' ) && strtolower( CUSTOMERIO_REGION ) === 'eu' ) {
                    $client->setRegion( 'eu' );
                }

            } catch ( Exception $e ) {
                error_log( 'Customer.io Initialization Error: ' . $e->getMessage() );
                return false;
            }
        } else {
            error_log( 'Customer.io credentials are not fully defined.' );
            return false;
        }
    }

    return $client;
}

/**
 * Track Customer.io Event
 *
 * This function tracks an event for a customer in Customer.io with a 'portal_' prefix.
 * It supports both identified and anonymous events.
 *
 * @param string      $email         Customer's email address. Required for identified events.
 * @param string      $event_name    Name of the event to track.
 * @param array       $data          Additional data to include with the event.
 * @param bool        $anonymous     Whether the event is anonymous. If true, event is sent without associating with a user.
 * @param string|null $anonymous_id  Unique identifier for anonymous events. Required if $anonymous is true.
 * @return void
 */
function tam_track_customerio_event( $email, $event_name, $data = array(), $anonymous = false, $anonymous_id = null ) {
    $client = tam_get_customerio_client();
    if ( $client ) {
        try {
            // Prepend 'portal_' to the event name
            $prefixed_event_name = 'portal_' . $event_name;

            if ( ! $anonymous ) {
                if ( ! is_email( $email ) ) {
                    error_log( 'Customer.io Tracking Error: Invalid email provided for identified event.' );
                    return;
                }

                // Track identified event using the customer's email
                $client->customers->event( array(
                    'id'    => $email, // Using email as identifier
                    'name'  => $prefixed_event_name,
                    'data'  => $data,
                ) );

                error_log( "Tracked identified event '{$prefixed_event_name}' for {$email} with data: " . json_encode( $data ) );
            } else {
                if ( empty( $anonymous_id ) ) {
                    error_log( 'Customer.io Tracking Error: anonymous_id is required for anonymous events.' );
                    return;
                }

                // Track anonymous event with a unique anonymous_id
                $client->events->anonymous( array(
                    'name'         => $prefixed_event_name,
                    'data'         => $data,
                    'anonymous_id' => $anonymous_id,
                ) );

                error_log( "Tracked anonymous event '{$prefixed_event_name}' with anonymous_id '{$anonymous_id}' and data: " . json_encode( $data ) );
            }
        } catch ( Exception $e ) {
            error_log( 'Customer.io Tracking Error: ' . $e->getMessage() );
        }
    } else {
        error_log( 'Customer.io client not available. Cannot track event.' );
    }
}

/**
 * Send Transactional Email via Customer.io
 *
 * This function sends a transactional email using a specified transactional template.
 *
 * @param string $email          Recipient's email address.
 * @param string $template_id    The identifier for the transactional email template in Customer.io.
 * @param array  $data           Data to populate the transactional email template.
 * @return void
 */
function tam_send_transactional_email( $email, $template_id, $data = array() ) {
    $client = tam_get_customerio_client();
    if ( $client ) {
        try {
            // Check if 'send' endpoint is initialized
            if ( ! isset( $client->send ) ) {
                error_log( 'Customer.io Send endpoint is not initialized.' );
                return;
            }

            if ( ! is_email( $email ) ) {
                error_log( 'Customer.io Transactional Email Error: Invalid email address.' );
                return;
            }

            // Prepare the payload for transactional email
            $payload = array(
                'to'                       => $email,
                'transactional_message_id' => $template_id,
                'data'                     => $data,
                'identifiers'              => array(
                    'email' => $email,
                ),
            );

            // Send transactional email
            $response = $client->send()->email( $payload );

            // Optionally, handle the response as needed
            // For example, log success or check response status

            error_log( "Sent transactional email using template '{$template_id}' to {$email} with data: " . json_encode( $data ) );

            // Example: Check response status if available
            if ( isset( $response['status'] ) && $response['status'] === 'success' ) {
                error_log( "Transactional email sent successfully to {$email}." );
            } else {
                error_log( "Failed to send transactional email to {$email}. Response: " . json_encode( $response ) );
            }
        } catch ( Exception $e ) {
            error_log( 'Customer.io Transactional Email Error: ' . $e->getMessage() );
        }
    } else {
        error_log( 'Customer.io client not available. Cannot send transactional email.' );
    }
}
