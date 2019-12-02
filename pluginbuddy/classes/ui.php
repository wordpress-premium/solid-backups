<?php
/**
 * PluginBuddy UI Class
 *
 * @author Dustin Bolton
 * @package BackupBuddy
 */

/**
 * Handles typical user interface items used in WordPress development.
 *
 * @version 1.0.0
 */
class pb_backupbuddy_ui {

	private $_tab_interface_tag = '';


	/*	pluginbuddy_ui->start_metabox()
	 *
	 *	Starts a metabox. Use with end_metabox().
	 *	@see pluginbuddy_ui->end_metabox
	 *
	 *	@param		string				$title				Title to display for the metabox.
	 *	@param		boolean				$echo				Echos if true; else returns.
	 *	@param		boolean/string		$small_or_css		true: size is limited smaller. false: size is limited larger. If a string then interpreted as CSS.
	 *	@return		null/string								Returns null if $echo is true; else returns string with HTML.
	 */
	public function start_metabox( $title, $echo = true, $small_or_css = false ) {
		if ( $small_or_css === false ) { // Large size.
			$css = 'width: 70%; min-width: 720px;';
		} elseif ( $small_or_css === true ) { // Small size.
			$css = 'width: 20%; min-width: 250px;';
		} else { // String so interpret as CSS.
			$css = $small_or_css;
		}

		$css .= ' padding-top: 0; margin-top: 10px; cursor: auto;';

		$response = '<div class="metabox-holder postbox" style="' . $css . '">
						<h3 class="hndle" style="cursor: auto;"><span>' . $title . '</span></h3>
						<div class="inside">';
		if ( $echo === true ) {
			echo $response;
		} else {
			return $response;
		}
	} // End start_metabox().



	/*	pluginbuddy_ui->end_metabox()
	 *
	 *	Ends a metabox. Use with start_metabox().
	 *	@see pluginbuddy_ui->start_metabox
	 *
	 *	@param		boolean		$echo		Echos if true; else returns.
	 *	@return		null/string				Returns null if $echo is true; else returns string with HTML.
	 */
	public function end_metabox( $echo = true ) {
		$response = '	</div>
					</div>';
		if ( $echo === true ) {
			echo $response;
		} else {
			return $response;
		}
	} // End end_metabox().

	/**
	 * Displays a styled, properly formatted title for pages.
	 *
	 * @param string $title       Title to display.
	 * @param bool   $echo        Whether or not to echo the string or return.
	 * @param bool   $br          Disables the line break after h1.
	 * @param string $icon_class  Customize the icon class.
	 *
	 * @return null|string  Returns null if $echo is true; else returns string with HTML.
	 */
	public function title( $title, $echo = true, $br = true, $icon_class = 'backupbuddy-icon-drive' ) {
		$return = sprintf( '<h1 style="zoom: 1.05;"><span class="%s"></span> %s</h1>', esc_attr( $icon_class ), $title );
		if ( true === $br ) {
			$return .= '<br />';
		}
		if ( true !== $echo ) {
			return $return;
		}
		echo $return;
	} // End title().


	/*	pluginbuddy_ui->button()
	 *
	 *	Displays a nice pretty styled button. How nice. Always returns.
	 *
	 *	@param		string		$url				URL (href) for the button to link to.
	 *	@param		string		$text				Text to display in the button.
	 *	@param		string		$title				Optional title text to display on hover in the title tag.
	 *	@param		boolean		$primary			(optional) Whether or not this is a primary button. Primary buttons are blue and strong where default non-primary is grey and gentle.
	 *	@param		string		$additional_class	(optional) Additional CSS class to apply to button. Useful for thickbox or other JS stuff.
	 *	@param		string		$id					(optional) HTML ID to apply. Useful for JS.
	 *	@return		string							HTML string for the button.
	 */
	public function button( $url, $text, $title = '', $primary = false, $additional_class = '', $id = '' ) {
		if ( $primary === false ) {
			return '<a class="button secondary-button ' . $additional_class . '" style="margin-top: 3px;" id="' . $id . '" title="' . $title . '" href="' . $url . '">' . $text . '</a>';
		} else {
			return '<a class="button button-primary ' . $additional_class . '" style="margin-top: 3px;" id="' . $id . '" title="' . $title . '" href="' . $url . '">' . $text . '</a>';
		}
	} // End button().



	/*	pluginbuddy_ui->note()
	 *
	 *	Display text in a subtle way.
	 *
	 *	@param		string		$text		Text of note.
	 *	@param		boolean		$echo		Whether or not to echo the string or return.
	 *	@return		null/string				Returns null if $echo is true; else returns string with HTML.
	 */
	public static function note( $text, $echo = true ) {
		$return = '<span class="description ui-note"><i>' . $text . '</i></span>';
		if ( true !== $echo ) {
			return $return;
		}
		echo $return;
	} // End note().

	/**
	 * Displays a nice table with multiple columns, rows, bulk actions, hover actions, etc similar to WordPress posts table.
	 * Currently only supports echo of output.
	 *
	 * @param array $items     Array of rows to display. Each row array contains an array with the columns. Typically set in controller.
	 *                         Ex: array( array( 'blue', 'red' ), array( 'brown', 'green' ) ).
	 *                         If the value for an item is an array then the first value will be assigned to the rel tag of any hover actions. If not
	 *                         an array then the value itself will be put in the rel tag.  If an array the second value will be the one displayed in the column.
	 *                         BackupBuddy needed the displayed item in the column to be a link (for downloading backups) but the backup file as the rel.
	 * @param array $settings  Array of all the various settings. Merged with defaults prior to usage. Typically set in view.
	 *                         See $default_settings at beginning of function for available settings.
	 *                         Ex: $settings = array(
	 *                             'action'        => pb_backupbuddy::plugin_url(),
	 *                             'columns'       => array( 'Group Name', 'Images', 'Shortcode' ),
	 *                             'hover_actions' => array( 'edit' => 'Edit Group Settings' ),
	 *                             'bulk_actions'  => array( 'delete_images' => 'Delete' ),
	 *                         );
	 *                         // Slug can be a URL. In this case the value of the hovered row will be appended to the end of the URL.
	 *                         // TODO: Make the first hover action be the link for the first listed item.
	 */
	public static function list_table( $items, $settings ) {
		$default_settings = array(
			'columns'                  => array(),
			'hover_actions'            => array(),
			'bulk_actions'             => array(),
			'hover_action_column_key'  => '', // int of column to set value= in URL and rel tag= for using in JS.
			'action'                   => '',
			'reorder'                  => '',
			'after_bulk'               => '',
			'css'                      => '',
			'table_class'              => '',
			'table_id'                 => '',
			'destination_id'           => '',
			'disable_top_bulk_actions' => false,
			'disable_tfoot'            => false,
			'disable_wrapper'          => false,
			'wrapper_class'            => false,
			'display_mode'             => '',
			'form_class'               => '',
		);

		// Merge defaults.
		$settings = apply_filters( 'backupbuddy_list_table_settings', array_merge( $default_settings, $settings ), $items );

		// Function to iterate through bulk actions. Top and bottom set are the same.
		if ( ! function_exists( 'bulk_actions' ) ) {
			/**
			 * Bulk Actions function if it doesn't already exist.
			 *
			 * @param array  $settings    Settings array.
			 * @param bool   $hover_note  Display Hover note.
			 * @param string $class       Wrapper class name.
			 * @param bool   $echo        Echo output or return.
			 *
			 * @return string|null  Output or null if echoed.
			 */
			function bulk_actions( $settings, $hover_note = true, $class = '', $echo = true ) {
				if ( count( $settings['bulk_actions'] ) <= 0 ) {
					return;
				}

				$hover_note = $hover_note && count( $settings['hover_actions'] ) > 0;
				$class      = $class ? sprintf( ' class="%s"', esc_attr( $class ) ) : '';
				$output     = '<div' . $class . '>';
				if ( count( $settings['bulk_actions'] ) == 1 ) {
					foreach ( $settings['bulk_actions'] as $action_slug => $action_title ) {
						$output .= '<input type="hidden" name="bulk_action" value="' . $action_slug . '">';
						$output .= '<input type="submit" name="do_bulk_action" value="' . $action_title . '" class="button secondary-button backupbuddy-do_bulk_action">';
					}
				} else {
					$output .= '<select name="bulk_action" class="actions">';
					foreach ( $settings['bulk_actions'] as $action_slug => $action_title ) {
						$output .= '<option>Bulk Actions</option>';
						$output .= '<option value="' . $action_slug . '">' . $action_title . '</option>';
					}
					$output .= '</select> &nbsp;';
					//$output .= self::button( '#', 'Apply' );
					$output .= '<input type="submit" name="do_bulk_action" value="Apply" class="button secondary-button backupbuddy-do_bulk_action">';
				}
				$output .= '&nbsp;&nbsp;';
				$output .= $settings['after_bulk'];

				$output .= '<div class="alignright actions">';
				if ( true === $hover_note && count( $settings['hover_actions'] ) ) {
					$output .= pb_backupbuddy::$ui->note( 'Hover over items above for additional options.', false );
				}
				if ( '' != $settings['reorder'] ) {
					$output .= '<input type="submit" name="save_order" id="save_order" value="Save Order" class="button-secondary" />';
				}
				$output .= '</div><!-- .actions -->';

				$output .= '</div><!-- wrapper -->';

				if ( true !== $echo ) {
					return $output;
				}

				echo $output;
			} // End subfunction bulk_actions.
		} // End if function does not exist.

		if ( '' != $settings['action'] ) {
			$form_class = ! empty( $settings['form_class'] ) ? sprintf( ' class="%s"', esc_attr( $settings['form_class'] ) ) : '';
			printf( '<form method="post" action="' . $settings['action'] . '"%s>', $form_class );
			pb_backupbuddy::nonce();
			if ( '' != $settings['reorder'] ) {
				echo '<input type="hidden" name="order" value="" id="pb_order">';
			}
		}

		if ( true !== $settings['disable_wrapper'] ) {
			printf( '<div class="backupbuddy-list-table-wrapper %s" style="%s">', esc_attr( $settings['wrapper_class'] ), esc_attr( $settings['css'] ) );
		}

		// Display bulk actions (top).
		if ( true !== $settings['disable_top_bulk_actions'] ) {
			bulk_actions( $settings, false, 'bulk-actions top-bulk-actions' );
		}

		echo '<table class="widefat striped';
		if ( ! empty( $settings['table_class'] ) ) {
			echo ' ' . esc_attr( $settings['table_class'] );
		}
		echo '"';
		if ( ! empty( $settings['table_id'] ) ) {
			echo ' id="' . esc_attr( $settings['table_id'] ) . '"';
		}
		echo '>';

		self::column_headings( $settings, true );

		echo '<tbody';
		if ( '' != $settings['reorder'] ) {
			echo ' class="pb_reorder"';
		}
		echo '>';

		// LOOP THROUGH EACH ROW.
		$itemi = 0;
		$month = false;
		foreach ( (array) $items as $item_id => $item ) {
			$itemi++;
			$timestamp_attr = '';
			$timestamp      = self::get_timestamp( $item );
			if ( $timestamp ) {
				$timestamp_attr = sprintf( ' data-timestamp="%s"', esc_attr( $timestamp ) );
			}

			$addl_row_class = ' month-' . strtolower( date( 'M', $timestamp ) );
			if ( date( 'M', $timestamp ) !== $month ) {
				$addl_row_class .= ' begin-month';
				$month           = date( 'M', $timestamp );
			}

			echo sprintf( '<tr class="entry-row%s" data-id="%s" data-destination-id="%s"%s>', esc_attr( $addl_row_class ), esc_attr( basename( $item_id ) ), esc_attr( $settings['destination_id'] ), $timestamp_attr );
			if ( count( $settings['bulk_actions'] ) > 0 ) {
				echo '<th scope="row" class="check-column"><input type="checkbox" name="items[]" class="entries" value="' . esc_attr( $item_id ) . '"></th>';
			}
			$column_class   = sanitize_title( strip_tags( $settings['columns'][0] ) );
			$column_comment = self::get_comment( $item );
			echo sprintf( '<td class="%s"%s>', esc_attr( $column_class ), $column_comment );
			self::first_column_content( $item, $item_id, $itemi, $settings, true );
			echo '</td>';

			if ( '' != $settings['reorder'] ) {
				$count = count( $item ) + 1; // Extra row for reordering.
			} else {
				$count = count( $item );
			}

			// LOOP THROUGH COLUMNS FOR THIS ROW.
			for ( $i = 1; $i < $count; $i++ ) {
				if ( ! isset( $item[ $i ] ) ) {
					continue; // This row does not have a corresponding index-based item.  It is probably a named key not for use in table?
				}
				$column_class = ! empty( $settings['columns'][ $i ] ) ? sanitize_title( strip_tags( $settings['columns'][ $i ] ) ) : '';
				echo '<td';
				if ( '' != $settings['reorder'] ) {
					if ( $i == $settings['reorder'] ) {
						echo ' align="center"';
						$column_class .= ' pb_draghandle';
					}
				}
				if ( $column_class ) {
					echo sprintf( ' class="%s"', esc_attr( $column_class ) );
				}
				echo '>';

				if ( $settings['reorder'] != '' && $i == $settings['reorder'] ) {
					echo '<img src="' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/draghandle.png" alt="Click and drag to reorder">';
				} else {
					self::column_content( $item, $i, $item_id, $itemi, $settings, true );
				}

				echo '</td>';
			}

			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		// Display bulk actions (bottom).
		bulk_actions( $settings, true, 'bulk-actions bottom-bulk-actions' );

		if ( true !== $settings['disable_wrapper'] ) {
			echo '</div>';
		}

		if ( '' != $settings['action'] ) {
			echo '</form>';
		}
	} // list_table.

	/**
	 * Try to find timestamp value.
	 *
	 * @param array $item  Row array.
	 *
	 * @return int  Row timestamp.
	 */
	public static function get_timestamp( $item ) {
		$timestamp = '';
		if ( ! is_array( $item ) ) {
			if ( is_int( $item ) ) {
				$timestamp = $item;
			}
		} elseif ( ! empty( $item[0][1] ) && is_int( $item[0][1] ) ) {
			$timestamp = $item[0][1];
		} elseif ( ! empty( $item[0] ) && is_int( $item[0] ) ) {
			$timestamp = $item[0];
		}

		return (int) $timestamp;
	}

	/**
	 * Check if comment found in row item.
	 *
	 * @param array $item  Row item array.
	 *
	 * @return string  Comment attribute.
	 */
	public static function get_comment( $item ) {
		if ( ! is_array( $item ) || empty( $item[0] ) || ! is_array( $item[0] ) ) {
			return '';
		}
		if ( empty( $item[0][2] ) ) {
			return '';
		}
		return sprintf( ' title="%s"', esc_attr( $item[0][2] ) );
	}

	/**
	 * Output Column headings in thead and tfoot.
	 *
	 * @param array $settings  Settings array.
	 * @param bool  $echo      Echo or return output.
	 *
	 * @return string|null  Output or null if echoed.
	 */
	public static function column_headings( $settings, $echo = false ) {
		$output = '<thead><tr class="thead">';
		if ( count( $settings['bulk_actions'] ) > 0 ) {
			$output .= '<th scope="col" class="check-column"><input type="checkbox" class="check-all-entries" /></th>';
		}
		foreach ( $settings['columns'] as $column ) {
			$column_class = sanitize_title( strip_tags( $column ) );
			$output      .= sprintf( '<th class="%s">%s</th>', $column_class, $column );
		}
		$output .= '</tr></thead>';

		if ( true !== $settings['disable_tfoot'] ) {
			$output .= '<tfoot><tr class="thead">';
			if ( count( $settings['bulk_actions'] ) > 0 ) {
				$output .= '<th scope="col" class="check-column"><input type="checkbox" class="check-all-entries" /></th>';
			}
			foreach ( $settings['columns'] as $column ) {
				$column_class = str_replace( ' ', '_', strtolower( strip_tags( $column ) ) );
				$output      .= sprintf( '<th class="%s">%s</th>', $column_class, $column );
			}
			$output .= '</tr></tfoot>';
		}

		if ( true !== $echo ) {
			return $output;
		}

		echo $output;
	}

	/**
	 * Output first column content.
	 *
	 * @param array $item      Row item array.
	 * @param int   $item_id   Row item ID.
	 * @param int   $itemi     Row item incrementer.
	 * @param array $settings  Settings array.
	 * @param bool  $echo      Return or echo the output.
	 *
	 * @return string|null  Output or null if echoed.
	 */
	public static function first_column_content( $item, $item_id, $itemi, $settings, $echo = false ) {
		$output = '';
		if ( is_array( $item[0] ) ) {
			if ( isset( $item[0][1] ) && '' == $item[0][1] ) {
				$output .= '&nbsp;';
			} else {
				$output .= $item[0][1];
			}
		} else {
			if ( '' == $item[0] ) {
				$output .= '&nbsp;';
			} else {
				$output .= $item[0];
			}
		}

		$output = apply_filters( 'backupbuddy_list_table_first_column_content', apply_filters( 'backupbuddy_list_table_column_content', $output, $item, $item_id, $itemi, $settings ), $item, $item_id, $itemi, $settings );

		$output .= self::hover_actions( $settings, $item, $item_id, $itemi );

		if ( true !== $echo ) {
			return $output;
		}

		echo $output;
	}

	/**
	 * Output every other column content.
	 *
	 * @param array $item      Row item array.
	 * @param int   $i         Column index.
	 * @param int   $item_id   Row item ID.
	 * @param int   $itemi     Row item incrementer.
	 * @param array $settings  Settings array.
	 * @param bool  $echo      Return or echo the output.
	 *
	 * @return string|null  Output or null if echoed.
	 */
	public static function column_content( $item, $i, $item_id, $itemi, $settings, $echo = false ) {
		$output = '';
		if ( '' == $item[ $i ] ) {
			$output .= '&nbsp;';
		} else {
			$output .= $item[ $i ];
		}

		$output = apply_filters( 'backupbuddy_list_table_column_content', $output, $item, $i, $item_id, $itemi, $settings );

		if ( true !== $echo ) {
			return $output;
		}

		echo $output;
	}

	/**
	 * Output Hover Actions.
	 *
	 * @param array $settings  Settings array.
	 * @param array $item      Row item array.
	 * @param int   $item_id   Row item ID.
	 * @param int   $itemi     Row item incrementer.
	 * @param bool  $echo      Return or echo the output.
	 *
	 * @return string|null  Output or null if echoed.
	 */
	public static function hover_actions( $settings, $item, $item_id, $itemi, $echo = false ) {
		$output = '';

		$settings['hover_actions'] = apply_filters( 'backupbuddy_list_table_hover_actions', $settings['hover_actions'], $item_id, $item, $itemi );

		if ( $settings['hover_actions'] ) {
			$output .= '<div class="row-actions" style="margin-top: 10px;">'; //  style="margin:0; padding:0;"
		}

		$i = 0;

		foreach ( $settings['hover_actions'] as $action_slug => $action_title ) { // Display all hover actions.
			$i++;
			// If filter is set to not display edit link for first schedule. Don't show it.
			if ( 1 === $itemi && ! empty( $settings['hide_edit_for_first_schedule'] ) && 'edit' == $action_slug ) {
				continue;
			}

			if ( '' != $settings['hover_action_column_key'] ) {
				if ( is_array( $item[ $settings['hover_action_column_key'] ] ) ) {
					$hover_action_column_value = $item[ $settings['hover_action_column_key'] ][0];
				} else {
					$hover_action_column_value = $item[ $settings['hover_action_column_key'] ];
				}
			} else {
				$hover_action_column_value = '';
			}

			if ( strstr( $action_slug, '/' ) === false ) { // Word hover action slug.
				$action_url = ! empty( $settings['action'] ) ? $settings['action'] : pb_backupbuddy::page_url();
				$hover_link = $action_url . '&' . $action_slug . '=' . $item_id;
				if ( $hover_action_column_value ) {
					$hover_link .= '&value=' . $hover_action_column_value;
				}
			} else { // URL hover action slug so just append value to URL.
				$hover_link = $action_slug . $hover_action_column_value;
			}

			// Some hosts don't allow get params that end in .zip.
			if ( '.zip' === strtolower( substr( $hover_link, -4 ) ) ) {
				$hover_link .= '&bub_rand=' . rand( 100, 999 );
			}

			$output .= '<a href="' . $hover_link . '" class="pb_' . pb_backupbuddy::settings( 'slug' ) . '_hoveraction_' . $action_slug . '" rel="' . $hover_action_column_value . '">' . $action_title . '</a>';
			if ( $i < count( $settings['hover_actions'] ) ) {
				$output .= ' | ';
			}
		}
		if ( $settings['hover_actions'] ) {
			$output .= '</div>';
		}

		if ( true !== $echo ) {
			return $output;
		}

		echo $output;
	}

	/**
	 *	pb_backupbuddy::get_feed()
	 *
	 *	Gets an RSS or other feed and inserts it as a list of links...
	 *
	 *	@param		string		$feed		URL to the feed.
	 *	@param		int			$limit		Number of items to retrieve.
	 *	@param		string		$append		HTML to include in the list. Should usually be <li> items including the <li> code.
	 *	@param		string		$replace	String to replace in every title returned. ie twitter includes your own username at the beginning of each line.
	 *	@return		string
	 */
	public function get_feed( $feed, $limit, $append = '', $replace = '' ) {
		$return = '';

		$feed_html = get_transient( md5( $feed ) );

		if ( false === $feed_html ) {
			$feed_html = '';
			require_once(ABSPATH.WPINC.'/feed.php');
			$rss = fetch_feed( $feed );
			if ( is_wp_error( $rss ) ) {
				$return .= '{Temporarily unable to load feed.}';
				return $return;
			}
			$maxitems = $rss->get_item_quantity( $limit ); // Limit
			$rss_items = $rss->get_items(0, $maxitems);
		}

		$return .= '<ul class="pluginbuddy-nodecor" style="margin-left: 10px;">';


		if ( $feed_html == '' ) {
			foreach ( (array) $rss_items as $item ) {
				$feed_html .= '<li style="list-style-type: none;"><a href="' . $item->get_permalink() . '" target="_blank">';
				$title =  $item->get_title(); //, ENT_NOQUOTES, 'UTF-8');
				if ( $replace != '' ) {
					$title = str_replace( $replace, '', $title );
				}
				if ( strlen( $title ) < 30 ) {
					$feed_html .= $title;
				} else {
					$feed_html .= substr( $title, 0, 32 ) . ' ...';
				}
				$feed_html .= '</a></li>';
			}
			set_transient( md5( $feed ), $feed_html, 300 ); // expires in 300secs aka 5min
		} else {
			//echo 'CACHED';
		}
		$return .= $feed_html;

		$return .= $append;
		$return .= '</ul>';


		return $return;
	} // End get_feed().



	/**
	 *	pb_backupbuddy::tip()
	 *
	 *	Displays a message to the user when they hover over the question mark. Gracefully falls back to normal tooltip.
	 *	HTML is supposed within tooltips.
	 *
	 *	@param		string		$message		Actual message to show to user.
	 *	@param		string		$title			Title of message to show to user. This is displayed at top of tip in bigger letters. Default is blank. (optional)
	 *	@param		boolean		$echo_tip		Whether to echo the tip (default; true), or return the tip (false). (optional)
	 *	@return		string/null					If not echoing tip then the string will be returned. When echoing there is no return.
	 */
	public function tip( $message, $title = '', $echo_tip = true ) {
		if ( '' != $title ) {
			$message = $title . ' - ' . $message;
		}
		$tip = ' <a class="pluginbuddy_tip" title="' . $message . '"><img src="' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/pluginbuddy_tip.png" alt="(?)" /></a>';
		if ( $echo_tip === true ) {
			echo $tip;
		} else {
			return $tip;
		}
	} // End tip().

	/**
	 * Displays a message to the user at the top of the page when in the dashboard.
	 *
	 * @param string $message     Message you want to display to the user.
	 * @param bool   $error       OPTIONAL! true indicates this alert is an error and displays as red. Default: false.
	 * @param string $error_code  OPTIONAL! Error code number to use in linking in the wiki for easy reference.
	 * @param string $rel_tag     Rel attribute value.
	 * @param string $more_css    Additional inline styles.
	 * @param array  $args        Array of additional arguments.
	 */
	public function alert( $message, $error = false, $error_code = '', $rel_tag = '', $more_css = '', $args = array() ) {
		$log_error = false;
		$id        = 'message';
		$style     = '';
		$rel       = '';
		$classes   = 'pb_backupbuddy_alert';

		if ( ! empty( $args['id'] ) ) {
			$id = sprintf( ' id="%s"', esc_attr( $args['id'] ) );
		}
		if ( $rel_tag || ! empty( $args['rel'] ) ) {
			$rel_tag = ! empty( $args['rel'] ) ? $args['rel'] : $rel_tag;
			$rel     = sprintf( ' rel="%s"', esc_attr( $rel_tag ) );
		}
		if ( $more_css || ! empty( $args['css'] ) ) {
			$more_css = ! empty( $args['css'] ) ? $args['css'] : $more_css;
			$style    = sprintf( ' style="%s"', esc_attr( $more_css ) );
		}
		if ( ! empty( $args['class'] ) ) {
			$classes .= ' ' . $args['class'];
		}
		if ( false === $error ) {
			$classes .= ' updated fade';
		} else {
			$classes  .= ' error';
			$log_error = true;
		}

		$alert = sprintf( '<div class="%s"%s%s%s>%%s</div>', esc_attr( $classes ), $id, $style, $rel );

		if ( '' != $error_code ) {
			$message  .= sprintf( ' <a href="https://ithemeshelp.zendesk.com/hc/en-us/articles/211132377-Error-Codes-#%s" target="_blank"><i>%s Error Code %s - Click for more details.</i></a>', esc_attr( $error_code ), esc_html( pb_backupbuddy::settings( 'name' ) ), esc_html( $error_code ) );
			$log_error = true;
		}

		if ( true === $log_error ) {
			pb_backupbuddy::log( $message . sprintf( ' Error Code: %s', esc_html( $error_code ) ), 'error' );
		}

		echo sprintf( $alert, $message );
	} // End alert().

	/**
	 * Displays a DISMISSABLE message to the user at the top of the page when in the dashboard.
	 *
	 * @param string $unique_id  Unique Message ID.
	 * @param string $message    Message you want to display to the user.
	 * @param bool   $error      OPTIONAL! true indicates this alert is an error and displays as red. Default: false.
	 * @param string $more_css   OPTIONAL! Error code number to use in linking in the wiki for easy reference.
	 * @param array  $args       Array of additional args to pass.
	 */
	public function disalert( $unique_id, $message, $error = false, $more_css = '', $args = array() ) {
		if ( '' == $unique_id || ! isset( pb_backupbuddy::$options['disalerts'][ $unique_id ] ) ) {
			$message = '<a style="float: right;" class="pb_backupbuddy_disalert" href="javascript:void(0);" title="' . __( 'Dismiss this alert. Unhide dismissed alerts on the Settings page.', 'it-l10n-backupbuddy' ) . '" alt="' . pb_backupbuddy::ajax_url( 'disalert' ) . '"><b>' . __( 'Dismiss', 'it-l10n-backupbuddy' ) . '</b></a><div style="margin-right: 120px;">' . $message . '</div>';
			$this->alert( $message, $error, '', $unique_id, $more_css, $args );
		} else {
			echo '<!-- Previously Dismissed Alert: `' . htmlentities( $message ) . '` -->';
		}

	} // End disalert().

	/**
	 *	pb_backupbuddy::video()
	 *
	 *	Displays a YouTube video to the user when they hover over the question video mark.
	 *	HTML is supposed within tooltips.
	 *
	 *	@param		string		$video_key		YouTube video key from the URL ?v=VIDEO_KEY_HERE  -- To jump to a certain timestamp add #SECONDS to the end of the key, where SECONDS is the number of seconds into the video to start at. Example to start 65 seconds into a video: 9ZHWGjBr84s#65. This must be in seconds format.
	 *	@param		string		$title			Title of message to show to user. This is displayed at top of tip in bigger letters. Default is blank. (optional)
	 *	@param		boolean		$echo_tip		Whether to echo the tip (default; true), or return the tip (false). (optional)
	 *	@return		string/null					If not echoing tip then the string will be returned. When echoing there is no return.
	 */
	public function video( $video_key, $title = '', $echo_tip = true ) {

		self::enqueue_thickbox();

		if ( strstr( $video_key, '#' ) ) {
			$video = explode( '#', $video_key );
			$video[1] = '&start=' . $video[1];
		} else {
			$video[0] = $video_key;
			$video[1] = '';
		}

		$tip = '<a target="_blank" href="http://www.youtube.com/embed/' . urlencode( $video[0] ) . '?autoplay=1' . $video[1] . '&TB_iframe=1&width=600&height=400" class="thickbox pluginbuddy_tip" title="Video Tutorial - ' . $title . '"><img src="' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/pluginbuddy_play.png" alt="(video)" /></a>';
		if ( $echo_tip === true ) {
			echo $tip;
		} else {
			return $tip;
		}
	} // End video().



	/**
	 * pb_backupbuddy::enqueue_thickbox()
	 *
	 * Enqueues the required scripts / styles needed to use thickbox
	 *
	 * @return null
	 */
	public function enqueue_thickbox() {

		if ( !defined( 'PB_IMPORTBUDDY' ) ) {
			global $wp_scripts;
			if ( is_object( $wp_scripts ) ) {
				if ( !in_array( 'thickbox', $wp_scripts->done ) ) {
					wp_enqueue_script( 'thickbox' );
					wp_print_scripts( 'thickbox' );
					wp_print_styles( 'thickbox' );
				}
			}
		}
	} // End enqueue_thickbox().



	/*	start_tabs()
	 *
	 *	Starts a tabbed interface.
 	 *	@see end_tabs().
	 *
	 *	@param		string		$interface_tag		Tag/slug for this entire tabbed interface. Should be unique.
	 *	@param		array		$tabs				Array containing an array of settings for this tabbed interface. Ex:  array( array( 'title'> 'my title', 'slug' => 'mytabs' ) );
	 *												Optional setting with key `ajax_url` may define a URL for AJAX loading.
	 *	@param		string		$css				Additional CSS to apply to main outer div. (optional)
	 *	@param		boolean		$echo				Echo output instead of returning. (optional)
	 *	@param		int			$active_tab_index	Tab to start as active/selected.
	 *	@return		null/string						null if $echo = false, all data otherwise.
	 */
	public function start_tabs( $interface_tag, $tabs, $css = '', $echo = true, $active_tab_index = 0 ) {
		$this->_tab_interface_tag = $interface_tag;

		pb_backupbuddy::load_script( 'pb_tabs.js', true );

		$prefix = 'pb_' . pb_backupbuddy::settings( 'slug' ) . '_'; // pb_PLUGINSLUG_
		$return = '';

		/*
		$return .= '<script type="text/javascript">';
		$return .= '	jQuery(document).ready(function() {';
		$return .= '		jQuery("#' . $prefix . $this->_tab_interface_tag . '_tabs").tabs({ active: ' . $active_tab_index . ' });';
		$return .= '	});';
		$return .= '</script>';
		*/

		$return .= '<div class="backupbuddy-tabs-wrap">';


		$return .= '<h2 class="nav-tab-wrapper">';
		$i = 0;
		foreach( $tabs as $tab ) {
			if ( ! isset( $tab['css'] ) ) {
				$tab['css'] = '';
			}
			$active_tab_class = '';
			if ( $active_tab_index == $i ) {
				$active_tab_class = 'nav-tab-active';
			}
			if ( isset( $tab['ajax'] ) && ( $tab['ajax_url'] != '' ) ) { // AJAX tab.
				$return .= '<a class="nav-tab nav-tab-' . $i . ' ' . $active_tab_class . ' bb-tab-' . $tab['slug'] . '" href="javascript:void(0)" data-ajax="' . $tab['ajax_url'] . '">' . $tab['title'] . '</a>';
			} elseif ( isset( $tab['url'] ) && ( $tab['url'] != '' ) ) {
				$return .= '<a class="nav-tab nav-tab-' . $i . ' ' . $active_tab_class . ' bb-tab-' . $tab['slug'] . '" href="' . $tab['url'] . '">' . $tab['title'] . '</a>';
			} else { // Standard; NO AJAX.
				$return .= '<a class="nav-tab nav-tab-' . $i . ' ' . $active_tab_class . ' bb-tab-' . $tab['slug'] . '" style="' . $tab['css'] . '" href="#' . $prefix . $this->_tab_interface_tag . '_tab_' . $tab['slug'] . '">' . $tab['title'] . '</a>';
			}
			$i++;
		}
		$return .= '</h2>';


		/*
		$return .= '<div id="' . $prefix . $this->_tab_interface_tag . '_tabs" style="' . $css . '">';
		$return .= '<ul>';
		foreach( $tabs as $tab ) {
			if ( ! isset( $tab['css'] ) ) {
				$tab['css'] = '';
			}
			if ( isset( $tab['ajax'] ) && ( $tab['ajax_url'] != '' ) ) { // AJAX tab.
				$return .= '<li><a href="' . $tab['ajax_url'] . '"><span>' . $tab['title'] . '</span></a></li>';
			} else { // Standard; NO AJAX.
				$return .= '<li style="' . $tab['css'] . '"><a href="#' . $prefix . $this->_tab_interface_tag . '_tab_' . $tab['slug'] . '"><span>' . $tab['title'] . '</span></a></li>';
			}
		}
		$return .= '</ul>';
		$return .= '<br>';
		$return .= '<div class="tabs-borderwrap">';
		*/

		$return .= '<div class="backupbuddy-tab-blocks">';

		if ( $echo === true ) {
			echo $return;
		} else {
			return $return;
		}

	} // End start_tabs().



	/*	end_tabs()
	 *
	 *	Closes off a tabbed interface.
	 *	@see start_tabs().
	 *
	 *	@param		boolean		$echo				Echo output instead of returning.  (optional)
	 *	@return		null/string						null if $echo = false, all data otherwise.
	 */
	public function end_tabs( $echo = true ) {

		/*
		$return = '';
		$return .= '	</div>';
		$return .= '</div>';
		*/
		$return = '</div></div>';

		$this->_tab_interface_tag = '';

		if ( $echo === true ) {
			echo $return;
		} else {
			return $return;
		}

	} // End end_tabs().



	/*	start_tab()
	 *
	 *	Opens the start of an individual page to be loaded by a tab.
	 *	@see end_tab().
	 *
	 *	@param		string		$tab_tag			Unique tag for this tab section. Must match the tag defined when creating the tab interface.
	 *	@param		boolean		$echo				Echo output instead of returning.  (optional)
	 *	@return		null/string						null if $echo = false, all data otherwise.
	 */
	public function start_tab( $tab_tag, $echo = true ) {

		$prefix = 'pb_' . pb_backupbuddy::settings( 'slug' ) . '_'; // pb_PLUGINSLUG_
		$return = '';

		$return .= '<div class="backupbuddy-tab" id="' . $prefix . $this->_tab_interface_tag . '_tab_' . $tab_tag . '">';


		if ( $echo === true ) {
			echo $return;
		} else {
			return $return;
		}

	} // End start_tab().



	/*	end_tab()
	 *
	 *	Closes this tab section.
	 *	@see start_tab().
	 *
	 *	@param		string		$tab_tag			Unique tag for this tab section. Must match the tag defined when creating the tab interface.
	 *	@param		boolean		$echo				Echo output instead of returning.  (optional)
	 *	@return		null/string						null if $echo = false, all data otherwise.
	 */
	public function end_tab( $echo = true ) {

		$return = '</div>';


		if ( $echo === true ) {
			echo $return;
		} else {
			return $return;
		}

	} // End end_tab().


	/**
	 * Output HTML headers when using AJAX.
	 *
	 * @param bool   $js          Whether or not to load javascript. Default false.
	 * @param bool   $padding     Whether or not to padd wrapper div. Default has padding.
	 * @param string $body_class  Body class.
	 */
	public function ajax_header( $js = true, $padding = true, $body_class = '' ) {
		echo '<html>';
		echo '<head>';
		echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
		echo '<title>BackupBudddy iFrame</title>';

		wp_print_styles( 'wp-admin' );
		wp_print_styles( 'dashicons' );
		wp_print_styles( 'buttons' );
		wp_print_styles( 'colors' );

		if ( $js === true ) {
			wp_enqueue_script( 'jquery' );
			wp_print_scripts( 'jquery' );
		}

		pb_backupbuddy::load_style( 'wp-admin.css' );
		pb_backupbuddy::load_style( 'thickboxed.css' );

		//echo '<link rel="stylesheet" href="' . pb_backupbuddy::plugin_url(); . '/css/admin.css" type="text/css" media="all" />';
		pb_backupbuddy::load_script( 'admin.js', true );
		pb_backupbuddy::load_style( 'admin.css' );
		pb_backupbuddy::load_script( 'jquery-ui-tooltip', false );
		pb_backupbuddy::load_style( 'jQuery-ui-1.11.2.css', true );

		printf( '<body class="wp-core-ui %s" style="background: inherit;">', esc_attr( $body_class ) );
		if ( $padding === true ) {
			echo '<div class="bb-iframe-divpadding-noscroll" style="padding: 12px; padding-left: 20px; padding-right: 20px; overflow: scroll;">';
		} else {
			echo '<div>';
		}

	} // End ajax_header().


	function ajax_footer( $js_common = true ) {
		echo '</div>';

		if ( true === $js_common ) {
			pb_backupbuddy::load_script( 'common' ); // Needed for table 'select all' feature.
		}

		echo '</body>';
		echo '</head>';
		echo '</html>';
	} // End ajax_footer().



} // End class pluginbuddy_ui.

