<?php
// includes/admin-access.php

/**
 * Restrict WP Admin Access
 *
 * This function prevents non-administrator users from accessing the wp-admin area.
 */
function tam_restrict_wp_admin_access() {
    // Allow access to admin-ajax.php
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }

    // Allow access to the REST API
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return;
    }

    // Allow access to certain admin pages like profile.php if needed
    $allowed_pages = array( 'admin-ajax.php', 'async-upload.php', 'media-upload.php', 'profile.php' );
    $current_page = basename( $_SERVER['PHP_SELF'] );
    if ( in_array( $current_page, $allowed_pages ) ) {
        return;
    }

    // Check if the user is in the admin area and does not have administrator capabilities
    if ( is_admin() && ! current_user_can( 'administrator' ) ) {
        wp_redirect( home_url() ); // Redirect to the home page
        exit;
    }
}
add_action( 'admin_init', 'tam_restrict_wp_admin_access' );
