<?php
if ( !defined( 'ABSPATH' ) ) exit;

class Valuepay_Fluentform_Init {

    // Register hooks
    public function __construct() {

        add_action( 'fluentform_loaded', array( $this, 'load_dependencies' ) );

        // Smartcodes
        add_filter( 'fluentform_form_settings_smartcodes', array( $this, 'register_smartcodes' ) );
        add_filter( 'fluentform_shortcode_parser_callback_valuepay.payment_id', array( $this, 'smartcode_parse_payment_id' ), 10, 2 );
        add_filter( 'fluentform_shortcode_parser_callback_valuepay.payment_url', array( $this, 'smartcode_parse_payment_url' ), 10, 2 );

    }

    // Load required files
    public function load_dependencies() {

        if ( !class_exists( 'FluentFormPro' ) ) {
            return;
        }

        require_once( VALUEPAY_FLUENTFORM_PATH . 'includes/class-valuepay-fluentform-handler.php' );
        require_once( VALUEPAY_FLUENTFORM_PATH . 'includes/class-valuepay-fluentform-processor.php' );

        $handler = new Valuepay_Fluentform_Handler();
        $handler->init();

    }

    // Register smartcodes (shortcodes)
    public function register_smartcodes( $groups ) {

        $groups[] = array(
            'title' => __( 'ValuePay Payment Details', 'valuepay-fluentform' ),
            'shortcodes' => array(
                '{valuepay.payment_id}'  => __( 'ValuePay Payment ID', 'valuepay-fluentform' ),
                '{valuepay.payment_url}' => __( 'ValuePay Payment URL', 'valuepay-fluentform' ),
            ),
        );

        return $groups;

    }

    // Parse smartcode for ValuePay payment ID
    public function smartcode_parse_payment_id( $return, $instance ) {

        $form       = $instance::getForm();
        $entry      = $instance::getEntry();
        $fields     = \FluentForm\App\Modules\Form\FormFieldsParser::getEntryInputs( $form );
        $submission = \FluentForm\App\Modules\Form\FormDataParser::parseFormSubmission( $entry, $form, $fields, true );

        $return = \FluentForm\App\Helpers\Helper::getSubmissionMeta( $submission->id, '_valuepay_payment_id' );

        return $return;

    }

    // Parse smartcode for ValuePay payment URL
    public function smartcode_parse_payment_url( $return, $instance ) {

        $form       = $instance::getForm();
        $entry      = $instance::getEntry();
        $fields     = \FluentForm\App\Modules\Form\FormFieldsParser::getEntryInputs( $form );
        $submission = \FluentForm\App\Modules\Form\FormDataParser::parseFormSubmission( $entry, $form, $fields, true );

        $payment_id = $this->smartcode_parse_payment_id( $return, $instance );
        $payment_type = valuepay_fluentform_get_form_submission_value( $form, $submission, 'payment_type' );

        if ( $payment_type === 'recurring' ) {
            $return = 'https://valuepay.my/m/' . $payment_id;
        } else {
            $return = 'https://valuepay.my/b/' . $payment_id;
        }

        return $return;

    }

}
new Valuepay_Fluentform_Init();
