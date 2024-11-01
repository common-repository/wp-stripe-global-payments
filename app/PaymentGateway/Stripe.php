<?php

namespace ChinaPayments\PaymentGateway;

use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\Settings as API_Settings;
use ChinaPayments\Model\StripeCustomers as Model_Stripe_Customer;
use ChinaPayments\ThirdPartyIntegration\Freemius as CP_Freemius;
use Stripe\Stripe as Stripe_Library;
use Stripe\StripeClient;
use WP_Error;
class Stripe extends Skeleton {
    protected $_stripeClient;

    public static function setup_start_connection( $options ) : array {
        $response = API_ChinaPayments::instance()->request( 'stripe/connect', array(
            'is_live' => ( isset( $options['is_live'] ) ? intval( $options['is_live'] ) : 0 ),
        ), 'POST' );
        if ( !isset( $response['url'] ) ) {
            return array(
                'status' => 'error',
                'error'  => __( 'Could not determine the connection URL, please try again later.', 'china-payments' ),
            );
        }
        return array(
            'status' => 'ok',
            'type'   => 'redirect',
            'url'    => $response['url'],
        );
    }

    public static function save_master_credentials_response( $credentials ) : bool {
        if ( !is_array( $credentials ) ) {
            return false;
        }
        if ( !isset( $credentials['access_token'] ) || !isset( $credentials['stripe_publishable_key'] ) || !isset( $credentials['stripe_user_id'] ) || !isset( $credentials['is_live'] ) ) {
            return false;
        }
        API_Settings::instance()->update( array(
            'stripe_is_live'                                                                                      => intval( $credentials['is_live'] ),
            'stripe_' . (( intval( $credentials['is_live'] ) ? 'live' : 'test' )) . '_user_id'                    => $credentials['stripe_user_id'],
            'stripe_' . (( intval( $credentials['is_live'] ) ? 'live' : 'test' )) . '_public_key'                 => $credentials['stripe_publishable_key'],
            'stripe_' . (( intval( $credentials['is_live'] ) ? 'live' : 'test' )) . '_secret_key'                 => $credentials['access_token'],
            'stripe_' . (( intval( $credentials['is_live'] ) ? 'live' : 'test' )) . '_connect_integration_secret' => $credentials['connect_integration_secret'] ?? '',
        ) );
        $instance = new self();
        $instance->attach_settings_credentials( intval( $credentials['is_live'] ) );
        $instance->handle_webhook_integrity();
        return true;
    }

    protected $_account_id;

    protected $_public_key;

    protected $_secret_key;

    protected $_is_live;

    protected $_connect_integration_secret;

    public function get_account_id() {
        return $this->_account_id;
    }

    public function get_public_key() {
        return $this->_public_key;
    }

    public function get_secret_key() {
        return $this->_secret_key;
    }

    public function is_live() {
        return $this->_is_live;
    }

    public function stripeClient() {
        if ( $this->_stripeClient !== null ) {
            return $this->_stripeClient;
        }
        if ( !class_exists( '\\Stripe\\StripeClient' ) ) {
            require_once CHINA_PAYMENTS_BASE_PATH . '/lib/stripe/init.php';
        }
        try {
            Stripe_Library::setAppInfo( 'China Payments WordPress Plugin', CHINA_PAYMENTS_VERSION, 'https://chinapaymentsplugin.com' );
            $this->_stripeClient = new StripeClient($this->_secret_key);
        } catch ( \Exception $e ) {
            $this->_secret_key = null;
            $this->_public_key = null;
            $this->_stripeClient = null;
            return null;
        }
        return $this->_stripeClient;
    }

    /**
     * @return $this
     */
    public function attach_settings_credentials( $is_live = null ) {
        if ( $is_live === null ) {
            $is_live = intval( API_Settings::instance()->get( 'stripe_is_live' ) );
        }
        if ( $is_live ) {
            $this->_account_id = API_Settings::instance()->get( 'stripe_live_user_id' );
            $this->_public_key = API_Settings::instance()->get( 'stripe_live_public_key' );
            $this->_secret_key = API_Settings::instance()->get( 'stripe_live_secret_key' );
            $this->_connect_integration_secret = API_Settings::instance()->get( 'stripe_live_connect_integration_secret' );
            $this->_is_live = 1;
        } else {
            $this->_account_id = API_Settings::instance()->get( 'stripe_test_user_id' );
            $this->_public_key = API_Settings::instance()->get( 'stripe_test_public_key' );
            $this->_secret_key = API_Settings::instance()->get( 'stripe_test_secret_key' );
            $this->_connect_integration_secret = API_Settings::instance()->get( 'stripe_test_connect_integration_secret' );
            $this->_is_live = 0;
        }
        $this->_stripeClient = null;
        return $this;
    }

    public function is_configured() : bool {
        return $this->get_public_key() !== '';
    }

    public function delete_settings_credentials( $is_live = true ) {
        if ( $is_live ) {
            API_Settings::instance()->update( array(
                'stripe_live_user_id'                    => '',
                'stripe_live_public_key'                 => '',
                'stripe_live_secret_key'                 => '',
                'stripe_live_connect_integration_secret' => '',
            ) );
        } else {
            API_Settings::instance()->update( array(
                'stripe_test_user_id'                    => '',
                'stripe_test_public_key'                 => '',
                'stripe_test_secret_key'                 => '',
                'stripe_test_connect_integration_secret' => '',
            ) );
        }
        delete_transient( CHINA_PAYMENTS_ALIAS . '_stripe_apple_pay_domain' );
    }

    public function attach_credentials( $credentials ) {
        $this->_account_id = $credentials['account_id'];
        $this->_public_key = $credentials['public_key'];
        $this->_secret_key = $credentials['private_key'];
        $this->_is_live = $credentials['is_live'];
        $this->_stripeClient = null;
    }

    public function get_name() : string {
        return __( 'Stripe', 'china-payments' );
    }

    public function get_logo_url() : string {
        return plugins_url( 'interface/img/payment-gateway/logo-stripe.png', CHINA_PAYMENTS_BASE_FILE_PATH );
    }

    public function get_description() : string {
        $response = __( 'Stripe has partnered with Alipay and WeChat Pay to allow merchants to accept payments from Chinese customers. Connect your Stripe account in a few clicks, and then you can activate WeChat Pay and Alipay.', 'china-payments' );
        if ( china_payments_fs()->is_free_plan() ) {
            $response .= ' ' . sprintf( __( 'Note: The free version of China Payments Plugin charges a small %1$s fee in order to help us continue providing great features to the community. To remove the fee and get access to useful tools like recurring subscription payments for Alipay and checkout optimizations for Chinese customers, please %2$s', 'china-payments' ), '2%', '<a href="' . esc_url( admin_url( 'admin.php?page=china-payments-pricing' ) ) . '" target="_blank">' . __( 'Upgrade >', 'china-payments' ) . '</a>' );
        }
        return $response;
    }

    public function get_account_name() : string {
        if ( empty( $this->get_account_id() ) ) {
            return '';
        }
        try {
            $account_name = get_transient( CHINA_PAYMENTS_ALIAS . '_stripe_account_name_' . $this->get_account_id() );
            if ( empty( $account_name ) ) {
                $account_information = $this->stripeClient()->accounts->retrieve( $this->get_account_id() );
                $account_name = sanitize_text_field( $account_information->settings->dashboard->display_name );
                set_transient( CHINA_PAYMENTS_ALIAS . '_stripe_account_name_' . $this->get_account_id(), $account_name, DAY_IN_SECONDS );
            }
        } catch ( \Exception $e ) {
            return '';
        }
        return $account_name;
    }

    public function get_default_currency() {
        if ( empty( $this->get_account_id() ) ) {
            return '';
        }
        $default_currency = get_transient( CHINA_PAYMENTS_ALIAS . '_stripe_default_currency_' . $this->get_account_id() );
        try {
            if ( empty( $default_currency ) ) {
                $account_information = $this->stripeClient()->accounts->retrieve( $this->get_account_id() );
                $default_currency = sanitize_text_field( $account_information->default_currency );
                set_transient( CHINA_PAYMENTS_ALIAS . '_stripe_default_currency_' . $this->get_account_id(), $default_currency, DAY_IN_SECONDS );
            }
        } catch ( \Exception $e ) {
            return '';
        }
        return $default_currency;
    }

    /**
     * @todo Implement this properly, business name is not present in the retrieve account information, need to figure out a work-around
     * @return string
     */
    public function get_business_name() : string {
        return $this->get_account_name();
    }

    public function get_payment_methods_administration() : array {
        $response = array(
            'alipay' => array(
                'name'         => __( 'Alipay', 'china-payments' ),
                'alias'        => 'alipay',
                'is_available' => 1,
                'description'  => '<p>' . '<span>' . __( 'Alipay', 'china-payments' ) . '</span>' . '<img alt="alipay" src="' . plugins_url( 'interface/img/payment-gateway/payment-method-alipay.svg', CHINA_PAYMENTS_BASE_FILE_PATH ) . '"/>' . '</p>' . '<p>' . __( "Alipay enables Chinese consumers to pay directly via online transfer from their bank account. Customers are redirected to Alipay's payment page to log in and approve payments.", 'china-payments' ) . '</p>' . '<p><a href="https://stripe.com/docs/payments/alipay" target="_blank">' . __( 'Check country availability >', 'china-payments' ) . '</a></p>',
            ),
            'wechat' => array(
                'name'         => __( 'WeChat Pay', 'china-payments' ),
                'alias'        => 'wechat',
                'is_available' => 1,
                'description'  => '<p>' . '<span>' . __( 'WeChat Pay', 'china-payments' ) . '</span>' . '<img alt="wechat" src="' . plugins_url( 'interface/img/payment-gateway/payment-method-wechat-pay.svg', CHINA_PAYMENTS_BASE_FILE_PATH ) . '"/>' . '</p>' . '<p>' . __( 'WeChat Pay enables Chinese consumers to pay directly via online transfer from their account. Customers are given a QR Code to scan using their WeChat mobile application to approve payments.', 'china-payments' ) . '</p>' . '<p><a href="https://stripe.com/docs/payments/wechat-pay" target="_blank">' . __( 'Check country availability >', 'china-payments' ) . '</a></p>',
            ),
        );
        return apply_filters( 'china_payments_stripe_payment_methods_administration', $response );
    }

    public function get_webhook_settings_administration() : array {
        $test_fields_description = '<p>' . sprintf( __( 'Create an Endpoint in the %1$s, to send the event: %2$s', 'china-payments' ), '<a href="https://dashboard.stripe.com/test/webhooks" target="_blank">' . __( 'Stripe Webhooks Settings', 'china-payments' ) . '</a>', '<strong>payment_intent.succeeded</strong>' ) . '</p>';
        $live_fields_description = sprintf( __( 'Create an Endpoint in the %1$s, to send the event: %2$s', 'china-payments' ), '<a href="https://dashboard.stripe.com/webhooks" target="_blank">' . __( 'Stripe Webhooks Settings', 'china-payments' ) . '</a>', '<strong>payment_intent.succeeded</strong>' );
        $test_fields_description .= '<p>' . sprintf( __( 'Our %s covers how to configure Webhooks properly.', 'china-payments' ), '<a href="https://docs.chinapaymentsplugin.com/faqs/how-do-i-setup-stripe-webhooks" target="_blank">' . __( 'Documentation', 'china-payments' ) . '</a>' ) . '</p>';
        $live_fields_description .= '<p>' . sprintf( __( 'Our %s covers how to configure Webhooks properly.', 'china-payments' ), '<a href="https://docs.chinapaymentsplugin.com/faqs/how-do-i-setup-stripe-webhooks" target="_blank">' . __( 'Documentation', 'china-payments' ) . '</a>' ) . '</p>';
        return array(
            'title'                   => __( 'Webhook Settings (Recommended)', 'china-payments' ),
            'title_popup'             => __( 'Webhook Settings', 'china-payments' ),
            'test_configured'         => ( china_payments_setting_get( 'stripe_test_webhook_secret' ) !== '' ? 1 : 0 ),
            'test_available'          => china_payments_setting_get( 'stripe_test_public_key' ) !== '',
            'test_fields_description' => $test_fields_description,
            'test_fields'             => array(
                'stripe_test_webhook_url'    => array(
                    'label' => __( 'Webhook URL', 'china-payments' ),
                    'type'  => 'text',
                    'name'  => 'stripe_test_webhook_url',
                    'order' => 1,
                    'value' => rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/webhook/stripe-callback/test',
                ),
                'stripe_test_webhook_secret' => array(
                    'label' => __( 'Webhook Signing Secret', 'china-payments' ),
                    'type'  => 'text',
                    'name'  => 'stripe_test_webhook_secret',
                    'order' => 2,
                    'value' => china_payments_setting_get( 'stripe_test_webhook_secret' ),
                ),
            ),
            'live_configured'         => ( china_payments_setting_get( 'stripe_live_webhook_secret' ) !== '' ? 1 : 0 ),
            'live_available'          => china_payments_setting_get( 'stripe_live_public_key' ) !== '',
            'live_fields_description' => $live_fields_description,
            'live_fields'             => array(
                'stripe_live_webhook_url'    => array(
                    'label' => __( 'Webhook URL', 'china-payments' ),
                    'type'  => 'text',
                    'name'  => 'stripe_live_webhook_url',
                    'order' => 1,
                    'value' => rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/webhook/stripe-callback/live',
                ),
                'stripe_live_webhook_secret' => array(
                    'label' => __( 'Webhook Signing Secret', 'china-payments' ),
                    'type'  => 'text',
                    'name'  => 'stripe_live_webhook_secret',
                    'order' => 2,
                    'value' => china_payments_setting_get( 'stripe_live_webhook_secret' ),
                ),
            ),
        );
    }

    public function handle_webhook_integrity() {
        return false;
        $webhooks = $this->stripeClient()->webhookEndpoints->all( array(
            'limit' => 100,
        ) );
    }

    public function remove_webhook() {
        return false;
    }

    public function is_webhook_configured() {
        return false;
        $webhook_create = $this->stripeClient()->webhookEndpoints->create( array() );
        var_dump( $webhook_create );
        exit;
    }

    public function get_customer_id( $information ) {
        foreach ( array('email_address', 'currency') as $required_param ) {
            if ( !isset( $information[$required_param] ) ) {
                return new WP_Error('rest_error', esc_html( sprintf( __( 'Missing request param %s', 'china-payments' ), $required_param ) ), array(
                    'status' => 400,
                ));
            }
        }
        if ( !is_email( $information['email_address'] ) ) {
            return new WP_Error('rest_error', esc_html( sprintf( __( 'Invalid email address provided : %s', 'china-payments' ), 'email_address' ) ), array(
                'status' => 400,
            ));
        }
        $stripeCustomer = Model_Stripe_Customer::findOrCreate( array(
            'is_live'           => ( $this->is_live() ? 1 : 0 ),
            'email_address'     => $information['email_address'],
            'stripe_account_id' => $this->get_account_id(),
        ) );
        $stripeClient = $this->stripeClient();
        if ( $stripeCustomer->stripe_id === '' ) {
            $args = array(
                'email' => $stripeCustomer->email_address,
            );
            if ( isset( $args['first_name'] ) && isset( $information['last_name'] ) ) {
                $args['name'] = $information['first_name'] . ' ' . $information['last_name'];
            }
            $response = $stripeClient->customers->create( $args );
            $stripeCustomer->stripe_id = $response->id;
            $stripeCustomer->save();
        }
        return $stripeCustomer->stripe_id;
    }

}
