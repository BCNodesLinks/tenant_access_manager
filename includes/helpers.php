<?php
// includes/helpers.php

/**
 * Get Tenant by Email
 *
 * Retrieves the tenant ID associated with the given email.
 *
 * @param string $email The email to search for.
 * @return int|false Returns the tenant post ID if found, false otherwise.
 */
function tam_get_tenant_by_email( $email ) {
    global $wpdb;
    $email = strtolower( sanitize_email( $email ) );
    $cache_key = 'tam_tenant_email_' . md5( $email );

    // Attempt to get from cache
    $tenant_id = wp_cache_get( $cache_key, 'tam_cache_group' );
    if ( false !== $tenant_id ) {
        error_log( 'Tenant ID retrieved from cache for email: ' . $email . ' - Tenant ID: ' . $tenant_id );
        return $tenant_id;
    }

    $query = $wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID
        WHERE pm.meta_key = %s AND LOWER(pm.meta_value) = %s
        AND pm2.meta_key = %s AND pm2.meta_value = %s
        AND p.post_type = %s LIMIT 1",
        '_tam_tenant_emails',
        $email,
        '_tam_tenant_access_type',
        'email',
        'tenant'
    );
    $tenant_id = $wpdb->get_var( $query );

    if ( $tenant_id ) {
        error_log( 'Tenant found for email: ' . $email . ' - Tenant ID: ' . $tenant_id );
        // Cache the result for 12 hours
        wp_cache_set( $cache_key, $tenant_id, 'tam_cache_group', 12 * HOUR_IN_SECONDS );
        return $tenant_id;
    }

    error_log( 'No tenant found for email: ' . $email );
    return false;
}

/**
 * Get Tenant by Domain
 *
 * Retrieves the tenant ID associated with the given domain, considering the access type.
 *
 * @param string $domain The domain to search for.
 * @return int|false Returns the tenant post ID if found, false otherwise.
 */
function tam_get_tenant_by_domain( $domain ) {
    global $wpdb;
    $domain = strtolower( sanitize_text_field( $domain ) );
    $cache_key = 'tam_tenant_domain_' . $domain;

    // Attempt to get from cache
    $tenant_id = wp_cache_get( $cache_key, 'tam_cache_group' );
    if ( false !== $tenant_id ) {
        error_log( 'Tenant ID retrieved from cache for domain: ' . $domain . ' - Tenant ID: ' . $tenant_id );
        return $tenant_id;
    }

    $query = $wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID
        WHERE pm.meta_key = %s AND LOWER(pm.meta_value) = %s
        AND pm2.meta_key = %s AND pm2.meta_value = %s
        AND p.post_type = %s LIMIT 1",
        '_tam_tenant_domains',
        $domain,
        '_tam_tenant_access_type',
        'domain',
        'tenant'
    );
    $tenant_id = $wpdb->get_var( $query );

    if ( $tenant_id ) {
        error_log( 'Tenant found for domain: ' . $domain . ' - Tenant ID: ' . $tenant_id );
        // Cache the result for 12 hours
        wp_cache_set( $cache_key, $tenant_id, 'tam_cache_group', 12 * HOUR_IN_SECONDS );
        return $tenant_id;
    }

    error_log( 'No tenant found for domain: ' . $domain );
    return false;
}

/**
 * Get Authenticated Tenant Logo URL
 *
 * Retrieves the logo URL for the authenticated tenant.
 *
 * @param string $size Image size (default 'full').
 * @return string URL of the tenant logo or default logo.
 */
function tam_get_authenticated_tenant_logo_url( $size = 'full' ) {
    // Start QM Timer for Tenant Logo Retrieval
    do_action( 'qm/start', 'Tenant Logo Retrieval' );

    $auth_data = tam_validate_user_authentication();
    if ( $auth_data ) {
        $tenant_id = intval( $auth_data['tenant_id'] );
        // Get logo ID from post meta
        $logo_id = get_post_meta( $tenant_id, 'tenant_logo', true );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, $size );
            // Stop QM Timer
            do_action( 'qm/stop', 'Tenant Logo Retrieval' );
            return $logo_url;
        }
    }
    // Return default logo URL if tenant logo not set or user not authenticated
    $default_logo_url = TAM_PLUGIN_URL . 'assets/images/default-logo.png'; // Adjust the path as needed
    // Stop QM Timer
    do_action( 'qm/stop', 'Tenant Logo Retrieval' );
    return $default_logo_url;
}

/**
 * Get Authenticated Tenant Logo URL
 *
 * Retrieves the logo URL for the authenticated tenant.
 *
 * @param string $size Image size (default 'full').
 * @return string URL of the tenant logo or default logo.
 */
function tam_get_authenticated_tenant_background_url( $size = 'full' ) {
    // Start QM Timer for Tenant Logo Retrieval
    do_action( 'qm/start', 'Tenant Background Retrieval' );

    $auth_data = tam_validate_user_authentication();
    if ( $auth_data ) {
        $tenant_id = intval( $auth_data['tenant_id'] );
        // Get logo ID from post meta
        $logo_id = get_post_meta( $tenant_id, 'tenant_background', true );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, $size );
            // Stop QM Timer
            do_action( 'qm/stop', 'Tenant Background Retrieval' );
            return $logo_url;
        }
    }
    // Return default logo URL if tenant logo not set or user not authenticated
    $default_background_url = TAM_PLUGIN_URL . 'assets/images/default-background.png'; // Adjust the path as needed
    // Stop QM Timer
    do_action( 'qm/stop', 'Tenant Background Retrieval' );
    return $default_background_url;
}

/**
 * Display Tenant Logo in Templates
 *
 * Echoes the tenant logo image HTML.
 *
 * @param string $size  Image size (default 'full').
 * @param string $class CSS class for the image.
 * @return void
 */
function tam_display_tenant_logo( $size = 'full', $class = 'tam-tenant-logo' ) {
    // Start QM Timer for Tenant Logo Display
    do_action( 'qm/start', 'Tenant Logo Display' );

    $logo_url = tam_get_authenticated_tenant_logo_url( $size );
    if ( $logo_url ) {
        echo '<img src="' . esc_url( $logo_url ) . '" class="' . esc_attr( $class ) . '" alt="' . esc_attr__( 'Tenant Logo', 'tenant-access-manager' ) . '" loading="lazy" />';
    } else {
        // Optionally, display a default logo or nothing
        echo ''; // Or you can provide a default logo here
    }

    // Stop QM Timer for Tenant Logo Display
    do_action( 'qm/stop', 'Tenant Logo Display' );
}

/**
 * Retrieve Tenant Name Based on Tenant ID
 *
 * This function retrieves the Tenant Name from the 'tenant' custom post type based on the Tenant ID.
 *
 * @param int $tenant_id Tenant ID.
 * @return string Tenant Name.
 */
function tam_get_tenant_name( $tenant_id ) {
    // Start QM Timer for Tenant Name Retrieval
    do_action( 'qm/start', 'Tenant Name Retrieval' );

    // Ensure Tenant ID is an integer
    $tenant_id = intval( $tenant_id );

    // Retrieve the tenant post
    $tenant_post = get_post( $tenant_id );

    // Check if the post exists and is of type 'tenant'
    if ( $tenant_post && $tenant_post->post_type === 'tenant' ) {
        $tenant_name = get_the_title( $tenant_post );
        // Stop QM Timer
        do_action( 'qm/stop', 'Tenant Name Retrieval' );
        return $tenant_name;
    }

    // Return 'Unknown Tenant' if not found
    // Stop QM Timer
    do_action( 'qm/stop', 'Tenant Name Retrieval' );
    return 'Unknown Tenant';
}

?>
