<?php
/**
 * WooCommerce Product Sync B to A API Class.
 *
 * Handles communication with Website A's WooCommerce REST API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Product_Sync_API_B_To_A {

    /**
     * The API URL for Website A.
     *
     * @var string
     */
    private $api_url;

    /**
     * The Consumer Key for Website A.
     *
     * @var string
     */
    private $consumer_key;

    /**
     * The Consumer Secret for Website A.
     *
     * @var string
     */
    private $consumer_secret;

    /**
     * Constructor.
     */
    public function __construct() {
        $options = get_option( 'wc_product_sync_b_to_a_settings' );
        $this->api_url       = isset( $options['api_url'] ) ? trailingslashit( $options['api_url'] ) : '';
        $this->consumer_key  = isset( $options['consumer_key'] ) ? $options['consumer_key'] : '';
        $this->consumer_secret = isset( $options['consumer_secret'] ) ? $options['consumer_secret'] : '';
    }

    /**
     * Make a GET request to Website A's API.
     *
     * @param string $endpoint The API endpoint.
     * @param array  $args     Optional. Query arguments.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function get( $endpoint, $args = array() ) {
        return $this->request( 'GET', $endpoint, $args );
    }

    /**
     * Make a POST request to Website A's API.
     *
     * @param string $endpoint The API endpoint.
     * @param array  $data     The data to send.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function post( $endpoint, $data = array() ) {
        return $this->request( 'POST', $endpoint, $data );
    }

    /**
     * Make a PUT request to Website A's API.
     *
     * @param string $endpoint The API endpoint.
     * @param array  $data     The data to send.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function put( $endpoint, $data = array() ) {
        return $this->request( 'PUT', $endpoint, $data );
    }

    /**
     * Make an API request.
     *
     * @param string $method   The HTTP method (GET, POST, PUT).
     * @param string $endpoint The API endpoint.
     * @param array  $data     The data to send (for POST/PUT) or query arguments (for GET).
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    private function request( $method, $endpoint, $data = array() ) {
        if ( empty( $this->api_url ) || empty( $this->consumer_key ) || empty( $this->consumer_secret ) ) {
            WC_Product_Sync_Logger_B_To_A::log( 'API credentials are not set. Please configure the plugin settings.', 'error' );
            return new WP_Error( 'api_credentials_missing', 'API credentials are not set.' );
        }

        $url = $this->api_url . $endpoint;
        $args = array(
            'method'    => $method,
            'headers'   => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'   => 30,
            'sslverify' => false, // Consider changing to true in production with proper SSL setup.
        );

        // Add authentication to the URL for GET requests, or to the body for POST/PUT.
        if ( 'GET' === $method ) {
            $url = add_query_arg(
                array(
                    'consumer_key'    => $this->consumer_key,
                    'consumer_secret' => $this->consumer_secret,
                ), $url
            );
            if ( ! empty( $data ) ) {
                $url = add_query_arg( $data, $url );
            }
        } else {
            $args['body'] = json_encode( $data );
            $args['headers']['Authorization'] = 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret );
        }

        WC_Product_Sync_Logger_B_To_A::log( sprintf( 'Making %s request to %s with data: %s', $method, $url, json_encode( $data ) ), 'info' );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'API Request Error: %s', $response->get_error_message() ), 'error' );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        $http_code = wp_remote_retrieve_response_code( $response );

        if ( $http_code < 200 || $http_code >= 300 ) {
            WC_Product_Sync_Logger_B_To_A::log( sprintf( 'API Request Failed with status %d: %s', $http_code, $body ), 'error' );
            return new WP_Error( 'api_request_failed', sprintf( 'API request failed with status %d: %s', $http_code, $body ), array( 'status' => $http_code, 'response' => $data ) );
        }

        WC_Product_Sync_Logger_B_To_A::log( sprintf( 'API Request Successful. Response: %s', $body ), 'info' );

        return $data;
    }
}


