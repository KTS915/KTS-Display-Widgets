document.addEventListener( 'DOMContentLoaded', function() {
	'use strict';

	var widgets = document.getElementById( 'widgets-right' ).querySelectorAll( '.widget-top' );

	widgets.forEach( function( widget ) {
		widget.addEventListener( 'toggle', function( e ) {
			var inside, opts, form, data;
			if ( e.target.hasAttribute( 'open' ) ) {
				inside = e.target.querySelector( '.widget-inside' );
				form = inside.querySelector( 'form' );
				opts = inside.querySelector( '.dw_opts' );
				if ( opts == null ) {
					return;
				}

				data = new FormData( form );
				data.append( 'action', 'dw_show_widget' );
				data.delete( 'widget_number' );
				data.append( 'widget_number', ( inside.querySelector( 'input.multi_number' ).value == '') ? inside.querySelector( 'input.widget_number' ).value : inside.querySelector( 'input.multi_number' ).value );

				fetch( ajaxurl, {
					method: 'POST',
					body: new URLSearchParams( data ),
					credentials: 'same-origin'
				} )
				.then( function( response ) {
					if ( response.ok ) {
						return response.text(); // no errors
					}
					throw new Error( response.status );
				} )
				.then( function( html ) {
					opts.innerHTML = html;
				} )
				.catch( function( error ) {
					console.log( error );
				} );
			}
		} );
	} );

} );
