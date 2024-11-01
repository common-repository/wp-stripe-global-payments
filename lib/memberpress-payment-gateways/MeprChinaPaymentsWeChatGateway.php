<?php
class MeprChinaPaymentsWeChatGateway extends ChinaPayments_Skeleton_Gateway {

  public $cp_gateway_id = 'MeprChinaPaymentsWeChatGateway';

  public function __construct() {
    $this->name = __( 'WeChat Pay', 'china-payments' );
    $this->key  = 'china_payments_wechat';
    $this->icon = plugins_url( 'interface/img/payment-gateway/payment-method-wechat-pay.svg', CHINA_PAYMENTS_BASE_FILE_PATH );
    $this->desc = __( 'A WeChat Pay QR Code will display.', 'china-payments' );
    $this->set_defaults();

    $this->capabilities = [ 'process-payments' ];
  }

  public function get_stripe_payment_method_alias() {
    return 'wechat_pay';
  }

}
