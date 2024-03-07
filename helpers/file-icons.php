<?php
/**
 * File Icon Styles Helpers
 *
 * @package BackupBuddy
 */

/**
 * Build an array of icons to be used with jQueryFileTree
 *
 * @return array  Array where key is relative path to icon, value is string or array of selectors.
 */
function itbub_get_file_icons() {
	$icons = array(
		'assets/dist/images/filetree/directory.png'   => array( '.directory', '.directory-settings' ),
		'assets/dist/images/filetree/folder_open.png' => '.expanded',
		'assets/dist/images/filetree/file.png'        => array('.file', '.file-settings' ),
		'assets/dist/images/filetree/spinner.gif'     => '.wait',
		'assets/dist/images/filetree/film.png'        => array( '.ext_3gp', '.ext_avi', '.ext_mov', '.ext_mp4', '.ext_mpg', '.ext_mpeg', '.ext_wmv' ),
		'assets/dist/images/filetree/code.png'        => array( '.ext_afp', '.ext_afpa', '.ext_asp', '.ext_aspx', '.ext_c', '.ext_cfm', '.ext_cgi', '.ext_cpp', '.ext_h', '.ext_lasso', '.ext_vb', '.ext_xml' ),
		'assets/dist/images/filetree/application.png' => array( '.ext_bat', '.ext_com', '.ext_exe' ),
		'assets/dist/images/filetree/picture.png'     => array( '.ext_bmp', '.ext_gif', '.ext_jpg', '.ext_jpeg', '.ext_pcx', '.ext_png', '.ext_tif', '.ext_tiff' ),
		'assets/dist/images/filetree/css.png'         => '.ext_css',
		'assets/dist/images/filetree/doc.png'         => '.ext_doc',
		'assets/dist/images/filetree/flash.png'       => array( '.ext_fla', '.ext_swf' ),
		'assets/dist/images/filetree/html.png'        => array( '.ext_htm', '.ext_html' ),
		'assets/dist/images/filetree/java.png'        => '.ext_jar',
		'assets/dist/images/filetree/script.png'      => array( '.ext_js', '.ext_pl', '.ext_py' ),
		'assets/dist/images/filetree/txt.png'         => array( '.ext_log', '.ext_txt' ),
		'assets/dist/images/filetree/music.png'       => array( '.ext_m4p', '.ext_mp3', '.ext_ogg', '.ext_wav' ),
		'assets/dist/images/filetree/pdf.png'         => '.ext_pdf',
		'assets/dist/images/filetree/php.png'         => '.ext_php',
		'assets/dist/images/filetree/ppt.png'         => '.ext_ppt',
		'assets/dist/images/filetree/psd.png'         => '.ext_psd',
		'assets/dist/images/filetree/ruby.png'        => array( '.ext_rb', '.ext_rbx', '.ext_rhtml', '.ext_ruby' ),
		'assets/dist/images/filetree/linux.png'       => '.ext_rpm',
		'assets/dist/images/filetree/db.png'          => '.ext_sql',
		'assets/dist/images/filetree/xls.png'         => '.ext_xls',
		'assets/dist/images/filetree/zip.png'         => '.ext_zip',
	);
	return apply_filters( 'itbub_file_icons', $icons );
}

/**
 * Output or return styles to customize icons for jQueryFileTree
 *
 * @param string $background_position  Global background-position for all icons.
 * @param bool   $wrap_style_tag       Wrap output in <style/> tag.
 * @param bool   $echo                 Echo output (otherwise return).
 *
 * @return string|void  When echo is false, returns string of styles.
 */
function itbub_file_icon_styles( $background_position = '6px 6px', $wrap_style_tag = false, $echo = true ) {
	if ( ! class_exists( 'pb_backupbuddy' ) ) {
		return false;
	}

	$output = '';
	$icons  = itbub_get_file_icons();

	if ( ! count( $icons ) ) {
		return $output;
	}

	if ( true === $wrap_style_tag ) {
		$output .= '<style type="text/css">';
	}

	foreach ( $icons as $selector ) :
		if ( is_array( $selector ) ) :
			foreach ( $selector as $class ) :
				$output .= sprintf( '.jqueryFileTree li%s,', esc_html( $class ) );
			endforeach;
		else :
			$output .= sprintf( '.jqueryFileTree li%s,', esc_html( $selector ) );
		endif;
	endforeach;
	$output = rtrim( $output, ',' );
	$output .= '{ background-position: ' . esc_html( $background_position ) . '; background-repeat: no-repeat; }';

	foreach ( $icons as $icon => $selector ) :
		if ( is_array( $selector ) ) :
			foreach ( $selector as $class ) :
				$output .= sprintf( '.jqueryFileTree li%s,', esc_html( $class ) );
			endforeach;
			$output = rtrim( $output, ',' );
			$output .= sprintf( '{ background-image: url(\'%s/%s\'); }', pb_backupbuddy::plugin_url(), $icon );
		else :
			$output .= sprintf( '.jqueryFileTree li%s { background-image: url(\'%s/%s\'); }', esc_html( $selector ), pb_backupbuddy::plugin_url(), $icon );
		endif;
	endforeach;

	if ( true === $wrap_style_tag ) {
		$output .= '</style>';
	}

	if ( false === $echo ) {
		return $output;
	}

	echo $output;
}
