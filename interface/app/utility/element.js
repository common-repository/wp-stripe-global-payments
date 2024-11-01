function china_payments_element_loader( type = '') {
  let response = '';

  response += '<div class="china-payments-application-loader-wrapper"' + ( type !== '' ? ' data-china-payments-loader-type="' + type + '"' : '' ) + '>';

  if( type === 'mini' ) {
    response += '<div><div></div></div>';
  } else if( ChinaPayments.settings.loader_icon === '' ) {
    response += '<div>' +
                  '<div>' +
                    '<div>' +
                      '<div>' +
                        '<div>' +
                          '<div>' +
                          '</div>' +
                        '</div>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                '</div>';
  } else {
    response += '<img alt="loader" src="' + ChinaPayments.settings.loader_icon + '"/>';
    response += '<div>' +
                  '<div>' +
                    '<div>' +
                      '<div>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                '</div>';
  }


  response += '</div>';

  return response;
}