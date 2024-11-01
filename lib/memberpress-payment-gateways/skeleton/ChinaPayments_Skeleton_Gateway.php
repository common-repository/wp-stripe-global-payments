<?php

use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use ChinaPayments\Request as CP_Request;
use ChinaPayments\ThirdPartyIntegration\Freemius as CP_Freemius;
abstract class ChinaPayments_Skeleton_Gateway extends MeprBaseRealGateway {
    public $payment_failed_error = '';

    public $account_page_id_str = '';

    public $login_page_id_str = '';

    public $thankyou_page_id_str = '';

    public abstract function get_stripe_payment_method_alias();

    public function china_payments_get_payment_intent_redirect_url( $payment_intent_information, \MeprTransaction $txn ) : string {
        return rest_url( CHINA_PAYMENTS_REST_API_PREFIX . '/v1/third-party-integration/memberpress/complete-payment/' . $txn->trans_num );
    }

    public function china_payments_get_payment_intent_information( \MeprTransaction $txn ) {
        $mepr_options = MeprOptions::fetch();
        $stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe' );
        $stripe_customer_id = $this->china_payments_get_stripe_customer_id( $txn );
        $response = null;
        if ( !isset( $response['payment_intent_id'] ) ) {
            $response = API_ChinaPayments::instance()->request( 'stripe/payment-intent-or-setup', [
                'account_id'                  => $stripeIntegration->get_account_id(),
                'customer_id'                 => $stripe_customer_id,
                'payment_method'              => $this->get_stripe_payment_method_alias(),
                'price_information'           => [
                    'is_recurring' => 0,
                    'amount'       => intval( floatval( $txn->total ) * 100 ),
                    'currency'     => strtolower( $mepr_options->currency_code ),
                ],
                'is_live'                     => intval( $stripeIntegration->is_live() ),
                'meta_data'                   => [
                    'cp_integration'     => 'memberpress',
                    'domain_name'        => china_payments_domain_name(),
                    'mp_transaction_num' => $txn->trans_num,
                ],
                'mandate_customer_acceptance' => [
                    'ip_address' => china_payments_http_ip_address(),
                    'user_agent' => china_payments_http_user_agent(),
                ],
                'secret_key'                  => china_payments_encrypt( $stripeIntegration->get_secret_key(), CP_Freemius::instance()->get_anonymous_id(), md5( get_site_url() ) ),
            ], 'POST' );
            if ( !isset( $response['payment_intent_id'] ) ) {
                return new \WP_Error('rest_error', esc_html( $response['message'] ?? 'Unexpected Error' ), [
                    'status' => 400,
                ]);
            }
        }
        $txn->update_meta( 'china_payments_stripe_method_alias', $this->get_stripe_payment_method_alias() );
        $txn->update_meta( 'china_payments_is_live', $stripeIntegration->is_live() );
        $txn->update_meta( 'china_payments_payment_intent_id', $response['payment_intent_id'] );
        $txn->update_meta( 'china_payments_payment_intent_secret', $response['payment_intent_secret'] );
        $txn->update_meta( 'china_payments_payment_intent_requires_action', $response['payment_intent_requires_action'] ?? 0 );
        return $response;
    }

    public function china_payments_get_stripe_customer_id( \MeprTransaction $txn ) {
        $mepr_options = MeprOptions::fetch();
        $user = get_user_by( 'ID', $txn->user_id );
        return CP_PaymentGateway::get_integration_from_settings( 'stripe' )->get_customer_id( [
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email_address' => $user->user_email,
            'currency'      => strtolower( $mepr_options->currency_code ),
        ] );
    }

    public function validate_payment_form( $errors ) {
        $txn = new MeprTransaction($_POST['mepr_transaction_id']);
        $payment_intent_information = $this->china_payments_get_payment_intent_information( $txn );
        if ( is_wp_error( $payment_intent_information ) ) {
            return [$payment_intent_information->get_error_message()];
        }
        return [];
    }

    /**
     * @param \MeprTransaction $txn
     * @return void
     * @throws Exception
     */
    public function process_payment_form( $txn ) {
        $payment_intent_information = [
            'payment_intent_id'              => $txn->get_meta( 'china_payments_payment_intent_id', true ),
            'payment_intent_secret'          => $txn->get_meta( 'china_payments_payment_intent_secret', true ),
            'payment_intent_requires_action' => intval( $txn->get_meta( 'china_payments_payment_intent_requires_action', true ) ),
        ];
        if ( empty( $payment_intent_information['payment_intent_id'] ) || empty( $payment_intent_information['payment_intent_id'] ) ) {
            $payment_intent_information = $this->china_payments_get_payment_intent_information( $txn );
        }
        if ( is_wp_error( $payment_intent_information ) ) {
            throw new \Exception($payment_intent_information->get_error_message());
        }
        $redirect_location = $this->china_payments_get_payment_intent_redirect_url( $payment_intent_information, $txn );
        wp_redirect( $redirect_location );
        exit;
    }

    public function display_payment_form(
        $amount,
        $user,
        $product_id,
        $transaction_id
    ) {
        china_payments_register_universal_interface();
        $txn = new MeprTransaction(intval( $transaction_id ));
        if ( empty( $txn->get_meta( 'china_payments_payment_intent_id', true ) ) ) {
            $response = $this->china_payments_get_payment_intent_information( $txn );
            if ( is_wp_error( $response ) ) {
                echo '<div data-china-payments-notification="danger">' . esc_html( $response->get_error_message() ) . '</div>';
                return;
            }
        }
        CP_Request::instance()->set_request_setting( 'trans_id', $transaction_id );
        require_once CHINA_PAYMENTS_BASE_PATH . '/templates/memberpress-payment-handler.php';
        return;
    }

    public function display_payment_page( $txn ) {
        return;
    }

    public function load( $settings ) {
        $this->settings = (object) $settings;
        $this->set_defaults();
    }

    /**
     *  Set default plugin settings
     */
    protected function set_defaults() {
        if ( !isset( $this->settings ) ) {
            $this->settings = array();
        }
        $this->settings = (object) array_merge( array(
            'gateway'              => $this->cp_gateway_id,
            'id'                   => $this->generate_id(),
            'label'                => $this->name,
            'icon'                 => $this->icon,
            'use_label'            => true,
            'use_icon'             => true,
            'desc'                 => $this->desc,
            'use_desc'             => true,
            'email'                => '',
            'sandbox'              => false,
            'force_ssl'            => true,
            'payment_failed_error' => '',
        ), (array) $this->settings );
        $this->id = $this->settings->id;
        $this->label = $this->settings->label;
        $this->desc = $this->settings->desc;
        $this->icon = $this->settings->icon;
        $this->use_label = $this->settings->use_label;
        $this->use_icon = $this->settings->use_icon;
        $this->use_desc = $this->settings->use_desc;
        $this->payment_failed_error = $this->settings->payment_failed_error;
    }

    /**
     *  Used to send data to a given payment gateway.
     */
    public function process_payment( $txn ) {
    }

    public function enqueue_payment_form_scripts() {
        china_payments_register_universal_interface();
    }

    public function display_options_form() {
        $mepr_options = MeprOptions::fetch();
        ?>
    <table>
      <tr>
        <td colspan="2">
    <?php 
        echo '<p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . CHINA_PAYMENTS_MENU_SLUG ) ) . '#payment-gateways" target="_blank">';
        echo __( "Manage Payment Gateway Connection Setting s>", "china-payments" );
        echo '</a>';
        echo '</p>';
        ?>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <label><?php 
        _e( 'Description', 'memberpress' );
        ?></label><br/>
          <textarea name="<?php 
        echo $mepr_options->integrations_str;
        ?>[<?php 
        echo $this->id;
        ?>][desc]" rows="3" cols="45"><?php 
        echo stripslashes( $this->settings->desc );
        ?></textarea>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <label><?php 
        _e( 'Payment Failed', 'memberpress' );
        ?></label><br/>
          <textarea name="<?php 
        echo $mepr_options->integrations_str;
        ?>[<?php 
        echo $this->id;
        ?>][payment_failed_error]" rows="3" cols="45"><?php 
        echo stripslashes( $this->settings->payment_failed_error );
        ?></textarea>
        </td>
      </tr>
    </table>
    <?php 
    }

    public function record_payment() {
        return;
    }

    public function process_refund( MeprTransaction $txn ) {
        return;
    }

    public function record_refund() {
        return;
    }

    public function record_subscription_payment() {
        return;
    }

    /** Used to record a declined payment. */
    public function record_payment_failure() {
        return;
    }

    public function process_trial_payment( $txn ) {
        return;
    }

    public function record_trial_payment( $txn ) {
        return;
    }

    public function process_create_subscription( $txn ) {
        return;
    }

    public function record_create_subscription() {
        return;
    }

    public function process_update_subscription( $subscription_id ) {
        return;
    }

    public function record_update_subscription() {
        return;
    }

    public function process_suspend_subscription( $subscription_id ) {
        return;
    }

    public function record_suspend_subscription() {
        return;
    }

    public function process_resume_subscription( $subscription_id ) {
        return;
    }

    public function record_resume_subscription() {
        return;
    }

    public function process_cancel_subscription( $subscription_id ) {
        return;
    }

    public function record_cancel_subscription() {
        return;
    }

    public function process_signup_form( $txn ) {
        return;
    }

    public function validate_options_form( $errors ) {
        return [];
    }

    public function display_update_account_form( $subscription_id, $errors = array(), $message = "" ) {
        return;
    }

    public function validate_update_account_form( $errors = array() ) {
        return [];
    }

    public function process_update_account_form( $subscription_id ) {
        return;
    }

    public function is_test_mode() {
        return isset( $this->settings->test_mode ) && $this->settings->test_mode;
    }

    public function force_ssl() {
        return true;
    }

    public static function gateways_dropdown( $field_name, $curr_gateway, $obj_id ) {
        return;
    }

}
