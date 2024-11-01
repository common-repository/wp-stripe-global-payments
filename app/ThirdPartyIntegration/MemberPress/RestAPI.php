<?php

namespace ChinaPayments\ThirdPartyIntegration\MemberPress;

use ChinaPayments\PaymentGateway as CP_PaymentGateway;
use ChinaPayments\Request as CP_Request;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use MeprTransaction;
use MeprOptions;
use MeprUtils;

class RestAPI {

	/**
	 * @var RestAPI;
	 */
	protected static $_instance;

	/**
	 * @return RestAPI
	 */
	public static function instance(): RestAPI {
		if ( self::$_instance === null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function setup() {
		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/third-party-integration/memberpress/complete-payment/(?P<mp_transaction_num>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, '_complete_payment' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);

		register_rest_route(
			CHINA_PAYMENTS_REST_API_PREFIX . '/v1',
			'/third-party-integration/memberpress/stripe-payment-completed/(?P<mp_transaction_num>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, '_stripe_payment_completed' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);
	}

	public function _complete_payment( WP_REST_Request $request ) {
		CP_Request::instance()->set_request_setting( 'trans_num', $request->get_param( 'mp_transaction_num' ) );

		header( 'Content-Type: text/html' );

		china_payments_register_universal_interface();

		require_once CHINA_PAYMENTS_BASE_PATH . '/templates/rest-memberpress-payment-handler.php';
		exit;
	}

	public function _stripe_payment_completed( WP_REST_Request $request ) {
		$trans        = MeprTransaction::get_one_by_trans_num( $request->get_param( 'mp_transaction_num' ) );
		$txn          = new MeprTransaction( $trans->id );
		$mepr_options = MeprOptions::fetch();

		$is_live           = intval( $txn->get_meta( 'china_payments_is_live', true ) );
		$payment_intent_id = $txn->get_meta( 'china_payments_payment_intent_id', true );

		if ( empty( $payment_intent_id ) ) {
			return new WP_Error( 'payment_intent_id_not_found', 'Payment Intent ID not found' );
		}

		$stripeIntegration = CP_PaymentGateway::get_integration_from_settings( 'stripe', $is_live );

		try {
			$payment_intent = $stripeIntegration->stripeClient()->paymentIntents->retrieve( $payment_intent_id );

			if ( $payment_intent->status !== 'succeeded' ) {
				if ( isset( $payment_intent->last_payment_error ) && ! empty( $payment_intent->last_payment_error ) ) {
					return new WP_Error( 'payment_failed', $payment_intent->last_payment_error->message );
				}

				return new WP_Error( 'payment_failed', 'Payment Failed' );
			}
		} catch ( \Exception $e ) {
			return new WP_Error( 'payment_failed', $e->getMessage() );
		}

		if ( $txn->status !== MeprTransaction::$complete_str ) {
			$txn->status = MeprTransaction::$complete_str;
			$txn->store();

			MeprUtils::send_transaction_receipt_notices( $txn );
		}

		if ( ! empty( $mepr_options->thankyou_page_id ) ) {
			$permalink = get_the_permalink( $mepr_options->thankyou_page_id );
			$permalink = add_query_arg( 'trans_num', $txn->trans_num, $permalink );
			$permalink = add_query_arg( 'membership_id', $txn->product_id, $permalink );

			wp_redirect( $permalink );
			exit;
		} else {
			wp_redirect( get_site_url() );
			exit;
		}
	}
}
