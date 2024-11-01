<?php $order = wc_get_order( ChinaPayments\Request::instance()->get_request_setting( 'checkout_order_id' ) ); ?>

<?php if( isset( $_GET[ 'china-payments-gateway' ] ) && $_GET[ 'china-payments-gateway' ] === 'stripe' ) : ?>
  <div data-china-payments-component="woocommerce-payment-handler"
       data-china-payments-component-args="<?php echo esc_attr( json_encode( [
          'stripe_publishable_key'=> ChinaPayments\PaymentGateway::get_integration_from_settings( 'stripe' )->get_public_key(),
          'payment_intent_id'     => ( isset( $_GET[ 'china-payments-intent-id' ] ) ? sanitize_text_field( $_GET[ 'china-payments-intent-id' ] ) : '' ),
          'payment_intent_secret' => ( isset( $_GET[ 'china-payments-intent-secret' ] ) ? sanitize_text_field( $_GET[ 'china-payments-intent-secret' ] ) : '' ),
          'setup_intent_id'       => ( isset( $_GET[ 'china-payments-setup-id' ] ) ? sanitize_text_field( $_GET[ 'china-payments-setup-id' ] ) : '' ),
          'setup_intent_secret'   => ( isset( $_GET[ 'china-payments-setup-secret' ] ) ? sanitize_text_field( $_GET[ 'china-payments-setup-secret' ] ) : '' ),
          'payment_method'        => ( isset( $_GET[ 'china-payments-method' ] ) ? sanitize_text_field( $_GET[ 'china-payments-method' ] ) : '' ),
          'return_url'            => rest_url() . CHINA_PAYMENTS_REST_API_PREFIX . '/v1/third-party-integration/woocommerce/stripe-payment-completed/' . $order->get_id(),
          'return_url_error'      => $order->get_checkout_payment_url(),
          'error_message'         => ChinaPayments\Request::instance()->get_request_setting( 'checkout_error_message' )
       ] ) ); ?>"></div>
<?php endif; ?>