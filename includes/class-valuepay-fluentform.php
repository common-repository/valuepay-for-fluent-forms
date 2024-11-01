<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Valuepay_Fluentform {

    // Load dependencies
    public function __construct() {

        // Functions
        require_once( VALUEPAY_FLUENTFORM_PATH . 'includes/functions.php' );

        // API
        require_once( VALUEPAY_FLUENTFORM_PATH . 'includes/abstracts/abstract-valuepay-fluentform-client.php' );
        require_once( VALUEPAY_FLUENTFORM_PATH . 'includes/class-valuepay-fluentform-api.php' );

        // Admin
        require_once( VALUEPAY_FLUENTFORM_PATH . 'admin/class-valuepay-fluentform-admin.php' );

        // Initialize payment gateway
        require_once( VALUEPAY_FLUENTFORM_PATH . 'includes/class-valuepay-fluentform-init.php' );

    }

}
new Valuepay_Fluentform();
