<?php
/**
 * WooCommerce Product Sync Hooks Class.
 *
 * Handles the synchronization logic using WooCommerce hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_Hooks {

    /**
     * The API instance.
     *
     * @var WC_Product_Sync_API
     */
    private $api;

    /**
     * Holds product IDs to be synced at the end of the request.
     *
     * @var array
     */
    private static $sync_queue = [];

    /**
     * Constructor.
     */
    public function __construct() {
        // All hooks now feed into a queue to be processed on shutdown.
        // This avoids race conditions and ensures all data is saved before syncing.
        add_action( 'save_post_product', [ $this, 'queue_sync_from_post_id' ], 20, 1 );
        add_action( 'woocommerce_new_product', [ $this, 'queue_sync_from_product_id' ], 10, 1 );
        add_action( 'woocommerce_update_product', [ $this, 'queue_sync_from_product_id' ], 10, 1 );

        add_action( 'woocommerce_product_set_stock', [ $this, 'queue_sync_from_product_obj' ], 20, 1 );
        add_action( 'woocommerce_product_set_stock_status', [ $this, 'queue_sync_from_product_id' ], 20, 2 );

        add_action( 'woocommerce_product_set_regular_price', [ $this, 'queue_sync_from_product_obj' ], 20, 2 );
        add_action( 'woocommerce_product_set_sale_price', [ $this, 'queue_sync_from_product_obj' ], 20, 2 );

        add_action( 'updated_postmeta', [ $this, 'queue_sync_from_meta' ], 10, 4 );
        add_action( 'added_postmeta', [ $this, 'queue_sync_from_meta' ], 10, 4 );

        // Hook for term changes, which happens after post save.
        add_action( 'set_object_terms', [ $this, 'queue_sync_from_post_id' ], 10, 1 );

        $this->api = new WC_Product_Sync_API();
    }

    /**
     * Adds a product ID to the sync queue and registers the shutdown action.
     *
     * @param int $product_id The product ID to queue.
     */
    private function queue_product_for_sync( $product_id ) {
        if ( ! is_numeric( $product_id ) || $product_id <= 0 ) {
            return;
        }

        // Add product to the queue.
        self::$sync_queue[] = $product_id;

        // If the shutdown action hasn't been registered yet, register it.
        if ( ! has_action( 'shutdown', [ $this, 'process_sync_queue' ] ) ) {
            add_action( 'shutdown', [ $this, 'process_sync_queue' ] );
        }
    }

    /**
     * Processes the sync queue on shutdown.
     */
    public function process_sync_queue() {
        if ( empty( self::$sync_queue ) ) {
            return;
        }

        // Get unique product IDs to avoid redundant syncs.
        $unique_ids = array_unique( self::$sync_queue );

        foreach ( $unique_ids as $product_id ) {
            $this->do_sync( $product_id );
        }
    }

    /**
     * Performs the actual product synchronization.
     *
     * @param int $product_id The product ID to sync.
     */
    private function do_sync( $product_id ) {
        if ( $this->is_product_excluded( $product_id ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Product ID %d excluded from shutdown sync due to category/tag rules.', $product_id ), 'info' );
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        $data = $this->__wps_build_full_payload( $product );

        WC_Product_Sync_Logger::log( sprintf( 'Shutdown Sync: Attempting to sync product ID %d. Payload: %s', $product_id, json_encode( $data ) ), 'info' );

        $response = $this->api->post( 'product-sync/create-with-id', $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Shutdown Sync: Failed to sync product ID %d. Error: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger::log( sprintf( 'Shutdown Sync: Successfully synced product ID %d.', $product_id ), 'info' );
        }
    }

    // --- Hook Wrapper Methods ---

    public function queue_sync_from_post_id( $post_id ) {
        if ( 'product' === get_post_type( $post_id ) ) {
            $this->queue_product_for_sync( $post_id );
        }
    }

    public function queue_sync_from_product_id( $product_id ) {
        $this->queue_product_for_sync( $product_id );
    }

    public function queue_sync_from_product_obj( $product ) {
        if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
            $this->queue_product_for_sync( $product->get_id() );
        }
    }

    public function queue_sync_from_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( ! in_array( $meta_key, array( '_regular_price', '_sale_price' ), true ) ) {
            return;
        }
        $this->queue_sync_from_post_id( $object_id );
    }


    /**
     * Build full product payload from a product object.
     */
    protected function __wps_build_full_payload( $product ) {
        if ( ! $product ) {
            return array();
        }

        // Try existing internal helper(s) if present.
        if ( method_exists( $this, 'prepare_product_data' ) ) {
            $data = $this->prepare_product_data( $product );
        } elseif ( method_exists( $this, 'get_product_data_payload' ) ) {
            $data = $this->get_product_data_payload( $product );
        } elseif ( method_exists( $this, 'build_product_payload' ) ) {
            $data = $this->build_product_payload( $product );
        } else {
            // Fallback minimal, still "full enough" for your needs.
            $data = array(
                'id'                 => $product->get_id(),
                'name'               => $product->get_name(),
                'slug'               => $product->get_slug(),
                'status'             => $product->get_status(),
                'catalog_visibility' => $product->get_catalog_visibility(),
                'regular_price'      => $product->get_regular_price(),
                'sale_price'         => $product->get_sale_price(),
                'manage_stock'       => $product->get_manage_stock(),
                'stock_quantity'     => $product->get_stock_quantity(),
                'stock_status'       => $product->get_stock_status(),
                'short_description'  => $product->get_short_description(),
                'description'        => $product->get_description(),
                'sku'                => $product->get_sku(),
            );
            // Categories: send slugs so B can map.
            $category_ids = $product->get_category_ids();
            $data['categories'] = array();
            if ( ! empty( $category_ids ) ) {
                foreach ( $category_ids as $cat_id ) {
                    $term = get_term( $cat_id, 'product_cat' );
                    if ( $term && ! is_wp_error( $term ) ) {
                        $data['categories'][] = array( 'slug' => $term->slug, 'name' => $term->name );
                    }
                }
            }
        }

        // Enforce 'id' presence
        if ( empty( $data['id'] ) ) {
            $data['id'] = $product->get_id();
        }

        return $data;
    }

    /**
     * Check if a product should be excluded from synchronization.
     *
     * @param int $product_id The product ID.
     * @return bool True if the product should be excluded, false otherwise.
     */
    private function is_product_excluded( $product_id ) {
        // Exclude products in category 'composant-pc' AND tag ID 3876
        if ( has_term( 'composant-pc', 'product_cat', $product_id ) && has_term( 3876, 'product_tag', $product_id ) ) {
            return true;
        }
        return false;
    }
}
