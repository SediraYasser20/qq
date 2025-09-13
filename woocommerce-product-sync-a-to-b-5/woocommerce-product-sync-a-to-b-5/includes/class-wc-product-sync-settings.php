<?php
/**
 * WooCommerce Product Sync Settings Class.
 *
 * Handles the plugin settings page and options.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_Settings {

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
            __( 'WC Product Sync', 'wc-product-sync' ),
            __( 'WC Product Sync', 'wc-product-sync' ),
            'manage_options',
            'wc-product-sync',
            array( $this, 'settings_page_content' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'wc_product_sync_options',
            'wc_product_sync_settings',
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'wc_product_sync_section_api',
            __( 'Website B API Settings', 'wc-product-sync' ),
            array( $this, 'api_settings_section_callback' ),
            'wc-product-sync'
        );

        add_settings_field(
            'wc_product_sync_api_url',
            __( 'Website B API URL', 'wc-product-sync' ),
            array( $this, 'api_url_callback' ),
            'wc-product-sync',
            'wc_product_sync_section_api'
        );

        add_settings_field(
            'wc_product_sync_consumer_key',
            __( 'Website B Consumer Key', 'wc-product-sync' ),
            array( $this, 'consumer_key_callback' ),
            'wc-product-sync',
            'wc_product_sync_section_api'
        );

        add_settings_field(
            'wc_product_sync_consumer_secret',
            __( 'Website B Consumer Secret', 'wc-product-sync' ),
            array( $this, 'consumer_secret_callback' ),
            'wc-product-sync',
            'wc_product_sync_section_api'
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
            $api_url = esc_url_raw( trim( $input['api_url'] ) );
            // Force HTTPS only
            if ( stripos( $api_url, 'https://' ) === 0 ) {
                $sanitized_input['api_url'] = $api_url;
            } else {
                add_settings_error(
                    'wc_product_sync_settings',
                    'invalid_api_url',
                    __( 'API URL must start with https://', 'wc-product-sync' )
                );
            }
        }

        if ( isset( $input['consumer_key'] ) ) {
            $sanitized_input['consumer_key'] = sanitize_text_field( $input['consumer_key'] );
        }

        if ( isset( $input['consumer_secret'] ) ) {
            $sanitized_input['consumer_secret'] = sanitize_text_field( $input['consumer_secret'] );
        }

        return $sanitized_input;
    }

    /**
     * API settings section callback.
     */
    public function api_settings_section_callback() {
        echo '<p>' . esc_html__( 'Enter the API details for Website B. For security, make sure you use a dedicated API user with limited permissions.', 'wc-product-sync' ) . '</p>';
    }

    /**
     * API URL field callback.
     */
    public function api_url_callback() {
        $options = get_option( 'wc_product_sync_settings' );
        $api_url = isset( $options['api_url'] ) ? $options['api_url'] : '';
        echo '<input type="url" id="wc_product_sync_api_url" name="wc_product_sync_settings[api_url]" value="' . esc_attr( $api_url ) . '" class="regular-text" placeholder="https://websiteb.com/wp-json/wc/v3/" required>';
    }

    /**
     * Consumer Key field callback.
     */
    public function consumer_key_callback() {
        $options = get_option( 'wc_product_sync_settings' );
        $consumer_key = isset( $options['consumer_key'] ) ? $options['consumer_key'] : '';
        echo '<input type="text" id="wc_product_sync_consumer_key" name="wc_product_sync_settings[consumer_key]" value="' . esc_attr( $consumer_key ) . '" class="regular-text" autocomplete="off">';
    }

    /**
     * Consumer Secret field callback.
     */
    public function consumer_secret_callback() {
        $options = get_option( 'wc_product_sync_settings' );
        $consumer_secret = isset( $options['consumer_secret'] ) ? $options['consumer_secret'] : '';
        echo '<input type="password" id="wc_product_sync_consumer_secret" name="wc_product_sync_settings[consumer_secret]" value="' . esc_attr( $consumer_secret ) . '" class="regular-text" autocomplete="new-password">';
        echo '<p class="description">' . esc_html__( 'Stored securely in the database. You can also define WC_SYNC_CONSUMER_SECRET in wp-config.php to avoid saving it here.', 'wc-product-sync' ) . '</p>';
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
                settings_fields( 'wc_product_sync_options' );
                do_settings_sections( 'wc-product-sync' );
                submit_button( __( 'Save Settings', 'wc-product-sync' ) );
                ?>
            </form>
        </div>
        <?php
    }
}
