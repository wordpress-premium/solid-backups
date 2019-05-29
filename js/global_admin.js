jQuery(document).ready(function() {
	// Hide a dismissable alert and send AJAX call so it won't be shown in the future.
	jQuery( '.pb_backupbuddy_disalert' ).click( function(e) {
		e.preventDefault();
		var this_unique_id = jQuery(this).parents('.pb_backupbuddy_alert').attr('rel');
		if ( ( '' === this_unique_id ) || ( 'undefined' == typeof this_unique_id ) ) { // Don't save dismissing as this is just a one-time dismissable alert.
			jQuery(this).parents('.pb_backupbuddy_alert').slideUp();
			return;
		}
		var this_disalert_url = jQuery(this).attr('alt');
		//alert( unique_id );
		
		jQuery.post( this_disalert_url,
			{ unique_id: this_unique_id },
			function(data) {
				data = jQuery.trim( data );
				if ( data != '1' ) {
					alert( 'Error saving dismissal. Error: ' + data );
				}
				if ( e.currentTarget.href ) {
					window.location.href = e.currentTarget.href;
				}
			}
		);
		
		jQuery(this).parents('.pb_backupbuddy_alert').slideUp();
		
	});

	jQuery( '.pb_backupbuddy_alert[rel="deprecated_s3_destinations"] ').on('click', '.backupbuddy-nag-button', function() {
		jQuery(this).parent().siblings('.more_info').slideToggle();
	});
	
	
	
});



function backupbuddy_save_textarea_as_file( id_name, filename_serial ) {
    var textFileAsBlob = new Blob([ jQuery( id_name ).text() ], {type:'text/plain'});
    var fileNameToSaveAs = 'backupbuddy-' + filename_serial + '.txt';

    var downloadLink = document.createElement("a");
    downloadLink.download = fileNameToSaveAs;
    downloadLink.innerHTML = "Download File";
    downloadLink.setAttribute('target', '_new'); // Safari loads this link as a page instead of directly downloading.
   // if ( ( 'undefined' != typeof window.webkitURL ) && ( window.webkitURL !== null) ) {
        // Chrome allows the link to be clicked
        // without actually adding it to the DOM.
        //downloadLink.href = window.webkitURL.createObjectURL(textFileAsBlob);
   // } else {
        // Firefox requires the link to be added to the DOM
        // before it can be clicked.
        downloadLink.href = window.URL.createObjectURL(textFileAsBlob);
        downloadLink.onclick = backupbuddy_destroyClickedElement;
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
    //}

    downloadLink.click();
}


function backupbuddy_destroyClickedElement(event) {
    document.body.removeChild(event.target);
}
