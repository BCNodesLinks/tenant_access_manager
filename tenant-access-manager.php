<?php
/**
 * Plugin Name: Tenant Access Manager
 * Description: Allows admin to create tenants with access to specific resources and restricts site access until email confirmation.
 * Version: 1.9
 * Author: Ben Campbell
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Start output buffering to prevent accidental output
ob_start();

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

// Include Customer.io Integration BEFORE Access Control
require_once TAM_PLUGIN_DIR . 'includes/customerio.php';

// Include Authentication Handlers
require_once TAM_PLUGIN_DIR . 'includes/auth.php';

// Include Access Control
require_once TAM_PLUGIN_DIR . 'includes/access-control.php';

// Activation and Deactivation Hooks
function tam_activate_plugin() {
    // Trigger CPT registration on activation
    tam_register_custom_post_types();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tam_activate_plugin' );

function tam_deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tam_deactivate_plugin' );

// Initialize Plugin
function tam_initialize_plugin() {
    // Any initialization code can go here
}
add_action( 'plugins_loaded', 'tam_initialize_plugin' );

// Enqueue Auto-Logout Script
function tam_enqueue_scripts() {
    wp_enqueue_script( 'tam-auto-logout', TAM_PLUGIN_URL . 'assets/js/auto-logout.js', array( 'jquery' ), '1.0', true );

    // Pass the inactivity time (in milliseconds) and logout nonce to the script
    wp_localize_script( 'tam-auto-logout', 'tamSettings', array(
        'inactivityTime' => 15 * 60 * 1000, // 15 minutes in milliseconds
        'logoutNonce'    => wp_create_nonce( 'tam_logout_action' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'tam_enqueue_scripts' );

// Log Customer.io Client Initialization
add_action( 'init', function() {
    $client = tam_get_customerio_client();
    if ( $client ) {
        error_log( 'Customer.io client initialized successfully.' );
    } else {
        error_log( 'Customer.io client failed to initialize.' );
    }
} );

// Flush the output buffer on shutdown
add_action( 'shutdown', 'tam_flush_output_buffer_main' );
function tam_flush_output_buffer_main() {
    if ( ob_get_length() ) {
        ob_end_flush();
    }
}
