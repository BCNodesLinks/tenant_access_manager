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
  
    // Check if the current user is an administrator
    if ( current_user_can( 'administrator' ) ) {
        return; // Allow admins unrestricted access
    }

    // Get authenticated user tenant data
    $auth_data = tam_get_current_user_tenant_data();

    if ( $auth_data ) {
        // User is authenticated
        // Tenant-specific access control for single posts
        if ( is_singular( array( 'flow', 'resource', 'rep' ) ) ) {
            $post_type = get_post_type();
            $meta_key  = $post_type . 's'; // e.g., 'flows', 'resources', 'reps'
            $tenant_id = intval( $auth_data['tenant_id'] );
            $current_id = get_the_ID();
            $flow_name = get_the_title( $current_id );
            $items = get_post_meta( $tenant_id, $meta_key, true );
           if ( $items ) {
                $item_ids = is_array( $items ) ? array_map( 'intval', $items ) : array( intval( $items ) );

                
                if ( ! in_array( $current_id, $item_ids, true ) ) {
                    wp_redirect( home_url( '/no-access/' ) );
                    exit;
                } else {
                    // For 'flow' posts, track flow view event
                    if ( 'flow' === $post_type ) {
                        tam_track_flow_view_event( $current_id, $flow_name );
                    }
                }
            } else {
                wp_redirect( home_url( '/no-access/' ) );
                exit;
            }
        } else {
        }
    } else {
        // User is not authenticated

        // Allow access to admin, AJAX, and email confirmation URLs
        if ( is_admin() ) {
            return;
        }

        if ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {
            return;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        // Allow access to email confirmation via 'tam_confirm_email' parameter
        if ( isset( $_GET['tam_confirm_email'] ) ) {
            return;
        }

        // Define allowed pages
        $allowed_pages = array( 'login', 'terms', 'privacy', 'cookie-policy', 'no-access' );

        // Allow access to specific pages
        if ( is_page( $allowed_pages ) ) {
            return;
        }

        // Remove default login redirect if on login page to prevent /login/ redirecting to /wp-login.php
        if ( is_page( 'login' ) ) {
            remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
        }

        // If not logged in and none of the above conditions are met, redirect to login
        $login_page = get_page_by_path( 'login' );
        if ( $login_page ) {
            wp_redirect( get_permalink( $login_page->ID ) );
            exit;
        } else {
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

    $auth_data = tam_get_current_user_tenant_data();
    if ( $auth_data ) {
        $tenant_id = intval( $auth_data['tenant_id'] );
        
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
        
        if ( $items ) {
            $item_ids = is_array( $items ) ? array_map( 'intval', $items ) : array( intval( $items ) );
            
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
                    $meta_query = array_merge( $query->query_vars['meta_query'], $meta_query );
                }

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
    
    static $is_running = false;

    if ( $is_running ) {
        // Prevent recursion
        return;
    }

    $is_running = true;

    // Ensure we're modifying the 'post' post type query
    if ( isset( $query->query_vars['post_type'] ) && ( 'post' === $query->query_vars['post_type'] || ( is_array( $query->query_vars['post_type'] ) && in_array( 'post', $query->query_vars['post_type'] ) ) ) ) {
        $auth_data = tam_get_current_user_tenant_data();

        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            
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
                $meta_query = array_merge( $query->query_vars['meta_query'], $meta_query );
            }

            // Set the new meta_query
            $query->set( 'meta_query', $meta_query );
        } else {
            // If not authenticated, hide all posts
            $query->set( 'post__in', array( 0 ) );
        }
    } else {
        
    }

    $is_running = false;
}
add_action( 'elementor/query/tenant_blog_posts', 'tam_filter_elementor_blog_posts_by_query_id', 10, 1 );

/**
 * Track Flow View Event
 *
 * This function tracks when a customer views a flow using Customer.io.
 *
 * @param int    $flow_id   ID of the flow being viewed.
 * @param string $flow_name Name of the flow being viewed.
 * @return void
 */
function tam_track_flow_view_event( $flow_id, $flow_name ) {
    $auth_data = tam_get_current_user_tenant_data();
    if ( $auth_data ) {
        $email = $auth_data['email'];

        tam_track_customerio_event( $email, 'flow_viewed', array(
            'flow_id'   => $flow_id,
            'flow_name' => $flow_name,
        ) );

    } else {
       
    }
}

/**
 * Additional Access Control Hooks or Functions
 *
 * Add any other access control related functions here.
 */

/**
 * Customize Elementor Query to Filter Flows by Tenant
 *
 * Filters Elementor queries for Flows to show only those associated with the authenticated tenant.
 *
 * @param WP_Query $query The Elementor query instance.
 */
function tam_filter_elementor_flows_by_query_id( $query ) {
    static $is_running = false;

    if ( $is_running ) {
        return;
    }

    $is_running = true;

    // Ensure we're modifying the 'flow' post type query
    if ( isset( $query->query_vars['post_type'] ) ) {
        $post_type = $query->query_vars['post_type'];

        if ( ( is_array( $post_type ) && in_array( 'flow', $post_type ) ) || $post_type === 'flow' ) {
            $auth_data = tam_get_current_user_tenant_data();
            if ( $auth_data ) {
                $tenant_id = intval( $auth_data['tenant_id'] );
                $flows = get_post_meta( $tenant_id, 'flows', true );

                if ( $flows ) {
                    $flow_ids = is_array( $flows ) ? array_map( 'intval', $flows ) : array( intval( $flows ) );
                    $query->set( 'post__in', $flow_ids );
                    $query->set( 'orderby', 'post__in' ); // Preserve order
                } else {
                    $query->set( 'post__in', array( 0 ) ); // No flows
                }
            } else {
                $query->set( 'post__in', array( 0 ) ); // Not authenticated
            }
        }
    }

    $is_running = false;
}
add_action( 'elementor/query/tenant_flows', 'tam_filter_elementor_flows_by_query_id' );

/**
 * Customize Elementor Query to Filter Resources by Tenant
 *
 * Filters Elementor queries for Resources to show only those associated with the authenticated tenant.
 *
 * @param WP_Query $query The Elementor query instance.
 */
function tam_filter_elementor_resources_by_query_id( $query ) {
    static $is_running = false;

    if ( $is_running ) {
        return;
    }

    $is_running = true;

    // Ensure we're modifying the 'resource' post type query
    if ( isset( $query->query_vars['post_type'] ) ) {
        $post_type = $query->query_vars['post_type'];

        if ( ( is_array( $post_type ) && in_array( 'resource', $post_type ) ) || $post_type === 'resource' ) {
            $auth_data = tam_get_current_user_tenant_data();
            if ( $auth_data ) {
                $tenant_id = intval( $auth_data['tenant_id'] );
                $resources = get_post_meta( $tenant_id, 'resources', true );

                if ( $resources ) {
                    $resource_ids = is_array( $resources ) ? array_map( 'intval', $resources ) : array( intval( $resources ) );
                    $query->set( 'post__in', $resource_ids );
                    $query->set( 'orderby', 'post__in' ); // Preserve order
                } else {
                    $query->set( 'post__in', array( 0 ) ); // No resources
                }
            } else {
                $query->set( 'post__in', array( 0 ) ); // Not authenticated
            }
        }
    }

    $is_running = false;
}
add_action( 'elementor/query/tenant_resources', 'tam_filter_elementor_resources_by_query_id' );

/**
 * Customize Elementor Query to Filter Reps by Tenant
 *
 * Filters Elementor queries for Reps to show only those associated with the authenticated tenant.
 *
 * @param WP_Query $query The Elementor query instance.
 */
function tam_filter_elementor_reps_by_query_id( $query ) {
    static $is_running = false;

    if ( $is_running ) {
       return;
    }

    $is_running = true;

    // Ensure we're modifying the 'rep' post type query
    if ( isset( $query->query_vars['post_type'] ) ) {
        $post_type = $query->query_vars['post_type'];

        if ( ( is_array( $post_type ) && in_array( 'rep', $post_type ) ) || $post_type === 'rep' ) {
            $auth_data = tam_get_current_user_tenant_data();
            if ( $auth_data ) {
                $tenant_id = intval( $auth_data['tenant_id'] );
                $reps = get_post_meta( $tenant_id, 'reps', true );

                if ( $reps ) {
                    $rep_ids = is_array( $reps ) ? array_map( 'intval', $reps ) : array( intval( $reps ) );
                    $query->set( 'post__in', $rep_ids );
                    $query->set( 'orderby', 'post__in' ); // Preserve order
                } else {
                    $query->set( 'post__in', array( 0 ) ); // No reps
                }
            } else {
                $query->set( 'post__in', array( 0 ) ); // Not authenticated
            }
        }
    }

    $is_running = false;
}
add_action( 'elementor/query/tenant_reps', 'tam_filter_elementor_reps_by_query_id' );
?>
