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
     * Constructor.
     */
    public function __construct() {
        // Full-sync on any product save/update and specific stock/price changes
        add_action( 'save_post_product', [ $this, '__wps_sync_full_on_save' ], 20, 3 );
        add_action( 'woocommerce_product_set_stock', [ $this, '__wps_sync_full_from_stock_obj' ], 20, 1 );
        add_action( 'woocommerce_product_set_stock_status', [ $this, '__wps_sync_full_from_status' ], 20, 2 );
        add_action( 'woocommerce_product_set_regular_price', [ $this, '__wps_sync_full_from_price' ], 20, 2 );
        add_action( 'woocommerce_product_set_sale_price', [ $this, '__wps_sync_full_from_price' ], 20, 2 );

        // NEW: hook into meta updates to catch all price changes reliably
        add_action( 'updated_postmeta', [ $this, '__wps_sync_from_price_meta' ], 10, 4 );
        add_action( 'added_postmeta', [ $this, '__wps_sync_from_price_meta' ], 10, 4 );

        $this->api = new WC_Product_Sync_API();

        // Hook into product save action to detect changes.
        add_action( 'woocommerce_update_product', array( $this, 'sync_product_data' ), 10, 1 );
        add_action( 'woocommerce_new_product', array( $this, 'sync_new_product' ), 10, 1 );
        add_action( 'woocommerce_product_set_stock', array( $this, 'sync_product_stock' ), 10, 1 );
        add_action( 'woocommerce_product_set_stock_status', array( $this, 'sync_product_stock_status' ), 10, 3 );
    }

    /**
     * Synchronize product data (stock status and catalog visibility) when a product is updated.
     *
     * @param int $product_id The product ID.
     */
    public function sync_product_data( $product_id ) {
        if ( $this->is_product_excluded( $product_id ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Product ID %d excluded from sync due to category/tag rules.', $product_id ), 'info' );
            return;
        }
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return;
        }

        $data = array(
            'stock_status'    => $product->get_stock_status(),
            'catalog_visibility' => $product->get_catalog_visibility(),
        );

        WC_Product_Sync_Logger::log( sprintf( 'Attempting to sync product ID %d data: %s', $product_id, json_encode( $data ) ), 'info' );

        $response = $this->api->post( 'product-sync/create-with-id', $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Failed to sync product ID %d data: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger::log( sprintf( 'Successfully synced product ID %d data.', $product_id ), 'info' );
        }
    }

    /**
     * Synchronize new product creation.
     *
     * @param int $product_id The new product ID.
     */
    public function sync_new_product( $product_id ) {
        if ( $this->is_product_excluded( $product_id ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Product ID %d excluded from sync due to category/tag rules.', $product_id ), 'info' );
            return;
        }
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return;
        }

        // Prepare product data for Website B, excluding images.
        $data = $this->__wps_build_full_payload( $product );

        WC_Product_Sync_Logger::log( sprintf( 'Attempting to create new product on Website B. Original ID: %d', $product_id ), 'info' );

        $response = $this->api->post( 'product-sync/create-with-id', $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Failed to create new product on Website B (Original ID %d): %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger::log( sprintf( 'Successfully created new product on Website B (Original ID %d).', $product_id ), 'info' );
        }
    }

    /**
     * Synchronize product stock quantity when stock is set.
     *
     * @param WC_Product $product The product object.
     */
    public function sync_product_stock( $product ) {
        if ( ! $product ) {
            return;
        }

        $product_id = $product->get_id();
        if ( $this->is_product_excluded( $product_id ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Product ID %d excluded from sync due to category/tag rules.', $product_id ), 'info' );
            return;
        }
        $data = array(
            'stock_quantity' => $product->get_stock_quantity(),
        );

        WC_Product_Sync_Logger::log( sprintf( 'Attempting to sync product ID %d stock quantity: %d', $product_id, $product->get_stock_quantity() ), 'info' );

        $response = $this->api->post( 'product-sync/create-with-id', $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Failed to sync product ID %d stock quantity: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger::log( sprintf( 'Successfully synced product ID %d stock quantity.', $product_id ), 'info' );
        }
    }

    /**
     * Synchronize product stock status when stock status is set.
     *
     * @param int    $product_id   The product ID.
     * @param string $stock_status The new stock status.
     * @param WC_Product $product      The product object.
     */
    public function sync_product_stock_status( $product_id, $stock_status, $product ) {
        if ( $this->is_product_excluded( $product_id ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Product ID %d excluded from sync due to category/tag rules.', $product_id ), 'info' );
            return;
        }
        if ( ! $product ) {
            return;
        }

        $data = array(
            'stock_status' => $stock_status,
        );

        WC_Product_Sync_Logger::log( sprintf( 'Attempting to sync product ID %d stock status: %s', $product_id, $stock_status ), 'info' );

        $response = $this->api->post( 'product-sync/create-with-id', $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Failed to sync product ID %d stock status: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger::log( sprintf( 'Successfully synced product ID %d stock status.', $product_id ), 'info' );
        }
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
     * Centralized full sync to Website B
     */
    public function __wps_sync_full_product_by_id( $product_id ) {
        if ( $this->is_product_excluded( $product_id ) ) {
            WC_Product_Sync_Logger::log( sprintf( 'Product ID %d excluded from sync due to category/tag rules.', $product_id ), 'info' );
            return;
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }
        $data = $this->__wps_build_full_payload( $product );

        // Always POST to our custom endpoint that creates/updates by same ID
        if ( isset( $this->api ) && method_exists( $this->api, 'post' ) ) {
            $this->api->post( 'product-sync/create-with-id', $data );
        } else {
            // Fallback: direct REST call if the plugin exposes a different client
            if ( method_exists( $this, 'post_to_remote' ) ) {
                $this->post_to_remote( 'product-sync/create-with-id', $data );
            }
        }
    }

    // Hook wrappers
    public function __wps_sync_full_on_save( $post_id, $post, $update ) {
        if ( 'product' !== get_post_type( $post_id ) ) return;
        // Avoid infinite loops / autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        $this->__wps_sync_full_product_by_id( $post_id );
    }

    public function __wps_sync_full_from_stock_obj( $stock ) {
        if ( ! is_object( $stock ) || ! method_exists( $stock, 'get_product_id' ) ) return;
        $this->__wps_sync_full_product_by_id( $stock->get_product_id() );
    }

    public function __wps_sync_full_from_status( $product_id, $status ) {
        $this->__wps_sync_full_product_by_id( $product_id );
    }

    public function __wps_sync_full_from_price( $value, $product ) {
        if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
            $this->__wps_sync_full_product_by_id( $product->get_id() );
        }
        return $value;
    }

    /**
     * NEW: Sync price changes when meta is updated (catching all admin/import flows).
     */
    public function __wps_sync_from_price_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( ! in_array( $meta_key, array( '_regular_price', '_sale_price' ), true ) ) {
            return;
        }

        if ( 'product' !== get_post_type( $object_id ) ) {
            return;
        }

        WC_Product_Sync_Logger::log(
            sprintf( 'Price meta "%s" changed for product ID %d. Triggering full product sync.', $meta_key, $object_id ),
            'info'
        );

        $this->__wps_sync_full_product_by_id( $object_id );
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
