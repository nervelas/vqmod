/* global jQuery, wp, CGM_Admin */
( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.cgm-media-upload', function ( e ) {
		e.preventDefault();
		var $btn   = $( this );
		var $field = $btn.closest( '.cgm-media-field' );
		var $id    = $field.find( '.cgm-media-id' );
		var $prev  = $field.find( '.cgm-media-preview' );

		var frame = wp.media( {
			title: CGM_Admin.chooseText,
			button: { text: CGM_Admin.useText },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			$id.val( att.id );
			var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
			$prev.html( '<img src="' + url + '" alt="">' );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.cgm-media-remove', function ( e ) {
		e.preventDefault();
		var $field = $( this ).closest( '.cgm-media-field' );
		$field.find( '.cgm-media-id' ).val( '' );
		$field.find( '.cgm-media-preview' ).empty();
	} );

} )( jQuery );
