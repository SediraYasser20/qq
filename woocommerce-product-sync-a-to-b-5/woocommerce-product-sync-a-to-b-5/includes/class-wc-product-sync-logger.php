<?php
/**
 * WooCommerce Product Sync Logger Class.
 *
 * Handles logging for the plugin with masking & truncation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_Logger {

    /**
     * The logger instance.
     *
     * @var WC_Logger|null
     */
    private static $logger = null;

    /**
     * Get the logger instance.
     *
     * @return WC_Logger
     */
    private static function get_logger() {
        if ( is_null( self::$logger ) ) {
            self::$logger = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
        }
        return self::$logger;
    }

    /**
     * Log a message safely.
     *
     * - Masks sensitive tokens (API keys, secrets).
     * - Truncates long messages to avoid leaking large payloads.
     *
     * @param string|array $message The message to log.
     * @param string       $level   The log level (e.g., 'info', 'error', 'debug').
     */
    public static function log( $message, $level = 'info' ) {
        $logger = self::get_logger();
        if ( ! $logger ) {
            return; // WooCommerce logger not available.
        }

        // Normalize message
        if ( is_array( $message ) || is_object( $message ) ) {
            $message = wp_json_encode( $message );
        }

        // Truncate very long logs
        $max_length = 1000;
        if ( strlen( $message ) > $max_length ) {
            $message = substr( $message, 0, $max_length ) . '... [truncated]';
        }

        // Mask secrets / long tokens (basic heuristic)
        $message = preg_replace_callback( '/[A-Za-z0-9_\-]{12,}/', function( $matches ) {
            $token = $matches[0];
            $len   = strlen( $token );
            return substr( $token, 0, 4 ) . str_repeat( '*', max(0, $len - 8)) . substr( $token, -4 );
        }, $message );

        // Log through WooCommerce
        $logger->log( $level, $message, array( 'source' => 'wc-product-sync' ) );
    }
}
