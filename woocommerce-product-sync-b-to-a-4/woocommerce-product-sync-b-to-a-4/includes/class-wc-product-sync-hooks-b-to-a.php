<?php
/**
 * WooCommerce Product Sync B to A Hooks Class.
 *
 * Handles the synchronization logic using WooCommerce hooks for B to A sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_Hooks_B_To_A {

    /**
     * The API instance.
     *
     * @var WC_Product_Sync_API_B_To_A
     */
    private $api;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api = new WC_Product_Sync_API_B_To_A();

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
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return;
        }

        $data = array(
            'stock_status'    => $product->get_stock_status(),
            'catalog_visibility' => $product->get_catalog_visibility(),
        );

        WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Attempting to sync product ID %d data to Website A: %s', $product_id, json_encode( $data ) ), 'info' );

        $response = $this->api->put( 'products/' . $product_id, $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Failed to sync product ID %d data to Website A: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Successfully synced product ID %d data to Website A.', $product_id ), 'info' );
        }
    }

    /**
     * Synchronize new product creation.
     *
     * @param int $product_id The new product ID.
     */
    public function sync_new_product( $product_id ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return;
        }

        // Prepare product data for Website A, excluding images.
        $data = array(
            'id'                 => $product->get_id(),
            'name'               => $product->get_name(),
            'slug'               => $product->get_slug(),
            'type'               => $product->get_type(),
            'status'             => $product->get_status(),
            'featured'           => $product->is_featured(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'description'        => $product->get_description(),
            'short_description'  => $product->get_short_description(),
            'sku'                => $product->get_sku(),
            'price'              => $product->get_price(),
            'regular_price'      => $product->get_regular_price(),
            'sale_price'         => $product->get_sale_price(),
            'date_on_sale_from'  => $product->get_date_on_sale_from() ? gmdate( 'Y-m-d H:i:s', $product->get_date_on_sale_from()->getTimestamp() ) : null,
            'date_on_sale_to'    => $product->get_date_on_sale_to() ? gmdate( 'Y-m-d H:i:s', $product->get_date_on_sale_to()->getTimestamp() ) : null,
            'on_sale'            => $product->is_on_sale(),
            'purchasable'        => $product->is_purchasable(),
            'total_sales'        => $product->get_total_sales(),
            'virtual'            => $product->is_virtual(),
            'downloadable'       => $product->is_downloadable(),
            'downloads'          => $product->get_downloads(),
            'download_limit'     => $product->get_download_limit(),
            'download_expiry'    => $product->get_download_expiry(),

            'button_text'        => $product->get_button_text(),
            'tax_status'         => $product->get_tax_status(),
            'tax_class'          => $product->get_tax_class(),
            'manage_stock'       => $product->get_manage_stock(),
            'stock_quantity'     => $product->get_stock_quantity(),
            'stock_status'       => $product->get_stock_status(),
            'backorders'         => $product->get_backorders(),
            'low_stock_amount'   => $product->get_low_stock_amount(),
            'sold_individually'  => $product->get_sold_individually(),
            'weight'             => $product->get_weight(),
            'length'             => $product->get_length(),
            'width'              => $product->get_width(),
            'height'             => $product->get_height(),
            'shipping_class_id'  => $product->get_shipping_class_id(),
            'reviews_allowed'    => $product->get_reviews_allowed(),
            'average_rating'     => $product->get_average_rating(),
            'rating_count'       => $product->get_rating_count(),
            'parent_id'          => $product->get_parent_id(),
            'purchase_note'      => $product->get_purchase_note(),
            'categories'         => array_map( function( $cat ) { return array( 'id' => $cat->term_id ); }, $product->get_categories() ),
            'tags'               => array_map( function( $tag ) { return array( 'id' => $tag->term_id ); }, $product->get_tags() ),
            'attributes'         => array_map( function( $attr ) { return $attr->get_data(); }, $product->get_attributes() ),
            'default_attributes' => $product->get_default_attributes(),
            'variations'         => array_map( function( $variation_id ) { return array( 'id' => $variation_id ); }, $product->get_children() ),
            'menu_order'         => $product->get_menu_order(),
            'price_html'         => $product->get_price_html(),
            'dimensions'         => array(
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
            ),
        );

        WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Attempting to create new product ID %d on Website A.', $product_id ), 'info' );

        $response = $this->api->post( 'products', $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Failed to create new product ID %d on Website A: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Successfully created new product ID %d on Website A.', $product_id ), 'info' );
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
        $data = array(
            'stock_quantity' => $product->get_stock_quantity(),
        );

        WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Attempting to sync product ID %d stock quantity to Website A: %d', $product_id, $product->get_stock_quantity() ), 'info' );

        $response = $this->api->put( 'products/' . $product_id, $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Failed to sync product ID %d stock quantity to Website A: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Successfully synced product ID %d stock quantity to Website A.', $product_id ), 'info' );
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
        if ( ! $product ) {
            return;
        }

        $data = array(
            'stock_status' => $stock_status,
        );

        WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Attempting to sync product ID %d stock status to Website A: %s', $product_id, $stock_status ), 'info' );

        $response = $this->api->put( 'products/' . $product_id, $data );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Failed to sync product ID %d stock status to Website A: %s', $product_id, $response->get_error_message() ), 'error' );
        } else {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Successfully synced product ID %d stock status to Website A.', $product_id ), 'info' );
        }
    }
}


