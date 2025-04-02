<?php
/*
Plugin Name: Woop Customer Roles
Description: Allows you to grant user roles to customers who bought certain products.
Version: 1.0
Author: Iszuddin Ismail
*/

// Define excluded roles as a constant
define( 'WOOP_CUSTOMER_ROLES_EXCLUDED', array( 'administrator', 'shop_manager', 'editor' ) );

// Add a new tab to product data tabs
function woop_customer_roles_product_tab( $tabs ) {
    if ( current_user_can( 'manage_woocommerce' ) ) { // Restrict to Administrator and Shop Manager
        $tabs['woop-customer-roles'] = array(
            'label'     => __( 'Grant Roles', 'woop-customer-roles' ),
            'target'    => 'woop-customer-roles',
            'class'     => array( 'show_if_simple', 'show_if_variable' ),
            'icon'      => 'dashicons-admin-users',
        );
    }
    return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'woop_customer_roles_product_tab' );

// Content of your custom tab
function woop_customer_roles_tab_content() {
    global $post;
    $user_roles = get_post_meta( $post->ID, 'woop_customer_roles_user_roles', true );
    $user_roles = ! empty( $user_roles ) ? (array) $user_roles : array();

    $all_roles = wp_roles()->roles;

    // Filter out excluded roles
    foreach ( WOOP_CUSTOMER_ROLES_EXCLUDED as $role_key ) {
        unset( $all_roles[ $role_key ] );
    }

    $role_names = array_column($all_roles, 'name');
    array_multisort($role_names, SORT_ASC, $all_roles);
    ?>
    <div id="woop-customer-roles" class="panel woocommerce_options_panel">
        <div class="options_group">
            <p class="description"><?php _e( 'Select roles to be granted when this product is purchased.', 'woop-customer-roles' ); ?></p>
            <p class="form-field">
                <label for="woop_customer_roles_user_roles"><?php _e( 'User Roles To Be Granted:', 'woop-customer-roles' ); ?></label>
                <select name="woop_customer_roles_user_roles[]" id="woop_customer_roles_user_roles" multiple="multiple" style="width: 100%;">
                    <?php
                    foreach ( $all_roles as $role_key => $role_value ) {
                        $selected = in_array( $role_key, $user_roles ) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr( $role_key ) . '" ' . $selected . '>' . esc_html( $role_value['name'] ) . '</option>';
                    }
                    ?>
                </select>
            </p>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_product_data_panels', 'woop_customer_roles_tab_content' );

// Save the custom tab data
function woop_customer_roles_save_product_tab_data( $post_id ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { // Restrict to Administrator and Shop Manager
        return;
    }

    $user_roles = isset( $_POST['woop_customer_roles_user_roles'] ) ? $_POST['woop_customer_roles_user_roles'] : array();
    $user_roles = array_map( 'sanitize_text_field', $user_roles );

    // Exclude restricted roles
    $user_roles = array_diff( $user_roles, WOOP_CUSTOMER_ROLES_EXCLUDED );

    update_post_meta( $post_id, 'woop_customer_roles_user_roles', $user_roles );
}
add_action( 'woocommerce_process_product_meta', 'woop_customer_roles_save_product_tab_data' );


// Enqueue Select2 and custom script
function woop_customer_roles_enqueue_scripts() {
    wp_enqueue_script( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array( 'jquery' ), '4.0.13', true );
    wp_enqueue_style( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13' );
    wp_enqueue_script( 'woop-customer-roles-script', plugin_dir_url( __FILE__ ) . 'js/app.js', array( 'jquery', 'select2' ), '1.0', true );
}
add_action( 'admin_enqueue_scripts', 'woop_customer_roles_enqueue_scripts' );


// Grant user roles to customers upon product purchase
function woop_customer_roles_grant_user_roles_on_purchase( $order_id ) {
    $order = wc_get_order( $order_id );
    $user_id = $order->get_customer_id(); // Assuming the order is associated with a registered user
    $user = new WP_User( $user_id );

    // Iterate through order items
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $product_user_roles = get_post_meta( $product_id, 'woop_customer_roles_user_roles', true );

        // If product has associated user roles, grant them to the customer
        if ( ! empty( $product_user_roles ) ) {

            // Grant user roles to the customer
            foreach ( $product_user_roles as $role ) {

                if(in_array($role, WOOP_CUSTOMER_ROLES_EXCLUDED)) {
                    continue; // Skip excluded roles
                }

                $user->add_role( $role );
            }
        }
    }
}
add_action( 'woocommerce_order_status_completed', 'woop_customer_roles_grant_user_roles_on_purchase' );
