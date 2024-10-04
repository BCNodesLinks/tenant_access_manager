<?php
// includes/meta-boxes.php

/**
 * Add Tenant Domains Meta Box to Tenant CPT
 */
function tam_add_tenant_domains_meta_box() {
    add_meta_box(
        'tam_tenant_domains',
        __( 'Tenant Domains', 'tenant-access-manager' ),
        'tam_tenant_domains_callback',
        'tenant',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'tam_add_tenant_domains_meta_box' );

/**
 * Tenant Domains Meta Box Callback
 *
 * @param WP_Post $post The post object.
 */
function tam_tenant_domains_callback( $post ) {
    wp_nonce_field( 'tam_tenant_domains_nonce_action', 'tam_tenant_domains_nonce' );
    $domains = get_post_meta( $post->ID, '_tam_tenant_domains', false ); // Retrieve all domains
    if ( ! is_array( $domains ) ) {
        $domains = array();
    }
    echo '<label>' . __( 'Enter domains associated with this tenant (one per line):', 'tenant-access-manager' ) . '</label><br/>';
    echo '<textarea name="tam_tenant_domains" rows="5" cols="50">' . esc_textarea( implode( "\n", $domains ) ) . '</textarea>';
}

/**
 * Save Tenant Domains Meta Box Data
 *
 * @param int $post_id The ID of the post being saved.
 */
function tam_save_tenant_domains_meta_box_data( $post_id ) {
    // Check if nonce is set
    if ( ! isset( $_POST['tam_tenant_domains_nonce'] ) ) {
        return;
    }

    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['tam_tenant_domains_nonce'], 'tam_tenant_domains_nonce_action' ) ) {
        return;
    }

    // Check autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check user permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Sanitize and save domains
    if ( isset( $_POST['tam_tenant_domains'] ) ) {
        $domains_input = sanitize_textarea_field( $_POST['tam_tenant_domains'] );
        $domains = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( "\n", $domains_input ) ) ) );

        // Delete existing domains
        delete_post_meta( $post_id, '_tam_tenant_domains' );

        // Add each domain as a separate meta entry
        foreach ( $domains as $domain ) {
            add_post_meta( $post_id, '_tam_tenant_domains', $domain );
        }
    }
}
add_action( 'save_post_tenant', 'tam_save_tenant_domains_meta_box_data' );
