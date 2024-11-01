<?php
if ( !defined( 'ABSPATH' ) ) exit;

abstract class Valuepay_Fluentform_Client {

    const API_URL = 'https://webservice.valuepay.my/';

    public $app_key;
    public $app_secret;

    protected $debug = true;

    // HTTP request headers
    private function get_headers() {

        return array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        );

    }

    // HTTP GET request
    protected function get( $route, $params = array() ) {
        return $this->request( $route, $params, 'GET' );
    }

    // HTTP POST request
    protected function post( $route, $params = array() ) {
        return $this->request( $route, $params );
    }

    // HTTP request
    protected function request( $route, $params = array(), $method = 'POST' ) {

        $url = self::API_URL . $route;

        $args['headers'] = $this->get_headers();

        $this->log( 'URL: ' . $url );
        $this->log( 'Headers: ' . wp_json_encode( $args['headers'] ) );

        if ( $params ) {
            $args['body'] = $method !== 'POST' ? $params : wp_json_encode( $params );
            $this->log( 'Body: ' . wp_json_encode( $params ) );
        }

        // Set request timeout to 30 seconds
        $args['timeout'] = 30;

        switch ( $method ) {
            case 'GET':
                $response = wp_remote_get( $url, $args );
                break;

            case 'POST':
                $response = wp_remote_post( $url, $args );
                break;

            default:
                $args['method'] = $method;
                $response = wp_remote_request( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            $this->log( 'Response Error: ' . $response->get_error_message() );
            throw new Exception( $response->get_error_message() );
        }

        $response = json_decode( wp_remote_retrieve_body( $response ), true );

        $this->log( 'Response: ' . wp_json_encode( $response ) );

        if ( isset( $response['wscode'] ) ) {
            if ( isset( $response['wsdata'] ) ) {
                return array(
                    $response['wscode'],
                    $response['wsdata'],
                );
            } else {
                throw new Exception( $this->get_formatted_error_message( $response['wscode'] ) );
            }
        }

        throw new Exception( 'Error Processing Request' );

    }

    // Get IPN response data
    public function get_ipn_response() {

        if ( !in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'POST' ) ) ) {
            return false;
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $response = $this->get_valid_ipn_response_callback();
        } else {
            $response = $this->get_valid_ipn_response_redirect();
        }

        if ( !$response ) {
            return false;
        }

        return $response;

    }

    // Sanitize and format IPN response data (callback)
    private function get_valid_ipn_response_callback() {

        $response = file_get_contents( 'php://input' );
        $response = json_decode( $response, true );

        $params = $this->get_callback_params();
        $allowed_params = array();

        foreach ( $params as $param ) {
            // Return false if required parameters is not passed to the URL
            if ( !isset( $response[ $param ] ) ) {
                return false;
            }

            $allowed_params[ $param ] = $this->sanitize_ipn_response( $response[ $param ] );
        }

        // Returns only allowed response data
        return $allowed_params;

    }

    // Sanitize and format IPN response data (redirect)
    private function get_valid_ipn_response_redirect() {

        $params = $this->get_redirect_params();
        $allowed_params = array();

        foreach ( $params as $param ) {
            // Return false if required parameters is not passed to the URL
            if ( !isset( $_GET[ $param ] ) ) {
                return false;
            }

            $allowed_params[ $param ] = $this->sanitize_ipn_response( $_GET[ $param ] );
        }

        // Returns only allowed response data
        return $allowed_params;

    }

    // Sanitize IPN response data
    private function sanitize_ipn_response( $value ) {

        if ( is_array( $value ) ) {
            $value = array_map( function( $value ) {
                return trim( sanitize_text_field( $value ) );
            }, $value );
        } else {
            $value = trim( sanitize_text_field( $value ) );
        }

        return $value;

    }

    // Get list of parameters that will be passed in callback URL
    private function get_callback_params() {

        return array(
            'bill_id',
            'collection_id',
            'orderno',
            'bill_amount',
            'bill_status',
            'buyer_data',
            'date_create',
            'payment_intent_id',
            'rephash',
            'timestamp',
        );

    }

    // Get list of parameters that will be passed in redirect URL
    private function get_redirect_params() {
        return array( 'order_id' );
    }

    // Validate IPN response data
    public function validate_ipn_response( $response ) {

        if ( !$this->verify_hash( $response ) ) {
            throw new Exception( 'Hash value mismatch.' );
        }

        return true;

    }

    // Verify hash parameter value received from IPN response data
    private function verify_hash( $response ) {

        if ( !$this->app_secret ) {
            throw new Exception( 'Missing application secret key.' );
        }

        if ( !isset( $response['rephash'] ) || empty( $response['rephash'] ) ) {
            return false;
        }

        $hash_data = array(
            $this->app_secret,
            $this->username,
        );

        // Parameter values used in hash calculation formula
        $params = array(
            'collection_id',
            'bill_id',
            'bill_amount',
            'payment_intent_id',
            'bill_status',
            'orderno',
        );

        // Check if required parameter values used in hash calculation formula is not exist or empty
        foreach ( $params as $param ) {
            if ( !isset( $response[ $param ] ) || empty( $response[ $param ] ) ) {
                return false;
            }

            $hash_data[] = $response[ $param ];
        }

        $generated_rephash = md5( implode( '', array_values( $hash_data ) ) );

        return $response['rephash'] == $generated_rephash;

    }


    // Returns formatted error message by its code
    private function get_formatted_error_message( $error_code ) {

        $errors = array(
            'WS00' => __( 'Request executed successfully.', 'valuepay-fluentform' ),
            'WS01' => __( 'Invalid endpoint', 'valuepay-fluentform' ),
            'WS02' => __( 'No request body', 'valuepay-fluentform' ),
            'WS03' => __( 'Data is not properly formatted', 'valuepay-fluentform' ),
            'E01' => __( 'Missing mandatory field', 'valuepay-fluentform' ),
            'E02' => __( 'Collection missing mandatory field for open bill amount', 'valuepay-fluentform' ),
            'E03' => __( 'Collection missing mandatory field for fixed number amount', 'valuepay-fluentform' ),
            'E04' => __( 'Collection value not valid amount', 'valuepay-fluentform' ),
            'E05' => __( 'Collection alias is not available', 'valuepay-fluentform' ),
            'E06' => __( 'Collection mandatory field indicator is not valid', 'valuepay-fluentform' ),
            'E07' => __( 'Unable to cancel collection or bill', 'valuepay-fluentform' ),
            'E08' => __( 'Unable to delete in-flight transaction. There is pending payment intent for this bill.', 'valuepay-fluentform' ),
            'E09' => __( 'Invalid or inactive collection ID or mandate ID', 'valuepay-fluentform' ),
            'E10' => __( 'Invalid bill ID', 'valuepay-fluentform' ),
            'E11' => __( 'Invalid payment intent ID', 'valuepay-fluentform' ),
            'E12' => __( 'Invalid merchant username or not active', 'valuepay-fluentform' ),
            'E13' => __( 'Invalid reqhash calculation', 'valuepay-fluentform' ),
            'E14' => __( 'Missing mandatory field for API', 'valuepay-fluentform' ),
            'E15' => __( 'Billing amount is not valid', 'valuepay-fluentform' ),
            'E16' => __( 'Billing buyer data field is not valid', 'valuepay-fluentform' ),
            'E17' => __( 'Billing mobile number missing country code or invalid length', 'valuepay-fluentform' ),
            'E18' => __( 'Billing e-mail address is invalid', 'valuepay-fluentform' ),
            'E19' => __( 'Billing order number length exceed limit', 'valuepay-fluentform' ),
            'E20' => __( 'Billing frontend or backend URL format is not valid or unsecured', 'valuepay-fluentform' ),
            'E21' => __( 'Billing frontend or backend URL certificate cannot be verified with CA', 'valuepay-fluentform' ),
        );

        return isset( $errors[ $error_code ] ) ? $errors[ $error_code ] : false;

    }

    // Debug logging
    private function log( $message ) {
        return;
    }

}
