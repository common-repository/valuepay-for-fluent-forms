<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Valuepay_Fluentform_Admin {

    private $fluentform_pro_minimum_version = '4.2.0';

    // Register hooks
    public function __construct() {

        add_action( 'plugin_action_links_' . plugin_basename( VALUEPAY_FLUENTFORM_BASENAME ), array( $this, 'register_settings_link' ) );
        add_action( 'admin_notices', array( $this, 'fluentform_notices' ) );

    }

    // Register plugin settings link
    public function register_settings_link( $links ) {

        if ( $this->is_fluentform_activated() && $this->is_fluentform_pro_activated() ) {
            $url = admin_url( 'admin.php?page=fluent_forms_settings&component=payment_settings#/payment_methods' );
            $label = __( 'Settings', 'valuepay-fluentform' );

            $settings_link = sprintf( '<a href="%s">%s</a>', $url, $label );
            array_unshift( $links, $settings_link );
        }

        return $links;

    }

    // Show notice if Fluent Forms is not installed and activated
    public function fluentform_notices() {

        // Check if Fluent Forms is not installed and activated
        if ( !$this->is_fluentform_activated() ) {
            valuepay_fluentform_notice( __( 'Fluent Forms needs to be installed and activated.', 'valuepay-fluentform' ), 'error' );
        }

        // Check if Fluent Forms Pro is installed, activated and meets minimum version requirement
        if ( $this->is_fluentform_pro_activated() ) {
            if ( !$this->is_fluentform_pro_minimum_version() ) {
                valuepay_fluentform_notice(
                    sprintf(
                        __( 'Fluent Forms Pro version needs to be at least %s.', 'valuepay-fluentform' ),
                        $this->fluentform_pro_minimum_version
                    ),
                    'error'
                );
            }
        } else {
            valuepay_fluentform_notice( __( 'Fluent Forms Pro needs to be installed and activated.', 'valuepay-fluentform' ), 'error' );
        }

    }

    // Check if Fluent Forms is installed and activated
    private function is_fluentform_activated() {
        return in_array( 'fluentform/fluentform.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }

    // Check if Fluent Forms Pro is installed and activated
    private function is_fluentform_pro_activated() {
        return in_array( 'fluentformpro/fluentformpro.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }

    // Check if Fluent Forms Pro meets minimum version requirement
    private function is_fluentform_pro_minimum_version() {
        return version_compare( $this->fluentform_pro_minimum_version, FLUENTFORMPRO_VERSION, '<=' );
    }

}
new Valuepay_Fluentform_Admin();
