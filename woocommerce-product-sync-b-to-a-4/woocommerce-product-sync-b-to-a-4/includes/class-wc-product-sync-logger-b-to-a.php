<?php
/**
 * WooCommerce Product Sync B to A Logger Class.
 *
 * Handles logging for the B to A plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_Logger_B_To_A {

    /**
     * The logger instance.
     *
     * @var WC_Logger
     */
    private static $logger = null;

    /**
     * Get the logger instance.
     *
     * @return WC_Logger
     */
    private static function get_logger() {
        if ( is_null( self::$logger ) ) {
            self::$logger = wc_get_logger();
        }
        return self::$logger;
    }

    /**
     * Log a message.
     *
     * @param string $message The message to log.
     * @param string $level   The log level (e.g., 'info', 'error', 'debug').
     */
    public static function log( $message, $level = 'info' ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return; // WooCommerce logger not available.
        }
        self::get_logger()->log( $level, $message, array( 'source' => 'wc-product-sync-b-to-a' ) );
    }
}


