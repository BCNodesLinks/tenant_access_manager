<?php
// includes/access-control.php

/**
 * Tenant Access Manager - Access Control
 *
 * This file handles access restrictions and event tracking for the Tenant Access Manager plugin.
 * It ensures that users can only access content associated with their tenant and tracks user interactions
 * using Customer.io events with a 'portal_' prefix.
 */

/**
 * Consolidated Access Control
 *
 * This function consolidates multiple access control functionalities into a single function hooked to 'template_redirect'.
 * It restricts site access for unauthenticated users, allows access to specific pages, and handles post-type specific access.
 */
function tam_consolidated_access_control() {
    do_action( 'qm/start', 'Access Control - Start' );

    // Allow access if user is logged in
    if ( is_user_logged_in() ) {
        do_action( 'qm/stop', 'Access Control - Start' );
        return;
    }

    // Allow access to admin, AJAX, and email confirmation URLs
    if ( is_admin() || strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        do_action( 'qm/stop', 'Access Control - Start' );
        return;
    }

    // Allow access to email confirmation via 'tam_confirm_email' parameter
    if ( isset( $_GET['tam_confirm_email'] ) ) {
        do_action( 'qm/stop', 'Access Control - Start' );
        return;
    }

    // Define allowed pages
    $allowed_pages = array( 'login', 'terms', 'privacy', 'cookie-policy', 'no-access' );

    // Allow access to specific pages
    if ( is_page( $allowed_pages ) ) {
        do_action( 'qm/stop', 'Access Control - Start' );
        return;
    }

    // Remove default login redirect if on login page to prevent /login/ redirecting to /wp-login.php
    if ( is_page( 'login' ) ) {
        remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
    }

    // Validate authentication cookie
    $auth_data = tam_validate_user_authentication();
    if ( $auth_data ) {
        // Tenant-specific access control for single posts
        if ( is_singular( array( 'flow', 'resource', 'rep' ) ) ) {
            do_action( 'qm/start', 'Tenant-Specific Access Control' );

            $post_type = get_post_type();
            $meta_key  = $post_type . 's'; // e.g., 'flows', 'resources', 'reps'
            $tenant_id = intval( $auth_data['tenant_id'] );
            $items     = get_post_meta( $tenant_id, $meta_key, true );

            if ( $items ) {
                $current_id = get_the_ID();
                $item_ids   = is_array( $items ) ? array_map( 'intval', $items ) : array( intval( $items ) );

                if ( ! in_array( $current_id, $item_ids, true ) ) {
                    wp_redirect( home_url( '/no-access/' ) );
                    exit;
                } else {
                    // For 'flow' posts, track flow view event
                    if ( 'flow' === $post_type ) {
                        tam_track_flow_view_event( $auth_data['email'], $current_id, get_the_title( $current_id ) );
                    }
                }
            } else {
                wp_redirect( home_url( '/no-access/' ) );
                exit;
            }

            do_action( 'qm/stop', 'Tenant-Specific Access Control' );
        }
    } else {
        // Redirect unauthenticated users to the login page
        $login_page = get_page_by_path( 'login' );
        if ( $login_page ) {
            wp_redirect( get_permalink( $login_page->ID ) );
            exit;
        } else {
            wp_die( __( 'Login page not found.', 'tenant-access-manager' ) );
        }
    }

    do_action( 'qm/stop', 'Access Control - Start' );
}
add_action( 'template_redirect', 'tam_consolidated_access_control', 5 );

/**
 * Unified Pre_Get_Posts Filtering
 *
 * This function handles filtering for all relevant post types to ensure only tenant-specific content is displayed.
 *
 * @param WP_Query $query The WP_Query instance (passed by reference).
 */
function tam_unified_pre_get_posts_filter( $query ) {
    do_action( 'qm/start', 'Unified Pre_Get_Posts Filtering' );

    static $processed_post_types = array();

    if ( is_admin() || ! $query->is_main_query() ) {
        do_action( 'qm/stop', 'Unified Pre_Get_Posts Filtering' );
        return;
    }

    // Define the post types to filter
    $target_post_types = array( 'flow', 'resource', 'rep', 'post' );

    // Check if the current query is for one of the target post types
    $is_target = false;
    $current_post_type = '';

    foreach ( $target_post_types as $type ) {
        if ( is_post_type_archive( $type ) ) {
            $is_target = true;
            $current_post_type = $type;
            break;
        }
    }

    if ( ! $is_target ) {
        do_action( 'qm/stop', 'Unified Pre_Get_Posts Filtering' );
        return;
    }

    // If already processed this post type, skip
    if ( in_array( $current_post_type, $processed_post_types, true ) ) {
        do_action( 'qm/stop', 'Unified Pre_Get_Posts Filtering' );
        return;
    }

    // Add to processed post types
    $processed_post_types[] = $current_post_type;

    $auth_data = tam_validate_user_authentication();
    if ( $auth_data ) {
        $tenant_id = intval( $auth_data['tenant_id'] );

        // Determine the current post type archive
        if ( 'flow' === $current_post_type ) {
            $meta_key = 'flows';
        } elseif ( 'resource' === $current_post_type ) {
            $meta_key = 'resources';
        } elseif ( 'rep' === $current_post_type ) {
            $meta_key = 'reps';
        } elseif ( 'post' === $current_post_type ) {
            $meta_key = 'allowed_tenants';
        } else {
            do_action( 'qm/stop', 'Unified Pre_Get_Posts Filtering' );
            return;
        }

        $items = get_post_meta( $tenant_id, $meta_key, true );

        if ( $items ) {
            $item_ids = is_array( $items ) ? array_map( 'intval', $items ) : array( intval( $items ) );

            // Special handling for 'post' type with meta_query
            if ( 'allowed_tenants' === $meta_key ) {
                $meta_query = array(
                    'relation' => 'OR',
                    // Condition 1: allowed_tenants contains the current tenant ID
                    array(
                        'key'     => 'allowed_tenants',
                        'value'   => '"' . $tenant_id . '"', // Serialized array requires quotes
                        'compare' => 'LIKE',
                    ),
                    // Condition 2: allowed_tenants is an empty string (globally visible)
                    array(
                        'key'     => 'allowed_tenants',
                        'value'   => '',
                        'compare' => '=',
                    ),
                    // Condition 3: allowed_tenants does not exist (additional safety)
                    array(
                        'key'     => 'allowed_tenants',
                        'compare' => 'NOT EXISTS',
                    ),
                );

                // Log the constructed meta_query
                error_log( 'Constructed Meta Query: ' . print_r( $meta_query, true ) );

                // Merge with existing meta queries if any
                if ( isset( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
                    error_log( 'Existing Meta Query: ' . print_r( $query->query_vars['meta_query'], true ) );
                    $meta_query = array_merge( $query->query_vars['meta_query'], $meta_query );
                    error_log( 'Merged Meta Query: ' . print_r( $meta_query, true ) );
                }

                // Set the new meta_query
                $query->set( 'meta_query', $meta_query );
            } else {
                $query->set( 'post__in', $item_ids );
                $query->set( 'orderby', 'post__in' );
            }
        } else {
            if ( 'allowed_tenants' === $meta_key ) {
                $query->set( 'meta_query', array(
                    array(
                        'key'     => 'allowed_tenants',
                        'value'   => '',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => 'allowed_tenants',
                        'compare' => 'NOT EXISTS',
                    ),
                ) );
            } else {
                $query->set( 'post__in', array( 0 ) ); // No items assigned
            }
        }
    } else {
        // Not authenticated; restrict access
        $query->set( 'post__in', array( 0 ) );
    }

    do_action( 'qm/stop', 'Unified Pre_Get_Posts Filtering' );
}
add_action( 'pre_get_posts', 'tam_unified_pre_get_posts_filter' );

/**
 * Customize Elementor Query to Filter Blog Posts by Tenant
 *
 * Filters Elementor queries for Blog Posts to show only those associated with the authenticated tenant.
 *
 * @param WP_Query $query The Elementor query instance.
 */
function tam_filter_elementor_blog_posts_by_query_id( $query ) {
    do_action( 'qm/start', 'Elementor Blog Posts Query Filtering' );

    // Initial log to confirm the function is triggered
    error_log( 'tam_filter_elementor_blog_posts_by_query_id triggered.' );

    static $is_running = false;

    if ( $is_running ) {
        error_log( 'Prevented recursion in tam_filter_elementor_blog_posts_by_query_id' );
        do_action( 'qm/stop', 'Elementor Blog Posts Query Filtering' );
        return;
    }

    $is_running = true;

    // Log all query_vars for detailed insight
    error_log( 'Query Vars: ' . print_r( $query->query_vars, true ) );

    // Ensure we're modifying the 'post' post type query
    if ( isset( $query->query_vars['post_type'] ) ) {
        error_log( 'Post Type is set: ' . print_r( $query->query_vars['post_type'], true ) );
    } else {
        error_log( 'Post Type is NOT set.' );
    }

    if ( isset( $query->query_vars['post_type'] ) && ( 'post' === $query->query_vars['post_type'] || ( is_array( $query->query_vars['post_type'] ) && in_array( 'post', $query->query_vars['post_type'] ) ) ) ) {
        error_log( 'Post Type matches "post". Proceeding with tenant filtering.' );

        $auth_data = tam_validate_user_authentication();
        error_log( 'Authentication function called.' );

        if ( $auth_data ) {
            error_log( 'Authentication successful.' );
            $tenant_id = intval( $auth_data['tenant_id'] );
            error_log( 'Authenticated Tenant ID: ' . $tenant_id );

            // Define meta query with three conditions
            $meta_query = array(
                'relation' => 'OR',
                // Condition 1: allowed_tenants contains the current tenant ID
                array(
                    'key'     => 'allowed_tenants',
                    'value'   => '"' . $tenant_id . '"', // Serialized array requires quotes
                    'compare' => 'LIKE',
                ),
                // Condition 2: allowed_tenants is an empty string (globally visible)
                array(
                    'key'     => 'allowed_tenants',
                    'value'   => '',
                    'compare' => '=',
                ),
                // Condition 3: allowed_tenants does not exist (additional safety)
                array(
                    'key'     => 'allowed_tenants',
                    'compare' => 'NOT EXISTS',
                ),
            );

            // Log the constructed meta_query
            error_log( 'Constructed Meta Query: ' . print_r( $meta_query, true ) );

            // Merge with existing meta queries if any
            if ( isset( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
                error_log( 'Existing Meta Query: ' . print_r( $query->query_vars['meta_query'], true ) );
                $meta_query = array_merge( $query->query_vars['meta_query'], $meta_query );
                error_log( 'Merged Meta Query: ' . print_r( $meta_query, true ) );
            }

            // Set the new meta_query
            $query->set( 'meta_query', $meta_query );
            error_log( 'Meta Query has been set.' );

            // Optional: Log the final WP_Query arguments
            error_log( 'Final WP_Query Args: ' . print_r( $query->query_vars, true ) );

        } else {
            error_log( 'Authentication failed. User not authenticated.' );
            // If not authenticated, hide all posts
            $query->set( 'post__in', array( 0 ) );
            error_log( 'Set post__in to array(0) to hide all posts.' );
        }
    } else {
        error_log( 'Post Type does not match "post". Skipping tenant filtering.' );
    }

    $is_running = false;

    do_action( 'qm/stop', 'Elementor Blog Posts Query Filtering' );
}
add_action( 'elementor/query/tenant_blog_posts', 'tam_filter_elementor_blog_posts_by_query_id', 10, 1 );

/**
 * Track Flow View Event
 *
 * This function tracks when a customer views a flow using Customer.io.
 *
 * @param string $email     User's email address.
 * @param int    $flow_id   ID of the flow being viewed.
 * @param string $flow_name Name of the flow being viewed.
 * @return void
 */
function tam_track_flow_view_event( $email, $flow_id, $flow_name ) {
    tam_track_customerio_event( $email, 'flow_viewed', array(
        'flow_id'   => $flow_id,
        'flow_name' => $flow_name,
    ) );
}

/**
 * Additional Access Control Hooks or Functions
 *
 * Add any other access control related functions here.
 */
?>
