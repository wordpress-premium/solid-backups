<?php
/**
 * Form handling Class
 *
 * @package BackupBuddy
 * @author Dustin Bolton
 */

/**
 * Form framework for handling all forms, validation, and their display.
 */
class pb_backupbuddy_form {

	/**
	 * Form Name
	 *
	 * @var string
	 */
	private $_form_name = '';

	/**
	 * Save Point
	 *
	 * @var string
	 */
	private $_save_point = '';

	/**
	 * Form Inputs
	 *
	 * @var array
	 */
	private $_inputs = array();

	/**
	 * Form Prefix
	 *
	 * @var string
	 */
	private $_prefix = 'DEFAULT';

	/**
	 * Form Started
	 *
	 * @var bool
	 */
	private $_started = false;

	/**
	 * Form Ended
	 *
	 * @var bool
	 */
	private $_ended = false;

	/**
	 * Additional Query String
	 *
	 * @var string
	 */
	private $_additional_query_string = '';

	/**
	 * Loaded color styles
	 *
	 * @var bool
	 */
	private $_loaded_color = false;

	/**
	 * Default constructor. Sets up the form.
	 *
	 * @param string $form_name                Name / slug of the form.
	 * @param string $save_point               Save point to save form; Currently only used for settings form. @see pluginbuddy_settings->__construct().
	 * @param string $additional_query_string  Additional querystring to append to end of form action URL.
	 */
	public function __construct( $form_name, $save_point = '', $additional_query_string = '' ) {
		$this->_form_name               = $form_name;
		$this->_save_point              = $save_point;
		$this->_additional_query_string = $additional_query_string;
		$this->_prefix                  = 'pb_' . pb_backupbuddy::settings( 'slug' ) . '_';
	} // End __construct().

	/**
	 * Add a text input.
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item.
	 * @param string $rules  (optional) Rules to validate this form item against.
	 */
	public function text( $name, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
			'rules' => $rules,
		);
	} // End text().

	/**
	 * Add a text input, with NO RULES!
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item.
	 */
	public function plaintext( $name, $value ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
		);
	} // End text().

	/**
	 * Add a color input; this is a text input that has a color selector.
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item.
	 * @param string $rules  (optional) Rules to validate this form item against.
	 */
	public function color( $name, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
			'rules' => $rules,
		);
	} // End color().

	/**
	 * Add a hidden input.
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item.
	 * @param string $rules  (optional) Rules to validate this form item against.
	 */
	public function hidden( $name, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
			'rules' => $rules,
		);
	} // End hidden().

	/**
	 * Adds a text box wysiwyg.
	 *
	 * @see wp_editor() in WordPress core.
	 *
	 * @param string $name      Name / slug for this form item.
	 * @param string $value     Value for this form item.
	 * @param string $rules     (optional) Rules to validate this form item against.
	 * @param array  $settings  WordPress settings array to pass to wp_editor(). @see wp_editor().
	 */
	public function wysiwyg( $name, $value, $rules = '', $settings ) {
		$this->_inputs[ $name ] = array(
			'type'     => __FUNCTION__,
			'value'    => $value,
			'rules'    => $rules,
			'settings' => $settings,
		);
	} // End wysiwyg().

	/**
	 * Add a textarea input.
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item.
	 * @param string $rules  (optional) Rules to validate this form item against.
	 */
	public function textarea( $name, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
			'rules' => $rules,
		);
	} // End textarea().

	/**
	 * Add a select input.
	 *
	 * @param string $name     Name / slug for this form item.
	 * @param array  $options  Array of options for the dropdown.  The key is the slug and the value is the pretty user-displayed part. <option value="array_key">array_value</option>.
	 * @param string $value    Value for this form item.
	 * @param string $rules    (optional) Rules to validate this form item against.
	 */
	public function select( $name, $options, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'    => __FUNCTION__,
			'options' => $options,
			'value'   => $value,
			'rules'   => $rules,
		);
	} // End select().

	/**
	 * Add a radio input.
	 *
	 * @param string $name     Name / slug for this form item.
	 * @param array  $options  Array of options for the radio inputs. The key is the slug and the value is the pretty user-displayed part. <input type="radio" value="array_key">array_value.
	 * @param string $value    Value for this form item.
	 * @param string $rules    (optional) Rules to validate this form item against.
	 */
	public function radio( $name, $options, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'    => __FUNCTION__,
			'options' => $options,
			'value'   => $value,
			'rules'   => $rules,
		);
	} // End radio().

	/**
	 * Add a radio input.
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item.
	 * @param string $rules  (optional) Rules to validate this form item against.
	 */
	public function title( $name, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
			'rules' => $rules,
		);
	} // End radio().

	/**
	 * Adds custom html as an input.
	 *
	 * @param string $name  Name / slug for this form item.
	 * @param string $html  HTML content.
	 */
	public function html( $name, $html ) {
		$this->_inputs[ $name ] = array(
			'type' => __FUNCTION__,
			'html' => $html,
		);
	}

	/**
	 * Add a checkbox input.
	 *
	 * @param string $name     Name / slug for this form item.
	 * @param array  $options  Array format: array( 'unchecked' => 'unchecked_value', 'checked' => 'checked_value' ).
	 * @param string $value    Value for this form item.
	 * @param string $rules    (optional) Rules to validate this form item against.
	 */
	public function checkbox( $name, $options, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'    => __FUNCTION__,
			'options' => $options,
			'value'   => $value,
			'rules'   => $rules,
		);
	} // End checkbox().

	/**
	 * Add a password input.
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item.
	 * @param string $rules  (optional) Rules to validate this form item against.
	 */
	public function password( $name, $value, $rules = '' ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
			'rules' => $rules,
		);
	} // End password().

	/**
	 * Add a submit input.
	 *
	 * @param string $name   Name / slug for this form item.
	 * @param string $value  Value for this form item. This is the text in the displayed button.
	 */
	public function submit( $name, $value = '' ) {
		$this->_inputs[ $name ] = array(
			'type'  => __FUNCTION__,
			'value' => $value,
		);
	} // End submit().

	/**
	 * Starts the form output. Automatically runs under normal circumstances so usually should not need to be called directly.
	 *
	 * @return string  Form HTML.
	 */
	public function start() {
		$this->_started = true;

		if ( false !== stristr( $this->_additional_query_string, 'http' ) ) {
			$action_url = $this->_additional_query_string;
		} else {

			if ( pb_backupbuddy::page_url() != '' ) {
				$action_url = pb_backupbuddy::page_url() . '&' . $this->_additional_query_string;
			} else {
				$action_url = '?' . $this->_additional_query_string;
			}
		}

		$return  = '<form method="post" action="' . $action_url . '" class="pb_form pb_form ' . $this->_prefix . $this->_form_name . '_form" id="' . $this->_prefix . $this->_form_name . '_form">';
		$return .= '<input type="hidden" name="' . $this->_prefix . '" value="' . $this->_form_name . '">';
		return $return;
	} // End start().

	/**
	 * Ends the form setting nonce and closing </form>. NOT automatically run except in pluginbuddy_settings class.
	 *
	 * @param bool $echo  Echos the form html.
	 *
	 * @return string|null  Null if echo, Form HTML if $echo false.
	 */
	public function end( $echo = false ) {
		$this->_ended = true;

		$return  = pb_backupbuddy::nonce( false ); // Do not echo.
		$return .= '</form>';

		if ( true === $echo ) {
			echo $return;
		} else {
			return $return;
		}
	} // End end().

	/**
	 * Displays (echos) a form item and all its code/HTML.
	 *
	 * @see pluginbuddy_form->get().
	 *
	 * @param string $name  Name / slug for this form item.
	 * @param string $css   Additional CSS to apply to form item.
	 */
	public function display( $name, $css = '' ) {
		echo $this->get( $name, $css );
	} // End display().

	/**
	 * Returns a form item and all its code/HTML.
	 * Left column is hidden if an object's title = ''.
	 * title object type colspans 2.
	 *
	 * @param string $name         Name / slug for this form item.
	 * @param string $css          Additional CSS to apply to form item.
	 * @param string $classes      CSS Class names for elements.
	 * @param string $orientation  Optional: horizontal or vertical. Default: horizontal.
	 *
	 * @return string  All HTML, etc for this form item.
	 */
	public function get( $name, $css = '', $classes = '', $orientation = 'horizontal' ) {
		if ( true === $this->_ended ) { // Form already closed and ended. Fatal problem.
			return '{Error: Form already closed with end function so cannot add more fields. Only end in view after all displays are done.}';
		}

		if ( isset( $this->_inputs[ $name ] ) ) {
			$prefix = $this->_prefix;

			if ( false === $this->_started ) { // Form output has not started. Start it.
				$return = $this->start();
			} else {
				$return = '';
			}

			$input = &$this->_inputs[ $name ];

			if ( '' != $css ) {
				$css = ' style="' . $css . '"';
			}

			$id = $prefix . str_replace( '#', '__', $name );

			if ( 'text' == $input['type'] ) {

				$return .= '<input type="text" class="' . $classes . '" name="' . $prefix . $name . '" value="' . $input['value'] . '" id="' . esc_attr( $id ) . '"' . $css . '>';

			} elseif ( 'plaintext' == $input['type'] ) {

				$return .= '<span class="' . $classes . '" ' . $css . '>' . $input['value'] . '</span>';

			} elseif ( 'color' == $input['type'] ) {

				// TODO: this actually should only run once per PAGE load. add a function is_script and is_style into framework to see if loaded into framework yet or not.
				if ( false === $this->_loaded_color ) { // Only load the javascript, CSS, etc once per instance.
					pb_backupbuddy::load_script( 'jquery.miniColors.min.js', true );
					pb_backupbuddy::load_style( 'jquery.miniColors.css', true );
					echo '<script type="text/javascript">
						jQuery(document).ready( function() {
						jQuery( ".pb_colorpicker" ).miniColors({ letterCase: "uppercase" });
						});
					</script>
					<style type="text/css">
						.miniColors-trigger {
							background: url( ' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/minicolors/trigger.png ) center no-repeat;
						}
						.miniColors-colors {
							background: url( ' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/minicolors/gradient.png ) center no-repeat;
						}
						.miniColors-hues {
							background: url( ' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/minicolors/rainbow.png ) center no-repeat;
						}
						.miniColors-colorPicker {
							background: url( ' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/minicolors/circle.gif ) center no-repeat;
						}
						.miniColors-huePicker {
							background: url( ' . pb_backupbuddy::plugin_url() . '/pluginbuddy/images/minicolors/line.gif ) center no-repeat;
						}
					</style>';
					$this->_loaded_color = true;
				}

				if ( '' == $css ) { // default width.
					$css = ' style="width: 60px;"';
				}

				$return .= '<input class="pb_colorpicker ' . $classes . '" type="text" name="' . $prefix . $name . '" value="' . $input['value'] . '" id="' . esc_attr( $id ) . '"' . $css . '>';

			} elseif ( 'hidden' == $input['type'] ) {

				$return .= '<input type="hidden" name="' . $prefix . $name . '" value="' . $input['value'] . '" class="' . $classes . '" id="' . esc_attr( $id ) . '"' . $css . '>';

			} elseif ( 'wysiwyg' == $input['type'] ) {

				$wysiwyg_settings = array_merge( $input['settings'], array( 'textarea_name' => $prefix . $name ) );

				ob_start();
				wp_editor( $input['value'], $prefix . $name, $wysiwyg_settings );
				$return .= ob_get_contents();
				ob_end_clean();

			} elseif ( 'title' == $input['type'] ) {
				// no longer supported input.
			} elseif ( 'html' == $input['type'] ) {

				$return .= $input['html'];

			} elseif ( 'textarea' == $input['type'] ) {

				$return .= '<textarea name="' . $prefix . $name . '" class="' . $classes . '" id="' . esc_attr( $id ) . '"' . $css . '>' . $input['value'] . '</textarea>';

			} elseif ( 'password' == $input['type'] ) {

				// Quick fix - esc_attr is not callable during IMPORTBUDDY.
				if ( function_exists( 'esc_attr' ) ) {
					$input_value = esc_attr( $input['value'] );
				} else {
					$input_value = $input['value'];
				}
				$return .= '<input type="password" name="' . $prefix . $name . '" value="' . $input_value . '" class="' . $classes . '" id="' . esc_attr( $id ) . '"' . $css . '>';

			} elseif ( 'select' == $input['type'] ) {

				$return .= '<select name="' . $prefix . $name . '" class="' . $classes . '" id="' . esc_attr( $id ) . '"' . $css . '>';
				foreach ( $input['options'] as $option_value => $option_title ) {
					$return .= '<option value="' . $option_value . '"';
					if ( $option_value == $input['value'] ) {
						$return .= ' selected="selected"'; }
					$return .= '>' . $option_title . '</option>';
				}
				$return .= '</select>';

			} elseif ( 'radio' == $input['type'] ) {

				$return .= '<input type="hidden" name="' . $prefix . $name . '" value="">'; // default if no radio checked.

				$i = 0;
				foreach ( $input['options'] as $option_value => $option_title ) {
					$i++;
					$radio_id = $id . '_' . $i;
					$return .= '<input type="radio" name="' . $prefix . $name . '" class="' . $classes . '" id="' . esc_attr( $radio_id ) . '" value="' . $option_value . '"' . $css;

					if ( $option_value == $input['value'] ) { // Check if this item is selected.
						$return .= ' checked="checked"';
					}
					$return .= '> ' . $option_title;
					if ( $i < count( $input['options'] ) ) { // spacer between each one.
						if ( 'horizontal' == $orientation ) { // Horizonal display.
							$return .= ' &nbsp;&nbsp;&nbsp; ';
						} else { // Vertical display.
							$return .= '<br>';
						}
					}
				}
			} elseif ( 'checkbox' == $input['type'] ) {
				$return .= '<input type="hidden" name="' . $prefix . $name . '" value="' . $input['options']['unchecked'] . '">';
				$return .= '<input type="checkbox" name="' . $prefix . $name . '" class="' . $classes . '" id="' . esc_attr( $id ) . '" value="' . $input['options']['checked'] . '"' . $css;
				if ( $input['options']['checked'] == $input['value'] || ( isset( $input['default'] ) && $input['options']['checked'] == $input['default'] ) ) {
					$return .= ' checked';
				}
				$return .= '>';

			} elseif ( 'submit' == $input['type'] ) {

				$submit_id = $id . '_' . sanitize_title( $input['value'] );
				$return .= '<input class="button-primary ' . $classes . '" type="submit" name="' . esc_attr( $prefix . $name ) . '" value="' . esc_attr( $input['value'] ) . '" id="' . esc_attr( $submit_id ) . '"' . $css . '>';

			} else {

				$return .= '{Unknown form item type: `' . $input['type'] . '`.}';

			}

			return $return;
		} else {
			return '{Invalid form field: `' . $name . '`.}';
		}

	} // End get().

	/**
	 * Updates the value of an existing form item.
	 *
	 * @param string $name   Name of the item in the form to update. Ex: text.
	 * @param string $value  Value to apply to the form item.
	 */
	public function set_value( $name, $value ) {
		$this->_inputs[ $name ]['value'] = $value;
	} // End set_value().

	/**
	 * Get the submitted (POSTed) value of a form item.
	 *
	 * @param string $name  Name / slug for this form item.
	 *
	 * @return string|bool  False if not found. Strips WP slashes.
	 */
	public function get_value( $name ) {
		if ( pb_backupbuddy::_POST( $this->_prefix . $name ) != '' ) { // Submitted value exists, use it.
			return stripslashes_deep( pb_backupbuddy::_POST( $this->_prefix . $name ) );
		} else { // Nothing submitted, fail.
			return false;
		}
	} // End get_value().

	/**
	 * Tests whether a form item's rules on a provided value. If no value is provided then will try to get the POST'ed value.
	 *
	 * @see pb_backupbuddy::test_rule()
	 *
	 * @param string $name   Input/Field Name.
	 * @param string $value  Optional: This will be tested with the rule assigned to the form item with the provided name.
	 *                       If empty we will try to test based on a submitted post value if it exists.
	 *
	 * @return true|array  True if the value passes; array of error messages on failure.
	 */
	public function test( $name, $value = '' ) {
		if ( '' == $value ) { // No value, try to get it.
			if ( '' != pb_backupbuddy::_POST( $this->_prefix . $name ) ) { // Submitted value exists, use it.
				$value = pb_backupbuddy::_POST( $this->_prefix . $name );
			}
		}

		if ( isset( $this->_inputs[ $name ]['rules'] ) ) {
			return self::test_rule( $this->_inputs[ $name ]['rules'], $value );
		} else { // No tests. Passed.
			return true;
		}
	} // End test().

	/**
	 * Tests a provided ruleset against a value to verify whether it complies or not.
	 *
	 * @author Dan Harzheim
	 * @see pluginbuddy_form->test()
	 *
	 * @param string $ruleset  Rule(s) to validate against. See codex for details. TODO: document rulesets here.
	 * @param string $value    Value to validate.
	 *
	 * @return true|array  True on success; array of error(s) encountered on failure.
	 */
	public function test_rule( $ruleset, $value ) {
		$errors = array();
		if ( '' == $ruleset ) {
			return true;
		}

		$rules = explode( '|', $ruleset ); // Create array of rules.
		foreach ( $rules as $rule ) { // Iterate through each rule.

			// ***** GET RULE TYPE *****
			// Grab the type of the rule; ex: string, int, set, etc via regex.
			$rule_type_pos = strpos( $rule, '[' );

			if ( false === $rule_type_pos ) {
				$rule_type = $rule;
			} else {
				$rule_type = substr( $rule, 0, $rule_type_pos );
			}

			/**
			 * REQUIRED
			 *
			 * Rule is required.
			 * Fail if $value is empty.
			 * if fails:  $errors[] = 'Value is not a string.';
			 */
			if ( 'required' == $rule_type ) { // Required rule.
				if ( '' == $value ) {
					$errors[] = 'This value is required.';
					return $errors; // No more checking if left blank.
				}

				/**
				 * STRING
				 *
				 * Check to make sure that the string is the appropriate length.
				 */
			} elseif ( 'string' == $rule_type ) { // String rule.
				$subrule    = strstr( $rule, '[' );
				$hyphen_pos = strpos( $subrule, '-' );
				if ( '' != $hyphen_pos ) {
					$first_number  = substr( $subrule, 1, $hyphen_pos - 1 );
					$second_number = substr( $subrule, $hyphen_pos + 1, -1 );
					$val_length    = strlen( $value );
					if ( $val_length < $first_number || $val_length > $second_number ) {
						$errors[] = 'Length of value `' . htmlentities( $value ) . '` is invalid.';
					}
				}

				/**
				 * INT TYPE
				 *
				 * Make sure that the value fits inside of bounds
				 * Make sure it doesn't include a decimal
				 * Accepts blank value
				 */
			} elseif ( 'int' == $rule_type ) {
				if ( '' != $value ) {
					if ( ! is_numeric( $value ) || strpos( $value, '.' ) !== false ) {
						$errors[] = '`' . htmlentities( $value ) . '` is not a valid number.';
					} else {
							$subrule    = strstr( $rule, '[' );
							$hyphen_pos = strpos( $subrule, '-' );
						if ( '' != $hyphen_pos ) {
							$first_number  = substr( $subrule, 1, $hyphen_pos - 1 );
							$second_number = substr( $subrule, $hyphen_pos + 1, -1 );
							if ( $value < $first_number || $value > $second_number ) {
								$errors[] = 'Value `' . htmlentities( $value ) . '` is outside of the set bounds.';
							}
						}
					}
				}

				/**
				 * EMAIL TYPE
				 *
				 * Validate to make sure the e-mail address is actually an e-mail address.
				 */
			} elseif ( 'email' == $rule_type ) {
				if ( '' != $value ) {
					// TODO: Add custom callback functionality here.
					if ( ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
						$errors[] = 'Value `' . htmlentities( $value ) . '` is not a valid e-mail address.';
					}
				}

				/**
				 * SET TYPE
				 *
				 * Make sure that $value falls into one of the types.
				 */
			} elseif ( 'set' == $rule_type ) {
				// set[string,string,string,].
				$is_there  = false;
				$substring = strstr( $rule, '[' );
				$substring = substr( $substring, $substring + 1, $substring - 1 );
				$parts     = explode( ',', $substring );
				foreach ( $parts as $part ) {
					if ( $value == $part ) {
						$is_there = true;
					}
				}
				if ( false == $is_there ) {
					$errors[] = 'Value `' . htmlentities( $value ) . '` is not a valid value.';
				}

				/**
				 * NUM TYPE
				 *
				 * Make sure that $value is numeric, if so, make sure it fits inside of bounds
				 */
			} elseif ( 'number' == $rule_type ) {
				if ( '' != $value ) {
					if ( ! is_numeric( $value ) ) {
						$errors[] = $value . ' is not a number.';
					}
					$subrule    = strstr( $rule, '[' );
					$hyphen_pos = strpos( $subrule, '-' );
					if ( '' != $hyphen_pos ) {
						$first_number  = substr( $subrule, 1, $hyphen_pos - 1 );
						$second_number = substr( $subrule, $hyphen_pos + 1, -1 );
						if ( $value < $first_number || $value > $second_number ) {
							$errors[] = 'Value `' . htmlentities( $value ) . '` is outside of the set bounds.';
						}
					}
				}
			} else {
				// TODO: Add custom callback functionality here.
				// Unknown rule so notify the developer.
				$errors[] = '{Error #54589. Unknown rule `' . $rule_type . '`.}';
			}
		}

		if ( count( $errors ) === 0 ) { // No errors; success!
			return true;
		} else { // One or more errors encountered; return array of errors.
			return $errors;
		}
	} // End test_rule().

	/**
	 * Clears the value of all form items setting the value to an empty string ''.
	 */
	public function clear_values() {
		foreach ( $this->_inputs as &$input ) {
			$input['value'] = '';

		}
	} // End clear_values().

} // End class pluginbuddy_form
