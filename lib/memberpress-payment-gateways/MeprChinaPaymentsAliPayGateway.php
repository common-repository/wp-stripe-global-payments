<?php
class MeprChinaPaymentsAliPayGateway extends ChinaPayments_Skeleton_Gateway {

  public $cp_gateway_id = 'MeprChinaPaymentsAliPayGateway';

  public function __construct() {
    $this->name = __( 'Alipay', 'china-payments' );
    $this->key  = 'china_payments_alipay';
    $this->icon = plugins_url( 'interface/img/payment-gateway/payment-method-alipay.svg', CHINA_PAYMENTS_BASE_FILE_PATH );
    $this->desc = __( 'You will be redirected to the Alipay website to complete checkout.', 'china-payments' );
    $this->set_defaults();

    $this->capabilities = [ 'process-payments' ];
  }

  public function get_stripe_payment_method_alias() {
    return 'alipay';
  }

}
