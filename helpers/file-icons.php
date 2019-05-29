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
		'images/filetree/directory.png'   => '.directory',
		'images/filetree/folder_open.png' => '.expanded',
		'images/filetree/file.png'        => '.file',
		'images/filetree/spinner.gif'     => '.wait',
		'images/filetree/film.png'        => array( '.ext_3gp', '.ext_avi', '.ext_mov', '.ext_mp4', '.ext_mpg', '.ext_mpeg', '.ext_wmv' ),
		'images/filetree/code.png'        => array( '.ext_afp', '.ext_afpa', '.ext_asp', '.ext_aspx', '.ext_c', '.ext_cfm', '.ext_cgi', '.ext_cpp', '.ext_h', '.ext_lasso', '.ext_vb', '.ext_xml' ),
		'images/filetree/application.png' => array( '.ext_bat', '.ext_com', '.ext_exe' ),
		'images/filetree/picture.png'     => array( '.ext_bmp', '.ext_gif', '.ext_jpg', '.ext_jpeg', '.ext_pcx', '.ext_png', '.ext_tif', '.ext_tiff' ),
		'images/filetree/css.png'         => '.ext_css',
		'images/filetree/doc.png'         => '.ext_doc',
		'images/filetree/flash.png'       => array( '.ext_fla', '.ext_swf' ),
		'images/filetree/html.png'        => array( '.ext_htm', '.ext_html' ),
		'images/filetree/java.png'        => '.ext_jar',
		'images/filetree/script.png'      => array( '.ext_js', '.ext_pl', '.ext_py' ),
		'images/filetree/txt.png'         => array( '.ext_log', '.ext_txt' ),
		'images/filetree/music.png'       => array( '.ext_m4p', '.ext_mp3', '.ext_ogg', '.ext_wav' ),
		'images/filetree/pdf.png'         => '.ext_pdf',
		'images/filetree/php.png'         => '.ext_php',
		'images/filetree/ppt.png'         => '.ext_ppt',
		'images/filetree/psd.png'         => '.ext_psd',
		'images/filetree/ruby.png'        => array( '.ext_rb', '.ext_rbx', '.ext_rhtml', '.ext_ruby' ),
		'images/filetree/linux.png'       => '.ext_rpm',
		'images/filetree/db.png'          => '.ext_sql',
		'images/filetree/xls.png'         => '.ext_xls',
		'images/filetree/zip.png'         => '.ext_zip',
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
