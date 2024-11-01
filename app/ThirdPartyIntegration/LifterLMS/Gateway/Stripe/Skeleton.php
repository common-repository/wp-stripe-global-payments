<?php

namespace ChinaPayments\ThirdPartyIntegration\LifterLMS\Gateway\Stripe;

use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use ChinaPayments\ThirdPartyIntegration\Freemius as CP_Freemius;
use ChinaPayments\Template as CP_Template;
use LLMS_Coupon;
use LLMS_Order;
use LLMS_Payment_Gateway;
use WP_Error;
abstract class Skeleton extends LLMS_Payment_Gateway {
    /**
     * URL to the Stripe Dashboard
     *
     * @var  string
     */
    const DASHBOARD_URL = 'https://dashboard.stripe.com/';

    /**
     * Maximum transaction amount (in cents)
     *
     * @link https://support.stripe.com/questions/what-is-the-maximum-amount-i-can-charge-with-stripe
     *
     * @var int
     */
    const MAX_AMOUNT = 99999999;

    public function __construct() {
        $this->supports = array(
            'checkout_fields'    => true,
            'refunds'            => false,
            'single_payments'    => true,
            'recurring_payments' => false,
            'recurring_retry'    => false,
            'test_mode'          => false,
        );
        $this->admin_order_fields = wp_parse_args( array(
            'customer' => true,
            'source'   => true,
        ), $this->admin_order_fields );
        // add stripe specific fields.
        add_filter(
            'llms_get_gateway_settings_fields',
            array($this, 'settings_fields'),
            10,
            2
        );
        add_action( 'lifterlms_checkout_confirm_after_payment_method', array($this, 'confirm_order_html') );
        add_filter( 'llms_gateway_' . $this->id . '_show_confirm_order_button', '__return_false' );
    }

    /**
     * Output additional HTML on the order confirmation screen
     *
     * This will only ever happen for orders
     *
     * @since 5.0.0
     *
     * @param string $gateway_id ID of the payment gateway.
     * @return void
     */
    public function confirm_order_html( $gateway_id ) {
        // @todo HERE
        if ( $this->id !== $gateway_id ) {
            return;
        }
        $key = llms_filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
        $order = llms_get_order_by_key( $key );
        // Only proceed for orders using Stripe.
        if ( !$order || $this->id !== $order->get( 'payment_gateway' ) ) {
            return;
        }
        if ( isset( $_GET['payment_intent'] ) ) {
            $stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe' );
            $payment_intent_id = get_post_meta( $order->id, 'china_payments_payment_intent_id', true );
            if ( $payment_intent_id === $_GET['payment_intent'] ) {
                try {
                    $payment_intent = $stripeIntegration->stripeClient()->paymentIntents->retrieve( $payment_intent_id );
                    if ( $payment_intent->status !== 'succeeded' ) {
                        llms_get_template( 'notices/error.php', array(
                            'messages' => array(__( 'Order Payment Intent Failed', 'china-payments' )),
                        ) );
                        echo '<a class="llms-field-button llms-button-action" style="display:block;text-align: center;margin-bottom:10px;" href="' . esc_url( llms_confirm_payment_url( $order->get( 'order_key' ) ) ) . '">' . __( 'Retry', 'china-payments' ) . '</a>';
                        return;
                    } else {
                        if ( $order->status !== 'completed' && $order->status !== 'llms-completed' ) {
                            $this->log( $this->get_admin_title() . ' `complete_transaction()` started', $order );
                            $order->record_transaction( array(
                                'amount'         => $order->total,
                                'transaction_id' => ( !empty( $payment_intent->latest_charge ) ? $payment_intent->latest_charge : $payment_intent->id ),
                            ) );
                            $order->set_status( 'completed' );
                            $redirect = $this->get_complete_transaction_redirect_url( $order );
                            $this->log( $this->get_admin_title() . ' `complete_transaction()` finished', $redirect, $order );
                            // Ensure notification processors get dispatched since shutdown wont be called.
                            do_action( 'llms_dispatch_notification_processors' );
                        }
                        llms_get_template( 'notices/success.php', array(
                            'messages' => array(__( 'Payment Received', 'china-payments' )),
                        ) );
                        echo '<script>window.location = "' . esc_url( $redirect ) . '"</script>';
                        echo '<a class="llms-field-button llms-button-action" style="display:block;text-align: center;margin-bottom:10px;" href="' . esc_url( $redirect ) . '">' . __( 'View Success Page', 'china-payments' ) . '</a>';
                        return;
                    }
                } catch ( \Exception $e ) {
                    llms_get_template( 'notices/error.php', array(
                        'messages' => array($e->getMessage()),
                    ) );
                    echo '<a class="llms-field-button llms-button-action" style="display:block;text-align: center;margin-bottom:10px;" href="' . esc_url( llms_confirm_payment_url( $order->get( 'order_key' ) ) ) . '">' . __( 'Retry', 'china-payments' ) . '</a>';
                    return;
                }
            }
        }
        china_payments_register_universal_interface();
        CP_Template::load_template( 'lifterlms-payment-handler.php', array(
            'order' => $order,
        ) );
    }

    /**
     * Return a URL to a customer on the Stripe Dashboard
     *
     * @since 4.0.0
     * @since 5.0.0 DRY: Use `$this->get_dashboard_url()`.
     *
     * @param string $customer_id Gateway's customer ID.
     * @param string $api_mode Link to either the live or test site for the gateway, where applicable.
     * @return string
     */
    public function get_customer_url( $customer_id, $api_mode = 'live' ) {
        // @todo HERE
        $url = $this->get_dashboard_url( $api_mode ) . 'customers/' . $customer_id;
        /**
         * Filter the URL to a Customer on the Stripe dashboard.
         *
         * @since 4.0.0
         *
         * @param string $url URL to the customer.
         * @param string $customer_id Gateway's customer ID.
         * @param string $api_mode Link to either the live or test site for the gateway, where applicable.
         */
        return apply_filters(
            'china_payments_stripe_get_customer_url',
            $url,
            $customer_id,
            $api_mode
        );
    }

    /**
     * Retrieve an api-mode aware Stripe Dashboard URL.
     *
     * @since 5.0.0
     *
     * @param string $api_mode Either 'live' or 'test'.
     * @return string
     */
    public function get_dashboard_url( $api_mode = 'live' ) {
        $url = self::DASHBOARD_URL;
        if ( 'test' === $api_mode ) {
            $url .= 'test/';
        }
        /**
         * Filter the URL to the Stripe dashboard.
         *
         * @since 5.0.0
         *
         * @param string $url URL to the customer.
         * @param string $api_mode Link to either the live or test site for the gateway, where applicable.
         */
        return apply_filters( 'china_payments_stripe_get_dashboard_url', $url, $api_mode );
    }

    /**
     * Return a URL to a charge on the Stripe Dashboard
     *
     * @since 4.0.0
     * @since 5.0.0 DRY: Use `$this->get_dashboard_url()`.
     *
     * @param string $transaction_id Gateway's transaction ID.
     * @param string $api_mode Link to either the live or test site for the gateway, where applicable.
     * @return string
     */
    public function get_transaction_url( $transaction_id, $api_mode = 'live' ) {
        $url = $this->get_dashboard_url( $api_mode ) . 'payments/' . $transaction_id;
        /**
         * Filter the URL to a transaction/payment on the Stripe dashboard
         *
         * @since 5.0.0
         *
         * @param string $url URL to the dashboard.
         * @param string $transaction_id Stripe payment ID
         * @param string $api_mode Link to either the live or test site for the gateway, where applicable.
         */
        return apply_filters(
            'china_payments_stripe_get_transaction_url',
            $url,
            $transaction_id,
            $api_mode
        );
    }

    /**
     * Handle a Pending Order
     * Called by LLMS_Controller_Orders->create_pending_order() on checkout form submission
     * All data will be validated before it's passed to this function
     *
     * @param \LLMS_Order        $order Order object.
     * @param \LLMS_Access_Plan  $plan Access plan object.
     * @param \LLMS_Student      $person Student object.
     * @param \LLMS_Coupon|false $coupon Coupon object or false when none is being used.
     * @return null
     */
    public function handle_pending_order(
        $order,
        $plan,
        $person,
        $coupon = false
    ) {
        $payment_intent_information = $this->_gateway_get_payment_intent_information( $order );
        if ( is_wp_error( $payment_intent_information ) ) {
            llms_add_notice( $payment_intent_information->get_error_message(), 'error' );
            return;
        }
        llms_redirect_and_exit( llms_confirm_payment_url( $order->get( 'order_key' ) ) );
    }

    public function settings_fields( $default_fields, $gateway_id ) {
        if ( $this->id !== $gateway_id ) {
            return $default_fields;
        }
        return $default_fields;
    }

    private function _gateway_get_payment_intent_information( \LLMS_Order $order ) {
        $stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe' );
        $stripe_customer_id = $this->_gateway_get_order_stripe_customer_id( $order );
        $response = null;
        if ( !isset( $response['payment_intent_id'] ) ) {
            $response = API_ChinaPayments::instance()->request( 'stripe/payment-intent-or-setup', array(
                'account_id'                  => $stripeIntegration->get_account_id(),
                'customer_id'                 => $stripe_customer_id,
                'payment_method'              => $this->get_stripe_payment_method_alias(),
                'price_information'           => array(
                    'is_recurring' => 0,
                    'amount'       => intval( floatval( $order->total ) * 100 ),
                    'currency'     => $order->currency,
                ),
                'is_live'                     => intval( $stripeIntegration->is_live() ),
                'meta_data'                   => $this->_gateway_get_order_stripe_metadata( $order ),
                'mandate_customer_acceptance' => array(
                    'ip_address' => china_payments_http_ip_address(),
                    'user_agent' => china_payments_http_user_agent(),
                ),
                'secret_key'                  => china_payments_encrypt( $stripeIntegration->get_secret_key(), CP_Freemius::instance()->get_anonymous_id(), md5( get_site_url() ) ),
            ), 'POST' );
            if ( !isset( $response['payment_intent_id'] ) ) {
                return new \WP_Error('rest_error', esc_html( $response['message'] ?? 'Unexpected Error' ), array(
                    'status' => 400,
                ));
            }
        }
        update_post_meta( $order->id, 'china_payments_is_live', $stripeIntegration->is_live() );
        update_post_meta( $order->id, 'china_payments_payment_intent_id', $response['payment_intent_id'] );
        update_post_meta( $order->id, 'china_payments_payment_intent_secret', $response['payment_intent_secret'] );
        return $response;
    }

    private function _gateway_get_order_stripe_metadata( \LLMS_Order $order ) {
        return array(
            'cp_integration' => 'lifterlms',
            'domain_name'    => china_payments_domain_name(),
            'order_id'       => $order->id,
        );
    }

    private function _gateway_get_order_stripe_customer_id( \LLMS_Order $order ) {
        return CP_PaymentGateway::get_integration_from_settings( 'stripe' )->get_customer_id( array(
            'first_name'    => $order->billing_first_name,
            'last_name'     => $order->billing_last_name,
            'email_address' => $order->billing_email,
            'currency'      => $order->currency,
        ) );
    }

}
