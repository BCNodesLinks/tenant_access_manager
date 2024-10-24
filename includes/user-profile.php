<?php
// includes/user-profile.php

/**
 * Add Tenant Selection Field to User Profile
 *
 * @param WP_User $user The user object.
 */
function tam_show_tenant_selection_field( $user ) {
    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }

    // Get all tenants
    $args = array(
        'post_type'      => 'tenant',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    );
    $tenants = get_posts( $args );

    // Get the current tenant ID assigned to the user
    $tenant_id = get_user_meta( $user->ID, 'tenant_id', true );

    ?>
    <h3><?php _e( 'Tenant Association', 'tenant-access-manager' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="tenant_id"><?php _e( 'Select Tenant', 'tenant-access-manager' ); ?></label></th>
            <td>
                <select name="tenant_id" id="tenant_id">
                    <option value=""><?php _e( 'None', 'tenant-access-manager' ); ?></option>
                    <?php
                    if ( $tenants ) {
                        foreach ( $tenants as $tenant ) {
                            echo '<option value="' . esc_attr( $tenant->ID ) . '" ' . selected( $tenant_id, $tenant->ID, false ) . '>' . esc_html( $tenant->post_title ) . '</option>';
                        }
                    }
                    ?>
                </select>
                <p class="description"><?php _e( 'Associate this user with a tenant.', 'tenant-access-manager' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'tam_show_tenant_selection_field' );
add_action( 'edit_user_profile', 'tam_show_tenant_selection_field' );

/**
 * Save Tenant Selection Field
 *
 * @param int $user_id The user ID.
 */
function tam_save_tenant_selection_field( $user_id ) {
    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }

    if ( isset( $_POST['tenant_id'] ) ) {
        $tenant_id = intval( $_POST['tenant_id'] );
        if ( $tenant_id ) {
            update_user_meta( $user_id, 'tenant_id', $tenant_id );
        } else {
            delete_user_meta( $user_id, 'tenant_id' );
        }
    }
}
add_action( 'personal_options_update', 'tam_save_tenant_selection_field' );
add_action( 'edit_user_profile_update', 'tam_save_tenant_selection_field' );
