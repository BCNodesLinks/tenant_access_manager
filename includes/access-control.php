<?php
// includes/access-control.php

/**
 * Tenant Access Manager - Access Control
 *
 * This file handles access restrictions and event tracking for the Tenant Access Manager plugin.
 * It ensures that users can only access content associated with their tenant and tracks user interactions
 * using Customer.io events with a 'portal_' prefix.
 */

// Initial Log to Confirm File is Loaded
error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] access-control.php file loaded." );

/**
 * Consolidated Access Control
 *
 * This function consolidates multiple access control functionalities into a single function hooked to 'template_redirect'.
 * It restricts site access for unauthenticated users, allows access to specific pages, and handles post-type specific access.
 */
function tam_consolidated_access_control() {
    // Log Entry to Confirm Function is Called
    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_consolidated_access_control function called." );

    // Log the current URL being accessed
    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Current URL: " . esc_url( home_url( add_query_arg( null, null ) ) ) );

    // Check if the current user is an administrator
    if ( current_user_can( 'administrator' ) ) {
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Current user is an administrator. Granting unrestricted access." );
        return; // Allow admins unrestricted access
    }

    // Initialize authentication flag
    $is_authenticated = false;
    $auth_data = false;

    // Check if user is logged in via WordPress
    if ( is_user_logged_in() ) {
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] User is logged in via WordPress." );
        $is_authenticated = true;
        // Optionally, you can retrieve user data here if needed
    } else {
        // Check if user is authenticated via 'tam_user_token' cookie
        $auth_data = tam_validate_user_authentication();
        if ( $auth_data ) {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] User is authenticated via 'tam_user_token' cookie." );
            $is_authenticated = true;
        }
    }

    if ( $is_authenticated ) {
        // User is authenticated either via WordPress or via 'tam_user_token'
        // Proceed with tenant-specific access control and event tracking

        if ( $auth_data ) {
            // User authenticated via 'tam_user_token' (custom authentication)
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] User authenticated via 'tam_user_token': Email: {$auth_data['email']}, Tenant ID: {$auth_data['tenant_id']}, Tenant Name: {$auth_data['tenant_name']}" );
        }

        // Tenant-specific access control for single posts
        if ( is_singular( array( 'flow', 'resource', 'rep' ) ) ) {
            $post_type = get_post_type();
            $meta_key  = $post_type . 's'; // e.g., 'flows', 'resources', 'reps'
            $tenant_id = $auth_data ? intval( $auth_data['tenant_id'] ) : 0;
            $current_id = get_the_ID();
            $flow_name = get_the_title( $current_id );

            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Current Post Type: {$post_type}, Meta Key: {$meta_key}, Current Post ID: {$current_id}, Flow Name: {$flow_name}" );

            $items = get_post_meta( $tenant_id, $meta_key, true );
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Retrieved Post Meta for Tenant ID {$tenant_id} and Meta Key '{$meta_key}': " . print_r( $items, true ) );

            if ( $items ) {
                $item_ids = is_array( $items ) ? array_map( 'intval', $items ) : array( intval( $items ) );

                error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Allowed Post IDs for Tenant ID {$tenant_id} and Meta Key '{$meta_key}': " . implode( ', ', $item_ids ) );

                if ( ! in_array( $current_id, $item_ids, true ) ) {
                    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access Denied: Tenant ID {$tenant_id} does not have access to Post ID {$current_id}." );
                    wp_redirect( home_url( '/no-access/' ) );
                    exit;
                } else {
                    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access Granted: Tenant ID {$tenant_id} has access to Post ID {$current_id}." );
                    // For 'flow' posts, track flow view event
                    if ( 'flow' === $post_type ) {
                        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Post Type is 'flow'. Preparing to track 'portal_flow_viewed' event." );
                        tam_track_flow_view_event( $auth_data['email'], $current_id, $flow_name );
                        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] 'portal_flow_viewed' event tracking function called." );
                    }
                }
            } else {
                error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access Denied: No items found for Tenant ID {$tenant_id} and Meta Key '{$meta_key}'." );
                wp_redirect( home_url( '/no-access/' ) );
                exit;
            }
        } else {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Current query is not for a singular 'flow', 'resource', or 'rep' post type." );
        }
    } else {
        // User is not authenticated via WordPress or 'tam_user_token'

        // Allow access to admin, AJAX, and email confirmation URLs
        if ( is_admin() ) {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access allowed: is_admin() is true." );
            return;
        }

        if ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access allowed: Request URI contains 'wp-login.php'." );
            return;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access allowed: DOING_AJAX is true." );
            return;
        }

        // Allow access to email confirmation via 'tam_confirm_email' parameter
        if ( isset( $_GET['tam_confirm_email'] ) ) {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access allowed: 'tam_confirm_email' GET parameter is set." );
            return;
        }

        // Define allowed pages
        $allowed_pages = array( 'login', 'terms', 'privacy', 'cookie-policy', 'no-access' );

        // Allow access to specific pages
        if ( is_page( $allowed_pages ) ) {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Access allowed: Current page is one of the allowed pages: " . implode( ', ', $allowed_pages ) );
            return;
        }

        // Remove default login redirect if on login page to prevent /login/ redirecting to /wp-login.php
        if ( is_page( 'login' ) ) {
            remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Removed default login redirect for 'login' page." );
        }

        // If not logged in and none of the above conditions are met, redirect to login
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] User not authenticated. Redirecting to login page." );
        $login_page = get_page_by_path( 'login' );
        if ( $login_page ) {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Redirecting to login page: " . get_permalink( $login_page->ID ) );
            wp_redirect( get_permalink( $login_page->ID ) );
            exit;
        } else {
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Login page not found. Displaying error message." );
            echo '<p>' . __( 'Login page not found.', 'tenant-access-manager' ) . '</p>';
            exit;
        }
    }
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
    static $processed_post_types = array();

    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    // Define the post types to filter
    $target_post_types = array( 'flow', 'resource', 'rep', 'post' );

    // Identify if the current query is for one of the target post types
    $current_post_type = '';

    foreach ( $target_post_types as $type ) {
        if ( is_post_type_archive( $type ) ) {
            $current_post_type = $type;
            break;
        }
    }

    if ( empty( $current_post_type ) ) {
        return;
    }

    // Prevent re-processing the same post type within a single request
    if ( in_array( $current_post_type, $processed_post_types, true ) ) {
        return;
    }

    // Mark the current post type as processed
    $processed_post_types[] = $current_post_type;

    $auth_data = tam_validate_user_authentication();
    if ( $auth_data ) {
        $tenant_id = intval( $auth_data['tenant_id'] );
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: Authenticated Tenant ID: {$tenant_id}" );

        // Determine the meta_key based on post type
        switch ( $current_post_type ) {
            case 'flow':
                $meta_key = 'flows';
                break;
            case 'resource':
                $meta_key = 'resources';
                break;
            case 'rep':
                $meta_key = 'reps';
                break;
            case 'post':
                $meta_key = 'allowed_tenants';
                break;
            default:
                return;
        }

        $items = get_post_meta( $tenant_id, $meta_key, true );
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: Retrieved Post Meta for Tenant ID {$tenant_id} and Meta Key '{$meta_key}': " . print_r( $items, true ) );

        if ( $items ) {
            $item_ids = is_array( $items ) ? array_map( 'intval', $items ) : array( intval( $items ) );
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: Allowed Post IDs for Tenant ID {$tenant_id} and Meta Key '{$meta_key}': " . implode( ', ', $item_ids ) );

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

                // Merge with existing meta queries if present
                if ( isset( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
                    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: Merging existing meta queries with new conditions for Meta Key '{$meta_key}'." );
                    $meta_query = array_merge( $query->query_vars['meta_query'], $meta_query );
                }

                $query->set( 'meta_query', $meta_query );
                error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: Meta query set for Meta Key '{$meta_key}'." );
            } else {
                $query->set( 'post__in', $item_ids );
                $query->set( 'orderby', 'post__in' );
                error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: Post filtering set for Meta Key '{$meta_key}' with Post IDs: " . implode( ', ', $item_ids ) );
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
                error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: Meta query set for Meta Key '{$meta_key}' with empty or non-existing values." );
            } else {
                $query->set( 'post__in', array( 0 ) ); // No items assigned
                error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: No allowed items found for Meta Key '{$meta_key}'. Setting 'post__in' to [0]." );
            }
        }
    } else {
        // Not authenticated via 'tam_user_token'; restrict access
        $query->set( 'post__in', array( 0 ) );
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_unified_pre_get_posts_filter: User not authenticated. Restricting access to all posts by setting 'post__in' to [0]." );
    }
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
    // Initial log to confirm the function is triggered
    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Elementor query filter triggered." );

    static $is_running = false;

    if ( $is_running ) {
        // Prevent recursion
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] Elementor query filter recursion prevented." );
        return;
    }

    $is_running = true;

    // Ensure we're modifying the 'post' post type query
    if ( isset( $query->query_vars['post_type'] ) && ( 'post' === $query->query_vars['post_type'] || ( is_array( $query->query_vars['post_type'] ) && in_array( 'post', $query->query_vars['post_type'] ) ) ) ) {
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_filter_elementor_blog_posts_by_query_id: Modifying Elementor query for post type 'post'." );
        $auth_data = tam_validate_user_authentication();

        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_filter_elementor_blog_posts_by_query_id: Authenticated Tenant ID: {$tenant_id}" );

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

            // Merge with existing meta queries if any
            if ( isset( $query->query_vars['meta_query'] ) && is_array( $query->query_vars['meta_query'] ) ) {
                error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_filter_elementor_blog_posts_by_query_id: Merging existing meta queries with new conditions for Elementor posts." );
                $meta_query = array_merge( $query->query_vars['meta_query'], $meta_query );
            }

            // Set the new meta_query
            $query->set( 'meta_query', $meta_query );
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_filter_elementor_blog_posts_by_query_id: Meta query set for Elementor posts." );
        } else {
            // If not authenticated via 'tam_user_token', hide all posts
            $query->set( 'post__in', array( 0 ) );
            error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_filter_elementor_blog_posts_by_query_id: User not authenticated. Restricting access to all posts." );
        }
    } else {
        error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_filter_elementor_blog_posts_by_query_id: Elementor query filter not applied. Post type is not 'post'." );
    }

    $is_running = false;
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
    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_track_flow_view_event: Initiating tracking for 'portal_flow_viewed' event. Email: {$email}, Flow ID: {$flow_id}, Flow Name: {$flow_name}" );
    tam_track_customerio_event( $email, 'flow_viewed', array(
        'flow_id'   => $flow_id,
        'flow_name' => $flow_name,
    ) );
    error_log( "[TAM_DEBUG " . current_time( 'mysql' ) . "] tam_track_flow_view_event: 'portal_flow_viewed' event tracking initiated." );
}

/**
 * Additional Access Control Hooks or Functions
 *
 * Add any other access control related functions here.
 */
?>
