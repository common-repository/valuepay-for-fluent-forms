<?php
if ( !defined( 'ABSPATH' ) ) exit;

// Get all plugin settings
function valuepay_fluentform_get_settings( $form_id = null ) {

    $defaults = array(
        'is_active'     => 'no',
        'username'      => '',
        'app_key'       => '',
        'app_secret'    => '',
        'collection_id' => '',
        'mandate_id'    => '',
    );

    $settings = get_option( 'fluentform_payment_settings_valuepay' );
    $settings = apply_filters( 'valuepay_fluentform_get_settings', $settings, $form_id );

    return wp_parse_args( $settings, $defaults );

}

// Display notice
function valuepay_fluentform_notice( $message, $type = 'success' ) {

    $plugin = esc_html__( 'ValuePay for Fluent Forms', 'valuepay-fluentform' );

    printf( '<div class="notice notice-%1$s"><p><strong>%2$s:</strong> %3$s</p></div>', esc_attr( $type ), $plugin, $message );

}

// List of identity types accepted by ValuePay
function valuepay_fluentform_get_identity_types() {

    return array(
        1 => __( 'New IC No.', 'valuepay-fluentform' ),
        2 => __( 'Old IC No.', 'valuepay-fluentform' ),
        3 => __( 'Passport No.', 'valuepay-fluentform' ),
        4 => __( 'Business Reg. No.', 'valuepay-fluentform' ),
        5 => __( 'Others', 'valuepay-fluentform' ),
    );

}

// Get readable identity type
function valuepay_fluentform_get_identity_type( $key ) {
    $types = valuepay_fluentform_get_identity_types();
    return isset( $types[ $key ] ) ? $types[ $key ] : false;
}

// Format telephone number
function valuepay_fluentform_format_telephone( $telephone ) {

    // Get numbers only
    $telephone = preg_replace( '/[^0-9]/', '', $telephone );

    // Add country code in the front of phone number if the phone number starts with zero (0)
    if ( strpos( $telephone, '0' ) === 0 ) {
        $telephone = '+6' . $telephone;
    }

    // Add + symbol in the front of phone number if the phone number has no + symbol
    if ( strpos( $telephone, '+' ) !== 0 ) {
        $telephone = '+' . $telephone;
    }

    return $telephone;

}

// Get single submission value by admin label from the form
function valuepay_fluentform_get_form_submission_value( $form, $submission, $field ) {

    $labels = \FluentForm\App\Modules\Form\FormFieldsParser::getAdminLabels( $form );
    $field_id = array_search( $field, $labels );

    return isset( $submission->response[ $field_id ] ) ? $submission->response[ $field_id ] : false;

}
