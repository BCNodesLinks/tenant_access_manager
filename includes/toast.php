<?php
// includes/toast.php

/**
 * Display Toast Notification on Logout
 *
 * This function displays a toast notification when a user is automatically logged out due to inactivity.
 */
function tam_display_logout_toast() {
    // Check if 'autologout' parameter is present in the URL
    if ( isset( $_GET['autologout'] ) && '1' === $_GET['autologout'] ) {
        // Enqueue the toast notification script and styles
        wp_enqueue_script( 'tam-toast-script', TAM_PLUGIN_URL . 'includes/assets/js/toast.js', array( 'jquery' ), '1.0', true );
        wp_enqueue_style( 'tam-toast-style', TAM_PLUGIN_URL . 'includes/assets/css/toast.css', array(), '1.0' );

        // Localize the script with the message
        wp_localize_script( 'tam-toast-script', 'tamToast', array(
            'message' => __( 'You have been logged out due to inactivity.', 'tenant-access-manager' ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'tam_display_logout_toast' );
