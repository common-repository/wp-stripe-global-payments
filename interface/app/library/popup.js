ChinaPayments.Library.Popup = {

  _default_args : {
    type                : 'default',
    allow_close         : true,
    allow_overlay_close : true,
    trigger_app_init    : false
  },

  display: function ( content, configuration ) {
    configuration = ( typeof configuration === 'undefined' ? this._default_args : china_payments_parse_args( configuration, this._default_args ) );

    if ( jQuery("#china-payments-popup-overlay").length !== 0 )
      this.close();

    jQuery("body").append(
      '<div style="display:none;" id="china-payments-popup-overlay">' +
      '<div id="china-payments-popup-wrapper" class="china-payments-interaction-overflow-container">' +
      '<div id="china-payments-popup"></div>' +
      ( configuration.allow_close
        ? '<span class="china-payments-popup-close-icon" data-china-payments-popup-trigger="close">' +
            '<svg data-ec-type="fill" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
              '<path d="M13.8323 12.0001L21.6199 4.21215C22.1267 3.70557 22.1267 2.88651 21.6199 2.37993C21.1133 1.87336 20.2943 1.87336 19.7877 2.37993L11.9999 10.1679L4.21228 2.37993C3.70548 1.87336 2.88667 1.87336 2.3801 2.37993C1.8733 2.88651 1.8733 3.70557 2.3801 4.21215L10.1677 12.0001L2.3801 19.7881C1.8733 20.2947 1.8733 21.1138 2.3801 21.6204C2.63256 21.8731 2.96449 22 3.29619 22C3.62789 22 3.95959 21.8731 4.21228 21.6204L11.9999 13.8324L19.7877 21.6204C20.0404 21.8731 20.3721 22 20.7038 22C21.0355 22 21.3672 21.8731 21.6199 21.6204C22.1267 21.1138 22.1267 20.2947 21.6199 19.7881L13.8323 12.0001Z"/>' +
            '</svg>' +
          '</span>'
        : ''
      ) +
      '</div>' +
      '</div>'
    );

    let _obj = this;


    jQuery( "html, body" ).addClass("china-payments-popup-visible");

    jQuery("#china-payments-popup-overlay").attr( 'data-china-payments-type', configuration.type );

    this.getContainerObject().html(content);

    if( configuration.trigger_app_init )
      ChinaPayments.Init( this.getContainerObject() );

    jQuery( "#china-payments-popup-overlay").show().off("click");
    jQuery( document ).off( 'keyup.china_payments_popup' );

    if( configuration.allow_close ) {
      jQuery( '#china-payments-popup-overlay [data-china-payments-popup-trigger="close"]' ).on( "click", function() {
        _obj.close();
      });
    }

    if( configuration.allow_overlay_close ) {
      jQuery( "#china-payments-popup-overlay").on("click", function ( event ) {
        if( typeof jQuery(event.target).attr("id") !== "undefined"
          && jQuery(event.target).attr("id") === 'china-payments-popup-overlay' ) {
          _obj.close();
        }
      });
    }

    if( configuration.allow_close ) {
      jQuery( document ).on( 'keyup.china_payments_popup',function(e) {
        if (e.key !== "Escape")
          return;

        _obj.close();
      });
    }
  },

  getContainerObject : function() {
    return jQuery( "#china-payments-popup");
  },

  close: function () {
    ChinaPayments.Destroy( this.getContainerObject() );

    jQuery( document ).off( 'keyup.china_payments_popup' );
    jQuery( "#china-payments-popup-overlay" ).off("click").remove();
    jQuery( "html, body" ).removeClass("china-payments-popup-visible");
  },

};