<?php
/**
 * PluginBuddy Settings Class
 *
 * @package BackupBuddy
 */

/**
 * Handles setting up and parsing submitted data for settings pages. Uses form class for handling forms.
 * If a savepoint is passed to the constructor then settings will be auto-saved on save.
 * If false is passed to the savepoint then the process() function may be used to validate and grab submitted form data for custom processing.
 *
 * @see pluginbuddy_form
 *
 * @author Dustin Bolton
 */
class pb_backupbuddy_settings {

	/**
	 * The Form Object
	 *
	 * @var pb_bakcupbuddy_form
	 */
	private $_form;

	/**
	 * Form Name
	 *
	 * @var string
	 */
	private $_form_name = '';

	/**
	 * Form prefix
	 *
	 * @var string
	 */
	private $_prefix = '';

	/**
	 * Savepoint status
	 *
	 * @var string
	 */
	private $_savepoint;

	/**
	 * Settings array
	 *
	 * @var array
	 */
	private $_settings = array();

	/**
	 * Custom Title Width
	 *
	 * @var int
	 */
	private $_custom_title_width = '';

	/**
	 * Default constructor.
	 *
	 * @param string $form_name                  Name / slug of the form.
	 * @param mixed  $save_point_or_custom_mode  Location in pb_backupbuddy::$options array to save to. Ex: groups#5 saves into: pb_backupbuddy::$options['groups'][5].
	 *                                           If false, the process() function will not save but will return results instead including form name => value pairs in an array for processing.
	 *                                           If array, then these will be treated as the defaults. Works the same as being false other than this.
	 * @param string $additional_query_string    Additional querystring variables to pass in the form action URL.
	 * @param int    $custom_title_width         Custom title width in pixels. Formats table sizing.
	 */
	public function __construct( $form_name, $save_point_or_custom_mode, $additional_query_string = '', $custom_title_width = '' ) {
		$this->_form_name          = $form_name;
		$this->_prefix             = 'pb_' . pb_backupbuddy::settings( 'slug' ) . '_';
		$this->_savepoint          = $save_point_or_custom_mode;
		$this->_custom_title_width = $custom_title_width;

		// TODO: no need to pass savepoint here?
		$this->_form = new pb_backupbuddy_form( $form_name, $save_point_or_custom_mode, $additional_query_string );
	} // End __construct().

	/**
	 * Register and add a setting to the settings form system.
	 *
	 * @param array $settings  Array of settings for this added setting. See $default_settings for list of options that can be defined.
	 */
	public function add_setting( $settings_config ) {
		$default_config  = array(
			'type'        => '',
			'name'        => '',
			'title'       => '',
			'tip'         => '',
			'css'         => '',
			'before'      => '',
			'after'       => '',
			'rules'       => '',
			'default'     => '',                 // IMPORTANT: Overrides default array. Also useful if savepoint is === false to override.
			'options'     => array(),
			'orientation' => 'horizontal',       // Used by radio and checkboxes. TODO: still need to add to checkboxes.
			'class'       => '',
			'classes'     => '',                 // String of additional classes.
			'row_class'   => '',                 // Class to apply to row td's in row.
		);
		$settings_config   = array_merge( $default_config, $settings_config );
		$this->_settings[] = $settings_config;

		// Figure out defaults.
		if ( '' != $settings_config['default'] ) { // Default was passed to add_setting().
			$default_value = $settings_config['default'];
		} else { // No default explictly set.
			$savepoint      = $this->_savepoint;
			$raw_name       = $settings_config['name'];
			$last_hashpoint = strrpos( $settings_config['name'], '#' );
			if ( false !== $last_hashpoint ) {
				$temp_savepoint = substr( $settings_config['name'], 0, $last_hashpoint );
				if ( false === $savepoint || '' == $savepoint ) {
					$savepoint = $temp_savepoint;
				} else {
					$savepoint = $savepoint . '#' . $temp_savepoint;
				}
				$raw_name = substr( $settings_config['name'], $last_hashpoint + 1 ); // Item name with savepoint portion stripped out.
			}
			if ( false !== $savepoint ) {

				if ( is_array( $savepoint ) ) { // Array of defaults was passed instead of savepoint.
					if ( isset( $savepoint[ $raw_name ] ) ) {
						$default_value = $savepoint[ $raw_name ];
					} else {
						$default_value = '';
					}
				} else { // No defaults provided, seek them out in plugins options array.

					// Default values are overwritten after a process() run with the latest data if a form was submitted.
					$group = pb_backupbuddy::get_group( $savepoint );
					if ( false === $group ) {
						$default_value = '';
					} else {
						if ( isset( $group[ $raw_name ] ) ) { // Default is defined.
							$default_value = $group[ $raw_name ];
						} else { // Default not defined.
							$default_value = '';
						}
					}
				} // end finding defaults in plugin options.
			} else { // Custom mode without a savepoint provided so no default set unless passed to add_setting().
				$default_value = '';
			}
		}

		// Process adding form item for the setting based on type.
		switch ( $settings_config['type'] ) {
			case 'text':
				$this->_form->text( $settings_config['name'], $default_value, $settings_config['rules'] );
				break;
			case 'plaintext':
				$this->_form->plaintext( $settings_config['name'], $default_value );
				break;
			case 'color':
				$this->_form->color( $settings_config['name'], $default_value, $settings_config['rules'] );
				break;
			case 'hidden':
				$this->_form->hidden( $settings_config['name'], $default_value, $settings_config['rules'] );
				break;
			case 'wysiwyg':
				$this->_form->wysiwyg( $settings_config['name'], $default_value, $settings_config['rules'], $settings_config['settings'] );
				break;
			case 'textarea':
				$this->_form->textarea( $settings_config['name'], $default_value, $settings_config['rules'] );
				break;
			case 'select':
				$this->_form->select( $settings_config['name'], $settings_config['options'], $default_value, $settings_config['rules'] );
				break;
			case 'password':
				$this->_form->password( $settings_config['name'], $default_value, $settings_config['rules'] );
				break;
			case 'radio':
				$this->_form->radio( $settings_config['name'], $settings_config['options'], $default_value, $settings_config['rules'] );
				break;
			case 'checkbox':
				$this->_form->checkbox( $settings_config['name'], $settings_config['options'], $default_value, $settings_config['rules'] );
				break;
			case 'submit':
				$this->_form->submit( $settings_config['name'], 'DEFAULT' ); // Submit button text is set in display_settings() param.
				break;
			case 'title':
				$this->_form->title( $settings_config['name'], $default_value, $settings_config['rules'] ); // Submit button text is set in display_settings() param.
				break;
			case 'html': // Raw HTML to inject at this spot.
				$this->_form->html( $settings_config['name'], $settings_config['html'] );
				break;
			default:
				echo '{Error: Unknown settings type `' . esc_html( $settings_config['type'] ) . '`.}';
				break;
		}

	} // End add_setting().

	/**
	 * Processes the form if applicable (if it was submitted).
	 * TODO: Perhaps add callback ability to this?
	 * This must come after all form elements have been added.
	 * This should usually happen in the controller prior to loading a view.
	 * IMPORTANT: Applies trim() to all submitted form values!
	 *
	 * @return array  When a savepoint was defined in class constructor an empty array is returned. (normal operation)
	 *                When savepoint === false an array is returned for custom form processing.
	 *                Format: array( 'errors' => false/array, 'data' => array( 'form_keys' => 'form_values' ) ).
	 */
	public function process() {
		// This form was indeed submitted. PROCESS IT!
		$form_name = pb_backupbuddy::_POST( $this->_prefix );
		if ( '' != $form_name && pb_backupbuddy::_POST( $this->_prefix ) === $this->_form_name ) {
			$errors = array();
			$_posts = pb_backupbuddy::_POST();

			// Cleanup.
			foreach ( $_posts as &$post_value ) {
				$post_value = trim( $post_value );
			}

			// loop through all posted variables, if its prefix matches this form's name then.
			foreach ( $_posts as $post_name => $post_value ) {
				if ( substr( $post_name, 0, strlen( $this->_prefix ) ) == $this->_prefix ) { // This settings form.
					$item_name = substr( $post_name, strlen( $this->_prefix ) );
					if ( '' != $item_name && 'settings_submit' != $item_name ) { // Skip the form name input; also settings submit button since it is not registered until view.
						$test_result = $this->_form->test( $item_name, $post_value );
						if ( true !== $test_result ) {
							foreach ( $this->_settings as $setting_index => $setting ) {
								if ( 'title' === $setting['type'] ) {
									continue;
								}
								if ( $setting['name'] === $item_name ) {
									$this->_settings[ $setting_index ]['error'] = true;
									$item_title                                 = $this->_settings[ $setting_index ]['title'];
								}
							}
							$errors[] = 'Validation failure on `' . $item_title . '`: ' . implode( ' ', $test_result );
							unset( $_posts[ $post_name ] ); // Removes this form item so it will not be updated during save later.
						} else { // Item validated. Remove prefix for later processing.
							$_posts[ $item_name ] = $_posts[ $post_name ];
							$this->_form->set_value( $item_name, $post_value ); // Set value to be equal to submitted value so if one or more item failed validation the entire form is not wiped out. Don't want user to have to re-enter valid data.
							unset( $_posts[ $post_name ] );
						}
					} else { // Submit button. Can unset it to clean up array for later.
						unset( $_posts[ $post_name ] );
					}
				} else { // Not for this form. Can unset it to clean up array for later.
					unset( $_posts[ $post_name ] );
				}
			}

			// Process!
			// Only save if in normal settings mode; if savepoint === false no saving here.
			if ( false === $this->_savepoint || is_array( $this->_savepoint ) ) {
				$return = array(
					'errors' => $errors,
					'data'   => $_posts,
				);
				return $return;
			} else { // Normal settings since savepoint !== false. Save into savepoint!

				if ( count( $errors ) > 0 ) { // Errors.
					pb_backupbuddy::alert( 'Error validating one or more fields as indicated below. Error(s):<br>' . implode( '<br>', $errors ), true );
				}
				// Prepare savepoint.
				if ( '' != $this->_savepoint ) {
					$savepoint_root = $this->_savepoint . '#';
				} else {
					$savepoint_root = '';
				}

				// The hard work.
				foreach ( $_posts as $post_name => $post_value ) { // Loop through all post items (not all may be our form). @see 83429594837534987.
					$this->_form->set_value( $post_name, $post_value );
					$old_value = isset( pb_backupbuddy::$options[ $post_name ] ) ? pb_backupbuddy::$options[ $post_name ] : false;
					do_action( 'itbub_save_setting', $post_name, $post_value, $old_value );

					// From old save_settings().
					$savepoint_subsection = &pb_backupbuddy::$options;
					$savepoint_levels     = explode( '#', $savepoint_root . $post_name );
					foreach ( $savepoint_levels as $savepoint_level ) {
						$savepoint_subsection = &$savepoint_subsection{$savepoint_level};
					}
					// Apply settings.
					$savepoint_subsection = stripslashes_deep( $post_value ); // Remove WordPress' magic-quotes-nonsense.
				}

				// Add a note to the save alert that some values are skipped due to errors.
				$error_note = '';
				if ( count( $errors ) > 0 ) {
					$error_note = ' One or more fields skipped due to error.';
				}

				pb_backupbuddy::save();
				pb_backupbuddy::alert( __( 'Settings saved.', 'it-l10n-backupbuddy' ) . $error_note );

				$return = array(
					'errors' => $errors,
					'data'   => $_posts,
				);
				return $return;
			} // End if savepoint !=== false.
		} // end submitted form.

		return array();
	} // End process().

	/**
	 * Displays all the registered settings in this object. Entire form and HTML is echo'd out.
	 *
	 * @see pluginbuddy_settings->get_settings()
	 *
	 * @param string $submit_button_title  Text to display in the submit button.
	 * @param string $before               Content before submit after.
	 * @param string $after                Content after submit button.
	 * @param string $save_button_class    Class for save button.
	 */
	public function display_settings( $submit_button_title, $before = '', $after = '', $save_button_class = '' ) {
		echo $this->get_settings( $submit_button_title, $before, $after, $save_button_class );
	} // End display_settings().

	/**
	 * Returns all the registered settings in this object. Entire form and HTML is returned.
	 *
	 * @see pluginbuddy_settings->display_settings()
	 * radio button additional options:  orientation [ vertical / horizontal ]
	 *
	 * @param string $submit_button_title  Text to display in the submit button.
	 * @param string $before               Content before submit after.
	 * @param string $after                Content after submit button.
	 * @param string $save_button_class    Class for save button.
	 *
	 * @return string  Returns entire string with everything in it to display.
	 */
	public function get_settings( $submit_button_title, $before = '', $after = '', $save_button_class = '' ) {
		$first_title = true; // first title's CSS top padding differs so must track.

		$return  = $this->_form->start();
		$return .= '<table class="form-table">';
		foreach ( $this->_settings as $settings ) {
			$th_css = '';

			if ( '' == $settings['title'] ) { // blank title so hide left column.
				$th_css .= ' display: none;';
			}

			if ( 'title' === $settings['type'] ) { // Title item.
				if ( true === $first_title ) { // First title in list.
					$return     .= '<tr style="border: 0;"><th colspan="2" style="border: 0; padding-top: 0; padding-bottom: 0;" class="' . $settings['row_class'] . '"><h3 class="title ' . $settings['class'] . '"';
					$return     .= ' style="margin-top: 0; margin-bottom: 0.5em;"';
					$first_title = false;
				} else { // Subsequent titles.
					$return .= '<tr style="border: 0;"><th colspan="2" style="border: 0;" class="' . $settings['row_class'] . '"><h3 class="title ' . $settings['class'] . '"';
					$return .= ' style="margin: 0.5em 0;"';
				}

				$return .= '>' . $settings['title'] . '</h3></th>';
			} elseif ( 'hidden' === $settings['type'] ) { // hidden form item. no title.
				$return .= $this->_form->get( $settings['name'], $settings['css'], $settings['classes'] );
			} else { // Normal item.
				$return .= '<tr class="' . $settings['row_class'] . '">';
				$return .= '<th scope="row" class="' . $settings['row_class'] . '"';
				if ( '' != $this->_custom_title_width ) {
					$return .= ' style="width: ' . $this->_custom_title_width . 'px; ' . $th_css . '"';
				} else {
					$return .= ' style="' . $th_css . '"';
				}
				$return .= '>';
				$return .= $settings['title'];
				if ( isset( $settings['tip'] ) && '' != $settings['tip'] ) {
					$return .= pb_backupbuddy::$ui->tip( $settings['tip'], '', false );
				}
				$return .= '</th>';
				if ( 'title' === $settings['type'] ) { // Extend width full length for title item.
					$return .= ' colspan="2"';
				}

				$return .= '<td class="' . $settings['row_class'] . '"';
				if ( '' == $settings['title'] ) { // No title so hide left column.
					$return .= ' colspan="2"';
				}
				$return .= '>';
				$return .= $settings['before'];
				if ( isset( $settings['error'] ) && true === $settings['error'] ) {
					$settings['css'] .= 'border: 1px dashed red;';
				}
				$return .= $this->_form->get( $settings['name'], $settings['css'], $settings['classes'], $settings['orientation'] );
				$return .= $settings['after'];
				$return .= '</td>';
				$return .= '</tr>';
			}
		}
		$return .= '</table><br>';

		// Submit button.
		$return .= $before;
		$return .= $this->_form->submit( 'settings_submit', $submit_button_title, $save_button_class );
		$return .= $this->_form->get( 'settings_submit', '', $save_button_class );
		$return .= $after;

		$return .= $this->_form->end();

		return $return;
	} // End get_settings().

	/**
	 * Clears the value of all form items setting the value to an empty string ''.
	 */
	public function clear_values() {
		$this->_form->clear_values();
	} // End clear_values().

	/**
	 * Replace the value of a form item.
	 *
	 * @param string $item_name  Name of the form setting item to update.
	 * @param string $value      Value to set the item to.
	 */
	public function set_value( $item_name, $value ) {
		$this->_form->set_value( $item_name, $value );
	} // End set_value().

} // End class pluginbuddy_settings.
