ChinaPayments.API = {

  post : function( path, request_data, callback ) {
    return this.__request( path, 'POST', request_data, callback );
  },

  fetch : function( action, request_data, callback ) {
    return this.__request( action, 'GET', request_data, callback );
  },

  __request : function( path, method, request_data, callback ) {
    return jQuery.ajax( {
      url        : ChinaPayments.settings.rest_url + path,
      method     : method,
      beforeSend : function ( xhr ) {
        xhr.setRequestHeader( 'X-WP-Nonce', ChinaPayments.settings.rest_nonce );
      },
      data       : request_data
    } ).done( function ( response, statusText, xhr ) {
      callback( response );
    } ).fail( function( $xhr ) {
      callback( $xhr.responseJSON );
    });
  },

};