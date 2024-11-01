<?php
if ( !defined( 'ABSPATH' ) ) exit;

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentMethods\BasePaymentMethod;

class Valuepay_Fluentform_Handler extends BasePaymentMethod {

    public function __construct() {
        parent::__construct( 'valuepay' );
    }

    public function init() {

        add_filter( 'fluentform_payment_method_settings_validation_' . $this->key, array( $this, 'validateSettings' ), 10, 2 );

        if ( !$this->isEnabled() ) {
            return;
        }

        add_filter( 'fluentform_transaction_data_' . $this->key, array( $this, 'modifyTransaction' ), 10, 1 );

        add_filter( 'fluentformpro_available_payment_methods', array( $this, 'pushPaymentMethodToForm' ) );

        $processor = new Valuepay_Fluentform_Processor();
        $processor->init();

    }

    public function pushPaymentMethodToForm( $methods ) {

        $methods[ $this->key ] = array(
            'title'        => __( 'ValuePay', 'valuepay-fluentform' ),
            'enabled'      => 'yes',
            'method_value' => $this->key,
            'settings'     => array(
                'option_label' => array(
                    'type'     => 'text',
                    'template' => 'inputText',
                    'value'    => __( 'Pay with ValuePay', 'valuepay-fluentform' ),
                    'label'    => __( 'Method Label', 'valuepay-fluentform' ),
                ),
            ),
        );

        return $methods;

    }

    public function validateSettings( $errors, $settings ) {

        if( ArrayHelper::get( $settings, 'is_active' ) == 'no' ) {
            return array();
        }

        if ( !ArrayHelper::get( $settings, 'username' ) ) {
            $errors['username'] = __( 'ValuePay merchant username is required.', 'valuepay-fluentform' );
        }

        if ( !ArrayHelper::get( $settings, 'app_key' ) ) {
            $errors['app_key'] = __( 'ValuePay application key is required.', 'valuepay-fluentform' );
        }

        if ( !ArrayHelper::get( $settings, 'app_secret' ) ) {
            $errors['app_secret'] = __( 'ValuePay application secret is required.', 'valuepay-fluentform' );
        }

        if ( !ArrayHelper::get( $settings, 'collection_id' ) && !ArrayHelper::get( $settings, 'mandate_id' ) ) {
            $errors['collection_id'] = __( 'ValuePay collection or mandate ID is required.', 'valuepay-fluentform' );
        }

        return $errors;

    }

    public function modifyTransaction( $transaction ) {
        return $transaction;
    }

    public function isEnabled() {
        $settings = $this->getGlobalSettings();
        return $settings['is_active'] == 'yes';
    }

    public function getGlobalFields() {

        return array(
            'label'  => __( 'ValuePay', 'valuepay-fluentform' ),
            'fields' => array(
                array(
                    'settings_key'   => 'is_active',
                    'type'           => 'yes-no-checkbox',
                    'label'          => 'Status',
                    'checkbox_label' => __( 'Enable ValuePay Payment Method', 'valuepay-fluentform' ),
                ),
                array(
                    'type'           => 'html',
                    'html'           => '<h2>' . esc_html__( 'API Credentials', 'valuepay-fluentform' ) . '</h2><p>' . esc_html__( 'API credentials can be obtained from ValuePay merchant dashboard in Business Profile page.', 'valuepay-fluentform' ) . '</p>',
                ),
                array(
                    'settings_key'   => 'username',
                    'type'           => 'input-text',
                    'data_type'      => 'text',
                    'label'          => __( 'Merchant Username', 'valuepay-fluentform' ),
                    'check_status'   => 'yes',
                ),
                array(
                    'settings_key'   => 'app_key',
                    'type'           => 'input-text',
                    'data_type'      => 'text',
                    'label'          => __( 'Application Key', 'valuepay-fluentform' ),
                    'check_status'   => 'yes',
                ),
                array(
                    'settings_key'   => 'app_secret',
                    'type'           => 'input-text',
                    'data_type'      => 'text',
                    'label'          => __( 'Application Secret', 'valuepay-fluentform' ),
                    'check_status'   => 'yes',
                ),
                array(
                    'type'           => 'html',
                    'html'           => '<h2>' . esc_html__( 'Collection & Mandate', 'valuepay-fluentform' ) . '</h2>',
                ),
                array(
                    'settings_key'   => 'collection_id',
                    'type'           => 'input-text',
                    'data_type'      => 'text',
                    'label'          => __( 'Collection ID', 'valuepay-fluentform' ),
                    'inline_help'    => __( 'Collection ID can be obtained from ValuePay merchant dashboard under FPX Payment menu, in My Collection List page. Leave blank to disable one time payment.', 'valuepay-fluentform' ),
                    'check_status'   => 'yes',
                ),
                array(
                    'settings_key'   => 'mandate_id',
                    'type'           => 'input-text',
                    'data_type'      => 'text',
                    'label'          => __( 'Mandate ID', 'valuepay-fluentform' ),
                    'inline_help'    => __( 'Mandate ID can be obtained from ValuePay merchant dashboard under E-Mandate Collection menu, in My Mandate List page. Leave blank to disable recurring payment.', 'valuepay-fluentform' ),
                    'check_status'   => 'yes',
                ),
            ),
        );

    }

    public function getGlobalSettings() {
        return valuepay_fluentform_get_settings();
    }

}
