<?php
/**
 * Plugin Name: WooCommerce Product Sync (B to A)
 * Plugin URI:  https://example.com/woocommerce-product-sync-b-to-a
 * Description: Synchronizes product stock status, catalog visibility, and new product creation from Website B to Website A.
 * Version:     1.0.0
 * Author:      Manus
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-product-sync-b-to-a
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'WC_PRODUCT_SYNC_B_TO_A_VERSION', '1.0.0' );
define( 'WC_PRODUCT_SYNC_B_TO_A_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_PRODUCT_SYNC_B_TO_A_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files.
require_once WC_PRODUCT_SYNC_B_TO_A_PATH . 'includes/class-wc-product-sync-settings-b-to-a.php';
require_once WC_PRODUCT_SYNC_B_TO_A_PATH . 'includes/class-wc-product-sync-api-b-to-a.php';
require_once WC_PRODUCT_SYNC_B_TO_A_PATH . 'includes/class-wc-product-sync-logger-b-to-a.php';
require_once WC_PRODUCT_SYNC_B_TO_A_PATH . 'includes/class-wc-product-sync-hooks-b-to-a.php';
require_once WC_PRODUCT_SYNC_B_TO_A_PATH . 'includes/class-wc-product-sync-receiver.php';

/**
 * The main plugin class for B to A sync.
 */
class WC_Product_Sync_B_To_A {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Initialize the plugin.
     */
    public function init() {
        // Load text domain for translation.
        load_plugin_textdomain( 'wc-product-sync-b-to-a', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Initialize classes.
        new WC_Product_Sync_Settings_B_To_A();
        new WC_Product_Sync_API_B_To_A();
        new WC_Product_Sync_Logger_B_To_A();
        new WC_Product_Sync_Hooks_B_To_A();
        new WC_Product_Sync_Receiver_B();
    }
}

// Instantiate the plugin.
new WC_Product_Sync_B_To_A();


