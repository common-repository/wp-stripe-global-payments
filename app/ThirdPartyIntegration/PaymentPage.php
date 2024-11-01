<?php

namespace ChinaPayments\ThirdPartyIntegration;

class PaymentPage {
    /**
     * @var PaymentPage
     */
    protected static $_instance = null;

    public static function instance() {
        if ( self::$_instance === null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public $assets_folder = '';

    public function setup() {
        $this->assets_folder = plugin_dir_url( CHINA_PAYMENTS_BASE_FILE_PATH ) . 'interface/';
        add_filter( 'payment_page_administration_dashboard', array($this, '_payment_page_administration_dashboard') );
        add_filter(
            'payment_page_stripe_payment_methods_frontend',
            array($this, '_payment_page_stripe_payment_methods_frontend'),
            100,
            2
        );
    }

    public function _payment_page_administration_dashboard( $response ) {
        if ( !payment_page_fs()->is_free_plan() ) {
            return $response;
        }
        $payment_method_alipay = array(
            'name'         => __( 'Alipay', 'china-payments' ),
            'alias'        => 'china_payments_alipay',
            'is_available' => 1,
            'description'  => '<p>' . '<span>' . __( 'Alipay', 'china-payments' ) . '</span>' . '<img alt="alipay" src="' . $this->assets_folder . 'img/payment-gateway/payment-method-alipay.svg" style="max-width: 100px;"/>' . '</p>' . '<p>' . __( "Alipay enables Chinese consumers to pay directly via online transfer from their bank account. Customers are redirected to Alipay's payment page to log in and approve payments.", 'china-payments' ) . '</p>',
        );
        $payment_method_wechat = false;
        foreach ( $response['stripe']['payment_methods'] as $k => $payment_method ) {
            if ( !in_array( $payment_method['alias'], array('wechat', 'alipay') ) ) {
                continue;
            }
            if ( $payment_method_wechat === false && $payment_method['alias'] === 'wechat' ) {
                continue;
            }
            unset($response['stripe']['payment_methods'][$k]);
        }
        $response['stripe']['payment_methods'][] = $payment_method_alipay;
        if ( $payment_method_wechat !== false ) {
            $response['stripe']['payment_methods'][] = $payment_method_wechat;
        }
        return $response;
    }

    public function _payment_page_stripe_payment_methods_frontend( $response, $active_payment_methods ) {
        if ( !payment_page_fs()->is_free_plan() ) {
            return $response;
        }
        if ( in_array( 'china_payments_alipay', $active_payment_methods ) ) {
            $response[] = array(
                'id'                    => 'china_payments_alipay',
                'name'                  => __( 'Alipay', 'china-payments' ),
                'payment_method'        => 'alipay',
                'has_recurring_support' => 0,
                'image'                 => $this->assets_folder . 'img/payment-gateway/payment-method-alipay.svg',
            );
        }
        if ( in_array( 'china_payments_wechat', $active_payment_methods ) ) {
            $response[] = array(
                'id'                    => 'china_payments_wechat',
                'name'                  => __( 'WeChat Pay', 'china-payments' ),
                'payment_method'        => 'wechat',
                'has_recurring_support' => 0,
                'image'                 => $this->assets_folder . 'img/payment-gateway/payment-method-wechat-pay.svg',
            );
        }
        return $response;
    }

}
