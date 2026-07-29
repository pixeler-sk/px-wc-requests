/**
 * Customer requests settings tab: injects a "Create page" button right under
 * each form-page selector. Creating a page happens via AJAX and the newly
 * created page is added to the dropdown and auto-selected.
 */
( function ( $ ) {
	'use strict';

	var S = window.pxer_settings || {};

	$( function () {
		$.each( S.types || {}, function ( type, info ) {
			var $select = $( '#pxer_' + type + '_page_id' );
			if ( ! $select.length ) {
				return;
			}
			var $wrap = $( '<p class="pxer-create-wrap" style="margin-top:8px;margin-bottom:0"></p>' );
			renderControls( $wrap, type, info, $select );
			// Place the controls directly below the selector (after its select2 UI).
			$select.closest( 'td' ).append( $wrap );
		} );
	} );

	function renderControls( $wrap, type, info, $select ) {
		$wrap.empty();

		var current = String( $select.val() || '0' );

		if ( info.page_id && current === String( info.page_id ) ) {
			$wrap.append( link( info.edit_url, S.i18n.edit, false ) );
			$wrap.append( ' ' );
			$wrap.append( link( info.view_url, S.i18n.view, true ) );
			return;
		}

		var label = ( S.i18n.create_for || 'Create page for %s' ).replace( '%s', info.label );
		var $btn  = $( '<a href="#" class="button button-primary pxer-create-page"></a>' ).text( label );

		$btn.on( 'click', function ( e ) {
			e.preventDefault();
			create( type, info, $btn, $wrap, $select );
		} );

		$wrap.append( $btn );
	}

	function create( type, info, $btn, $wrap, $select ) {
		var original = $btn.text();
		$btn.addClass( 'disabled' ).attr( 'aria-disabled', 'true' ).text( S.i18n.creating );

		$.post( S.ajax_url, {
			action: 'pxer_create_form_page',
			nonce:  S.nonce,
			type:   type
		} ).done( function ( res ) {
			if ( res && res.success && res.data ) {
				var d = res.data;

				// Add the option if it is not already present, then select it.
				if ( ! $select.find( 'option[value="' + d.page_id + '"]' ).length ) {
					$select.append( new Option( d.title, d.page_id, true, true ) );
				}
				$select.val( String( d.page_id ) ).trigger( 'change' );

				// Refresh the controls to the "edit/view" state.
				info.page_id  = d.page_id;
				info.edit_url = d.edit_url;
				info.view_url = d.view_url;
				renderControls( $wrap, type, info, $select );
				notice( $wrap, d.message || S.i18n.created, false );
			} else {
				var msg = ( res && res.data && res.data.message ) ? res.data.message : S.i18n.failed;
				$btn.removeClass( 'disabled' ).removeAttr( 'aria-disabled' ).text( original );
				notice( $wrap, msg, true );
			}
		} ).fail( function () {
			$btn.removeClass( 'disabled' ).removeAttr( 'aria-disabled' ).text( original );
			notice( $wrap, S.i18n.failed, true );
		} );
	}

	function link( url, text, blank ) {
		var $a = $( '<a class="button"></a>' ).attr( 'href', url ).text( text );
		if ( blank ) {
			$a.attr( 'target', '_blank' ).attr( 'rel', 'noopener' );
		}
		return $a;
	}

	function notice( $wrap, text, isError ) {
		$wrap.find( '.pxer-create-msg' ).remove();
		$( '<span class="pxer-create-msg" style="margin-left:8px"></span>' )
			.text( text )
			.css( 'color', isError ? '#b32d2e' : '#1a7f37' )
			.appendTo( $wrap );
	}

} )( jQuery );
