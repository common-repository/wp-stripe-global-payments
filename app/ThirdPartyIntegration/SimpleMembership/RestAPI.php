<?php

namespace ChinaPayments\ThirdPartyIntegration\SimpleMembership;

use ChinaPayments\API\ChinaPayments as API_ChinaPayments;
use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use ChinaPayments\Request as CP_Request;
use ChinaPayments\ThirdPartyIntegration\Freemius as CP_Freemius;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use 
    SwpmMemberUtils,
    SwpmLog,
    SwpmMiscUtils,
    SwpmTransactions,
    SwpmUtils
;
use Exception;
class RestAPI {
    /**
     * @var \ChinaPayments\ThirdPartyIntegration\SimpleMembership\RestAPI;
     */
    protected static $_instance;

    /**
     * @return RestAPI
     */
    public static function instance() : RestAPI {
        if ( self::$_instance === null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function setup() {
        register_rest_route( CHINA_PAYMENTS_REST_API_PREFIX . '/v1', '/third-party-integration/simple-membership/complete-payment/(?P<button_id>[\\w-]+)', array(
            'methods'             => WP_REST_Server::ALLMETHODS,
            'callback'            => array($this, '_complete_payment'),
            'permission_callback' => function () {
                return true;
            },
        ) );
        register_rest_route( CHINA_PAYMENTS_REST_API_PREFIX . '/v1', '/third-party-integration/simple-membership/stripe-payment-completed/(?P<button_id>[\\w-]+)/(?P<payment_intent_id>[\\w-]+)/(?P<payment_intent_secret>[\\w-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, '_stripe_payment_completed'),
            'permission_callback' => function () {
                return true;
            },
        ) );
    }

    public function _complete_payment( WP_REST_Request $request ) {
        header( 'Content-Type: text/html' );
        if ( $request->has_param( 'user_id' ) && !empty( $request->get_param( 'user_id' ) ) && $request->has_param( 'user_id_secret' ) && !empty( $request->get_param( 'user_id_secret' ) ) ) {
            $user_id = intval( $request->get_param( 'user_id' ) );
            $user_id_secret = md5( $user_id . (( defined( 'NONCE_SALT' ) ? NONCE_SALT : 'abc' )) );
            if ( $request->get_param( 'user_id_secret' ) !== $user_id_secret ) {
                return new WP_Error('invalid_user_id_secret', 'Invalid User ID Secret');
            }
            wp_set_current_user( intval( $request->get_param( 'user_id' ) ) );
        }
        CP_Request::instance()->set_request_setting( 'button_id', $request->get_param( 'button_id' ) );
        try {
            CP_Request::instance()->set_request_setting( 'payment_intent', $this->generate_payment_intent_by_button_id( $request ) );
        } catch ( Exception $e ) {
            return new WP_Error('rest_error', esc_html( $e->getMessage() ), array(
                'status' => 400,
            ));
        }
        china_payments_register_universal_interface();
        require_once CHINA_PAYMENTS_BASE_PATH . '/templates/rest-simple-membership-payment-handler.php';
        exit;
    }

    public function _stripe_payment_completed( WP_REST_Request $request ) {
        SwpmLog::log_simple_debug( 'China Payments Callback received. Processing request...', true );
        // Retrieve the CPT for this button
        $button_cpt = get_post( intval( $request->get_param( 'button_id' ) ) );
        if ( !$button_cpt ) {
            // Fatal error. Could not find this payment button post object.
            SwpmLog::log_simple_debug( 'Fatal Error! Failed to retrieve the payment button post object for the given button ID: ' . intval( $request->get_param( 'button_id' ) ), false );
            wp_die( esc_html( sprintf( 'Fatal Error! Payment button (ID: %d) does not exist. This request will fail.', intval( $request->get_param( 'button_id' ) ) ) ) );
        }
        if ( !$request->has_param( 'payment_intent_id' ) ) {
            SwpmLog::log_simple_debug( 'Fatal Error! Payment intent not found in request.', false );
            wp_die( esc_html( 'Fatal Error! Payment intent not found in request.' ) );
        }
        $pi = CP_PaymentGateway::get_integration_from_settings( 'stripe' )->stripeClient()->paymentIntents->retrieve( $request->get_param( 'payment_intent_id' ) );
        if ( $pi->client_secret !== $request->get_param( 'payment_intent_secret' ) ) {
            SwpmLog::log_simple_debug( 'Fatal Error! Failed to validate client secret', false );
            wp_die( esc_html( 'Fatal Error! Failed to validate client secret' ) );
        }
        ini_set( 'display_errors', 1 );
        ini_set( 'display_startup_errors', 1 );
        error_reporting( E_ALL );
        if ( $pi->status !== 'succeeded' ) {
            if ( isset( $pi->last_payment_error ) && !empty( $pi->last_payment_error ) ) {
                return new WP_Error('payment_failed', $pi->last_payment_error->message);
            }
            return new WP_Error('payment_failed', 'Payment Failed');
        }
        // Get the charge object based on the Stripe API version used in the payment intents object.
        if ( isset( $pi->latest_charge ) ) {
            // Using the new Stripe API version 2022-11-15 or later
            SwpmLog::log_simple_debug( 'Using the Stripe API version 2022-11-15 or later for Payment Intents object. Need to retrieve the charge object.', true );
            $charge_id = $pi->latest_charge;
            // For Stripe API version 2022-11-15 or later, the charge object is not included in the payment intents object. It needs to be retrieved using the charge ID.
            try {
                // Retrieve the charge object using the charge ID
                $charge = CP_PaymentGateway::get_integration_from_settings( 'stripe' )->stripeClient()->charges->retrieve( $charge_id );
            } catch ( \Stripe\Exception\ApiErrorException $e ) {
                // Handle the error
                SwpmLog::log_simple_debug( 'Stripe error occurred trying to retrieve the charge object using the charge ID. ' . $e->getMessage(), false );
                return new WP_Error('payment_failed', $e->getMessage());
                exit;
            }
        } else {
            // Using the old Stripe API version 2022-08-01 or earlier
            $charge = $pi->charges;
            $charge = $pi->charges->data[0];
            $charge_id = $charge->id;
            // The old method that is not needed anymore as we will read it from the charge object below.
            // $stripe_email = $charge->data[0]->billing_details->email;
            // $name = trim( $charge->data[0]->billing_details->name );
            // $bd_addr = $charge->data[0]->billing_details->address;
        }
        $customer = CP_PaymentGateway::get_integration_from_settings( 'stripe' )->stripeClient()->customers->retrieve( $pi->customer );
        $stripe_email = '';
        $name = '';
        if ( !empty( $customer ) && !empty( $customer->email ) ) {
            $stripe_email = $customer->email;
            $name = trim( $customer->name );
        }
        if ( empty( $stripe_email ) && !empty( $charge->billing_details->email ) ) {
            $stripe_email = $charge->billing_details->email;
            $name = trim( $charge->billing_details->name );
        }
        SwpmLog::log_simple_debug( 'Email: ' . $stripe_email . ', Name: ' . $name . ', Charge ID: ' . $charge_id, true );
        // Grab the charge ID and set it as the transaction ID.
        $txn_id = $charge_id;
        // The charge ID.
        // check if this payment has already been processed
        $payment = get_posts( array(
            'meta_key'       => 'txn_id',
            'meta_value'     => $txn_id,
            'posts_per_page' => 1,
            'offset'         => 0,
            'post_type'      => 'swpm_transactions',
        ) );
        wp_reset_postdata();
        if ( $payment ) {
            // payment has already been processed. Redirecting user to return_url
            $return_url = get_post_meta( $button_cpt->ID, 'return_url', true );
            if ( empty( $return_url ) ) {
                $return_url = SIMPLE_WP_MEMBERSHIP_SITE_HOME_URL;
            }
            if ( empty( $return_url ) ) {
                $return_url = get_site_url();
            }
            china_payments_redirect( $return_url );
            exit;
        }
        $price_in_cents = floatval( $pi->amount_received );
        $currency_code = strtoupper( $pi->currency );
        $zero_cents = unserialize( SIMPLE_WP_MEMBERSHIP_STRIPE_ZERO_CENTS );
        if ( in_array( $currency_code, $zero_cents, true ) ) {
            $payment_amount = $price_in_cents;
        } else {
            $payment_amount = $price_in_cents / 100;
            // The amount (in cents). This value is used in Stripe API.
        }
        $payment_amount = floatval( $payment_amount );
        $membership_level_id = get_post_meta( $button_cpt->ID, 'membership_level_id', true );
        // Validate and verify some of the main values.
        $true_payment_amount = get_post_meta( $button_cpt->ID, 'payment_amount', true );
        $true_payment_amount = apply_filters( 'swpm_payment_amount_filter', $true_payment_amount, $button_cpt->ID );
        $true_payment_amount = floatval( $true_payment_amount );
        if ( $payment_amount !== $true_payment_amount ) {
            // Fatal error. Payment amount may have been tampered with.
            $error_msg = 'Fatal Error! Received payment amount (' . $payment_amount . ') does not match with the original amount (' . $true_payment_amount . ')';
            SwpmLog::log_simple_debug( $error_msg, false );
            wp_die( esc_html( $error_msg ) );
        }
        $true_currency_code = get_post_meta( $button_cpt->ID, 'payment_currency', true );
        if ( $currency_code !== $true_currency_code ) {
            // Fatal error. Currency code may have been tampered with.
            $error_msg = 'Fatal Error! Received currency code (' . $currency_code . ') does not match with the original code (' . $true_currency_code . ')';
            SwpmLog::log_simple_debug( $error_msg, false );
            wp_die( esc_html( $error_msg ) );
        }
        // Everything went ahead smoothly with the charge.
        SwpmLog::log_simple_debug( 'Stripe SCA Buy Now charge successful.', true );
        $user_ip = SwpmUtils::get_user_ip_address();
        // Custom field data
        $custom_field_value = 'subsc_ref=' . $membership_level_id;
        $custom_field_value .= '&user_ip=' . $user_ip;
        if ( SwpmMemberUtils::is_member_logged_in() ) {
            $custom_field_value .= '&swpm_id=' . SwpmMemberUtils::get_logged_in_members_id();
        }
        $custom_field_value = apply_filters( 'swpm_custom_field_value_filter', $custom_field_value );
        $custom = $custom_field_value;
        $custom_var = SwpmTransactions::parse_custom_var( $custom );
        $swpm_id = ( isset( $custom_var['swpm_id'] ) ? $custom_var['swpm_id'] : '' );
        // Let's try to get first_name and last_name from full name
        $last_name = ( strpos( $name, ' ' ) === false ? '' : preg_replace( '#.*\\s([\\w-]*)$#', '$1', $name ) );
        $first_name = trim( preg_replace( '#' . $last_name . '#', '', $name ) );
        // Create the $ipn_data array.
        $ipn_data = array();
        $ipn_data['mc_gross'] = $payment_amount;
        $ipn_data['first_name'] = $first_name;
        $ipn_data['last_name'] = $last_name;
        $ipn_data['payer_email'] = $stripe_email;
        $ipn_data['membership_level'] = $membership_level_id;
        $ipn_data['txn_id'] = $txn_id;
        $ipn_data['subscr_id'] = $txn_id;
        /* Set the txn_id as subscriber_id so it is similar to PayPal buy now. Also, it can connect to the profile in the "payments" menu. */
        $ipn_data['swpm_id'] = $swpm_id;
        $ipn_data['ip'] = $custom_var['user_ip'];
        $ipn_data['custom'] = $custom;
        $ipn_data['gateway'] = 'stripe-sca';
        $ipn_data['status'] = 'completed';
        $ipn_data['payment_button_id'] = $button_cpt->ID;
        $ipn_data['is_live'] = ( CP_PaymentGateway::get_integration_from_settings( 'stripe' )->is_live() ? 1 : 0 );
        // Handle the membership signup related tasks.
        swpm_handle_subsc_signup_stand_alone(
            $ipn_data,
            $membership_level_id,
            $txn_id,
            $swpm_id
        );
        // Save the transaction record
        SwpmTransactions::save_txn_record( $ipn_data );
        SwpmLog::log_simple_debug( 'Transaction data saved.', true );
        do_action( 'swpm_payment_ipn_processed', $ipn_data );
        // Redirect the user to the return URL (or to the homepage if a return URL is not specified for this payment button).
        $return_url = get_post_meta( $button_cpt->ID, 'return_url', true );
        if ( empty( $return_url ) ) {
            $return_url = SIMPLE_WP_MEMBERSHIP_SITE_HOME_URL;
        }
        if ( empty( $return_url ) ) {
            $return_url = get_site_url();
        }
        SwpmLog::log_simple_debug( 'Redirecting customer to: ' . $return_url, true );
        SwpmLog::log_simple_debug( 'End of China Payments processing.', true, true );
        china_payments_redirect( $return_url );
        exit;
    }

    private function generate_payment_intent_by_button_id( WP_REST_Request $request ) {
        $button_id = intval( $request->get_param( 'button_id' ) );
        $stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe' );
        if ( empty( $stripeIntegration ) ) {
            throw new Exception('Stripe Integration not found');
        }
        $payment_amount = get_post_meta( $button_id, 'payment_amount', true );
        $payment_currency = get_post_meta( $button_id, 'payment_currency', true );
        $payment_method = get_post_meta( $button_id, 'button_type', true );
        $payment_method = ( $payment_method === 'cpp_alipay' ? 'alipay' : 'wechat_pay' );
        $button_cpt = get_post( $button_id );
        // Retrieve the CPT for this button
        $item_name = htmlspecialchars( $button_cpt->post_title );
        $swpm_member_id = ( SwpmMemberUtils::is_member_logged_in() ? SwpmMemberUtils::get_logged_in_members_id() : '' );
        $user_id = ( is_user_logged_in() ? get_current_user_id() : (( SwpmMemberUtils::is_member_logged_in() ? SwpmMemberUtils::get_wp_user_from_swpm_user_id( $swpm_member_id )->ID : '' )) );
        $data = array(
            'cp_integration'   => 'simple_memberships',
            'domain_name'      => china_payments_domain_name(),
            'swpm_button_id'   => $button_id,
            'swpm_button_name' => $item_name,
            'swpm_member_id'   => $swpm_member_id,
            'user_id'          => $user_id,
        );
        $data_stripe_customer = $data;
        $data_stripe_customer['currency'] = $payment_currency;
        if ( $request->has_param( 'email_address' ) ) {
            $data_stripe_customer['email_address'] = $request->get_param( 'email_address' );
        }
        $stripe_customer_id = china_payments_stripe_customer_id( $data_stripe_customer );
        $response = null;
        if ( !isset( $response['payment_intent_id'] ) ) {
            $response = API_ChinaPayments::instance()->request( 'stripe/payment-intent-or-setup', array(
                'account_id'                  => $stripeIntegration->get_account_id(),
                'customer_id'                 => $stripe_customer_id,
                'payment_method'              => $payment_method,
                'price_information'           => array(
                    'is_recurring' => 0,
                    'amount'       => intval( floatval( $payment_amount ) * 100 ),
                    'currency'     => $payment_currency,
                ),
                'is_live'                     => intval( $stripeIntegration->is_live() ),
                'meta_data'                   => $data,
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
        return $response;
    }

}
