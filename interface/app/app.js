// @codekit-prepend "utility/date.js"
// @codekit-prepend "utility/element.js"
// @codekit-prepend "utility/misc.js"
// @codekit-prepend "utility/string.js"

// @codekit-append "library/popup.js"

// @codekit-append "inc/api.js"
// @codekit-append "inc/hashtag.js"
// @codekit-append "inc/resource.js"
// @codekit-append "inc/template.js"

/**
 * @author Robert Rusu
 */
let ChinaPayments = {

  Library : {},
  Component : {},

  _assets : {
    requested : [],
    loaded    : []
  },
  _components : {
    pending   : {},
    instanced : {}
  },
  _template_prefix : 'china-payments-',

  settings : {},
  lang : {},

  Init : function ( containerTarget ) {
    containerTarget.find( '[data-china-payments-component]' ).not( '[data-china-payments-component-loaded]' ).each( function() { ChinaPayments.initComponent( jQuery(this) ); });

    containerTarget.find( '#china-payments-notification-container .notice-dismiss' ).on( "click", function() {
      ChinaPayments.API.post( 'china-payments/v1/administration/dismiss-notification', false );
    });
  },

  initComponent : function( container ) {
    let component = container.attr( 'data-china-payments-component' );

    this.setLoadingContent( container, '', ( component === 'listing-search' ? 'mini' : '' ) );

    if( typeof ChinaPayments.Component[ component ] !== "undefined" ) {
      ChinaPayments._instanceComponent( component, container );
    } else {
      ChinaPayments._loadComponent( component, container );
    }
  },

  getComponentInstance : function( component, component_index ) {
    component_index = parseInt( component_index );

    return ChinaPayments._components.instanced[ component ][ component_index ];
  },

  setLoadingContent : function( container, loading_text = '', type = '' ) {
    if( container.find( ' > .china-payments-application-loader-wrapper' ).length === 1 ) {
        container.find( ' > .china-payments-application-loader-status-text' ).remove();

      if( loading_text !== '' )
        container.prepend( '<div class="china-payments-application-loader-status-text">' + loading_text + '</div>' );

      return;
    }

    container.html( china_payments_element_loader( type ) );

    if( loading_text !== '' )
      container.prepend( '<div class="china-payments-application-loader-status-text">' + loading_text + '</div>' );
  },

  setNoResultsContent : function( container ) {
    container.html( '<div data-china-payments-notification="info">' + this.lang.no_results_response + '</div>' );
  },

  setErrorContent : function( container, response ) {
    if( typeof response.error_html !== "undefined" ) {
      container.html( response.error_html );
      ChinaPayments.Init( container );

      return;
    }

    let error = ( typeof response.error_notification !== "undefined" ? response.error_notification : response.error );

    container.html( '<div data-china-payments-notification="danger">' + error + '</div>' );
  },

  setCancelledContent : function( container ) {
    if( typeof window.closed !== "undefined" && window.closed ) {
      container.html( '' );
      return;
    }

    container.html( '<div data-china-payments-notification="info">' + this.lang.cancelled_request + '</div>' );
  },

  setFailedAssetFetchContent : function( container ) {
    if( typeof window.closed !== "undefined" && window.closed ) {
      container.html( '' );
      return;
    }

    container.html( '<div data-china-payments-notification="danger">' + this.lang.asset_failed_fetch + '</div>' );
  },

  setHTML : function( container, html ) {
    ChinaPayments.Destroy( container );

    container.html( html );

    ChinaPayments.Init( container );
  },

  _loadComponent : function( component, componentContainer = false ) {
    if( typeof this._components.pending[ component ] === "undefined" ) {
      if( componentContainer !== false )
        this._components.pending[ component ] = [ componentContainer ];

      this.Resource.loadCSS(
        this.getComponentAssetPath( component, 'style.css' ),
        {
          'china-payments-component-stylesheet' : component
        }
      )

      this.Resource.loadJS( this.getComponentAssetPath( component, 'controller.min.js' ), function() {
        ChinaPayments._loadedComponent( component );
      }, function() {
        if( componentContainer !== false )
          ChinaPayments.setFailedAssetFetchContent( componentContainer );
      } );

      return;
    }

    if( componentContainer === false )
      return;

    if( typeof this._components.pending[ component ] === "undefined" )
      this._components.pending[ component ] = [];

    this._components.pending[ component ][ this._components.pending[ component ].length ] = componentContainer;
  },

  _loadedComponent : function( component ) {
    if( typeof this._components.pending[ component ] === 'undefined' )
      return;

    if( this._components.pending[ component ].length !== 0 ) {
      jQuery.each( this._components.pending[ component ], function( index, componentContainer ) {
        ChinaPayments._instanceComponent( component, componentContainer );
      });
    }

    delete this._components.pending[ component ];
  },

  _instanceComponent : function( component, componentContainer ) {
    if( typeof componentContainer.attr( "data-china-payments-component-loaded" ) !== "undefined" )
      return;

    componentContainer.attr( 'data-china-payments-component-loaded', 0 );

    this.setLoadingContent( componentContainer );

    if( typeof this._components.instanced[ component ] === "undefined" )
      this._components.instanced[ component ] = [];

    this._components.instanced[ component ][ this._components.instanced[ component ].length ] = china_payments_clone_object( this.Component[ component ] );

    componentContainer.attr( "data-china-payments-component-instance-index", this._components.instanced[ component ].length - 1 );

    this._components.instanced[ component ][ this._components.instanced[ component ].length - 1 ].Init( componentContainer );

    componentContainer.attr( 'data-china-payments-component-loaded', 1 );

    this.Hashtag.Init();
  },

  getComponentAssetPath : function( component, path ) {
    if( typeof this.settings.component_injection[ component ] !== "undefined" ) {
      let component_injection = this.settings.component_injection[ component ];

      if( typeof component_injection === 'object' ) {
        let objectKeys   = Object.keys( component_injection ),
          _matched_index = false;

        jQuery.each( objectKeys, function( _objectKeyIndex, _objectKey ) {
          if( path.indexOf( _objectKey ) !== 0 )
            return true;

          _matched_index = _objectKey;

          return false;
        });

        if( false !== _matched_index )
          return component_injection[ _matched_index ] + '/' + path.replace( _matched_index, '' );

        return component_injection[ '__default' ] + '/' + path;
      }

      return this.settings.component_injection[ component ] + '/' + path;
    }

    return this.settings.library_url + 'component/' + component + '/' + path;
  },

  LoadAssets : function( asset, callback = false, _attach_version = true ) {
    if( typeof asset === "string" ) {
      if( china_payments_in_array( asset, ChinaPayments._assets.loaded ) ) {
        if( typeof callback === 'function' )
          callback();

        return;
      }

      if( china_payments_in_array( asset, ChinaPayments._assets.requested ) ) {
        setTimeout( function() {
          ChinaPayments.LoadAssets( asset, callback, _attach_version );
        }, 100 );

        return;
      }

      ChinaPayments._assets.requested[ ChinaPayments._assets.requested.length ] = asset;

      if( asset.endsWith( '.css' ) ) {
        ChinaPayments.Resource.loadCSS( asset );

        if( typeof callback === 'function' )
          callback();

        return;
      }

      ChinaPayments.Resource.loadJS( asset, callback, false, _attach_version );

      return;
    }

    let current_asset = _.head( asset );

    if( asset.length === 1 ) {
      this.LoadAssets( current_asset, callback );
      return;
    }

    let objectInstance   = this,
        remaining_assets = _.drop( asset, 1 );

    this.LoadAssets( current_asset, function() {
      objectInstance.LoadAssets( remaining_assets, callback );
    } );
  },

  isAssetLoaded : function( asset ) {
    if( typeof this._assets.loaded === "undefined" )
      return false;

    if( asset instanceof Array ) {
      let response = true;

      jQuery.each( asset, function( k, a ) {
        if( !ChinaPayments._assets.loaded.includes( a ) )
          response = false;
      });

      return response;
    }

    return this._assets.loaded.includes( asset );
  },

  Destroy : function( target ) {
    if( typeof target.attr( 'data-china-payments-component' ) !== "undefined" )
      ChinaPayments.__destroyComponent( target );

    if( typeof tinyMCE !== "undefined" ) {
      tinyMCE.triggerSave();

      target.find( ".wp-editor-area" ).each( function() {
        if( jQuery(this).attr( "id" ) !== "undefined" )
          tinyMCE.execCommand('mceRemoveEditor', false, jQuery(this).attr('id'));
      });
    }

    target.find( '[data-china-payments-component]' ).each( function() {
      ChinaPayments.__destroyComponent( jQuery(this) );
    });

    target.find( '[data-china-payments-library]' ).each( function() {
      let library = jQuery(this).attr( 'data-china-payments-library' );

      library = china_payments_uc_first(library);

      if( typeof ChinaPayments.Library[ library ] !== "undefined" ) {
        ChinaPayments.Library[library].Destroy( jQuery(this) );
      }
    });

    target.find( "*" ).off();
    target.html( '' );
  },

  __destroyComponent : function( targetContainer ) {
    let component       = targetContainer.attr( "data-china-payments-component" ),
        component_index = targetContainer.attr( "data-china-payments-component-instance-index" );

    let _is_stored = ( typeof ChinaPayments._components.instanced[ component ] !== 'undefined' && typeof ChinaPayments._components.instanced[ component ][component_index] !== 'undefined' )

    if( _is_stored && typeof ChinaPayments._components.instanced[ component ][component_index].__onDestroy === 'function' )
      ChinaPayments._components.instanced[ component ][component_index].__onDestroy();

    targetContainer.removeAttr( "data-china-payments-component" );
    targetContainer.removeAttr( "data-china-payments-component-loaded" );
    targetContainer.removeAttr( "data-china-payments-component-instance-index" );

    if( _is_stored )
      ChinaPayments._components.instanced[ component ].splice( component_index, 1 );
  },

  RemoveHTMLNode : function( target ) {
    this.Destroy( target );
    target.remove();
  }

};

function _china_payments_init_application( lang, configuration ) {
  ChinaPayments.lang     = lang;
  ChinaPayments.settings = china_payments_parse_args( configuration, ChinaPayments.settings );

  ChinaPayments.Init( jQuery( "body" ) );

  jQuery(window).trigger( "china_payments_ready" );
}

jQuery( document ).ready( function() {
  jQuery.extend(jQuery.expr[":"], {
    "containsCaseInsensitive": function (elem, i, match, array) {
      return (elem.textContent || elem.innerText || "").toLowerCase().indexOf((match[3] || "").toLowerCase()) >= 0;
    }
  });

  _china_payments_init_application( window.china_payments_data.lang, window.china_payments_data.configuration );
});
