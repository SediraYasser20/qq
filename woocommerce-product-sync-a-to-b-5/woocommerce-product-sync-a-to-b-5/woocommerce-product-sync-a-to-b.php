<?php
/**
 * Plugin Name: WooCommerce Product Sync
 * Plugin URI:  https://example.com/woocommerce-product-sync
 * Description: Synchronizes product stock status, catalog visibility, and new product creation between two WooCommerce sites.
 * Version:     1.0.0
 * Author:      Manus
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-product-sync
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'WC_PRODUCT_SYNC_VERSION', '1.0.0' );
define( 'WC_PRODUCT_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_PRODUCT_SYNC_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files.
require_once WC_PRODUCT_SYNC_PATH . 'includes/class-wc-product-sync-settings.php';
require_once WC_PRODUCT_SYNC_PATH . 'includes/class-wc-product-sync-api.php';
require_once WC_PRODUCT_SYNC_PATH . 'includes/class-wc-product-sync-logger.php';
require_once WC_PRODUCT_SYNC_PATH . 'includes/class-wc-product-sync-hooks.php';

/**
 * The main plugin class.
 */
class WC_Product_Sync {

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
        load_plugin_textdomain( 'wc-product-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Initialize classes.
        new WC_Product_Sync_Settings();
        new WC_Product_Sync_API();
        new WC_Product_Sync_Logger();
        new WC_Product_Sync_Hooks();
    }
}

// Instantiate the plugin.
new WC_Product_Sync();


