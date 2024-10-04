<?php
// includes/cpt.php

/**
 * Register Custom Post Types
 */
function tam_register_custom_post_types() {
    // Register Tenant CPT
    $labels_tenant = array(
        'name'               => _x( 'Tenants', 'Post Type General Name', 'tenant-access-manager' ),
        'singular_name'      => _x( 'Tenant', 'Post Type Singular Name', 'tenant-access-manager' ),
        'menu_name'          => __( 'Tenants', 'tenant-access-manager' ),
        'name_admin_bar'     => __( 'Tenant', 'tenant-access-manager' ),
        'add_new_item'       => __( 'Add New Tenant', 'tenant-access-manager' ),
        'new_item'           => __( 'New Tenant', 'tenant-access-manager' ),
        'edit_item'          => __( 'Edit Tenant', 'tenant-access-manager' ),
        'view_item'          => __( 'View Tenant', 'tenant-access-manager' ),
        'all_items'          => __( 'All Tenants', 'tenant-access-manager' ),
        'search_items'       => __( 'Search Tenants', 'tenant-access-manager' ),
    );
    $args_tenant = array(
        'label'              => __( 'Tenant', 'tenant-access-manager' ),
        'labels'             => $labels_tenant,
        'supports'           => array( 'title' ),
        'public'             => false,
        'show_ui'            => true,
        'menu_icon'          => 'dashicons-groups',
        'capability_type'    => 'post',
        'hierarchical'       => false,
    );
    register_post_type( 'tenant', $args_tenant );

    // Register Flow CPT
    $labels_flow = array(
        'name'               => _x( 'Flows', 'Post Type General Name', 'tenant-access-manager' ),
        'singular_name'      => _x( 'Flow', 'Post Type Singular Name', 'tenant-access-manager' ),
        'menu_name'          => __( 'Flows', 'tenant-access-manager' ),
        'name_admin_bar'     => __( 'Flow', 'tenant-access-manager' ),
        'add_new_item'       => __( 'Add New Flow', 'tenant-access-manager' ),
        'new_item'           => __( 'New Flow', 'tenant-access-manager' ),
        'edit_item'          => __( 'Edit Flow', 'tenant-access-manager' ),
        'view_item'          => __( 'View Flow', 'tenant-access-manager' ),
        'all_items'          => __( 'All Flows', 'tenant-access-manager' ),
        'search_items'       => __( 'Search Flows', 'tenant-access-manager' ),
    );
    $args_flow = array(
        'label'              => __( 'Flow', 'tenant-access-manager' ),
        'labels'             => $labels_flow,
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'public'             => true,
        'show_ui'            => true,
        'menu_icon'          => 'dashicons-admin-page',
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'flows' ),
    );
    register_post_type( 'flow', $args_flow );

    // Register Resource CPT
    $labels_resource = array(
        'name'               => _x( 'Resources', 'Post Type General Name', 'tenant-access-manager' ),
        'singular_name'      => _x( 'Resource', 'Post Type Singular Name', 'tenant-access-manager' ),
        'menu_name'          => __( 'Resources', 'tenant-access-manager' ),
        'name_admin_bar'     => __( 'Resource', 'tenant-access-manager' ),
        'add_new_item'       => __( 'Add New Resource', 'tenant-access-manager' ),
        'new_item'           => __( 'New Resource', 'tenant-access-manager' ),
        'edit_item'          => __( 'Edit Resource', 'tenant-access-manager' ),
        'view_item'          => __( 'View Resource', 'tenant-access-manager' ),
        'all_items'          => __( 'All Resources', 'tenant-access-manager' ),
        'search_items'       => __( 'Search Resources', 'tenant-access-manager' ),
    );
    $args_resource = array(
        'label'              => __( 'Resource', 'tenant-access-manager' ),
        'labels'             => $labels_resource,
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'public'             => true,
        'show_ui'            => true,
        'menu_icon'          => 'dashicons-admin-page',
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'resources' ),
    );
    register_post_type( 'resource', $args_resource );

    // Register Rep CPT
    $labels_rep = array(
        'name'               => _x( 'Reps', 'Post Type General Name', 'tenant-access-manager' ),
        'singular_name'      => _x( 'Rep', 'Post Type Singular Name', 'tenant-access-manager' ),
        'menu_name'          => __( 'Reps', 'tenant-access-manager' ),
        'name_admin_bar'     => __( 'Rep', 'tenant-access-manager' ),
        'add_new_item'       => __( 'Add New Rep', 'tenant-access-manager' ),
        'new_item'           => __( 'New Rep', 'tenant-access-manager' ),
        'edit_item'          => __( 'Edit Rep', 'tenant-access-manager' ),
        'view_item'          => __( 'View Rep', 'tenant-access-manager' ),
        'all_items'          => __( 'All Reps', 'tenant-access-manager' ),
        'search_items'       => __( 'Search Reps', 'tenant-access-manager' ),
    );
    $args_rep = array(
        'label'              => __( 'Rep', 'tenant-access-manager' ),
        'labels'             => $labels_rep,
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'public'             => true,
        'show_ui'            => true,
        'menu_icon'          => 'dashicons-admin-users',
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'reps' ),
    );
    register_post_type( 'rep', $args_rep );
}
add_action( 'init', 'tam_register_custom_post_types', 0 );
