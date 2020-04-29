jQuery(function( $ ) {
	$( '.backupbuddy-do_bulk_action' ).on( 'click', function() {
		var items = $( 'input[type="checkbox"][name^="items["]' ).length;
		if ( items ) {
			if ( ! $( 'input[type="checkbox"][name^="items["]:checked' ).length ) {
				alert( 'Select some items first.' );
				return false;
			}
		}

		return confirm( 'Are you sure you want to do this to all selected items?' );
	});

	$( '.pb_debug_show' ).on( 'click', function( e ) {
		var $btn = $( this ),
			$parent = $btn.parent();

		$btn.hide();
		$parent.children( '.pb_debug_hide').show();
		$parent.css( 'float', 'left' );
		$parent.css( 'width', '80%' );
		$parent.children( 'div' ).show();
	});

	$( '.pb_debug_hide' ).on( 'click', function( e ) {
		var $btn = $( this ),
			$parent = $btn.parent();

		$btn.hide();
		$parent.children( '.pb_debug_show').show();
		$parent.css( 'float', 'right' );
		$parent.css( 'width', '40px' );
		$parent.children( 'div' ).hide();
	});

	$( '.advanced-toggle-title' ).on( 'click', function( e ) {
		var $containerWrap = $( this ).closest( 'form' ),
			$titleToggle = $containerWrap.find( '.advanced-toggle-title' ),
			$rightArrow = $titleToggle.find( '.dashicons-arrow-right' );

		if ( $rightArrow.length > 0 ) {
			$rightArrow.removeClass( 'dashicons-arrow-right' ).addClass( 'dashicons-arrow-down' );
		} else {
			$titleToggle.find( '.dashicons-arrow-down' ).removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-right' );
		}

		$containerWrap.find( '.advanced-toggle' ).toggle();
	});

	$( '.pluginbuddy_tip' ).tooltip(); // Now using jQuery UI tooltip.

	if ('undefined' !== typeof $.tableDnD ) { // If tableDnD function loaded.
		$( '.pb_reorder' ).tableDnD({
			onDrop: function(tbody, row) {
				var new_order = new Array(),
					rows = tbody.rows;

				for (var i=0; i<rows.length; i++) {
					new_order.push( rows[i].id.substring(11) );
				}
				new_order = new_order.join( ',' );
				$( '#pb_order' ).val( new_order );
			},
			dragHandle: "pb_draghandle"
		});
	}

	$( '.pb_toggle' ).on( 'click', function( e ) {
		$( '#pb_toggle-' + $( this ).attr( 'id' ) ).slideToggle();
	});

	$( '.itbub-edits-summary .itbub-summary-item' ).on( 'click', function( e ) {
		var $el = $( this );

		$el.toggleClass( 'itbub-active' );
		$( '#' + $el.attr( 'rel' ) ).toggleClass( 'itbub-active' );
	});
});
