<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce\Gateway\Stripe;

use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use WC_Order;
use Exception;
class AliPay extends Skeleton implements Structure\Subscription {
    public $id = 'china_payments_stripe_alipay';

    public $has_fields = false;

    public function __construct() {
        $this->method_title = sprintf( __( '%s - Alipay', 'china-payments' ), CHINA_PAYMENTS_NAME );
        $this->method_description = __( 'Accept Alipay payments using your Stripe account.', 'china-payments' );
        $this->method_description .= '<br/>';
        $this->icon = plugins_url( 'interface/img/payment-gateway/payment-method-alipay.svg', CHINA_PAYMENTS_BASE_FILE_PATH );
        parent::__construct();
    }

    public function default_name() {
        return 'Alipay';
    }

    public function get_supported_currencies() {
        return apply_filters( 'china_payments_woocommerce_stripe_alipay_currencies', array(
            'CNY',
            'AUD',
            'CAD',
            'EUR',
            'GBP',
            'HKD',
            'JPY',
            'SGD',
            'MYR',
            'NZD',
            'USD'
        ) );
    }

    public function get_stripe_payment_method_alias() {
        return 'alipay';
    }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields['title']['default'] = __( 'Alipay', 'china-payments' );
        $this->form_fields['description']['default'] = __( 'You will be redirected to the Alipay website to complete checkout.', 'china-payments' );
    }

    public function process_payment( $order_id ) {
        $order_id = (int) $order_id;
        $order = new WC_Order($order_id);
        return parent::process_payment( $order_id );
    }

    public function order_charge_using_setup_intent( WC_Order $order ) {
        if ( $order->is_paid() ) {
            return;
        }
        $top_level_order = $this->_top_level_order( $order );
        $customer_id = $top_level_order->get_meta( 'china_payments_customer_id' );
        $payment_method = $top_level_order->get_meta( 'china_payments_payment_method' );
        $stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe', intval( $top_level_order->get_meta( 'china_payments_is_live' ) ) );
        $payment_intent = $stripeIntegration->stripeClient()->paymentIntents->create( array(
            'amount'               => intval( floatval( $order->get_total() ) * 100 ),
            'currency'             => strtolower( $order->get_currency() ),
            'payment_method_types' => array('alipay'),
            'confirm'              => true,
            'off_session'          => true,
            'payment_method'       => $payment_method,
            'customer'             => $customer_id,
            'metadata'             => $this->get_order_stripe_metadata( $order ),
        ) );
        if ( isset( $payment_intent->status ) && $payment_intent->status === 'succeeded' ) {
            $order->update_meta_data( 'china_payments_is_live', intval( $top_level_order->get_meta( 'china_payments_is_live' ) ) );
            $order->update_meta_data( 'china_payments_charge_payment_intent_id', $payment_intent->id );
            $order->payment_complete( $payment_intent->id );
            $order->save();
        }
    }

    public function get_transaction_url( $order ) {
        $this->view_transaction_url = 'https://dashboard.stripe.com';
        if ( !intval( $order->get_meta( 'china_payments_is_live' ) ) ) {
            $this->view_transaction_url .= '/test';
        }
        $this->view_transaction_url .= '/payments/%s';
        return parent::get_transaction_url( $order );
    }

}
