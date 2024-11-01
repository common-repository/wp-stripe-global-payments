<?php
  $payment_method = ( $order->get( 'payment_gateway' ) === 'china_payments_stripe_alipay' ? 'alipay' : 'wechat_pay' );

  $component_args = [
    'stripe_publishable_key'=> ChinaPayments\PaymentGateway::get_integration_from_settings( 'stripe' )->get_public_key(),
    'payment_intent_id'     => get_post_meta( $order->id, 'china_payments_payment_intent_id', true ),
    'payment_intent_secret' => get_post_meta( $order->id, 'china_payments_payment_intent_secret', true ),
    'setup_intent_id'       => '',
    'setup_intent_secret'   => '',
    'payment_method'        => $payment_method,
    'return_url'            => llms_confirm_payment_url( $order->get( 'order_key' ) ),
    'return_url_error'      => llms_confirm_payment_url( $order->get( 'order_key' ) ),
    'error_message'         => __( "Payment failed", 'china-payments' )
  ];
?>
<div data-china-payments-component="simple-membership-payment-handler"
     data-china-payments-component-args="<?php echo esc_attr( json_encode( $component_args ) ); ?>"></div>