<?php
// includes/customerio.php

use Customerio\Client;

/**
 * Tenant Access Manager - Customer.io Integration
 *
 * This file handles the integration with Customer.io, including tracking both
 * identified and anonymous events.
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
                $client = new Client( CUSTOMERIO_API_KEY, CUSTOMERIO_SITE_ID );

                // If you have an App API Key, set it here
                if ( defined( 'CUSTOMERIO_APP_KEY' ) ) {
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
            error_log( 'Customer.io credentials are not defined.' );
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
 * @param string      $email       Customer's email address. Required for identified events.
 * @param string      $event_name  Name of the event to track.
 * @param array       $data        Additional data to include with the event.
 * @param bool        $anonymous   Whether the event is anonymous. If true, event is sent without associating with a user.
 * @return void
 */
function tam_track_customerio_event( $email, $event_name, $data = array(), $anonymous = false ) {
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
                    'email' => $email,
                    'name'  => $prefixed_event_name,
                    'data'  => $data,
                ) );

                error_log( "Tracked identified event '{$prefixed_event_name}' for {$email} with data: " . json_encode( $data ) );
            } else {
                // Track anonymous event without associating it with a specific user
                $client->events->anonymous( array(
                    'name' => $prefixed_event_name,
                    'data' => $data,
                ) );

                error_log( "Tracked anonymous event '{$prefixed_event_name}' with data: " . json_encode( $data ) );
            }
        } catch ( Exception $e ) {
            error_log( 'Customer.io Tracking Error: ' . $e->getMessage() );
        }
    } else {
        error_log( 'Customer.io client not available. Cannot track event.' );
    }
}
