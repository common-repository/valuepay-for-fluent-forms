<?php
if ( !defined( 'ABSPATH' ) ) exit;

use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;
use FluentForm\App\Modules\Form\FormFieldsParser;

class Valuepay_Fluentform_Processor extends BaseProcessor {

    public $method = 'valuepay';

    protected $form;

    public function init() {

        add_filter( 'fluentform_rendering_field_data_select', array( $this, 'selectIdentityTypes' ), 10, 2 );
        add_filter( 'fluentform_rendering_field_data_select', array( $this, 'selectBanks' ), 10, 2 );

        add_action( 'fluentform_process_payment_' . $this->method, array( $this, 'handlePaymentAction' ), 10, 6 );
        add_action( 'fluent_payment_frameless_' . $this->method, array( $this, 'handleSessionRedirectBack' ) );
        add_action( 'fluentform_ipn_endpoint_' . $this->method, array( $this, 'handleIPN' ) );

        add_filter( 'fluentform_submitted_payment_items_' . $this->method, array( $this, 'validateSubmittedItems' ), 10, 4 );

    }

    // Display list of bank in the form
    public function selectIdentityTypes( $data, $form ) {

        if ( !$this->isFormPaymentMethodEnabled( $form ) ) {
            return false;
        }

        if ( isset( $data['settings']['admin_field_label'] ) && $data['settings']['admin_field_label'] == 'identity_type' ) {

            // Auto populate the options if it is empty
            if ( isset( $data['settings']['advanced_options'] ) && count( $data['settings']['advanced_options'] ) <= 1 ) {

                $i = 0;
                $identityTypes = valuepay_fluentform_get_identity_types();

                // Clear current options
                $data['settings']['advanced_options'] = array();

                foreach ( $identityTypes as $key => $value ) {
                    $i++;

                    $data['settings']['advanced_options'][] = array(
                        'label'      => esc_html( $value ),
                        'value'      => esc_html( $key ),
                        'calc_value' => '',
                        'id'         => $i,
                    );
                }
            }
        }

        return $data;

    }

    // Display list of bank in the form
    public function selectBanks( $data, $form ) {

        if ( !$this->isFormPaymentMethodEnabled( $form ) ) {
            return false;
        }

        if ( isset( $data['settings']['admin_field_label'] ) && $data['settings']['admin_field_label'] == 'bank' ) {
            $i = 0;
            $banks = $this->getBanks( $form->id );

            // Clear current options
            $data['settings']['advanced_options'] = array();

            foreach ( $banks as $key => $value ) {
                $i++;

                $data['settings']['advanced_options'][] = array(
                    'label'      => esc_html( $value ),
                    'value'      => esc_html( $key ),
                    'calc_value' => '',
                    'id'         => $i,
                );
            }
        }

        return $data;

    }

    // Check if the payment method is enabled on the specified form
    private function isFormPaymentMethodEnabled( $form ) {

        if ( isset( $form->fields['fields'] ) ) {
            foreach ( $form->fields['fields'] as $field ) {

                if ( !isset( $field['element'] ) || $field['element'] != 'payment_method' ) {
                    continue;
                }

                if ( !isset( $field['settings']['payment_methods'] ) ) {
                    continue;
                }

                foreach ( $field['settings']['payment_methods'] as $paymentMethod ) {
                    if ( isset( $paymentMethod['method_value'] ) && $paymentMethod['method_value'] == $this->method ) {
                        return true;
                    }

                    continue;
                }
            }
        }

        return false;

    }

    // Get list of banks from ValuePay
    private function getBanks( $formId ) {

        $banks = get_transient( 'valuepay_fluentform_banks' );

        if ( !$banks || !is_array( $banks ) ) {
            $banks = array();

            try {
                $settings = valuepay_fluentform_get_settings( $formId );

                $valuepay = new Valuepay_Fluentform_API(
                    $settings['username'],
                    $settings['app_key'],
                    $settings['app_secret']
                );

                $banksQuery = $valuepay->get_banks( array(
                    'username' => $valuepay->username,
                    'reqhash'  => md5( $valuepay->app_key . $valuepay->username ),
                ) );

                if ( isset( $banksQuery[1]['bank_list'] ) && !empty( $banksQuery[1]['bank_list'] ) ) {
                    $banks = $banksQuery[1]['bank_list'];

                    // Set transient, so that we can retrieve using transient
                    // instead of retrieve through API request to ValuePay.
                    set_transient( 'valuepay_fluentform_banks', $banks, DAY_IN_SECONDS );
                }
            } catch ( Exception $e ) {}
        }

        return $banks;

    }

    public function handlePaymentAction( $submissionId, $submissionData, $form, $methodSettings, $hasSubscriptions, $totalPayable ) {

        $this->setSubmissionId( $submissionId );
        $this->form = $form;
        $submission = $this->getSubmission();

        // Create the initial transaction here
        $transaction = $this->createInitialPendingTransaction( $submission );

        $this->handleRedirect( $transaction, $submission, $form, $methodSettings );

    }

    // Payment mode lways live, no sandbox account at the moment
    protected function getPaymentMode() {
        return 'live';
    }

    public function handleRedirect( $transaction, $submission, $form, $methodSettings ) {

        try {
            $currency = PaymentHelper::getFormCurrency( $form->id );

            if ( $currency !== 'MYR' ) {
                throw new Exception( 'Failed! Payment error: Currency not supported.' );
            }

            $paymentType = valuepay_fluentform_get_form_submission_value( $form, $submission, 'payment_type' );

            if ( $paymentType === 'recurring' ) {
                $paymentUrl = $this->getEnrolmentUrl( $transaction, $submission, $form );
            } else {
                $paymentUrl = $this->getBillUrl( $transaction, $submission, $form );
            }

            if ( !$paymentUrl ) {
                return;
            }

            do_action( 'ff_log_data', array(
                'parent_source_id' => $form->id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'info',
                'title'            => 'Redirect to ValuePay',
                'description'      => 'User redirect to ValuePay for completing the payment',
            ) );

            // Redirect to the payment page
            wp_send_json_success( array(
                'nextAction'   => 'payment',
                'actionName'   => 'normalRedirect',
                'redirect_url' => $paymentUrl,
                'message'      => __( 'You are redirecting to Valuepay.com to complete the purchase. Please wait while you are redirecting...', 'valuepay-fluentform' ),
                'result'       => array(
                    'insert_id' => $submission->id,
                ),
            ), 200 );

        } catch ( Exception $e ) {

            do_action( 'ff_log_data', array(
                'parent_source_id' => $form->id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => 'Valuepay Payment Redirect Error',
                'description'      => $e->getMessage(),
            ) );

            wp_send_json( array(
                'errors' => $e->getMessage()
            ), 400 );

        }

    }

    // Create an enrolment in ValuePay (for recurring payment)
    private function getEnrolmentUrl( $transaction, $submission, $form ) {

        // Payer info
        $payerName  = PaymentHelper::getCustomerName( $submission, $form );
        $payerPhone = isset( $submission->response['phone'] ) ? $submission->response['phone'] : null;

        if ( !$payerName ) {
            throw new Exception( __( 'Name is required.', 'valuepay-fluentform' ) );
        }

        if ( !$payerPhone ) {
            throw new Exception( __( 'Phone is required.', 'valuepay-fluentform' ) );
        }

        if ( !$transaction->payer_email ) {
            throw new Exception( __( 'Email is required', 'valuepay-fluentform' ) );
        }

        // Identity information and bank code
        $identityType  = valuepay_fluentform_get_form_submission_value( $form, $submission, 'identity_type' );
        $identityValue = valuepay_fluentform_get_form_submission_value( $form, $submission, 'identity_value' );
        $bank          = valuepay_fluentform_get_form_submission_value( $form, $submission, 'bank' );

        if ( !$identityType || !$identityValue ) {
            throw new Exception( __( 'Identity information is required for recurring payment', 'valuepay-fluentform' ) );
        }

        if ( !$bank ) {
            throw new Exception( __( 'Bank is required', 'valuepay-fluentform' ) );   
        }

        //////////////////////////////////////////////////////////////////////

        $settings = valuepay_fluentform_get_settings( $form->id );

        $params = array(
            'username'        => $settings['username'],
            'sub_fullname'    => $payerName,
            'sub_ident_type'  => $identityType,
            'sub_ident_value' => $identityValue,
            'sub_telephone'   => $payerPhone,
            'sub_email'       => $transaction->payer_email,
            'sub_mandate_id'  => $settings['mandate_id'],
            'sub_bank_id'     => $bank,
            'sub_amount'      => (float) ( $transaction->payment_total / 100 ),
        );

        $hashData = array(
            $settings['app_key'],
            $settings['username'],
            $params['sub_fullname'],
            $params['sub_ident_type'],
            $params['sub_telephone'],
            $params['sub_email'],
            $params['sub_mandate_id'],
            $params['sub_bank_id'],
            $params['sub_amount'],
        );

        $params['reqhash'] = md5( implode( '', array_values( $hashData ) ) );

        //////////////////////////////////////////////////////////////////////

        $valuepay = new Valuepay_Fluentform_API(
            $settings['username'],
            $settings['app_key'],
            $settings['app_secret']
        );

        list( $code, $response ) = $valuepay->set_enrol_data( $params );

        if ( isset( $response['method'] ) && isset( $response['method'] ) == 'GET' && isset( $response['action'] ) ) {

            // Payment details
            $updateData = array(
                'payment_note' => maybe_serialize( $response ),
                'charge_id'    => null,
            );

            $status = 'processing';

            $this->updateTransaction( $transaction->id, $updateData );
            $this->changeSubmissionPaymentStatus( $status );
            $this->changeTransactionStatus( $transaction->id, $status );
            $this->recalculatePaidTotal();
            $this->completePaymentSubmission( false );
            $this->setMetaData( 'is_form_action_fired', 'yes' );

            return $response['action'];
        }

        return false;

    }

    // Create a bill in ValuePay (for one time payment)
    private function getBillUrl( $transaction, $submission, $form ) {

        // Payer info
        $payerName  = PaymentHelper::getCustomerName( $submission, $form );
        $payerPhone = isset( $submission->response['phone'] ) ? $submission->response['phone'] : null;

        if ( !$payerName ) {
            throw new Exception( __( 'Name is required.', 'valuepay-fluentform' ) );
        }

        if ( !$payerPhone ) {
            throw new Exception( __( 'Phone is required.', 'valuepay-fluentform' ) );
        }

        if ( !$transaction->payer_email ) {
            throw new Exception( __( 'Email is required', 'valuepay-fluentform' ) );
        }

        //////////////////////////////////////////////////////////////////////

        $settings = valuepay_fluentform_get_settings( $form->id );

        $redirectUrl = add_query_arg( array(
            'fluentform_payment' => $submission->id,
            'payment_method'     => $this->method,
            'transaction_hash'   => $transaction->transaction_hash,
        ), site_url( '/' ) );

        $ipnDomain = site_url( 'index.php' );

        if ( defined( 'FLUENTFORM_PAY_IPN_DOMAIN' ) && FLUENTFORM_PAY_IPN_DOMAIN ) {
            $ipnDomain = FLUENTFORM_PAY_IPN_DOMAIN;
        }

        $callbackUrl = add_query_arg( array(
            'fluentform_payment_api_notify' => 1,
            'payment_method'                => $this->method,
            'submission_id'                 => $submission->id,
            'transaction_hash'              => $transaction->transaction_hash,
        ), $ipnDomain );

        $params = array(
            'username'          => $settings['username'],
            'orderno'           => $submission->id,
            'bill_amount'       => (float) ( $transaction->payment_total / 100 ),
            'collection_id'     => $settings['collection_id'],
            'buyer_data'        => array(
                'buyer_name'    => $payerName,
                'mobile_number' => $payerPhone,
                'email'         => $transaction->payer_email,
            ),
            'bill_frontend_url' => $redirectUrl,
            'bill_backend_url'  => $callbackUrl,
        );

        $hashData = array(
            $settings['app_key'],
            $settings['username'],
            $params['bill_amount'],
            $params['collection_id'],
            $params['orderno'],
        );

        $params['reqhash'] = md5( implode( '', array_values( $hashData ) ) );

        //////////////////////////////////////////////////////////////////////

        $valuepay = new Valuepay_Fluentform_API(
            $settings['username'],
            $settings['app_key'],
            $settings['app_secret']
        );

        list( $code, $response ) = $valuepay->create_bill( $params );

        if ( isset( $response['bill_id'] ) ) {
            Helper::setSubmissionMeta( $submission->id, '_valuepay_payment_id', $response['bill_id'] );
        }

        if ( isset( $response['bill_url'] ) ) {
            return $response['bill_url'];
        }

        return false;

    }

    public function handleIPN() {

        if ( !isset( $_REQUEST['submission_id'] ) || empty( $_REQUEST['submission_id'] ) ) {
            return false;
        }

        if ( !isset( $_REQUEST['payment_method'] ) || empty( $_REQUEST['payment_method'] ) ) {
            return false;
        }

        if ( !isset( $_REQUEST['transaction_hash'] ) || empty( $_REQUEST['transaction_hash'] ) ) {
            return false;
        }

        if ( !isset( $_REQUEST['fluentform_payment_api_notify'] ) || empty( $_REQUEST['fluentform_payment_api_notify'] ) ) {
            return false;
        }

        $submissionId = absint( $_REQUEST['submission_id'] );
        $transactionHash = sanitize_text_field( $_REQUEST['transaction_hash'] );

        $this->setSubmissionId( $submissionId );

        $form        = $this->getForm();
        $submission  = $this->getSubmission();
        $transaction = $this->getTransaction( $transactionHash, 'transaction_hash' );

        if ( !$transaction || $transaction->payment_method !== $this->method ) {
            return false;
        }

        if ( $transaction->status == 'paid' ) {
            wp_send_json_error();
        }

        try {

            $settings = valuepay_fluentform_get_settings( $form->id );

            $valuepay = new Valuepay_Fluentform_API(
                $settings['username'],
                $settings['app_key'],
                $settings['app_secret']
            );

            $response = $valuepay->get_ipn_response();

            // Verify submission ID
            if ( absint( $response['orderno'] ) !== $submissionId ) {
                wp_send_json_error();
            }

            if ( $valuepay->validate_ipn_response( $response ) ) {

                do_action( 'ff_log_data', array(
                    'parent_source_id' => $form->id,
                    'source_type'      => 'submission_item',
                    'source_id'        => $submission->id,
                    'component'        => 'Payment',
                    'status'           => 'success',
                    'title'            => 'Valuepay Payment Callback Success',
                    'description'      => 'Valid response data',
                ) );

                // Update payment status to paid
                if ( isset( $response['bill_status'] ) && $response['bill_status'] === 'paid' ) {
                    $this->handlePayment( $submission, $response );
                    wp_send_json_success();
                }
            }

        } catch ( Exception $e ) {

            do_action( 'ff_log_data', array(
                'parent_source_id' => $form->id,
                'source_type'      => 'submission_item',
                'source_id'        => $submission->id,
                'component'        => 'Payment',
                'status'           => 'error',
                'title'            => 'Valuepay Payment Callback Error',
                'description'      => $e->getMessage(),
            ) );

            wp_send_json_error( array( 'message' => $e->getMessage() ), 400 );

        }

        wp_send_json_error();

    }

    public function handlePayment( $submission, $response ) {

        $this->setSubmissionId( $submission->id );
        $transaction = $this->getLastTransaction( $submission->id );

        if ( !$transaction || $transaction->payment_method !== $this->method ) {
            return false;
        }

        // Get payment ID
        $paymentId = Helper::getSubmissionMeta( $submission->id, '_valuepay_payment_id' );

        // Verify payment ID
        if ( $response['bill_id'] !== $paymentId ) {
            throw new Exception( __( 'Invalid payment ID' ) );
        }

        switch ( $response['bill_status'] ) {
            case 'paid':
                $status = 'paid';
                break;

            case 'failed':
            case 'cancel':
                $status = 'failed';
                break;

            default:
                $status = 'pending';
                break;
        }

        // Payment details
        $updateData = array(
            'payment_note' => maybe_serialize( $response ),
            'charge_id'    => $response['bill_id'],
        );

        $this->updateTransaction( $transaction->id, $updateData );
        $this->changeSubmissionPaymentStatus( $status );
        $this->changeTransactionStatus( $transaction->id, $status );
        $this->recalculatePaidTotal();
        $this->completePaymentSubmission( false );
        $this->setMetaData( 'is_form_action_fired', 'yes' );

    }

    public function validateSubmittedItems( $paymentItems, $form, $formData, $subscriptionItems ) {

        if ( count( $subscriptionItems ) ) {
            wp_send_json( array(
                'errors' => __('Valuepay Error: ValuePay does not support subscriptions right now!', 'valuepay-fluentform')
            ), 423 );
        }

    }

    public function handleSessionRedirectBack( $data ) {

        $submissionId = intval( $data['fluentform_payment'] );
        $this->setSubmissionId( $submissionId );

        $submission = $this->getSubmission();

        $transactionHash = sanitize_text_field( $data['transaction_hash'] );
        $transaction = $this->getTransaction( $transactionHash, 'transaction_hash' );

        if ( !$transaction || !$submission ) {
            return;
        }

        $type = $transaction->status;
        $form = $this->getForm();

        if ( $type == 'paid' ) {
            $returnData = $this->getReturnData();
        } else {
            $returnData = [
                'insert_id' => $submission->id,
                'title'     => __( 'Payment was not marked as paid', 'valuepay-fluentform' ),
                'result'    => false,
                'error'     => __( 'Looks like you have is still on pending status', 'valuepay-fluentform' ),
            ];
        }

        $returnData['type'] = 'success';
        $returnData['is_new'] = false;

        $this->showPaymentView( $returnData );

    }

}
