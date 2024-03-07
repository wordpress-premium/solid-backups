<?php
/**
 * Dropbox Folder Selector
 *
 * Incoming vars:
 *     $destination_id
 *
 * @package BackupBuddy
 */

// Only output into page once.
global $backupbuddy_dropbox_folder_selector_printed;
if ( true !== $backupbuddy_dropbox_folder_selector_printed ) {
	$backupbuddy_dropbox_folder_selector_printed = true;
} else { // This has already been printed.
	return;
}
?>
<script type="text/javascript">
	var BackupBuddy = window.BackupBuddy || {},
		backupbuddy_dropbox_folder_create_ajax_url = '<?php echo pb_backupbuddy::ajax_url( 'dropbox_folder_create' ); ?>',
		backupbuddy_dropbox_folder_select_ajax_url = '<?php echo pb_backupbuddy::ajax_url( 'dropbox_folder_select' ); ?>';

	BackupBuddy.DropboxFolderSelector = ( function( $ ) {
		'use strict';

		var folder_select_path = [],
			folder_select_path_names = [],
			folder_select_paths = [];

		var init = function() {
			bind_back_button();
			bind_create_folder();
			bind_folder_select();
			bind_open_folder();
		},

		bind_back_button = function() {
			$( '.backupbuddy-dropbox-folder-row .backupbuddy-dropbox-back' ).off( 'click' ).on( 'click', function( e ) {
				e.preventDefault();
				var destination_id = $( this ).parent( '.backupbuddy-dropbox-folder-selector' ).attr( 'data-destination-id' ),
					prev_folder_id = folder_select_path[ destination_id ][ folder_select_path[ destination_id ].length - 2 ],
					prev_folder_name = folder_select_path_names[ destination_id ][ prev_folder_id ],
					prev_folder_path = folder_select_paths[ destination_id ][ prev_folder_id ];

				folder_select( destination_id, prev_folder_id, prev_folder_name, prev_folder_path, 'goback' );
			});
		}, // bind_back_button.

		bind_create_folder = function() {
			$( '.backupbuddy-dropbox-folder-row .backupbuddy-dropbox-create-folder' ).off( 'click' ).on( 'click', function( e ) {
				e.preventDefault();

				var new_folder_name = prompt( 'What would you like the new folder to be named?' );

				if ( 'undefined' === typeof new_folder_name || null === new_folder_name || ! new_folder_name.trim() ) {
					return false; // User hit cancel or didn't enter anything.
				}

				var destination_id = $(this).closest( '.backupbuddy-dropbox-folder-selector' ).attr( 'data-destination-id' ),
					$destination_wrap = get_destination_wrap( destination_id ),
					current_folder_id = folder_select_path[ destination_id ][ folder_select_path[ destination_id ].length - 1 ],
					current_folder_path = folder_select_paths[ destination_id ][ current_folder_id ];

				if ( '/' !== current_folder_path ) {
					current_folder_path += '/';
				}

				$( '.pb_backupbuddy_loading' ).show();

				$.post(
					backupbuddy_dropbox_folder_create_ajax_url,
					{
						oauth_code: $destination_wrap.find( '#pb_backupbuddy_oauth_code' ).val(),
						oauth_state: $destination_wrap.find( '#pb_backupbuddy_oauth_state' ).val(),
						oauth_token: $destination_wrap.find( '#pb_backupbuddy_oauth_token' ).val(),
						folder_path: current_folder_path + new_folder_name.trim()
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
							alert( 'Error #32793793: Unable to create folder. Details: `' + data.error + '`.' );
							return;
						}

						/*
						Gets back on success:
						data.id
						data.name
						data.path
						*/
						set_folder( destination_id, data.id, data.name, data.path );

						// Add folder to arrays.
						folder_select_path[ destination_id ].push( data.id );
						folder_select_path_names[ destination_id ][ data.id ] = data.name;
						folder_select_paths[ destination_id ][ data.id ] = data.path;

						// Update breadcrumbs.
						$destination_wrap.find( '.backupbuddy-dropbox-breadcrumbs' ).text( data.path );

						var finished_callback = function() {
							$destination_wrap.find( '.backupbuddy-dropbox-status-text' ).text( 'Created & selected new folder.' );
						};

						// Refresh current folder.
						folder_select( destination_id, current_folder_id, folder_select_path_names[ current_folder_id ], folder_select_paths[ current_folder_id ], 'refresh', finished_callback );
					}
				);
			});
		}, // bind_create_folder.

		bind_open_folder = function() {
			$( '.backupbuddy-dropbox-folder-row .backupbuddy-dropbox-folder-list-open' ).off( 'click' ).on( 'click', function( e ) {
				e.preventDefault();
				var destination_id = $( this ).closest( '.backupbuddy-dropbox-folder-selector' ).attr( 'data-destination-id' ),
					$folder = $( this ).parent( '.backupbuddy-dropbox-folder-list-folder' ),
					id = $folder.attr( 'data-id' ),
					path = $folder.attr( 'data-path' ),
					name = $folder.find('.backupbuddy-dropbox-folder-list-name').text();

				folder_select( destination_id, id, name, path );
			});
		}, // bind_open_folder.

		bind_folder_select = function() {
			$( '.backupbuddy-dropbox-folder-row .backupbuddy-dropbox-folder-list-selected' ).off( 'click' ).on( 'click', function( e ) {
				var destination_id = $( this ).closest( '.backupbuddy-dropbox-folder-selector' ).attr( 'data-destination-id' ),
					$folder = $( this ).parent( '.backupbuddy-dropbox-folder-list-folder' ),
					id = $folder.attr( 'data-id' ),
					path = $folder.attr( 'data-path' ),
					name = $folder.find('.backupbuddy-dropbox-folder-list-name').text();

				set_folder( destination_id, id, name, path );
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

		folder_select = function( destination_id, load_parent_id, load_parent_name, load_parent_path, command, finished_callback ) {
			var command = 'undefined' === typeof command ? 'select' : command;

			$( '.backupbuddy-dropbox-status-text' ).text( '' );

			if ( 'undefined' === typeof folder_select_path[ destination_id ] ) {
				folder_select_path[ destination_id ] = [];
				folder_select_path_names[ destination_id ] = { 'root': '/' };
				folder_select_paths[ destination_id ] = { 'root': '/' }
			}

			if ( 'goback' === command ) {
				var removed = folder_select_path[ destination_id ].pop();
				delete folder_select_path_names[ destination_id ][ removed ];
				delete folder_select_paths[ destination_id ][ removed ];
			}

			if ( 'undefined' == typeof load_parent_id || '' == load_parent_id ) {
				load_parent_id = 'root';
				load_parent_name = '/';
				load_parent_path = '/';
			}

			var $destination_wrap = get_destination_wrap( destination_id );
			$destination_wrap.find( '.pb_backupbuddy_loading' ).show();

			var params = {
				parent_id: load_parent_id,
				parent_name: load_parent_name,
				parent_path: load_parent_path,
			};
			if ( '' != destination_id  && 'NEW' != destination_id ) {
				params.destination_id = destination_id;
			} else {
				params.oauth_code = $destination_wrap.find( '#pb_backupbuddy_oauth_code' ).val();
				params.oauth_state = $destination_wrap.find( '#pb_backupbuddy_oauth_state' ).val();
				params.oauth_token = $destination_wrap.find( '#pb_backupbuddy_oauth_token' ).val();
			}

			$.post(
				backupbuddy_dropbox_folder_select_ajax_url,
				params,
				folder_select_ajax_response( destination_id, load_parent_id, load_parent_name, load_parent_path )
			);
		}, // folder_select.

		// Using closure callback so the destination_id can be passed into this and not be modified by other instances.
		folder_select_ajax_response = function( destination_id, load_parent_id, load_parent_name, load_parent_path ) {
			return function( data, textStatus, jqXHR ) {
				var $destination_wrap = get_destination_wrap( destination_id ),
					breadcrumbs = '';

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
					alert( 'Error #838933: Unable to get folder data. Details: `' + data.error + '`.' );
					return;
				}

				if ( ! folder_select_path[ destination_id ].includes( load_parent_id ) ) {
					folder_select_path[ destination_id ].push( load_parent_id );
					folder_select_path_names[ destination_id ][ load_parent_id ] = load_parent_name;
					folder_select_paths[ destination_id ][ load_parent_id ] = load_parent_path;
				}

				if ( 1 === folder_select_path[ destination_id ].length ) {
					$destination_wrap.find( '.backupbuddy-dropbox-back' ).prop( 'disabled', true );
				} else {
					$destination_wrap.find( '.backupbuddy-dropbox-back' ).prop( 'disabled', false );
				}

				$destination_wrap.find( '.backupbuddy-dropbox-folder-list' ).empty(); // Clear current listing.

				// Update breadcrumbs.
				breadcrumbs = folder_select_paths[ destination_id ][ load_parent_id ];
				$destination_wrap.find( '.backupbuddy-dropbox-breadcrumbs' ).text( breadcrumbs );

				if ( 0 === data.folders.length ) {
					$destination_wrap.find( '.backupbuddy-dropbox-folder-list' ).append( '<span class="description">No folders found at this location in your Dropbox.</span>' );
				} else {
					$.each( data.folders, function( index, folder ) {
						var folder_row = '<span data-path="' + folder.path + '" data-id="' + folder.id + '" class="backupbuddy-dropbox-folder-list-folder"><span class="backupbuddy-dropbox-folder-list-selected pb_label pb_label-info" title="Select this folder to use.">Select</span> <span class="backupbuddy-dropbox-folder-list-open" title="Expand folder & view folders within"><?php pb_backupbuddy::$ui->render_icon('plus'); ?></span> <span class="backupbuddy-dropbox-folder-list-name backupbuddy-dropbox-folder-list-open">' + folder.name + '</span>';

						if ( 'undefined' !== typeof folder.created && 'undefined' !== typeof folder.created_ago) {
							folder_row += '<span class="backupbuddy-dropbox-folder-list-created-wrap"><span class="backupbuddy-dropbox-folder-list-created">' + folder.created + '</span>&nbsp;&nbsp;Last Updated <span class="backupbuddy-dropbox-folder-list-created-ago">' + folder.created_ago + ' ago</span></span>';
						}
						folder_row += '</span>';

						$destination_wrap.find( '.backupbuddy-dropbox-folder-list' ).append( folder_row );
					});
				}

				if ( 'function' === typeof finished_callback ) {
					finished_callback();
				}

				init();
			};
		}, // folder_select_ajax_response.

		set_folder = function( destination_id, id, name, path ) {
			var $destination_wrap = get_destination_wrap( destination_id );

			$destination_wrap.find( '.backupbuddy-dropbox-folder-name-text' ).text( 'Selected folder name: "' + name + '"' );
			$destination_wrap.find( '#pb_backupbuddy_dropbox_folder_id' ).val( id );
			$destination_wrap.find( '#pb_backupbuddy_dropbox_folder_name' ).val( name );
			$destination_wrap.find( '#pb_backupbuddy_dropbox_folder_path' ).val( path );
		}; // set_folder.

		// Expose any publicly accessible functions.
		return {
			init: init,
			get_destination_wrap: get_destination_wrap,
			folder_select: folder_select
		};

	})( jQuery );

	jQuery(function( $ ){
		BackupBuddy.DropboxFolderSelector.init();
	});
</script>

<style>
	.backupbuddy-dropbox-folder-list {
		border: 1px solid #DFDFDF;
		background: #F9F9F9;
		padding: 5px;
		margin-top: 5px;
		margin-bottom: 10px;
		max-height: 175px;
		overflow: auto;
	}
	.backupbuddy-dropbox-folder-list::-webkit-scrollbar {
		-webkit-appearance: none;
		width: 11px;
		height: 11px;
	}
	.backupbuddy-dropbox-folder-list::-webkit-scrollbar-thumb {
		border-radius: 8px;
		border: 2px solid white; /* should match background, can't be transparent */
		background-color: rgba(0, 0, 0, .1);
	}
	.backupbuddy-dropbox-folder-list::-webkit-scrollbar-corner{
		background-color:rgba(0,0,0,0.0);
	}
	.backupbuddy-dropbox-folder-list > span {
		display: block;
	}
	.backupbuddy-dropbox-folder-list-selected {
		cursor: pointer;
		opacity: 0.8;
	}
	.backupbuddy-dropbox-folder-list-selected:hover {
		opacity: 1;
	}
	.backupbuddy-dropbox-folder-list-open {
		cursor: pointer;
	}
	.backupbuddy-dropbox-folder-list-open:hover {
		opacity: 1;
	}
	.backupbuddy-dropbox-folder-list > span:hover {
		background: #eaeaea;
	}
	.backupbuddy-dropbox-folder-list-folder {
		border-bottom: 1px solid #EBEBEB;
		padding: 5px;
	}
	.backupbuddy-dropbox-folder-list-folder::last-child {
		border-bottom: 0;
	}
	.backupbuddy-dropbox-folder-list-created-wrap {
		float: right;
		padding-right: 15px;
		color: #bebebe;
	}
	.backupbuddy-dropbox-folder-list-created {
		color: #000;
	}
</style>

<div class="backupbuddy-dropbox-folder-selector" data-is-template="true" style="display:none;">
	<?php
	printf( '%s <span class="backupbuddy-dropbox-folder-list-selected pb_label pb_label-info" title="Select this folder to use.">%s</span> %s <span class="dashicons dashicons-plus"></span> %s: ',
		esc_html__( 'Click', 'it-l10n-backupbuddy' ),
		esc_html__( 'Select', 'it-l10n-backupbuddy' ),
		esc_html__( 'to choose folder for storage or', 'it-l10n-backupbuddy' ),
		esc_html__( 'to expand & enter folder. Current Path', 'it-l10n-backupbuddy' )
	);
	?>
	<span class="backupbuddy-dropbox-breadcrumbs">/</span>
	<div class="backupbuddy-dropbox-folder-list"></div>
	<button class="backupbuddy-dropbox-back thickbox button button-secondary secondary-button">&larr; <?php esc_html_e( 'Back', 'it-l10n-backupbuddy' ); ?></button>&nbsp;&nbsp;
	<button class="backupbuddy-dropbox-create-folder thickbox button button-secondary secondary-button"><?php esc_html_e( 'Create Folder', 'it-l10n-backupbuddy' ); ?></button>
	&nbsp;<span class="pb_backupbuddy_loading" style="display: none; margin-left: 10px; vertical-align: -5px;"></span>
	&nbsp;<span class="backupbuddy-dropbox-status-text" style="vertical-align: -5px; font-style: italic;"></span>
</div>
