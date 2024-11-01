ChinaPayments.Component[ 'memberpress-payment-handler' ] = {

  container     : {},
  configuration : {
    stripe_publishable_key : '',
    payment_intent_id     : '',
    payment_intent_secret : '',
    setup_intent_id       : '',
    setup_intent_secret   : '',
    payment_method        : '',
    return_url            : '',
    return_url_error      : '',
    error_message         : ''
  },

  _stripeSDK : null,

  Init : function( container ) {
    this.container = container;

    let objectInstance = this;

    china_payments_component_configuration_parse( this, function() {
      if( typeof window.Stripe === "undefined" ) {
        ChinaPayments.LoadAssets( 'https://js.stripe.com/v3/', function() {
          objectInstance.__initStripeSDKLoaded();
        }, false );

        return;
      }

      objectInstance.__initStripeSDKLoaded();
    } );
  },

  __initStripeSDKLoaded : function() {
    if( this._stripeSDK === null )
      this._stripeSDK = window.Stripe( this.configuration.stripe_publishable_key, {
        apiVersion: "2020-08-27",
      } );

    if( this.configuration.payment_method === 'alipay' ) {
      this._handleAlipay();
    } else if( this.configuration.payment_method === 'wechat_pay' ) {
      this._handleWeChatPay();
    }
  },

  _handleAlipay : function() {
    let objectInstance = this;

    this._stripeSDK.confirmAlipayPayment(
      this.configuration.payment_intent_secret,
      {
        return_url: this.configuration.return_url
      }
    ).then(function(result) {
      if (result.error) {
        objectInstance.container.html( '<div data-china-payments-notification="danger">' + objectInstance.configuration.error_message + '</div>' )

        setTimeout( function() {
          window.location.href = objectInstance.configuration.return_url_error;
        }, 4000 );
        // Inform the customer that there was an error.
      }

      if( typeof result.paymentIntent !== 'undefined' && result.paymentIntent.status === "succeeded" )
        window.location.href = objectInstance.configuration.return_url;
    });
  },

  _handleWeChatPay : function() {
    let objectInstance = this;

    this.container.html( '' );

    this._stripeSDK.confirmWechatPayPayment(
      this.configuration.payment_intent_secret,
      {
        payment_method_options: {
          wechat_pay: {
            client: 'web',
          },
        }
      },
    ).then(function( response ) {
      if( typeof response.paymentIntent === "undefined" || response.paymentIntent.status !== "succeeded" ) {
        objectInstance.container.html( '<div data-china-payments-notification="danger">' + objectInstance.configuration.error_message + '</div>' )

        setTimeout( function() {
          window.location.href = objectInstance.configuration.return_url_error;
        }, 4000 );

        return;
      }

      window.location.href = objectInstance.configuration.return_url;
    });
  }

};