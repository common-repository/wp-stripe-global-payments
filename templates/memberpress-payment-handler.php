<div style="max-width:420px;margin:20px auto;">
  <?php
    $txn = new MeprTransaction( intval( ChinaPayments\Request::instance()->get_request_setting( 'trans_id' ) ) );
    $mepr_options = MeprOptions::fetch();

    $pm = $mepr_options->payment_method($txn->gateway );

    $component_args = [
      'stripe_publishable_key'=> ChinaPayments\PaymentGateway::get_integration_from_settings( 'stripe' )->get_public_key(),
      'payment_intent_id'     => $txn->get_meta( 'china_payments_payment_intent_id', true ),
      'payment_intent_secret' => $txn->get_meta( 'china_payments_payment_intent_secret', true ),
      'setup_intent_id'       => '',
      'setup_intent_secret'   => '',
      'payment_method'        => $txn->get_meta( 'china_payments_stripe_method_alias', true ),
      'return_url'            => rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/third-party-integration/memberpress/stripe-payment-completed/' . $txn->trans_num,
      'return_url_error'      => rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/third-party-integration/memberpress/complete-payment/' . $txn->trans_num,
      'error_message'         => $pm->payment_failed_error
    ];
  ?>
  <div data-china-payments-component="memberpress-payment-handler"
       data-china-payments-component-args="<?php echo esc_attr( json_encode( $component_args ) ); ?>"></div>
</div>