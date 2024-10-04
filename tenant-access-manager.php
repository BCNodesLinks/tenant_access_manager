<?php
/*
Plugin Name: Tenant Access Manager
Description: Allows admin to create tenants with access to specific resources and restricts site access until email confirmation.
Version: 1.8
Author: Ben Campbell
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin directory and URL
define( 'TAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Define a secret key for token generation (replace with your secure key)
if ( ! defined( 'TAM_SECRET_KEY' ) ) {
    define( 'TAM_SECRET_KEY', 'O*cx3sWWeaX|:Eg=qFm8$ky:IhM=T%#p;iybWlzOK,6qwoy{`>5v&!4hszV~ww|0' );
}

// Enable WordPress debug logging (ensure this is appropriate for your environment)
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}
if ( ! defined( 'WP_DEBUG_LOG' ) ) {
    define( 'WP_DEBUG_LOG', true );
}
if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
    define( 'WP_DEBUG_DISPLAY', false );
}

// Define Customer.io credentials (replace with your actual credentials)
if ( ! defined( 'CUSTOMERIO_SITE_ID' ) ) {
    define( 'CUSTOMERIO_SITE_ID', '33452c4a9f7be138382c' );
}
if ( ! defined( 'CUSTOMERIO_API_KEY' ) ) {
    define( 'CUSTOMERIO_API_KEY', 'a6f9268fa3464ebe5f2c' );
}

// Define a prefix for Customer.io events
if ( ! defined( 'TAM_EVENT_PREFIX' ) ) {
    define( 'TAM_EVENT_PREFIX', 'portal_' );
}

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

// Include Access Control
require_once TAM_PLUGIN_DIR . 'includes/access-control.php';

// Include Customer.io Integration
require_once TAM_PLUGIN_DIR . 'includes/customerio.php';

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
