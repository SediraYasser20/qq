<?php
/**
 * WooCommerce Product Sync B to A Settings Class.
 *
 * Handles the plugin settings page and options for B to A synchronization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_Settings_B_To_A {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'WC Product Sync B to A', 'wc-product-sync-b-to-a' ),
            __( 'WC Product Sync B to A', 'wc-product-sync-b-to-a' ),
            'manage_options',
            'wc-product-sync-b-to-a',
            array( $this, 'settings_page_content' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'wc_product_sync_b_to_a_options',
            'wc_product_sync_b_to_a_settings',
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'wc_product_sync_b_to_a_section_api',
            __( 'Website A API Settings', 'wc-product-sync-b-to-a' ),
            array( $this, 'api_settings_section_callback' ),
            'wc-product-sync-b-to-a'
        );

        add_settings_field(
            'wc_product_sync_b_to_a_api_url',
            __( 'Website A API URL', 'wc-product-sync-b-to-a' ),
            array( $this, 'api_url_callback' ),
            'wc-product-sync-b-to-a',
            'wc_product_sync_b_to_a_section_api'
        );

        add_settings_field(
            'wc_product_sync_b_to_a_consumer_key',
            __( 'Website A Consumer Key', 'wc-product-sync-b-to-a' ),
            array( $this, 'consumer_key_callback' ),
            'wc-product-sync-b-to-a',
            'wc_product_sync_b_to_a_section_api'
        );

        add_settings_field(
            'wc_product_sync_b_to_a_consumer_secret',
            __( 'Website A Consumer Secret', 'wc-product-sync-b-to-a' ),
            array( $this, 'consumer_secret_callback' ),
            'wc-product-sync-b-to-a',
            'wc_product_sync_b_to_a_section_api'
        );

        // New section for sync behavior
        add_settings_section(
            'wc_product_sync_b_to_a_section_behavior',
            __( 'Sync Behavior', 'wc-product-sync-b-to-a' ),
            array( $this, 'behavior_settings_section_callback' ),
            'wc-product-sync-b-to-a'
        );

        add_settings_field(
            'wc_product_sync_b_to_a_price_sync_mode',
            __( 'Price Syncing', 'wc-product-sync-b-to-a' ),
            array( $this, 'price_sync_mode_callback' ),
            'wc-product-sync-b-to-a',
            'wc_product_sync_b_to_a_section_behavior'
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input The settings input.
     * @return array The sanitized input.
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = array();

        if ( isset( $input['api_url'] ) ) {
            $sanitized_input['api_url'] = esc_url_raw( $input['api_url'] );
        }

        if ( isset( $input['consumer_key'] ) ) {
            $sanitized_input['consumer_key'] = sanitize_text_field( $input['consumer_key'] );
        }

        if ( isset( $input['consumer_secret'] ) ) {
            $sanitized_input['consumer_secret'] = sanitize_text_field( $input['consumer_secret'] );
        }

        if ( isset( $input['price_sync_mode'] ) ) {
            $allowed_modes = array( 'sync_normal', 'set_to_zero' );
            if ( in_array( $input['price_sync_mode'], $allowed_modes, true ) ) {
                $sanitized_input['price_sync_mode'] = $input['price_sync_mode'];
            }
        }

        return $sanitized_input;
    }

    /**
     * API settings section callback.
     */
    public function api_settings_section_callback() {
        echo '<p>' . esc_html__( 'Enter the API details for Website A.', 'wc-product-sync-b-to-a' ) . '</p>';
    }

    /**
     * API URL field callback.
     */
    public function api_url_callback() {
        $options = get_option( 'wc_product_sync_b_to_a_settings' );
        $api_url = isset( $options['api_url'] ) ? $options['api_url'] : '';
        echo '<input type="text" id="wc_product_sync_b_to_a_api_url" name="wc_product_sync_b_to_a_settings[api_url]" value="' . esc_attr( $api_url ) . '" class="regular-text" placeholder="https://websitea.com/wp-json/wc/v3/">';
    }

    /**
     * Consumer Key field callback.
     */
    public function consumer_key_callback() {
        $options = get_option( 'wc_product_sync_b_to_a_settings' );
        $consumer_key = isset( $options['consumer_key'] ) ? $options['consumer_key'] : '';
        echo '<input type="text" id="wc_product_sync_b_to_a_consumer_key" name="wc_product_sync_b_to_a_settings[consumer_key]" value="' . esc_attr( $consumer_key ) . '" class="regular-text">';
    }

    /**
     * Consumer Secret field callback.
     */
    public function consumer_secret_callback() {
        $options = get_option( 'wc_product_sync_b_to_a_settings' );
        $consumer_secret = isset( $options['consumer_secret'] ) ? $options['consumer_secret'] : '';
        echo '<input type="text" id="wc_product_sync_b_to_a_consumer_secret" name="wc_product_sync_b_to_a_settings[consumer_secret]" value="' . esc_attr( $consumer_secret ) . '" class="regular-text">';
    }

    /**
     * Behavior settings section callback.
     */
    public function behavior_settings_section_callback() {
        echo '<p>' . esc_html__( 'Define how incoming products are handled.', 'wc-product-sync-b-to-a' ) . '</p>';
    }

    /**
     * Price sync mode field callback.
     */
    public function price_sync_mode_callback() {
        $options = get_option( 'wc_product_sync_b_to_a_settings' );
        $price_sync_mode = isset( $options['price_sync_mode'] ) ? $options['price_sync_mode'] : 'sync_normal';
        ?>
        <select id="wc_product_sync_b_to_a_price_sync_mode" name="wc_product_sync_b_to_a_settings[price_sync_mode]">
            <option value="sync_normal" <?php selected( $price_sync_mode, 'sync_normal' ); ?>>
                <?php esc_html_e( 'Sync prices normally', 'wc-product-sync-b-to-a' ); ?>
            </option>
            <option value="set_to_zero" <?php selected( $price_sync_mode, 'set_to_zero' ); ?>>
                <?php esc_html_e( 'Set all incoming product prices to 0', 'wc-product-sync-b-to-a' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Choose whether to use the price from Website A or to set the price to zero for all synced products.', 'wc-product-sync-b-to-a' ); ?>
        </p>
        <?php
    }

    /**
     * Settings page content.
     */
    public function settings_page_content() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'wc_product_sync_b_to_a_options' );
                do_settings_sections( 'wc-product-sync-b-to-a' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }
}


