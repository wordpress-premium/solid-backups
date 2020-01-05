<?php
/**
 * OneDrive Folder Selector
 *
 * Incoming vars:
 *     $destination_id
 *
 * @package BackupBuddy
 */

// Only output into page once.
global $backupbuddy_onedrive_folder_selector_printed;
if ( true !== $backupbuddy_onedrive_folder_selector_printed ) {
	$backupbuddy_onedrive_folder_selector_printed = true;
} else { // This has already been printed.
	return;
}
?>
<script type="text/javascript">
	var BackupBuddy = window.BackupBuddy || {},
		backupbuddy_onedrive_folder_create_ajax_url = '<?php echo pb_backupbuddy::ajax_url( 'onedrive_folder_create' ); ?>',
		backupbuddy_onedrive_folder_select_ajax_url = '<?php echo pb_backupbuddy::ajax_url( 'onedrive_folder_select' ); ?>';

	BackupBuddy.OneDriveFolderSelector = ( function( $ ) {
		'use strict';

		var folder_select_path = [],
			folder_select_path_names = [];

		var init = function() {
			bind_back_button();
			bind_create_folder();
			bind_folder_select();
			bind_open_folder();
		},

		bind_back_button = function() {
			$( '.backupbuddy-onedrive-folder-row .backupbuddy-onedrive-back' ).off( 'click' ).on( 'click', function( e ) {
				e.preventDefault();
				var destination_id = $( this ).parent( '.backupbuddy-onedrive-folder-selector' ).attr( 'data-destination-id' ),
					prev_folder_id = folder_select_path[ destination_id ][ folder_select_path[ destination_id ].length - 2 ];

				folder_select( destination_id, prev_folder_id, '', 'goback' );
			});
		}, // bind_back_button.

		bind_create_folder = function() {
			$( '.backupbuddy-onedrive-folder-row .backupbuddy-onedrive-create-folder' ).off( 'click' ).on( 'click', function( e ) {
				e.preventDefault();

				var new_folder_name = prompt( 'What would you like the new folder to be named?' );

				if ( 'undefined' === typeof new_folder_name || null === new_folder_name || ! new_folder_name.trim() ) {
					return false; // User hit cancel or didn't enter anything.
				}

				var destination_id = $(this).closest( '.backupbuddy-onedrive-folder-selector' ).attr( 'data-destination-id' ),
					$destination_wrap = get_destination_wrap( destination_id ),
					current_folder_id = folder_select_path[ destination_id ][ folder_select_path[ destination_id ].length - 1 ];

				$( '.pb_backupbuddy_loading' ).show();

				$.post(
					backupbuddy_onedrive_folder_create_ajax_url,
					{
						oauth_code: $destination_wrap.find( '#pb_backupbuddy_oauth_code' ).val(),
						onedrive_state: $destination_wrap.find( '#pb_backupbuddy_onedrive_state' ).val(),
						parent_id: current_folder_id,
						folder_name: new_folder_name.trim()
					},
					function( data ) {
						$destination_wrap.find( '.pb_backupbuddy_loading' ).hide();

						if ( 'undefined' === typeof data.success ) {
							data = $.trim( data );
							try {
								data = $.parseJSON( data );
							} catch( e ) {
								alert( 'Error #3298484: Unexpected non-json response from server: `' + data + '`.' );
								return;
							}
						}

						if ( true !== data.success ) {
							alert( 'Error #32793793: Unable to create folder. Details: `' + data.message + '`.' );
							return;
						}

						/*
						Gets back on success:
						data.id
						data.name
						*/
						set_folder( destination_id, data.id, data.name );

						var finished_callback = function() {
							$destination_wrap.find( '.backupbuddy-onedrive-status-text' ).text( 'Created & selected new folder.' );
						};

						// Refresh current folder.
						folder_select( destination_id, current_folder_id, folder_select_path_names[ current_folder_id ], 'refresh', finished_callback );
					}
				);
			});
		}, // bind_create_folder.

		bind_open_folder = function() {
			$( '.backupbuddy-onedrive-folder-row .backupbuddy-onedrive-folder-list-open' ).off( 'click' ).on( 'click', function( e ) {
				e.preventDefault();
				var destination_id = $( this ).closest( '.backupbuddy-onedrive-folder-selector' ).attr( 'data-destination-id' ),
					$folder = $( this ).parent( '.backupbuddy-onedrive-folder-list-folder' ),
					id = $folder.attr( 'data-id' ),
					name = $folder.find('.backupbuddy-onedrive-folder-list-name').text();

				folder_select( destination_id, id, name );
			});
		}, // bind_open_folder.

		bind_folder_select = function() {
			$( '.backupbuddy-onedrive-folder-row .backupbuddy-onedrive-folder-list-selected' ).off( 'click' ).on( 'click', function( e ) {
				var destination_id = $( this ).closest( '.backupbuddy-onedrive-folder-selector' ).attr( 'data-destination-id' ),
					$folder = $( this ).parent( '.backupbuddy-onedrive-folder-list-folder' ),
					id = $folder.attr( 'data-id' ),
					name = $folder.find('.backupbuddy-onedrive-folder-list-name').text();

				set_folder( destination_id, id, name );
			});
		}, // bind_folder_select.

		// End init functions.

		get_destination_wrap = function( destination_id ) {
			var $destination_wrap = $( '.pb_backupbuddy_destpicker_id' );
			if ( 'NEW' != destination_id ) {
				$destination_wrap = $( '.backupbuddy-destination-wrap[data-destination_id="' + destination_id + '"]' ); // must use underscore here.
			}
			return $destination_wrap;
		}, // get_destination_wrap.

		folder_select = function( destination_id, load_parent_id, load_parent_name, command, finished_callback ) {
			$( '.backupbuddy-onedrive-status-text' ).text( '' );

			if ( 'undefined' === typeof folder_select_path[ destination_id ] ) {
				folder_select_path[ destination_id ] = [];
				folder_select_path_names[ destination_id ] = { 'root': '/' };
			}

			if ( 'undefined' == typeof load_parent_id || '' == load_parent_id ) {
				load_parent_id = 'root';
				load_parent_name = '';
			}

			var $destination_wrap = get_destination_wrap( destination_id );
			$destination_wrap.find( '.pb_backupbuddy_loading' ).show();

			var params = {
				parent_id: load_parent_id
			};
			if ( '' != destination_id  && 'NEW' != destination_id ) {
				params.destination_id = destination_id;
			} else {
				params.oauth_code = $destination_wrap.find( '#pb_backupbuddy_oauth_code' ).val();
				params.onedrive_state = $destination_wrap.find( '#pb_backupbuddy_onedrive_state' ).val();
			}

			$.post(
				backupbuddy_onedrive_folder_select_ajax_url,
				params,
				folder_select_ajax_response( destination_id, load_parent_id, load_parent_name, command )
			);
		}, // folder_select.

		// Using closure callback so the destination_id can be passed into this and not be modified by other instances.
		folder_select_ajax_response = function( destination_id, load_parent_id, load_parent_name, command ) {
			return function( data, textStatus, jqXHR ) {
				var $destination_wrap = get_destination_wrap( destination_id ),
					breadcrumbs = '';
				//console.log( 'onedrive response for destination ' + destination_id );

				$destination_wrap.find( '.pb_backupbuddy_loading' ).hide();

				if ( 'undefined' === typeof data.success ) {
					data = $.trim( data );
					try {
						data = $.parseJSON( data );
					} catch( e ) {
						alert( 'Error #48349844: Unexpected non-json response from server: `' + data + '`.' );
						return;
					}
				}

				if ( true !== data.success ) {
					alert( 'Error #838933: Unable to get folder data. Details: `' + data.message + '`.' );
					return;
				}

				if ( 'goback' == command ) {
					var removed = folder_select_path[ destination_id ].pop();
					delete folder_select_path_names[ destination_id ][ removed ];
				} else if ( 'refresh' == command ) {
					// do nothing?
				} else {
					folder_select_path[ destination_id ].push( load_parent_id );
					folder_select_path_names[ destination_id ][ load_parent_id ] = load_parent_name;
				}

				if ( 1 === folder_select_path[ destination_id ].length ) {
					$destination_wrap.find( '.backupbuddy-onedrive-back' ).prop( 'disabled', true );
				} else {
					$destination_wrap.find( '.backupbuddy-onedrive-back' ).prop( 'disabled', false );
				}

				$destination_wrap.find( '.backupbuddy-onedrive-folder-list' ).empty(); // Clear current listing.

				// Update breadcrumbs.
				$.each( folder_select_path[ destination_id ], function( index, crumb ) {
					breadcrumbs = breadcrumbs + folder_select_path_names[ destination_id ][ crumb ] + '/';
				});
				$destination_wrap.find( '.backupbuddy-onedrive-breadcrumbs' ).text( breadcrumbs );

				if ( 0 === data.folders.length ) {
					$destination_wrap.find( '.backupbuddy-onedrive-folder-list' ).append( '<span class="description">No folders found at this location in your OneDrive.</span>' );
				} else {
					$.each( data.folders, function( index, folder ) {
						$destination_wrap.find( '.backupbuddy-onedrive-folder-list' ).append( '<span data-id="' + folder.id + '" class="backupbuddy-onedrive-folder-list-folder"><span class="backupbuddy-onedrive-folder-list-selected pb_label pb_label-info" title="Select this folder to use.">Select</span> <span class="backupbuddy-onedrive-folder-list-open dashicons dashicons-plus" title="Expand folder & view folders within"></span> <span class="backupbuddy-onedrive-folder-list-name backupbuddy-onedrive-folder-list-open">' + folder.name + '</span><span class="backupbuddy-onedrive-folder-list-created-wrap"><span class="backupbuddy-onedrive-folder-list-created">' + folder.created + '</span>&nbsp;&nbsp;Last Updated <span class="backupbuddy-onedrive-folder-list-created-ago">' + folder.created_ago + ' ago</span></span></span>' );
					});
				}

				if ( 'function' === typeof finished_callback ) {
					finished_callback();
				}

				init();
			};
		}, // folder_select_ajax_response.

		set_folder = function( destination_id, id, name ) {
			var $destination_wrap = get_destination_wrap( destination_id );

			$destination_wrap.find( '.backupbuddy-onedrive-folder-name-text' ).text( 'Selected folder name: "' + name + '"' );
			$destination_wrap.find( '#pb_backupbuddy_onedrive_folder_id' ).val( id );
			$destination_wrap.find( '#pb_backupbuddy_onedrive_folder_name' ).val( name );
		}; // set_folder.

		// Expose any publicly accessible functions.
		return {
			init: init,
			get_destination_wrap: get_destination_wrap,
			folder_select: folder_select
		};

	})( jQuery );

	jQuery(function( $ ){
		BackupBuddy.OneDriveFolderSelector.init();
	});
</script>

<style>
	.backupbuddy-onedrive-folder-list {
		border: 1px solid #DFDFDF;
		background: #F9F9F9;
		padding: 5px;
		margin-top: 5px;
		margin-bottom: 10px;
		max-height: 175px;
		overflow: auto;
	}
	.backupbuddy-onedrive-folder-list::-webkit-scrollbar {
		-webkit-appearance: none;
		width: 11px;
		height: 11px;
	}
	.backupbuddy-onedrive-folder-list::-webkit-scrollbar-thumb {
		border-radius: 8px;
		border: 2px solid white; /* should match background, can't be transparent */
		background-color: rgba(0, 0, 0, .1);
	}
	.backupbuddy-onedrive-folder-list::-webkit-scrollbar-corner{
		background-color:rgba(0,0,0,0.0);
	}
	.backupbuddy-onedrive-folder-list > span {
		display: block;
	}
	.backupbuddy-onedrive-folder-list-selected {
		cursor: pointer;
		opacity: 0.8;
	}
	.backupbuddy-onedrive-folder-list-selected:hover {
		opacity: 1;
	}
	.backupbuddy-onedrive-folder-list-open {
		cursor: pointer;
	}
	.backupbuddy-onedrive-folder-list-open:hover {
		opacity: 1;
	}
	.backupbuddy-onedrive-folder-list > span:hover {
		background: #eaeaea;
	}
	.backupbuddy-onedrive-folder-list-folder {
		border-bottom: 1px solid #EBEBEB;
		padding: 5px;
	}
	.backupbuddy-onedrive-folder-list-folder::last-child {
		border-bottom: 0;
	}
	.backupbuddy-onedrive-folder-list-created-wrap {
		float: right;
		padding-right: 15px;
		color: #bebebe;
	}
	.backupbuddy-onedrive-folder-list-created {
		color: #000;
	}
</style>

<div class="backupbuddy-onedrive-folder-selector" data-is-template="true" style="display:none;">
	<?php
	printf( '%s <span class="backupbuddy-onedrive-folder-list-selected pb_label pb_label-info" title="Select this folder to use.">%s</span> %s <span class="dashicons dashicons-plus"></span> %s: ',
		esc_html__( 'Click', 'it-l10n-backupbuddy' ),
		esc_html__( 'Select', 'it-l10n-backupbuddy' ),
		esc_html__( 'to choose folder for storage or', 'it-l10n-backupbuddy' ),
		esc_html__( 'to expand & enter folder. Current Path', 'it-l10n-backupbuddy' )
	);
	?>
	<span class="backupbuddy-onedrive-breadcrumbs">/</span>
	<div class="backupbuddy-onedrive-folder-list"></div>
	<button class="backupbuddy-onedrive-back thickbox button secondary-button">&larr; <?php esc_html_e( 'Back', 'it-l10n-backupbuddy' ); ?></button>&nbsp;&nbsp;
	<button class="backupbuddy-onedrive-create-folder thickbox button secondary-button"><?php esc_html_e( 'Create Folder', 'it-l10n-backupbuddy' ); ?></button>
	&nbsp;<span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px; vertical-align: -5px;"><img src="<?php echo esc_attr( pb_backupbuddy::plugin_url() ); ?>/images/loading.gif" alt="<?php echo esc_attr( __( 'Loading...', 'it-l10n-backupbuddy' ) ); ?>" title="<?php echo esc_attr( __( 'Loading...', 'it-l10n-backupbuddy' ) ); ?>" width="16" height="16" style="vertical-align: -3px;" /></span>
	&nbsp;<span class="backupbuddy-onedrive-status-text" style="vertical-align: -5px; font-style: italic;"></span>
</div>
