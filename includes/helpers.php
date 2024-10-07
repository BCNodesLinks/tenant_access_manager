<?php
// includes/helpers.php

/**
 * Get Tenant by Domain
 *
 * Retrieves the tenant ID associated with the given domain.
 *
 * @param string $domain The domain to search for.
 * @return int|false Returns the tenant post ID if found, false otherwise.
 */
function tam_get_tenant_by_domain( $domain ) {
    error_log( 'Looking for tenant with domain: ' . $domain );
    global $wpdb;
    $domain = strtolower( $domain );
    $query = $wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = %s AND LOWER(pm.meta_value) = %s AND p.post_type = %s LIMIT 1",
        '_tam_tenant_domains',
        $domain,
        'tenant'
    );
    $tenant_id = $wpdb->get_var( $query );
    if ( $tenant_id ) {
        error_log( 'Tenant found. ID: ' . $tenant_id );
        return $tenant_id;
    }
    error_log( 'No tenant found matching domain: ' . $domain );
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
    $auth_data = tam_validate_user_authentication();
    if ( $auth_data ) {
        $tenant_id = intval( $auth_data['tenant_id'] );
        // Get logo ID from post meta
        $logo_id = get_post_meta( $tenant_id, 'tenant_logo', true );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, $size );
            return $logo_url;
        }
    }
    // Return default logo URL if tenant logo not set or user not authenticated
    $default_logo_url = TAM_PLUGIN_URL . 'assets/images/default-logo.png'; // Adjust the path as needed
    return $default_logo_url;
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
    $logo_url = tam_get_authenticated_tenant_logo_url( $size );
    if ( $logo_url ) {
        echo '<img src="' . esc_url( $logo_url ) . '" class="' . esc_attr( $class ) . '" alt="' . esc_attr__( 'Tenant Logo', 'tenant-access-manager' ) . '" />';
    } else {
        // Optionally, display a default logo or nothing
        echo ''; // Or you can provide a default logo here
    }
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
    // Ensure Tenant ID is an integer
    $tenant_id = intval( $tenant_id );

    // Retrieve the tenant post
    $tenant_post = get_post( $tenant_id );

    // Check if the post exists and is of type 'tenant'
    if ( $tenant_post && $tenant_post->post_type === 'tenant' ) {
        return get_the_title( $tenant_post );
    }

    // Return 'Unknown Tenant' if not found
    return 'Unknown Tenant';
}
