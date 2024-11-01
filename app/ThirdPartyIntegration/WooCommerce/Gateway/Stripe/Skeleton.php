<?php

namespace ChinaPayments\ThirdPartyIntegration\WooCommerce\Gateway\Stripe;

use WC_Order;
use WC_Payment_Gateway;
use WC_Blocks_Utils;
use ChinaPayments\ThirdPartyIntegration\WooCommerce as CP_WooCommerce;
use ChinaPayments\Template as CP_Template;
use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use ChinaPayments\Request as CP_Request;
use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\ThirdPartyIntegration\Freemius as CP_Freemius;
use Automattic\WooCommerce\Blocks\StoreApi\Routes\RouteException;
abstract class Skeleton extends WC_Payment_Gateway {
    public $supports = array('products');

    public function __construct() {
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options') );
        if ( $this->is_available() ) {
            add_action( 'wp_enqueue_scripts', array($this, '_wp_enqueue_scripts') );
        }
        if ( isset( $_GET['china-payments-intent-id'] ) && $_GET['china-payments-intent-secret'] || isset( $_GET['china-payments-setup-id'] ) && $_GET['china-payments-setup-secret'] ) {
            $this->_handle_payment_flow();
        }
    }

    public function is_available() {
        if ( !in_array( $this->get_order_currency(), $this->get_stripe_supported_currencies() ) ) {
            return false;
        }
        return parent::is_available();
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'                => array(
                'title'       => __( 'Is Enabled', 'china-payments' ),
                'label'       => sprintf( __( 'Enable %s', 'china-payments' ), $this->get_method_title() ),
                'type'        => 'checkbox',
                'description' => sprintf( __( 'Available for currencies : %s', 'china-payments' ), implode( ', ', $this->get_stripe_supported_currencies() ) ),
                'default'     => 'yes',
            ),
            'title'                  => array(
                'title'       => __( 'Title', 'china-payments' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'china-payments' ),
                'default'     => $this->default_name(),
                'desc_tip'    => true,
            ),
            'description'            => array(
                'title'       => __( 'Description', 'china-payments' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which the user sees during checkout.', 'china-payments' ),
                'desc_tip'    => true,
            ),
            'checkout_error_message' => array(
                'title'       => __( 'Error Message', 'china-payments' ),
                'type'        => 'text',
                'description' => __( 'Payment failed error message.', 'china-payments' ),
                'desc_tip'    => true,
                'default'     => 'Payment failed, returning to payment method selection.',
            ),
        );
        $this->form_fields = apply_filters( 'china_payments_woocommerce_gateway_form_fields', $this->form_fields, $this->id );
    }

    public function process_payment( $order_id ) {
        $order_id = (int) $order_id;
        $order = new WC_Order($order_id);
        $payment_intent_information = $this->get_payment_intent_information( $order );
        if ( is_wp_error( $payment_intent_information ) ) {
            throw new \Exception($payment_intent_information->get_error_code(), $payment_intent_information->get_error_message());
        }
        return array(
            'result'   => 'success',
            'redirect' => $this->get_payment_intent_redirect_url( $payment_intent_information, $order ),
        );
    }

    public abstract function default_name();

    public abstract function get_stripe_payment_method_alias();

    public abstract function get_supported_currencies();

    public function get_stripe_supported_currencies() {
        $stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe' );
        return array_intersect( array(strtoupper( $stripeIntegration->get_default_currency() ), 'CNY'), $this->get_supported_currencies() );
    }

    public function get_payment_intent_redirect_url( $payment_intent_information, $order ) : string {
        return add_query_arg( 'china-payments-gateway', 'stripe', add_query_arg( 'china-payments-method', $this->get_stripe_payment_method_alias(), add_query_arg( 'china-payments-intent-id', $payment_intent_information['payment_intent_id'], add_query_arg( 'china-payments-intent-secret', $payment_intent_information['payment_intent_secret'], wc_get_checkout_url() ) ) ) );
    }

    public function get_setup_intent_redirect_url( $setup_intent_information, $order ) : string {
        return add_query_arg( 'china-payments-gateway', 'stripe', add_query_arg( 'china-payments-method', $this->get_stripe_payment_method_alias(), add_query_arg( 'china-payments-setup-id', $setup_intent_information['setup_intent_id'], add_query_arg( 'china-payments-setup-secret', $setup_intent_information['setup_intent_secret'], wc_get_checkout_url() ) ) ) );
    }

    public function get_payment_intent_information( WC_Order $order ) {
        $stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe' );
        $stripe_customer_id = $this->get_order_stripe_customer_id( $order );
        $response = null;
        if ( !isset( $response['payment_intent_id'] ) ) {
            $response = API_ChinaPayments::instance()->request( 'stripe/payment-intent-or-setup', array(
                'account_id'                  => $stripeIntegration->get_account_id(),
                'customer_id'                 => $stripe_customer_id,
                'payment_method'              => $this->get_stripe_payment_method_alias(),
                'price_information'           => array(
                    'is_recurring' => 0,
                    'amount'       => intval( floatval( $order->get_total() ) * 100 ),
                    'currency'     => $order->get_currency(),
                ),
                'is_live'                     => intval( $stripeIntegration->is_live() ),
                'meta_data'                   => array(
                    'cp_integration' => 'woocommerce',
                    'domain_name'    => china_payments_domain_name(),
                    'order_id'       => $order->get_id(),
                ),
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
        $order->update_meta_data( 'china_payments_is_live', $stripeIntegration->is_live() );
        $order->update_meta_data( 'china_payments_payment_intent_id', $response['payment_intent_id'] );
        $order->update_meta_data( 'china_payments_payment_intent_secret', $response['payment_intent_secret'] );
        $order->save_meta_data();
        return $response;
    }

    public function get_setup_intent_information( WC_Order $order, $setupIntentData ) {
        return new \WP_Error('rest_error', sprintf( __( 'Recurring Payments are not enabled in %s' ), CHINA_PAYMENTS_NAME ), array(
            'status' => 400,
        ));
    }

    public function _wp_enqueue_scripts() {
        if ( !is_checkout() ) {
            return;
        }
        china_payments_register_universal_interface();
    }

    public function get_order_stripe_metadata( WC_Order $order ) {
        return array(
            'cp_integration' => 'woocommerce',
            'domain_name'    => china_payments_domain_name(),
            'order_id'       => $order->get_id(),
        );
    }

    public function get_order_stripe_customer_id( WC_Order $order ) {
        return CP_PaymentGateway::get_integration_from_settings( 'stripe' )->get_customer_id( array(
            'first_name'    => $order->get_billing_first_name(),
            'last_name'     => $order->get_billing_last_name(),
            'email_address' => $order->get_billing_email(),
            'currency'      => $order->get_currency(),
        ) );
    }

    public function get_order_currency( $order = null ) {
        if ( empty( $order ) ) {
            $order_id = absint( get_query_var( 'order-pay' ) );
            if ( !empty( $order_id ) ) {
                $order = wc_get_order( $order_id );
            }
        }
        if ( !empty( $order ) && is_object( $order ) && method_exists( $order, 'get_currency' ) ) {
            return strtolower( $order->get_currency() );
        }
        return CP_WooCommerce::instance()->get_current_currency();
    }

    public function _handle_payment_flow() {
        if ( !function_exists( 'WC' ) ) {
            return;
        }
        if ( !isset( WC()->session ) ) {
            return;
        }
        if ( !method_exists( WC()->session, 'get' ) ) {
            return;
        }
        $order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
        if ( empty( $order_id ) ) {
            $order_id = absint( WC()->session->get( 'store_api_draft_order' ) );
        }
        if ( empty( $order_id ) ) {
            return;
        }
        CP_Request::instance()->set_request_setting( 'checkout_order_id', $order_id );
        CP_Request::instance()->set_request_setting( 'checkout_error_message', $this->get_option( 'checkout_error_message' ) );
        if ( WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' ) ) {
            add_filter( 'template_include', function () {
                return CP_Template::locate_template( 'template-woocommerce-payment-handler.php' );
            }, 1000 );
        } else {
            add_filter(
                'wc_get_template',
                function (
                    $template,
                    $template_name,
                    $args,
                    $template_path,
                    $default_path
                ) {
                    if ( $template_name === 'checkout/form-checkout.php' ) {
                        return CP_Template::locate_template( 'woocommerce-payment-handler.php' );
                    }
                    return $template;
                },
                1000,
                5
            );
        }
    }

    public function _top_level_order( \WC_Order $order ) {
        if ( $order->get_meta( 'china_payments_is_live' ) !== '' ) {
            return $order;
        }
        if ( !empty( $order->get_meta( '_subscription_renewal' ) ) ) {
            $subscription = wcs_get_subscription( intval( $order->get_meta( '_subscription_renewal' ) ) );
            if ( !empty( $subscription ) ) {
                return $this->_top_level_order( wc_get_order( intval( $subscription->get_parent_id() ) ) );
            }
        }
        if ( !empty( $order->get_parent_id() ) ) {
            return new \WC_Order($order->get_parent_id());
        }
        return null;
    }

}
