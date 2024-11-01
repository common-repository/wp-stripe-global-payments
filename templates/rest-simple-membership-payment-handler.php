<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

  <div style="max-width:420px;margin:20px auto;">
    <?php
      $button_id = ChinaPayments\Request::instance()->get_request_setting( 'button_id' );
      $payment_intent = ChinaPayments\Request::instance()->get_request_setting( 'payment_intent' );
    ?>
    <?php if( !is_wp_error( $payment_intent ) ) : ?>
      <?php
        $payment_method = get_post_meta( $button_id, 'button_type', true );
        $payment_method = ( $payment_method === 'cpp_alipay' ? 'alipay' : 'wechat_pay' );

        $component_args = [
          'stripe_publishable_key'=> ChinaPayments\PaymentGateway::get_integration_from_settings( 'stripe' )->get_public_key(),
          'payment_intent_id'     => $payment_intent[ 'payment_intent_id' ],
          'payment_intent_secret' => $payment_intent[ 'payment_intent_secret' ],
          'setup_intent_id'       => '',
          'setup_intent_secret'   => '',
          'payment_method'        => $payment_method,
          'return_url'            => rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/third-party-integration/simple-membership/stripe-payment-completed/' . $button_id . '/' . $payment_intent[ 'payment_intent_id' ] . '/' . $payment_intent[ 'payment_intent_secret' ],
          'return_url_error'      => rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/third-party-integration/simple-membership/complete-payment/' . $button_id,
          'error_message'         => __( "Payment failed", 'china-payments' )
        ];
      ?>
      <div data-china-payments-component="simple-membership-payment-handler"
           data-china-payments-component-args="<?php echo esc_attr( json_encode( $component_args ) ); ?>"></div>
    <?php else : ?>
      <p><?php echo esc_html( $payment_intent->get_error_message() ); ?></p>
    <?php endif; ?>
  </div>

<?php wp_footer(); ?>
</body>
</html>