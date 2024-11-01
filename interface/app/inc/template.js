ChinaPayments.Template = {

  _getIdentifier : function( component, path ) {
    path = component + '/' + path;

    return ChinaPayments._template_prefix + path.replaceAll( '/template', '' ).replaceAll( '/', '-' ).replace( '.html', '' );
  },

  isLoaded : function( component, path ) {
    if( typeof path === "string" )
      return ( jQuery( '#tmpl-' + this._getIdentifier( component, path ) ).length > 0 );

    let objectInstance = this,
        response = true;

    jQuery.each( path, function( _path_key, _path_value ) {
      if( jQuery( '#tmpl-' + objectInstance._getIdentifier( component, _path_value ) ).length === 0 ) {
        response = false;
        return false;
      }
    });

    return response;
  },

  preload : function( component, path, callback ) {
    if( typeof path === "string" ) {
      if( this.isLoaded( component, path ) ) {
        if( typeof callback === 'function' )
          callback();

        return;
      }

      let objectInstance = this;

      ChinaPayments.Resource.getTemplateFile( ChinaPayments.getComponentAssetPath( component, path ), function( response ) {
        jQuery( "body" ).append( '<script type="text/template" id="tmpl-' + objectInstance._getIdentifier( component, path ) + '">' + response + '</script>');

        if( typeof callback === 'function' )
          callback();
      } );

      return;
    }

    let current_path = _.head( path );

    if( path.length === 1 ) {
      this.preload( component, current_path, callback );
      return;
    }

    let objectInstance  = this,
      remaining_paths = _.drop( path, 1 );

    this.preload( component, current_path, function() {
      objectInstance.preload( component, remaining_paths, callback );
    } );
  },

  get : function( component, path, args = {}, find_target = '' ) {
    if( typeof args !== "object" )
      args = {};

    let template_identifier = this._getIdentifier( component, path );

    if( jQuery( '#tmpl-' + template_identifier ).length > 0 ) {
      let template = wp.template( template_identifier );

      if( typeof find_target === "undefined" || find_target === '' )
        return template( args );

      jQuery( "body" ).append( '<div id="tmpl-find-' + template_identifier + '" style="display:none;">' + template( args ) + '</div>' );

      let template_find = jQuery( '#tmpl-find-' + template_identifier );

      let response = template_find.find( find_target ).html();

      template_find.remove();

      return response;
    }

    return false;
  },

  load : function( container, component, path, args = {}, callback = false, load_type = 'set' ) {
    if( typeof args !== "object" )
      args = {};

    let objectInstance = this;

    this.preload( component, path, function() {
      if( load_type === 'append' )
        container.append( objectInstance.get( component, path, args ) );
      else if( load_type === 'prepend' )
        container.prepend( objectInstance.get( component, path, args ) );
      else
        container.html( objectInstance.get( component, path, args ) );

      ChinaPayments.Init( container );

      if( typeof ChinaPayments.settings.template_extra[ component + '/' + path ] !== "undefined" ) {
        jQuery.each( ChinaPayments.settings.template_extra[ component + '/' + path ], function( template_extra_key, template_extra_data ) {
          ChinaPayments.LoadAssets( template_extra_data.file, function() {
            if( typeof window[ template_extra_data.callback ] === "function" )
              window[ template_extra_data.callback ]( container, args );
          });
        });
      }

      if( typeof callback === 'function' )
        callback();
    });
  },

};