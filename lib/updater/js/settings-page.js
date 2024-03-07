jQuery(document).ready(
	function() {
		if ( jQuery( '#ithemes-updater-redirect-to-url' ).length ) {
			window.location.replace( jQuery( '#ithemes-updater-redirect-to-url' ).val() );
		}
	}
);
