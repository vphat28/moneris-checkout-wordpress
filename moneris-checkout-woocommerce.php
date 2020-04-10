<?php
/*
Plugin Name: Moneris Checkout WooCommerce Integration
Plugin URI: https://moneris.com
Description: Moneris Checkout integration for WooCommerce
*/

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MONERIS_WC_PLUGIN_FILE' ) ) {
    define( 'MONERIS_WC_PLUGIN_FILE', __FILE__ );
}

/**
 * Activate the plugin.
 */
function moneris_checkout_woocommerce_activate( ) {
    $page = get_post( get_option( 'woocommerce_checkout_page_id' ) );

    if ( isset( $page->ID ) && !empty( $page->ID ) ) {

        $page->post_content = '[moneris_checkout_woocommerce]';
        wp_update_post( $page, true );
    }
}

register_activation_hook( __FILE__, 'moneris_checkout_woocommerce_activate' );


function moneris_checkout_woocommerce_add_shortcode( ) {

}
