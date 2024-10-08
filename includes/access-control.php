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
 * Restrict Site Access
 *
 * This function restricts access to the entire site for unauthenticated users,
 * except for specific pages like login, terms, privacy, and cookie policy.
 */
function tam_restrict_site_access() {
    if ( is_user_logged_in() ) {
        return;
    }

    // Allow access to admin, AJAX, and email confirmation URLs
    if ( is_admin() || strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        return;
    }

    if ( isset( $_GET['tam_confirm_email'] ) ) {
        return;
    }

    // Validate authentication cookie
    $auth_data = tam_validate_user_authentication();
    if ( $auth_data ) {
        return;
    }

    // Define the login page slug
    $login_page = get_page_by_path( 'login' );

    // Allow access to the login page if it exists
    if ( $login_page && is_page( $login_page->ID ) ) {
        return;
    }

    // Allow access to specific informational pages
    $allowed_pages = array( 'terms', 'privacy', 'cookie-policy' );
    if ( is_page( $allowed_pages ) ) {
        return;
    }

    // Redirect unauthenticated users to the login page
    if ( $login_page ) {
        wp_redirect( get_permalink( $login_page->ID ) );
        exit;
    } else {
        wp_die( __( 'Login page not found.', 'tenant-access-manager' ) );
    }
}
add_action( 'template_redirect', 'tam_restrict_site_access', 5 );

/**
 * Remove WordPress Redirect from /login/ to /wp-login.php
 */
function tam_remove_login_redirect() {
    if ( is_page( 'login' ) ) {
        remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
    }
}
add_action( 'template_redirect', 'tam_remove_login_redirect', 0 );

/**
 * Restrict Access to Flow Posts
 *
 * This function restricts access to single Flow posts based on tenant authentication
 * and tracks a Customer.io event when a flow is viewed.
 */
function tam_restrict_flow_access() {
    if ( is_singular( 'flow' ) ) {
        $auth_data = tam_validate_user_authentication();
        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            $flows = get_post_meta( $tenant_id, 'flows', true );

            if ( $flows ) {
                $current_flow_id = get_the_ID();
                $flow_ids = is_array( $flows ) ? array_map( 'intval', $flows ) : array( intval( $flows ) );

                if ( in_array( $current_flow_id, $flow_ids ) ) {
                    // Access granted, track the view event with flow name
                    $flow_name = get_the_title( $current_flow_id );
                    tam_track_flow_view_event( $auth_data['email'], $current_flow_id, $flow_name );
                    return; // Access granted
                } else {
                    wp_redirect( home_url( '/no-access/' ) );
                    exit;
                }
            } else {
                wp_redirect( home_url( '/no-access/' ) );
                exit;
            }
        } else {
            wp_redirect( home_url( '/no-access/' ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'tam_restrict_flow_access', 9 );

/**
 * Restrict Access to Resource Posts
 *
 * This function restricts access to single Resource posts based on tenant authentication.
 */
function tam_restrict_resource_access() {
    if ( is_singular( 'resource' ) ) {
        $auth_data = tam_validate_user_authentication();
        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            $resources = get_post_meta( $tenant_id, 'resources', true );

            if ( $resources ) {
                $current_resource_id = get_the_ID();
                $resource_ids = is_array( $resources ) ? array_map( 'intval', $resources ) : array( intval( $resources ) );

                if ( in_array( $current_resource_id, $resource_ids ) ) {
                    return; // Access granted
                } else {
                    wp_redirect( home_url( '/no-access/' ) );
                    exit;
                }
            } else {
                wp_redirect( home_url( '/no-access/' ) );
                exit;
            }
        } else {
            wp_redirect( home_url( '/no-access/' ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'tam_restrict_resource_access', 9 );

/**
 * Restrict Access to Rep Posts
 *
 * This function restricts access to single Rep posts based on tenant authentication.
 */
function tam_restrict_rep_access() {
    if ( is_singular( 'rep' ) ) {
        $auth_data = tam_validate_user_authentication();
        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            $reps = get_post_meta( $tenant_id, 'reps', true );

            if ( $reps ) {
                $current_rep_id = get_the_ID();
                $rep_ids = is_array( $reps ) ? array_map( 'intval', $reps ) : array( intval( $reps ) );

                if ( in_array( $current_rep_id, $rep_ids ) ) {
                    return; // Access granted
                } else {
                    wp_redirect( home_url( '/no-access/' ) );
                    exit;
                }
            } else {
                wp_redirect( home_url( '/no-access/' ) );
                exit;
            }
        } else {
            wp_redirect( home_url( '/no-access/' ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'tam_restrict_rep_access', 9 );

/**
 * Filter Flows Archive by Tenant
 *
 * This function filters the Flows archive page to show only flows associated with the authenticated tenant.
 *
 * @param WP_Query $query The WP_Query instance (passed by reference).
 */
function tam_filter_flows_by_tenant( $query ) {
    if ( ! is_admin() && $query->is_main_query() && is_post_type_archive( 'flow' ) ) {
        $auth_data = tam_validate_user_authentication();
        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            $flows = get_post_meta( $tenant_id, 'flows', true );

            if ( $flows ) {
                $flow_ids = is_array( $flows ) ? array_map( 'intval', $flows ) : array( intval( $flows ) );
                $query->set( 'post__in', $flow_ids );
                $query->set( 'orderby', 'post__in' );
            } else {
                $query->set( 'post__in', array( 0 ) ); // No flows assigned
            }
        } else {
            $query->set( 'post__in', array( 0 ) ); // Not authenticated
        }
    }
}
add_action( 'pre_get_posts', 'tam_filter_flows_by_tenant' );

/**
 * Filter Resources Archive by Tenant
 *
 * This function filters the Resources archive page to show only resources associated with the authenticated tenant.
 *
 * @param WP_Query $query The WP_Query instance (passed by reference).
 */
function tam_filter_resources_by_tenant( $query ) {
    if ( ! is_admin() && $query->is_main_query() && is_post_type_archive( 'resource' ) ) {
        $auth_data = tam_validate_user_authentication();
        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            $resources = get_post_meta( $tenant_id, 'resources', true );

            if ( $resources ) {
                $resource_ids = is_array( $resources ) ? array_map( 'intval', $resources ) : array( intval( $resources ) );
                $query->set( 'post__in', $resource_ids );
                $query->set( 'orderby', 'post__in' );
            } else {
                $query->set( 'post__in', array( 0 ) ); // No resources assigned
            }
        } else {
            $query->set( 'post__in', array( 0 ) ); // Not authenticated
        }
    }
}
add_action( 'pre_get_posts', 'tam_filter_resources_by_tenant' );

/**
 * Filter Reps Archive by Tenant
 *
 * This function filters the Reps archive page to show only reps associated with the authenticated tenant.
 *
 * @param WP_Query $query The WP_Query instance (passed by reference).
 */
function tam_filter_reps_by_tenant( $query ) {
    if ( ! is_admin() && $query->is_main_query() && is_post_type_archive( 'rep' ) ) {
        $auth_data = tam_validate_user_authentication();
        if ( $auth_data ) {
            $tenant_id = intval( $auth_data['tenant_id'] );
            $reps = get_post_meta( $tenant_id, 'reps', true );

            if ( $reps ) {
                $rep_ids = is_array( $reps ) ? array_map( 'intval', $reps ) : array( intval( $reps ) );
                $query->set( 'post__in', $rep_ids );
                $query->set( 'orderby', 'post__in' );
            } else {
                $query->set( 'post__in', array( 0 ) ); // No reps assigned
            }
        } else {
            $query->set( 'post__in', array( 0 ) ); // Not authenticated
        }
    }
}
add_action( 'pre_get_posts', 'tam_filter_reps_by_tenant' );

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
        error_log( 'Prevented recursion in tam_filter_elementor_flows_by_query_id' );
        return;
    }

    $is_running = true;

    // Ensure we're modifying the 'flow' post type query
    if ( isset( $query->query_vars['post_type'] ) ) {
        $post_type = $query->query_vars['post_type'];

        if ( ( is_array( $post_type ) && in_array( 'flow', $post_type ) ) || $post_type === 'flow' ) {
            $auth_data = tam_validate_user_authentication();
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
        error_log( 'Prevented recursion in tam_filter_elementor_resources_by_query_id' );
        return;
    }

    $is_running = true;

    // Ensure we're modifying the 'resource' post type query
    if ( isset( $query->query_vars['post_type'] ) ) {
        $post_type = $query->query_vars['post_type'];

        if ( ( is_array( $post_type ) && in_array( 'resource', $post_type ) ) || $post_type === 'resource' ) {
            $auth_data = tam_validate_user_authentication();
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
        error_log( 'Prevented recursion in tam_filter_elementor_reps_by_query_id' );
        return;
    }

    $is_running = true;

    // Ensure we're modifying the 'rep' post type query
    if ( isset( $query->query_vars['post_type'] ) ) {
        $post_type = $query->query_vars['post_type'];

        if ( ( is_array( $post_type ) && in_array( 'rep', $post_type ) ) || $post_type === 'rep' ) {
            $auth_data = tam_validate_user_authentication();
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
 * Allow Access to No-Access Page
 *
 * Ensures that the no-access page is accessible without authentication.
 */
function tam_allow_no_access_page() {
    if ( is_page( 'no-access' ) ) {
        return;
    }
}
add_action( 'template_redirect', 'tam_allow_no_access_page', 100 );

/**
 * Allow Access to Login Page
 *
 * Ensures that the login page is accessible without authentication.
 */
function tam_allow_login_page() {
    if ( is_page( 'login' ) ) {
        return;
    }
}
add_action( 'template_redirect', 'tam_allow_login_page', 100 );

/**
 * Additional Access Control Hooks or Functions
 *
 * Add any other access control related functions here.
 */

/**
 * Customize Elementor Query to Filter Blog Posts by Tenant
 *
 * Filters Elementor queries for Blog Posts to show only those associated with the authenticated tenant.
 *
 * @param WP_Query $query The Elementor query instance.
 */
function tam_filter_elementor_blog_posts_by_query_id( $query ) {

    // Initial log to confirm the function is triggered
    error_log( 'tam_filter_elementor_blog_posts_by_query_id triggered.' );

    static $is_running = false;

    if ( $is_running ) {
        error_log( 'Prevented recursion in tam_filter_elementor_blog_posts_by_query_id' );
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
}
add_action( 'elementor/query/tenant_blog_posts', 'tam_filter_elementor_blog_posts_by_query_id', 10, 1 );
