<?php
/**
 * WooCommerce Product Sync Receiver (Website B).
 *
 * Adds a custom REST endpoint under wc/v3 so we can create a product
 * with a specific ID (matching Website A's ID).
 *
 * Route: /wp-json/wc/v3/product-sync/create-with-id  (POST)
 *
 * Auth: Uses WooCommerce REST API authentication (consumer key/secret).
 * Permission: Requires a user with capability `manage_woocommerce` or `edit_products`.
 *
 * Notes:
 * - We DO NOT handle images here (per requirement).
 * - If the product already exists on B (same ID), we update basic fields.
 * - If it doesn't exist, we create a product row with that exact ID, then set fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_Receiver_B {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            'wc/v3',
            '/product-sync/create-with-id',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'create_with_id' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                    'args'                => array(),
                ),
            )
        );
    }

    public function permission_check( $request ) {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_products' );
    }

    public function create_with_id( WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $desired_id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        if ( ! $desired_id ) {
            return new WP_Error( 'missing_id', __( 'Product ID is required.', 'wc-product-sync-b-to-a' ), array( 'status' => 400 ) );
        }

        // Basic validation
        $existing = get_post( $desired_id );
        if ( $existing && 'product' !== $existing->post_type ) {
            return new WP_Error( 'id_conflict', __( 'The requested ID already exists for a different post type.', 'wc-product-sync-b-to-a' ), array( 'status' => 409 ) );
        }

        // Try to create if not exists.
        if ( ! $existing ) {
            // First, try via wp_insert_post with import_id (preferred).
            $postarr = array(
                'post_type'   => 'product',
                'post_status' => ! empty( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft',
                'post_title'  => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
                'post_name'   => isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '',
                'import_id'   => $desired_id,
            );

            $inserted_id = wp_insert_post( $postarr, true );

            if ( is_wp_error( $inserted_id ) ) {
                if ( class_exists( 'WC_Product_Sync_Logger_B_To_A' ) ) {
                    WC_Product_Sync_Logger_B_To_A::log( sprintf( 'wp_insert_post failed for desired ID %d: %s', $desired_id, $inserted_id->get_error_message() ), 'error' );
                }
                return new WP_Error(
                    'insert_failed',
                    sprintf( __( 'Failed to insert product with ID %d. Reason: %s', 'wc-product-sync-b-to-a' ), $desired_id, $inserted_id->get_error_message() ),
                    array( 'status' => 500 )
                );
            }

            // wp_insert_post worked, but we MUST verify it used the desired ID.
            if ( (int) $inserted_id !== (int) $desired_id ) {
                // This is a critical failure. WordPress created a post with a different ID.
                // We must delete the incorrect post and return an error.
                wp_delete_post( $inserted_id, true );

                if ( class_exists( 'WC_Product_Sync_Logger_B_To_A' ) ) {
                    WC_Product_Sync_Logger_B_To_A::log( sprintf( 'CRITICAL: wp_insert_post created post with ID %d instead of desired ID %d. The incorrect post has been deleted.', $inserted_id, $desired_id ), 'critical' );
                }

                return new WP_Error(
                    'id_mismatch',
                    sprintf( __( 'Failed to create product with specified ID %d. WordPress assigned a different ID (%d). This may be due to a conflicting post or a database issue. The operation was aborted.', 'wc-product-sync-b-to-a' ), $desired_id, $inserted_id ),
                    array( 'status' => 500 )
                );
            }
        }

        // We have a product post with the exact ID; now set WooCommerce data.
        $product_type = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'simple';
        wp_set_object_terms( $desired_id, $product_type, 'product_type' );
        $product = wc_get_product( $desired_id );

        // Failsafe: if product doesn't exist, create an instance.
        if ( ! $product ) {
            $product_class = WC_Product_Factory::get_product_classname( $desired_id, $product_type );
            if ( ! class_exists( $product_class ) ) {
                $product_class = 'WC_Product_Simple';
            }
            $product = new $product_class( $desired_id );
        }

        // Set basic props
        if ( isset( $data['name'] ) ) $product->set_name( wp_kses_post( $data['name'] ) );
        if ( isset( $data['status'] ) ) $product->set_status( sanitize_key( $data['status'] ) );
        if ( isset( $data['short_description'] ) ) $product->set_short_description( wp_kses_post( $data['short_description'] ) );
        if ( isset( $data['description'] ) ) $product->set_description( wp_kses_post( $data['description'] ) );
        if ( isset( $data['sku'] ) ) $product->set_sku( wc_clean( $data['sku'] ) );

        $settings = get_option( 'wc_product_sync_b_to_a_settings' );
        $price_sync_mode = isset( $settings['price_sync_mode'] ) ? $settings['price_sync_mode'] : 'sync_normal';

        if ( $product->is_type( 'variable' ) ) {
            if ( ! empty( $data['attributes'] ) ) {
                $attributes = array();
                foreach ( $data['attributes'] as $attr_data ) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name( $attr_data['name'] );
                    $attribute->set_options( $attr_data['options'] );
                    $attribute->set_position( 0 );
                    $attribute->set_visible( true );
                    $attribute->set_variation( true );
                    $attributes[] = $attribute;
                }
                $product->set_attributes( $attributes );
            }
            if ( ! empty( $data['variations'] ) ) {
                foreach ( $data['variations'] as $var_data ) {
                    $variation_id = $this->get_variation_id_by_sku( $product, $var_data['sku'] );
                    $variation = new WC_Product_Variation( $variation_id ?: 0 );
                    $variation->set_parent_id( $product->get_id() );
                    $variation->set_attributes( $var_data['attributes'] );
                    $variation->set_sku( $var_data['sku'] );
                    $variation->set_manage_stock( $var_data['manage_stock'] );
                    $variation->set_stock_quantity( $var_data['stock_quantity'] );
                    $variation->set_stock_status( $var_data['stock_status'] );
                    if ( 'set_to_zero' === $price_sync_mode ) {
                        $variation->set_regular_price( '0' );
                        $variation->set_sale_price( '' );
                    } else {
                        if ( isset( $var_data['regular_price'] ) ) $variation->set_regular_price( $var_data['regular_price'] );
                        if ( isset( $var_data['sale_price'] ) ) $variation->set_sale_price( $var_data['sale_price'] );
                    }
                    $variation->save();
                }
            }
        } else {
            if ( 'set_to_zero' === $price_sync_mode ) {
                $product->set_regular_price( '0' );
                $product->set_sale_price( '' );
            } else {
                if ( isset( $data['regular_price'] ) ) $product->set_regular_price( $data['regular_price'] );
                if ( isset( $data['sale_price'] ) ) $product->set_sale_price( $data['sale_price'] );
            }
            if ( isset( $data['manage_stock'] ) ) $product->set_manage_stock( $data['manage_stock'] );
            if ( isset( $data['stock_quantity'] ) ) $product->set_stock_quantity( $data['stock_quantity'] );
            if ( isset( $data['stock_status'] ) ) $product->set_stock_status( $data['stock_status'] );
        }

        if ( ! $existing ) {
            if ( ! empty( $data['categories'] ) ) {
                $cat_ids = array();
                foreach ( $data['categories'] as $cat ) {
                    $term = get_term_by( 'slug', $cat['slug'], 'product_cat' );
                    if ( ! $term && ! empty( $cat['name'] ) ) {
                        $term_info = wp_insert_term( $cat['name'], 'product_cat', array( 'slug' => $cat['slug'] ) );
                        if ( ! is_wp_error( $term_info ) ) $term = get_term( $term_info['term_id'], 'product_cat' );
                    }
                    if ( $term ) $cat_ids[] = $term->term_id;
                }
                $product->set_category_ids( $cat_ids );
            }
        }

        if ( has_term( 'composant-pc', 'product_cat', $desired_id ) ) {
            $product->set_catalog_visibility( 'visible' );
        } else {
            if ( isset( $data['catalog_visibility'] ) ) $product->set_catalog_visibility( sanitize_key( $data['catalog_visibility'] ) );
        }

        $product->save();
        WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Created/updated product with forced ID %d via custom endpoint.', $desired_id ), 'info' );
        return new WP_REST_Response( array( 'id' => $desired_id, 'success' => true ), 201 );
    }

    /**
     * Helper to find a variation ID by SKU for a given parent product.
     *
     * @param WC_Product $product The parent product.
     * @param string     $sku     The SKU to find.
     * @return int The variation ID, or 0 if not found.
     */
    private function get_variation_id_by_sku( $product, $sku ) {
        if ( ! $product || ! $product->is_type( 'variable' ) || empty( $sku ) ) {
            return 0;
        }

        foreach ( $product->get_children() as $child_id ) {
            $variation = wc_get_product( $child_id );
            if ( $variation && $variation->get_sku() === $sku ) {
                return $child_id;
            }
        }

        return 0;
    }
}
