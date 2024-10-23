<?php
/**
 * Tenant Access Manager - Toast Notification
 *
 * Displays a toast notification on the login page if the user was auto-logged out.
 */

// Hook into 'wp_enqueue_scripts' to enqueue scripts and styles conditionally
function tam_enqueue_toast_notification() {
    // Check if we're on the login page and auto_logout parameter is set
    if ( is_page( 'login' ) && isset( $_GET['auto_logout'] ) && $_GET['auto_logout'] == '1' ) {
        // Enqueue the toast notification script
        wp_enqueue_script( 'tam-toast', TAM_PLUGIN_URL . 'includes/assets/js/tam-toast.js', array(), '1.0', true );

        // Enqueue the toast notification styles
        wp_enqueue_style( 'tam-toast-style', TAM_PLUGIN_URL . 'includes/assets/css/tam-toast.css', array(), '1.0' );

        // Pass the message to the script
        wp_localize_script( 'tam-toast', 'tamToastSettings', array(
            'message' => __( 'You have been logged out due to inactivity.', 'tenant-access-manager' ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'tam_enqueue_toast_notification' );
?>
