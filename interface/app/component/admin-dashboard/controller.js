ChinaPayments.Component[ 'admin-dashboard' ] = {

  container     : {},
  configuration : {},
  data : {},

  _currentlyDisplayed : '',
  _resumeQuickSetup : false,
  _skipQuickSetup : false,

  _xhrSetPaymentMethodStatusMAP : {},

  Init : function( container ) {
    this.container = container;

    let objectInstance = this;

    china_payments_component_configuration_parse( this, function() {
      ChinaPayments.Template.preload( 'admin-dashboard', [
        'template/_navigation.html',
        'template/_payment-gateways.html',
        'template/_integrations.html',
        'template/_optimizations.html'
      ], function() {
        objectInstance._loadData();
      } )
    } );
  },

  _loadData : function() {
    let objectInstance = this;

    ChinaPayments.setLoadingContent( this.container );

    ChinaPayments.API.fetch('china-payments/v1/administration/dashboard', false, function( response ) {
      if( typeof response !== 'object' ) {
        ChinaPayments.setFailedAssetFetchContent( objectInstance.container );
        return;
      }

      objectInstance.data = response;

      objectInstance._loadTemplate();
    } );
  },

  _getTemplateArgs : function() {
    let objectInstance = this,
        response = china_payments_parse_args( this.data, this.configuration );

    response.current_page = china_payments_hashtag_container_from_browser( this.container );

    if( response.current_page !== 'payment-gateways'
        && response.current_page !== 'integrations'
        && response.current_page !== 'optimizations' )
      response.current_page = 'payment-gateways';

    response.quick_setup_index = false;
    response.quick_setup_return_index = false;
    response.quick_setup_skip_index = false;

    jQuery.each( response.quick_setup_steps, function( step_key, step ) {
      if( step.is_completed && !objectInstance._resumeQuickSetup )
        return true;

      if( response.quick_setup_index !== false ) {
        if( typeof step.requires_steps === 'undefined' ) {
          response.quick_setup_skip_index = step_key;

          return false;
        }

        if( objectInstance._resumeQuickSetup )
          return true;

        let good = true;

        jQuery.each( step.requires_steps, function( req_step_key, req_step ) {
          if( response.quick_setup_steps[ req_step ].is_completed )
            return true;

          good = false;
          return false;
        });

        if( good ) {
          response.quick_setup_skip_index = step_key;

          return false;
        }

        return true;
      }

      response.quick_setup_index = step_key;
    });

    if( this._skipQuickSetup !== false ) {
      response.quick_setup_return_index = response.quick_setup_index;
      response.quick_setup_index = this._skipQuickSetup;

      response.quick_setup_skip_index = false;
    }

    // Allow back in the process from Integrations step ( non true setup process, but desired )
    if( response.current_page === 'integrations' && response.quick_setup_return_index === false && response.quick_setup_skip_index === false )
      response.quick_setup_return_index = 0;

    if( response.quick_setup_index !== false && !this.data.quick_setup_skipped ) {
      response.current_page = response.quick_setup_steps[ response.quick_setup_index ].template;

      if( response.current_page !== china_payments_hashtag_container_from_browser( this.container ) ) {
        this.container.attr( 'data-china-payments-hashtag-identifier', response.current_page );
        china_payments_hashtag_container_to_browser(this.container);
      }
    }

    return response;
  },

  _loadTemplate : function() {
    let objectInstance = this,
        template_args = this._getTemplateArgs();

    this._currentlyDisplayed = template_args.current_page;

    this.container.attr( "data-china-payments-hashtag-identifier", this._currentlyDisplayed );

    ChinaPayments.Template.load( this.container, 'admin-dashboard', 'template/default.html', template_args, function() {
      objectInstance._bindQuickSetupEvents();
      objectInstance._bindTemplateEvents();
      objectInstance._bindPaymentEvents();

      let _cookie = china_payments_get_cookie( 'china_payments_dashboard_open_gateway' )

      if( _cookie === '' || typeof _cookie === 'undefined' )
        _cookie = 'stripe';

      if( _cookie !== 'disabled' )
        objectInstance.container
                      .find( '[data-china-payments-has-payment-methods="1"][data-china-payments-gateway-alias="' + _cookie + '"] > [data-china-payments-component-admin-dashboard-section="header"] > h2' )
                      .trigger( "click" );
    });
  },

  _bindQuickSetupEvents : function() {
    let objectInstance = this;

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger="quick-setup"]' ).on( "click", function() {
      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      objectInstance.data.quick_setup_skipped = 0;
      objectInstance._resumeQuickSetup = true;
      objectInstance._loadTemplate();
    } );

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger="quick-setup-exit"]' ).on( "click", function() {
      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      objectInstance.data.quick_setup_skipped = 1;

      ChinaPayments.API.post('china-payments/v1/administration/set-quick-setup-skip', { 'status' : 1 }, function( response ) {
        objectInstance.data = response;

        objectInstance._loadTemplate();
      } );
    } );

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger^="quick_setup_skip_"]' ).on( "click", function() {
      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      objectInstance._skipQuickSetup = parseInt( jQuery(this).attr( 'data-china-payments-component-admin-dashboard-trigger' ).replace( "quick_setup_skip_", "" ) );
      objectInstance._loadTemplate();
    });

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger="quick_setup_return"]' ).on( "click", function() {
      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      objectInstance._skipQuickSetup = false;
      objectInstance._loadTemplate();
    });
  },

  _bindTemplateEvents : function() {
    let objectInstance = this;

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger^="install_plugin_"]' ).on( "click", function() {
      if( typeof jQuery(this).attr( "disabled" ) !== 'undefined' )
        return;

      if( jQuery(this).find( '.china-payments-application-loader-wrapper' ).length > 0 )
        return;

      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      let parentContainer = jQuery(this).parents( '[data-china-payments-component-admin-dashboard-section="integration_information"]:first' );

      parentContainer.find( '[data-china-payments-notification]' ).remove();

      let identifier = jQuery(this).attr( 'data-china-payments-component-admin-dashboard-trigger' ).replace( 'install_plugin_', '' );

      ChinaPayments.API.post('china-payments/v1/plugin/install', {
        'identifier' : identifier
      }, function( response ) {
        if( typeof response.message !== 'undefined' ) {
          parentContainer.append( '<div data-china-payments-notification="danger">' + response.message + '</div>' );

          return;
        }

        ChinaPayments.API.post('china-payments/v1/plugin/activate', {
          'identifier' : identifier
        }, function( response ) {
          objectInstance._loadData();
        } );
      } );
    } );

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger^="activate_plugin_"]' ).on( "click", function() {
      if( typeof jQuery(this).attr( "disabled" ) !== 'undefined' )
        return;

      if( jQuery(this).find( '.china-payments-application-loader-wrapper' ).length > 0 )
        return;

      let parentContainer = jQuery(this).parents( '[data-china-payments-component-admin-dashboard-section="integration_information"]:first' );

      parentContainer.find( '[data-china-payments-notification]' ).remove();

      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      let identifier = jQuery(this).attr( 'data-china-payments-component-admin-dashboard-trigger' ).replace( 'activate_plugin_', '' );

      ChinaPayments.API.post('china-payments/v1/plugin/activate', { 'identifier' : identifier }, function( response ) {
        if( typeof response.message !== 'undefined' ) {
          parentContainer.append( '<div data-china-payments-notification="danger">' + response.message + '</div>' );

          return;
        }

        objectInstance._loadData();
      } );
    } );
  },

  _bindPaymentEvents : function() {
    let objectInstance = this;

    this.container.find( '[data-china-payments-has-payment-methods="1"] > [data-china-payments-component-admin-dashboard-section="header"] > h2' ).on( "click", function() {
      let payment_gateway_container = jQuery(this).parents( '[data-china-payments-has-payment-methods]' ),
          payment_method_container = payment_gateway_container.find( '[data-china-payments-component-admin-dashboard-section="payment_methods_container"]' );

      if( parseInt( payment_gateway_container.attr( 'data-china-payments-has-payment-methods-visible' ) ) ) {
        payment_gateway_container.attr( 'data-china-payments-has-payment-methods-visible', 0 );
        payment_method_container.slideUp( "slow" );
        china_payments_set_cookie( 'china_payments_dashboard_open_gateway', 'disabled', 60 * 24 * 30 );
      } else {
        payment_gateway_container.attr( 'data-china-payments-has-payment-methods-visible', 1 );
        payment_method_container.slideDown( "slow" );
        china_payments_set_cookie( 'china_payments_dashboard_open_gateway', payment_gateway_container.attr( "data-china-payments-gateway-alias" ), 60 * 24 * 30 );
      }

    });

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger="gateway_mode_checkbox"]' ).on( "change", function() {
      let mode = jQuery(this).is( ":checked" ) ? 'test' : 'live';

      jQuery(this).parents( '[data-china-payments-gateway-mode]' ).attr( 'data-china-payments-gateway-mode', mode );

      objectInstance.data.payment_gateway.stripe.mode = mode;

      ChinaPayments.API.post('china-payments/v1/payment-gateway/set-mode', {
        'payment_gateway' : jQuery(this).parents( '[data-china-payments-gateway-alias]:first' ).attr( "data-china-payments-gateway-alias" ),
        'is_live' : ( jQuery(this).is( ":checked" ) ? 0 : 1 )
      }, function( response ) {

      } );
    });

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger^="payment_method_"]' ).on( "change", function() {
      let gateway_container = jQuery(this).parents( '[data-china-payments-gateway-alias]:first' ),
          gateway = gateway_container.attr( "data-china-payments-gateway-alias" ),
          payment_method = jQuery(this).attr( 'data-china-payments-component-admin-dashboard-trigger' ).replace( "payment_method_", "" ),
          table_td_container = jQuery(this).parents( 'td:first' );

      if( jQuery(this).is( ":checked" ) ) {
        objectInstance.data.payment_gateway[ gateway ].payment_methods_enabled.push( payment_method );
      } else {
        objectInstance.data.payment_gateway[ gateway ].payment_methods_enabled = objectInstance.data.payment_gateway.stripe.payment_methods_enabled.filter(function(value, index, arr){
          return value !== payment_method;
        });
      }

      table_td_container.find( ' > label' ).hide();
      table_td_container.append( china_payments_element_loader( 'mini' ) );

      if( typeof objectInstance._xhrSetPaymentMethodStatusMAP[ gateway ] !== 'undefined'
          && objectInstance._xhrSetPaymentMethodStatusMAP[ gateway ] !== false )
        objectInstance._xhrSetPaymentMethodStatusMAP[ gateway ].abort();

      objectInstance._xhrSetPaymentMethodStatusMAP[ gateway ] = ChinaPayments.API.post('china-payments/v1/payment-gateway/set-payment-methods', {
        'payment_gateway' : gateway,
        'payment_methods' : objectInstance.data.payment_gateway[ gateway ].payment_methods_enabled,
      }, function( response ) {
        if( typeof response !== 'object' || response.status !== 'ok' )
          return;

        if( typeof response.refresh !== 'undefined' && parseInt( response.refresh ) )
          objectInstance._loadData();

        objectInstance._xhrSetPaymentMethodStatusMAP[ gateway ] = false;

        objectInstance.container.find( '[data-china-payments-component-admin-dashboard-trigger^="payment_method_"]' ).each( function() {
          let current_table_container = jQuery(this).parents( 'td:first' );

          if( current_table_container.find( ' > label' ).is( ":hidden" ) ) {
            current_table_container.find( '.china-payments-application-loader-wrapper' ).remove();
            current_table_container.find( ' > label' ).show();
          }
        });
      } );
    });

    this.container.find(
      '[data-china-payments-component-admin-dashboard-trigger^="gateway_connect_"],' +
      '[data-china-payments-component-admin-dashboard-trigger^="gateway_settings_"]'
    ).on( "click", function() {
      if( jQuery(this).find( '.china-payments-application-loader-wrapper' ).length > 0 )
        return;

      if( parseInt( ChinaPayments.settings.is_https ) ) {
        if( window.location.href.indexOf( ChinaPayments.settings.site_url ) === -1 ) {
          ChinaPayments.Library.Popup.display(
            '<div data-china-payments-notification="danger">' +
                      objectInstance.data.lang.notification_url_mismatch_ssl.replace( "%s", jQuery(this).parents( '[data-china-payments-gateway-alias]:first' ).find( '> [data-china-payments-component-admin-dashboard-section="header"]:first > h2' ).text() ) +
                    '</div>'
          );
          return;
        }
      }

      let invalid_url_characters = [];

      jQuery.each( objectInstance.data.invalid_url_characters, function( _key, invalid_character ) {
        if( ChinaPayments.settings.site_url.indexOf( invalid_character ) !== -1 )
          invalid_url_characters.push( invalid_character );
      } );

      if( invalid_url_characters.length > 0 ) {
        ChinaPayments.Library.Popup.display(
          '<div data-china-payments-notification="danger">' +
                    objectInstance.data.lang.notification_url_invalid_characters
                        .replace( "%s", '<strong>' + invalid_url_characters.join( " " ) + '</strong>' )
                        .replace( "%s", jQuery(this).parents( '[data-china-payments-gateway-alias]:first' ).find( '> [data-china-payments-component-admin-dashboard-section="header"]:first > h2' ).text() ) +
                  '</div>'
        );
        return;
      }

      let triggerObject = jQuery(this),
          paymentGatewayContainer = jQuery(this).parents( '[data-china-payments-gateway-alias]:first' ),
          is_live = (
        jQuery(this).attr( "data-china-payments-component-admin-dashboard-trigger" )
                    .replace( 'gateway_connect_', '' )
                    .replace( 'gateway_settings_', '' ) === 'live'
          ? 1
          : 0
      ),
          payment_gateway = paymentGatewayContainer.attr( "data-china-payments-gateway-alias" ),
          _button_inner_html = jQuery(this).html();

      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      ChinaPayments.API.fetch('china-payments/v1/payment-gateway/connect', {
        payment_gateway : payment_gateway,
        is_live         : is_live
      }, function( response ) {
        if( response.type === 'redirect' ) {
          window.location = response.url;
          return;
        }

        if( response.type === "settings" ) {
          let title = paymentGatewayContainer.find( ' > [data-china-payments-component-admin-dashboard-section="header"] > h2 ' ).text() + ' ' +
                      '<span data-china-payments-mode="' + ( is_live ? 'live' : 'test' ) + '">' +
                        ( is_live ? objectInstance.data.lang.payment_gateway_mode_live : objectInstance.data.lang.payment_gateway_mode_test  ) +
                      '</span>';

          title = objectInstance.data.lang.payment_gateway_settings_title.replace( "%s", title );

          triggerObject.html( _button_inner_html );

          ChinaPayments.Library.Popup.display(
            '<div data-china-payments-component="admin-form" ' +
                         'data-china-payments-component-args="' + _.escape( JSON.stringify( {
                                                                                            title       : title,
                                                                                            description : response.description,
                                                                                            fields      : response.fields,
                                                                                            operations  : response.operations,
                                                                                            rest_data   : {
                                                                                              payment_gateway : payment_gateway,
                                                                                              is_live         : is_live
                                                                                            },
                                                                                            rest_path   : 'china-payments/v1/payment-gateway/save-settings',
                                                                                          } ) ) + '"></div>',
            {
              trigger_app_init : true
            }
          );

          ChinaPayments.Library.Popup.getContainerObject()
                                   .find( '[data-china-payments-component="admin-form"]' )
                                   .on( 'china_payments_settings_saved', function() {
                                     ChinaPayments.Library.Popup.close();
                                     objectInstance._loadData();
                                   });
        }
      } );
    });

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger^="gateway_disconnect_"]' ).on( "click", function() {
      ChinaPayments.setLoadingContent( jQuery(this), '', 'mini' );

      ChinaPayments.API.post('china-payments/v1/payment-gateway/disconnect', {
        'payment_gateway' : jQuery(this).parents( '[data-china-payments-gateway-alias]:first' ).attr( "data-china-payments-gateway-alias" ),
        'is_live' : ( jQuery(this).attr( "data-china-payments-component-admin-dashboard-trigger" ).replace( 'gateway_disconnect_', '' ) === 'live' ? 1 : 0 )
      }, function( response ) {
        objectInstance._loadData();
      } );
    });

    this.container.find( '[data-china-payments-component-admin-dashboard-trigger^="payment_gateway_webhook_settings_"]' ).on( "click", function() {
      let triggerObject = jQuery(this),
          gateway_container = jQuery(this).parents( '[data-china-payments-gateway-alias]:first' ),
          payment_gateway = gateway_container.attr( "data-china-payments-gateway-alias" ),
          args  = JSON.parse( triggerObject.attr( "data-china-payments-component-admin-dashboard-args" ) ),
          _temp = triggerObject.attr( "data-china-payments-component-admin-dashboard-trigger" ).replace( 'payment_gateway_webhook_settings_', '' ),
          is_live = ( _temp.substr( 0, 4 ) === 'live' ? 1 : 0 );

      ChinaPayments.Library.Popup.display(
        '<div data-china-payments-component="admin-form" ' +
                     'data-china-payments-component-args="' + _.escape( JSON.stringify( {
                        title       : args.title,
                        description : args.description,
                        fields      : args.fields,
                        operations  : {
                          save : {
                            label : objectInstance.data.lang.payment_gateway_webhook_settings_save,
                            type  : 'save',
                            order : 1,
                          }
                        },
                        rest_data   : {
                          payment_gateway : payment_gateway,
                          is_live         : is_live
                        },
                        rest_path   : 'china-payments/v1/payment-gateway/save-webhook-settings',
                      } ) ) + '"></div>',
        {
          trigger_app_init : true
        }
      );

      ChinaPayments.Library.Popup.getContainerObject()
                               .find( '[data-china-payments-component="admin-form"]' )
                               .on( 'china_payments_settings_saved', function() {
                                   ChinaPayments.Library.Popup.close();
                                   objectInstance._loadData();
                               });
    });
  },

  __onWindowHashChange : function( hash ) {
    if( parseInt( this.data.quick_setup_skipped ) === 0 )
      ChinaPayments.API.post('china-payments/v1/administration/set-quick-setup-skip', { 'status' : 1 }, function( response ) {} );

    if( hash === this._currentlyDisplayed )
      return;

    this._loadTemplate();
  }

};