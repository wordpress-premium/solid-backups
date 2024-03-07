<?php
/**
 * Google Drive (v2) Folder Selecter Interface.
 *
 * Incoming Vars:
 *   int $disable_gzip  If Gzip is disabled.
 *
 * @package BackupBuddy
 */

?>
<script>
	var backupbuddy_gdrive2_folderSelect_path = [],
		backupbuddy_gdrive2_folderSelect_pathNames = [], // { 'root': '/' }
		backupbuddy_gdrive2_disable_gzip = '<?php echo esc_js( $disable_gzip ); ?>';

	function backupbuddy_gdrive2_getDestinationWrap( destinationID ) {
		if ( 'NEW' != destinationID ) {
			return jQuery( '.backupbuddy-destination-wrap[data-destination_id="' + destinationID + '"]' );
		}
		return jQuery( '.pb_backupbuddy_destpicker_id' );
	}

	function backupbuddy_gdrive2_folderSelect( destinationID, loadParentID, loadParentTitle, command, finishedCallback ) {
		jQuery( '.backupbuddy-gdrive2-statusText' ).text( '' );

		if ( 'undefined' == typeof backupbuddy_gdrive2_folderSelect_path[ destinationID ] ) {
			backupbuddy_gdrive2_folderSelect_path[ destinationID ] = [];
			backupbuddy_gdrive2_folderSelect_pathNames[ destinationID ] = { 'root': '/' };
		}

		if ( ( 'undefined' == typeof loadParentID ) || ( '' == loadParentID ) ) {
			loadParentID = 'root';
			loadParentTitle = '';
		}

		var destinationWrap = backupbuddy_gdrive2_getDestinationWrap( destinationID );
		destinationWrap.find( '.pb_backupbuddy_loading' ).show();

		var clientID = destinationWrap.find( '#pb_backupbuddy_client_id' ).val(),
			token = destinationWrap.find( '#pb_backupbuddy_token' ).val(),
			service_account_email = destinationWrap.find( '#pb_backupbuddy_service_account_email' ).val(),
			service_account_file = destinationWrap.find( '#pb_backupbuddy_service_account_file' ).val();

		jQuery.post(
			'<?php echo pb_backupbuddy::ajax_url( 'gdrive_folder_select' ); ?>',
			{
				service_account_email: service_account_email,
				service_account_file: service_account_file,
				clientID: clientID,
				disable_gzip: backupbuddy_gdrive2_disable_gzip,
				token: token,
				parentID: loadParentID,
				gdrive_version: 2
			},
			backupbuddy_gdrive2_folderSelect_ajaxResponse( destinationID, loadParentID, loadParentTitle, command )
		);
	}

	// Using closure callback so the destinationID can be passed into this and not be modified by other instances.
	function backupbuddy_gdrive2_folderSelect_ajaxResponse( destinationID, loadParentID, loadParentTitle, command ) {
		return function( response, textStatus, jqXHR ) {
			var destinationWrap = backupbuddy_gdrive2_getDestinationWrap( destinationID );
			destinationWrap.find( '.pb_backupbuddy_loading' ).hide();

			if ( 'undefined' === typeof response.success ) {
				alert( 'Error #3298484: Unexpected non-json response from server: `' + response + '`.' );
				return;
			}
			if ( true !== response.success ) {
				alert( 'Error #32793793: Unable to load folder. Details: `' + response.error + '`.' );
				return;
			}

			if ( response.error ) {
				alert( response.error );
			}

			if ( 'goback' == command ) {
				var removed = backupbuddy_gdrive2_folderSelect_path[ destinationID ].pop();
				delete backupbuddy_gdrive2_folderSelect_pathNames[ removed ];
			} else if ( 'refresh' == command ) {
				// same location
			} else {
				backupbuddy_gdrive2_folderSelect_path[ destinationID ].push( loadParentID );
				backupbuddy_gdrive2_folderSelect_pathNames[ destinationID ][ loadParentID ] = loadParentTitle;
			}
			if ( 1 == backupbuddy_gdrive2_folderSelect_path[ destinationID ].length ) {
				destinationWrap.find( '.backupbuddy-gdrive2-back' ).prop( 'disabled', true );
			} else {
				destinationWrap.find( '.backupbuddy-gdrive2-back' ).prop( 'disabled', false );
			}

			destinationWrap.find( '.backupbuddy-gdrive2-folderList' ).empty(); // Clear current listing.

			// Update breadcrumbs.
			var breadcrumbs = '';
			jQuery.each( backupbuddy_gdrive2_folderSelect_path[ destinationID ], function( index, crumb ) {
				breadcrumbs = breadcrumbs + backupbuddy_gdrive2_folderSelect_pathNames[ destinationID ][ crumb ] + '/';
			});
			destinationWrap.find( '.backupbuddy-gdrive2-breadcrumbs' ).text( breadcrumbs );

			jQuery.each( response.folders, function( index, folder ) {
				var row_html = '<span data-id="' + folder.id + '" class="backupbuddy-gdrive2-folderList-folder"><span class="backupbuddy-gdrive2-folderList-selected pb_label pb_label-info" title="Select this folder to use.">Select</span> <span class="backupbuddy-gdrive2-folderList-open dashicons dashicons-plus" title="Expand folder & view folders within"></span> <span class="backupbuddy-gdrive2-folderList-title backupbuddy-gdrive2-folderList-open">' + folder.title + '</span>';

				if ( folder.created && folder.createdAgo ) {
					row_html += '<span class="backupbuddy-gdrive2-folderList-createdWrap"><span class="backupbuddy-gdrive2-folderList-created">' + folder.created + '</span>&nbsp;&nbsp;Modified <span class="backupbuddy-gdrive2-folderList-createdAgo">' + folder.createdAgo + ' ago</span></span>';
				}

				row_html += '</span>';

				destinationWrap.find( '.backupbuddy-gdrive2-folderList' ).append( row_html );
			});
			if ( 0 === response.folders.length ) {
				destinationWrap.find( '.backupbuddy-gdrive2-folderList' ).append( '<span class="description">No folders found at this location in your Google Drive.</span>' );
			}

			if ( 'function' == typeof finishedCallback ) {
				finishedCallback();
			}
		};
	} // backupbuddy_gdrive2_folderSelect_ajaxResponse.

	function backupbuddy_gdrive2_setFolder( destinationID, id, title ) {
		destinationWrap = backupbuddy_gdrive2_getDestinationWrap( destinationID );

		destinationWrap.find( '.backupbuddy-gdrive2-folder-name-text' ).text( 'Selected folder name: "' + title + '"' );
		destinationWrap.find( '#pb_backupbuddy_folder_id' ).val( id );
		destinationWrap.find( '#pb_backupbuddy_folder_name' ).val( title );
	} // backupbuddy_gdrive2_setFolder.

	jQuery(function( $ ) {

		// OPEN a folder.
		$( '.pb_backupbuddy_settings_form' ).on( 'click', '.backupbuddy-gdrive2-folderList-open', function(event){
			var destinationID = $( this ).closest( '.backupbuddy-gdrive2-folderSelector' ).attr( 'data-destinationID' ),
				folderObj = $( this ).closest( '.backupbuddy-gdrive2-folderList-folder' ),
				id = folderObj.attr( 'data-id' ),
				title = folderObj.find('.backupbuddy-gdrive2-folderList-title').text();

			backupbuddy_gdrive2_folderSelect( destinationID, id, title );
		});

		// Go UP a folder
		$( '.pb_backupbuddy_settings_form' ).on( 'click', '.backupbuddy-gdrive2-back', function(e){
			e.preventDefault();

			var destinationID = $( this ).closest( '.backupbuddy-gdrive2-folderSelector' ).attr( 'data-destinationID' ),
				prevFolderID = backupbuddy_gdrive2_folderSelect_path[ destinationID ][ backupbuddy_gdrive2_folderSelect_path[ destinationID ].length - 2 ];

			backupbuddy_gdrive2_folderSelect( destinationID, prevFolderID, '', 'goback' );
		});

		// SELECT a folder.
		$( '.pb_backupbuddy_settings_form' ).on( 'click', '.backupbuddy-gdrive2-folderList-selected', function(e){
			var destinationID = $( this ).closest( '.backupbuddy-gdrive2-folderSelector' ).attr( 'data-destinationID' ),
				folderObj = $( this ).closest( '.backupbuddy-gdrive2-folderList-folder' ),
				id = folderObj.attr( 'data-id' ),
				title = folderObj.find('.backupbuddy-gdrive2-folderList-title').text();

			backupbuddy_gdrive2_setFolder( destinationID, id, title );
		});

		// CREATE a folder.
		$( '.pb_backupbuddy_settings_form' ).on( 'click', '.backupbuddy-gdrive2-createFolder', function(e){
			e.preventDefault();
			var destinationID = $( this ).closest( '.backupbuddy-gdrive2-folderSelector' ).attr( 'data-destinationID' ),
				destinationWrap = backupbuddy_gdrive2_getDestinationWrap( destinationID ),
				currentFolderID = backupbuddy_gdrive2_folderSelect_path[ destinationID ][ backupbuddy_gdrive2_folderSelect_path[ destinationID ].length - 1 ],
				newFolderName = prompt( 'What would you like the new folder to be named?' );

			newFolderName = newFolderName ? newFolderName.trim() : false;

			if ( ! newFolderName ) {
				return false; // User hit cancel.
			}

			$( '.pb_backupbuddy_loading' ).show();
			$.post(
				'<?php echo pb_backupbuddy::ajax_url( 'gdrive_folder_create' ); ?>',
				{
					service_account_email: destinationWrap.find( '#pb_backupbuddy_service_account_email' ).val(),
					service_account_file: destinationWrap.find( '#pb_backupbuddy_service_account_file' ).val(),
					clientID: destinationWrap.find( '#pb_backupbuddy_client_id' ).val(),
					token: destinationWrap.find( '#pb_backupbuddy_token' ).val(),
					parentID: currentFolderID,
					folderName: newFolderName,
					gdrive_version: 2
				},
				function( response ) {
					destinationWrap.find( '.pb_backupbuddy_loading' ).hide();
					if ( 'undefined' === typeof response.success ) {
						alert( 'Error #3298484: Unexpected non-json response from server: `' + response + '`.' );
						return;
					}
					if ( true !== response.success ) {
						alert( 'Error #32793793: Unable to create folder. Details: `' + response.error + '`.' );
						return;
					}

					/*
					Gets back on success:
					response.folder_id
					response.folder_name
					*/
					backupbuddy_gdrive2_setFolder( destinationID, response.folder_id, response.folder_name );

					var finishedCallback = function(){
						destinationWrap.find( '.backupbuddy-gdrive2-statusText' ).text( 'Created & selected new folder.' );
					};

					// Refresh current folder.
					backupbuddy_gdrive2_folderSelect( destinationID, currentFolderID, backupbuddy_gdrive2_folderSelect_pathNames[ currentFolderID ], 'refresh', finishedCallback );
				}
			);
		});
	});
</script>
<style>
	.backupbuddy-gdrive2-folderList {
		border: 1px solid #DFDFDF;
		background: #F9F9F9;
		padding: 5px;
		margin-top: 5px;
		margin-bottom: 10px;
		max-height: 175px;
		overflow: auto;
	}

	.backupbuddy-gdrive2-folderList::-webkit-scrollbar {
		-webkit-appearance: none;
		width: 11px;
		height: 11px;
	}
	.backupbuddy-gdrive2-folderList::-webkit-scrollbar-thumb {
		border-radius: 8px;
		border: 2px solid white; /* should match background, can't be transparent */
		background-color: rgba(0, 0, 0, .1);
	}
	.backupbuddy-gdrive2-folderList::-webkit-scrollbar-corner{
		background-color:rgba(0,0,0,0.0);
	}

	.backupbuddy-gdrive2-folderList > span {
		display: block;
		/*padding: 0;
		margin-left: -4px;*/
	}
	.backupbuddy-gdrive2-folderList-selected {
		cursor: pointer;
		opacity: 0.8;
	}
	.backupbuddy-gdrive2-folderList-selected:hover {
		opacity: 1;
	}
	.backupbuddy-gdrive2-folderList-open {
		cursor: pointer;
	}
	.backupbuddy-gdrive2-folderList-open:hover {
		opacity: 1;
	}
	.backupbuddy-gdrive2-folderList > span:hover {
		background: #eaeaea;
	}
	.backupbuddy-gdrive2-folderList-folder {
		border-bottom: 1px solid #EBEBEB;
		padding: 5px;
	}
	.backupbuddy-gdrive2-folderList-folder:last-child {
		border-bottom: 0;
	}
	.backupbuddy-gdrive2-folderList-title {
		/*font-size: 1.2em;*/
	}
	.backupbuddy-gdrive2-folderList-createdWrap {
		float: right;
		padding-right: 15px;
		color: #bebebe;
	}
	.backupbuddy-gdrive2-folderList-created {
		color: #000;
	}
	.backupbuddy-gdrive2-folderList-createdAgo {
		/*opacity: 0.6;*/
	}
</style>

<div class="backupbuddy-gdrive2-folderSelector" data-isTemplate="true" style="display: none;">
	<?php esc_html_e( 'Click', 'it-l10n-backupbuddy' ); ?>
	<span class="backupbuddy-gdrive2-folderList-selected pb_label pb_label-info" title="Select this folder to use."><?php esc_html_e( 'Select', 'it-l10n-backupbuddy' ); ?></span>
	<?php esc_html_e( 'to choose folder for storage or', 'it-l10n-backupbuddy' ); ?>
	<span class="dashicons dashicons-plus"></span>
	<?php esc_html_e( 'to expand & enter folder. Current Path:', 'it-l10n-backupbuddy' ); ?>

	<span class="backupbuddy-gdrive2-breadcrumbs">/</span>
	<div class="backupbuddy-gdrive2-folderList"></div>

	<button class="backupbuddy-gdrive2-back thickbox button button-secondary secondary-button">&larr; Back</button>&nbsp;&nbsp;
	<button class="backupbuddy-gdrive2-createFolder thickbox button button-secondary secondary-button">Create Folder</button>&nbsp;
	<span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px; vertical-align: -5px;"></span>&nbsp;
		<span class="backupbuddy-gdrive2-statusText" style="vertical-align: -5px; font-style: italic;"></span>
</div>
