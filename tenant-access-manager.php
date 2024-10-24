<?php
/*
Plugin Name: Tenant Access Manager
Description: Allows admin to create tenants with access to specific resources and restricts site access until email confirmation.
Version: 1.9
Author: Ben Campbell
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin directory and URL
define( 'TAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Define a prefix for Customer.io events
if ( ! defined( 'TAM_EVENT_PREFIX' ) ) {
    define( 'TAM_EVENT_PREFIX', 'portal_' );
}

define( 'TAM_CUSTOMERIO_TRANSACTIONAL_TEMPLATE_ID', '3' ); // Replace with your actual template ID

/*
define( 'TAM_SECRET_KEY', getenv( 'TAM_SECRET_KEY' ) );
define( 'CUSTOMERIO_API_KEY', getenv( 'CUSTOMERIO_API_KEY' ) );
define( 'CUSTOMERIO_SITE_ID', getenv( 'CUSTOMERIO_SITE_ID' ) );
define( 'CUSTOMERIO_APP_KEY', getenv( 'CUSTOMERIO_APP_KEY' ) ); // If applicable
define( 'CUSTOMERIO_REGION', getenv( 'CUSTOMERIO_REGION' ) ); // or set a default

The above are all set in wp-config.php
*/

// Include the Composer autoloader
if ( file_exists( TAM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once TAM_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    error_log( 'Tenant Access Manager: Composer autoload.php not found.' );
    return;
}

// Include helper functions
require_once TAM_PLUGIN_DIR . 'includes/helpers.php';

// Include Custom Post Types
require_once TAM_PLUGIN_DIR . 'includes/cpt.php';

// Include Meta Boxes
require_once TAM_PLUGIN_DIR . 'includes/meta-boxes.php';

// Include Shortcodes
require_once TAM_PLUGIN_DIR . 'includes/shortcodes.php';

// Include Authentication Handlers
require_once TAM_PLUGIN_DIR . 'includes/auth.php';

// Include Customer.io Integration BEFORE Access Control
require_once TAM_PLUGIN_DIR . 'includes/customerio.php';

// Include Access Control
require_once TAM_PLUGIN_DIR . 'includes/access-control.php';

// Include Toast Notification
require_once TAM_PLUGIN_DIR . 'includes/toast.php';

// Include User Profile Modifications
require_once TAM_PLUGIN_DIR . 'includes/user-profile.php';

// Include Admin Access Control
require_once TAM_PLUGIN_DIR . 'includes/admin-access.php';

// Activation and Deactivation Hooks
function tam_activate_plugin() {
    // Trigger CPT registration on activation
    tam_register_custom_post_types();
    flush_rewrite_rules();

    // Add 'viewer' role
    add_role(
        'viewer',
        __( 'Viewer', 'tenant-access-manager' ),
        array(
            'read' => true, // Allows viewing of the site
        )
    );
}
register_activation_hook( __FILE__, 'tam_activate_plugin' );


function tam_deactivate_plugin() {
    // Remove 'viewer' role
    remove_role( 'viewer' );

    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tam_deactivate_plugin' );


// Initialize Plugin
function tam_initialize_plugin() {
    // Any initialization code can go here
}
add_action( 'plugins_loaded', 'tam_initialize_plugin' );

// Enqueue Auto-Logout Script
function tam_enqueue_auto_logout_script() {
    // Only enqueue for authenticated users
    if ( is_user_logged_in() ) {
        // Define the logout URL with nonce
        $login_page = get_page_by_path( 'login' );
        if ( $login_page ) {
            $logout_url = add_query_arg( array(
                'tam_logout'       => '1',
                'tam_logout_nonce' => wp_create_nonce( 'tam_logout_action' ),
            ), get_permalink( $login_page->ID ) );
        } else {
            $logout_url = wp_logout_url(); // Fallback URL
        }

        // Enqueue the auto-logout script
        wp_enqueue_script( 'tam-auto-logout', TAM_PLUGIN_URL . 'includes/assets/js/auto-logout.js', array(), '1.0', true );

        // Determine inactivity time based on session expiration
        $expiration = ( is_user_logged_in() && ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) ? tam_get_current_session_expiration() : 30 * MINUTE_IN_SECONDS;
        $inactivity_time = $expiration * 1000; // Convert to milliseconds

        // Pass the inactivity time and logout URL to the script
        wp_localize_script( 'tam-auto-logout', 'tamSettings', array(
            'inactivityTime' => $inactivity_time,
            'logoutUrl'      => esc_url_raw( $logout_url ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'tam_enqueue_auto_logout_script' );

// Set Authentication Cookie Expiration
/**
 * Set Authentication Cookie Expiration
 *
 * This function sets the authentication cookie expiration time based on the "Remember Me" option.
 *
 * @param int  $expiration The expiration time in seconds.
 * @param int  $user_id    The user ID.
 * @param bool $remember   Whether to remember the user.
 * @return int Modified expiration time.
 */
function tam_set_auth_cookie_expiration( $expiration, $user_id, $remember ) {
    if ( $remember ) {
        // Set expiration to 14 days if "Remember Me" is checked
        return 14 * DAY_IN_SECONDS;
    } else {
        // Set expiration to 30 minutes
        return 1 * MINUTE_IN_SECONDS;
    }
}
add_filter( 'auth_cookie_expiration', 'tam_set_auth_cookie_expiration', 99, 3 );

// Added Helper Function
/**
 * Get Current Session Expiration Time
 *
 * Retrieves the remaining time until the current session expires.
 *
 * @return int Remaining session time in seconds.
 */
function tam_get_current_session_expiration() {
    if ( is_user_logged_in() && ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
        $cookie = wp_parse_auth_cookie( '', 'logged_in' );
        if ( $cookie && isset( $cookie['expiration'] ) ) {
            $remaining_time = $cookie['expiration'] - time();
            return $remaining_time > 0 ? $remaining_time : 0;
        }
    }
    // Default to 30 minutes if unable to determine
    return 1 * MINUTE_IN_SECONDS;
}

// Log Customer.io Client Initialization
add_action( 'init', function() {
    $client = tam_get_customerio_client();
} );
