<?php
/*
Plugin Name: Moneris Checkout WooCommerce Integration
Plugin URI: https://moneris.com
Description: Moneris Checkout integration for WooCommerce
*/
use Carbon_Fields\Container;
use Carbon_Fields\Field;
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MONERIS_WC_PLUGIN_DIR' ) ) {
    define( 'MONERIS_WC_PLUGIN_DIR', __DIR__ );
}

require_once MONERIS_WC_PLUGIN_DIR . '/vendor/autoload.php';

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

function moneris_checkout_woocommerce_add_shortcode_callback( $atts ) {
    $ticket = new \Moneris\Checkout\Ticket();
    $template = new \Moneris\Checkout\Template();
    $ticketData = $ticket->getTicket();
    $woocommerce = WC();
    ob_start( );
    echo $template->display_template( 'checkout.phtml', $ticketData );
    $content = ob_get_contents();
    ob_end_clean();
    return '<div>' . $content . '</div>';
}

function moneris_checkout_frontend_script() {
    wp_enqueue_script( 'moneris-checkout-wc-script', plugin_dir_url( __FILE__ ) . 'js/moneris-checkout.js', array( 'jquery' ), '1.0.0', true );
}

add_action( 'wp_enqueue_scripts', 'moneris_checkout_frontend_script' );

function moneris_checkout_woocommerce_init( ) {
    $bootstrap = new \Moneris\Checkout\Bootstrap();
    $bootstrap->init();
}

function moneris_checkout_woocommerce_dbi_load_carbon_fields() {
    \Carbon_Fields\Carbon_Fields::boot();
}
add_action( 'after_setup_theme', 'moneris_checkout_woocommerce_dbi_load_carbon_fields' );

add_action( 'init', 'moneris_checkout_woocommerce_init' );


function moneris_checkout_woocommerce_dbi_add_plugin_settings_page() {
    Container::make( 'theme_options', __( 'Moneris Checkout WooCommerce Setting' ) )
        ->set_page_parent( 'options-general.php' )
        ->add_fields( array(
            Field::make( 'text', 'moneris_store_id', 'Store ID' )
                ->set_attribute( maxLength, 32 ),
            Field::make( 'text', 'moneris_api_key', 'API key' )
                ->set_attribute( maxLength, 200 ),
            Field::make( 'text', 'moneris_checkout_checkout_id', 'Moneris Checkout ID' )
                ->set_attribute( maxLength, 20 ),
            Field::make( 'checkbox', 'moneris_checkout_test_mode', __( 'Test Mode' ) )
                ->set_option_value( 'yes' ),
        ) );
}
add_action( 'carbon_fields_register_fields', 'moneris_checkout_woocommerce_dbi_add_plugin_settings_page' );
