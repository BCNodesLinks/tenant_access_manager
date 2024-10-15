<?php
// includes/meta-boxes.php

/**
 * Add Tenant Access Type Meta Box to Tenant CPT
 */
function tam_add_tenant_access_type_meta_box() {
    add_meta_box(
        'tam_tenant_access_type',
        __( 'Tenant Access Type', 'tenant-access-manager' ),
        'tam_tenant_access_type_callback',
        'tenant',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'tam_add_tenant_access_type_meta_box' );

/**
 * Tenant Access Type Meta Box Callback
 *
 * @param WP_Post $post The post object.
 */
function tam_tenant_access_type_callback( $post ) {
    wp_nonce_field( 'tam_tenant_access_type_nonce_action', 'tam_tenant_access_type_nonce' );
    $access_type = get_post_meta( $post->ID, '_tam_tenant_access_type', true );
    if ( ! $access_type ) {
        $access_type = 'domain'; // Default to domain-based access
    }
    echo '<label>' . __( 'Select Access Type for this Tenant:', 'tenant-access-manager' ) . '</label><br/>';
    echo '<select name="tam_tenant_access_type">';
    echo '<option value="domain"' . selected( $access_type, 'domain', false ) . '>' . __( 'Domain-based Access', 'tenant-access-manager' ) . '</option>';
    echo '<option value="email"' . selected( $access_type, 'email', false ) . '>' . __( 'Email-based Access', 'tenant-access-manager' ) . '</option>';
    echo '</select>';
}

/**
 * Save Tenant Access Type Meta Box Data
 *
 * @param int $post_id The ID of the post being saved.
 */
function tam_save_tenant_access_type_meta_box_data( $post_id ) {
    // Check if nonce is set
    if ( ! isset( $_POST['tam_tenant_access_type_nonce'] ) ) {
        return;
    }

    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['tam_tenant_access_type_nonce'], 'tam_tenant_access_type_nonce_action' ) ) {
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

    // Sanitize and save access type
    if ( isset( $_POST['tam_tenant_access_type'] ) ) {
        $access_type = sanitize_text_field( $_POST['tam_tenant_access_type'] );
        if ( in_array( $access_type, array( 'domain', 'email' ), true ) ) {
            update_post_meta( $post_id, '_tam_tenant_access_type', $access_type );
        }
    }
}
add_action( 'save_post_tenant', 'tam_save_tenant_access_type_meta_box_data' );

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

/**
 * Add Tenant Emails Meta Box to Tenant CPT
 */
function tam_add_tenant_emails_meta_box() {
    add_meta_box(
        'tam_tenant_emails',
        __( 'Tenant Emails', 'tenant-access-manager' ),
        'tam_tenant_emails_callback',
        'tenant',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'tam_add_tenant_emails_meta_box' );

/**
 * Tenant Emails Meta Box Callback
 *
 * @param WP_Post $post The post object.
 */
function tam_tenant_emails_callback( $post ) {
    wp_nonce_field( 'tam_tenant_emails_nonce_action', 'tam_tenant_emails_nonce' );
    $emails = get_post_meta( $post->ID, '_tam_tenant_emails', false ); // Retrieve all emails
    if ( ! is_array( $emails ) ) {
        $emails = array();
    }
    echo '<label>' . __( 'Enter emails associated with this tenant (one per line):', 'tenant-access-manager' ) . '</label><br/>';
    echo '<textarea name="tam_tenant_emails" rows="5" cols="50">' . esc_textarea( implode( "\n", $emails ) ) . '</textarea>';
}

/**
 * Save Tenant Emails Meta Box Data
 *
 * @param int $post_id The ID of the post being saved.
 */
function tam_save_tenant_emails_meta_box_data( $post_id ) {
    // Check if nonce is set
    if ( ! isset( $_POST['tam_tenant_emails_nonce'] ) ) {
        return;
    }

    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['tam_tenant_emails_nonce'], 'tam_tenant_emails_nonce_action' ) ) {
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

    // Sanitize and save emails
    if ( isset( $_POST['tam_tenant_emails'] ) ) {
        $emails_input = sanitize_textarea_field( $_POST['tam_tenant_emails'] );
        $emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( "\n", $emails_input ) ) ) );

        // Delete existing emails
        delete_post_meta( $post_id, '_tam_tenant_emails' );

        // Add each email as a separate meta entry
        foreach ( $emails as $email ) {
            add_post_meta( $post_id, '_tam_tenant_emails', $email );
        }
    }
}
add_action( 'save_post_tenant', 'tam_save_tenant_emails_meta_box_data' );
