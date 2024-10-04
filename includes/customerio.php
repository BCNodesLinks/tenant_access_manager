<?php
// includes/customerio.php

use Customerio\Client;

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
        if ( defined( 'CUSTOMERIO_API_KEY' ) && defined( 'CUSTOMERIO_SITE_ID' ) ) {
            try {
                $client = new Client( CUSTOMERIO_API_KEY, CUSTOMERIO_SITE_ID );
            } catch ( Exception $e ) {
                error_log( 'Customer.io Initialization Error: ' . $e->getMessage() );
                return false;
            }
        } else {
            error_log( 'Customer.io credentials are not defined.' );
            return false;
        }
    }

    return $client;
}

/**
 * Track Customer.io Event
 *
 * This function tracks an event for a customer in Customer.io with a prefix.
 *
 * @param string $email      Customer's email address.
 * @param string $event_name Name of the event to track.
 * @param array  $data       Additional data to include with the event.
 * @return void
 */
function tam_track_customerio_event( $email, $event_name, $data = array() ) {
    $client = tam_get_customerio_client();
    if ( $client ) {
        try {
            // Generate a unique customer ID (e.g., using email hash)
            $customer_id = md5( strtolower( $email ) );

            // Prepend the defined prefix to the event name
            $prefixed_event_name = TAM_EVENT_PREFIX . $event_name;

            // Track the event
            $client->customers->event( array(
                'id'   => $customer_id,
                'name' => $prefixed_event_name,
                'data' => $data,
            ) );

            error_log( "Tracked event '{$prefixed_event_name}' for {$email} with data: " . json_encode( $data ) );
        } catch ( Exception $e ) {
            error_log( 'Customer.io Tracking Error: ' . $e->getMessage() );
        }
    } else {
        error_log( 'Customer.io client not available. Cannot track event.' );
    }
}
