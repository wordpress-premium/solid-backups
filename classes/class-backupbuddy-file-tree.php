<?php
/**
 * Used for creating HTML file trees.
 *
 * @package BackupBuddy
 */

/**
 * Generates a File tree from array of files.
 */
class BackupBuddy_File_Tree {

	/**
	 * Backup file used for tree.
	 *
	 * @var bool
	 */
	private $backup_file = false;

	/**
	 * File array used for Tree.
	 *
	 * @var array
	 */
	protected $files = array();

	/**
	 * Default Output Format
	 *
	 * Supports table or list.
	 *
	 * @var string
	 */
	protected $format = 'table';

	/**
	 * Which UI to use.
	 *
	 * Supports tree or panels.
	 *
	 * @var string
	 */
	protected $ui = 'panels';

	/**
	 * Column Headers.
	 *
	 * @var array
	 */
	private $columns = array();

	/**
	 * Skips empty folders during loop.
	 *
	 * Not exactly the best name for this, but basically when looping recursively, it's not necessary to hang onto folder paths, the folder will be created if there are contents.
	 * However when not recursive, we need the folder placeholder there to drill down in case there are files below, which we won't know until they are retrieved.
	 *
	 * @var bool
	 */
	private $skip_empty = true;

	/**
	 * If tree should be recursive.
	 *
	 * @var bool
	 */
	private $recursive = true;

	/**
	 * Plugin info array.
	 *
	 * @var array
	 */
	private $plugin_info = array();

	/**
	 * Theme info array.
	 *
	 * @var array
	 */
	private $theme_info = array();

	/**
	 * Class Constructor.
	 *
	 * @param array $files  File array to be used with tree.
	 * @param array $args   File Tree options/args.
	 */
	public function __construct( array $files, $args = array() ) {
		$this->files = $files;
		if ( isset( $args['skip_empty'] ) ) {
			$this->skip_empty = $args['skip_empty'];
		}
		if ( ! empty( $args['format'] ) ) {
			if ( ! in_array( $args['format'], array( 'list', 'table' ), true ) ) {
				return false;
			}
			$this->format = $args['format'];
		}
		if ( ! empty( $args['ui'] ) ) {
			if ( ! in_array( $args['ui'], array( 'tree', 'panels' ), true ) ) {
				return false;
			}
			$this->ui = $args['ui'];
		}

		if ( ! empty( $args['plugin_info'] ) ) {
			$this->plugin_info = $args['plugin_info'];
		}

		if ( ! empty( $args['theme_info'] ) ) {
			$this->theme_info = $args['theme_info'];
		}

		if ( ! empty( $args['backup_file'] ) ) {
			$this->backup_file = $args['backup_file'];
		}

		return $this;
	}

	/**
	 * Get the backup type based on the file name.
	 *
	 * @return string  Backup type.
	 */
	public function get_backup_type() {
		if ( ! $this->backup_file ) {
			return false;
		}

		return backupbuddy_core::parse_file( $this->backup_file, 'type' );
	}

	/**
	 * Check if backup zip file exists.
	 *
	 * @return bool  If exists.
	 */
	public function backup_file_exists() {
		if ( ! $this->backup_file ) {
			return false;
		}

		return file_exists( backupbuddy_core::getBackupDirectory() . $this->backup_file );
	}

	/**
	 * Retrieves the tree structured html of files.
	 *
	 * @param bool $echo  Whether the tree should be echoed.
	 *
	 * @return string  File tree html.
	 */
	public function get_html( $echo = false ) {
		$tree    = $this->get_array();
		$parent  = '';
		$is_root = true === $this->skip_empty || '__root__' === $this->skip_empty;

		$this->recursive = is_string( $this->skip_empty ) ? false : $this->skip_empty;

		if ( is_string( $this->skip_empty ) && ! $is_root ) {
			$parent = $this->skip_empty;
		}

		if ( $this->recursive ) {
			//$tree = $this->consolidate_folders( $tree ); // Experimental.
		}

		$tree    = $this->sort_array( $tree );
		$output  = '';
		$wrapper = '%s';

		$this->columns = array(
			'file'     => __( 'File/Folder', 'it-l10n-backupbuddy' ),
			'size'     => __( 'Size', 'it-l10n-backupbuddy' ),
			'modified' => __( 'Last Modified', 'it-l10n-backupbuddy' ),
		);

		$up_link = '/';
		if ( $parent ) {
			$up_link = str_replace( basename( $parent ) . '/', '', $parent );
			if ( ! $up_link ) {
				$up_link = '/';
			}
		}

		$root_path_text = '/';
		if ( 'plugins' === $this->get_backup_type() ) {
			$root_path_text = '/ wp-content / plugins /';
		} elseif ( 'themes' === $this->get_backup_type() ) {
			$root_path_text = '/ wp-content / themes /';
		} elseif ( 'media' === $this->get_backup_type() ) {
			$root_path_text = '/ wp-content / uploads /';
		}

		$disabled    = ! $parent ? ' disabled' : '';
		$breadcrumb  = sprintf( '<a href="#%s" class="breadcrumb navigate-up%s">&#10094;</a>', esc_attr( $up_link ), esc_attr( $disabled ) );
		$breadcrumb .= sprintf( '<a href="#/" class="breadcrumb root-directory%s">%s</a>', esc_attr( $disabled ), esc_html( $root_path_text ) );
		if ( $parent ) {
			$segments = explode( '/', trim( $parent, '/' ) );
			$last     = count( $segments ) - 1;
			$path     = '';
			foreach ( $segments as $index => $segment ) {
				$path .= $segment . '/';
				if ( $last === $index ) {
					$breadcrumb .= sprintf( '<span class="breadcrumb">%s</span>', esc_html( $segment ) );
				} else {
					$breadcrumb .= sprintf( '<a href="#%s" class="breadcrumb">%s</a>', esc_attr( $path ), esc_html( $segment ) );
				}
				$breadcrumb .= '<span class="breadcrumb">/</span>';
			}
		}

		if ( 'list' === $this->format ) {
			$tree_html = $this->recursive_tree_loop( $tree, $parent );
			$header    = '';

			if ( 'panels' === $this->ui ) {
				$header .= '<div class="navigation">';
				$header .= $breadcrumb;
				$header .= '</div>';
			}

			$header .= '<div class="list-header">';
			foreach ( $this->columns as $col_class => $col_label ) {
				$header .= sprintf( '<span class="%s">%s</span>', $col_class, $col_label );
			}
			$header .= '</div>';

			$ul_id    = 'tree_root';
			$ul_class = '';
			$attr     = '';

			if ( $parent ) {
				$ul_id    = '';
				$ul_class = 'child';
				if ( count( $this->columns ) === 5 ) {
					$ul_class .= ' contains-version-info';
				}
			}

			if ( $ul_id ) {
				$attr .= sprintf( ' id="%s"', esc_attr( $ul_id ) );
			}
			if ( $ul_class ) {
				$attr .= sprintf( ' class="%s"', esc_attr( $ul_class ) );
			}

			$wrapper  = sprintf( '<ul%s>', $attr );
			$wrapper .= '%s</ul>';

			$footer = '</ul>';

			$empty = sprintf( $wrapper, '<li><div class="empty-directory">%s</div></li>' );

			if ( 'panels' === $this->ui ) {
				$empty = $header . $empty . $footer;
			}
		} elseif ( 'table' === $this->format ) {
			$tree_html   = $this->recursive_tree_loop( $tree, $parent );
			$parent_id   = $this->generate_html_id( $parent );
			$table_class = 'widefat';
			$table_id    = 'tree_root';

			if ( $parent ) {
				$table_class .= ' child';
				$table_id     = '';
				if ( count( $this->columns ) === 5 ) {
					$table_class .= ' contains-version-info';
				}
			}
			$attr = sprintf( ' class="%s"', esc_attr( $table_class ) );
			if ( $table_id ) {
				$attr .= sprintf( ' id="%s"', esc_attr( $table_id ) );
			}
			$header  = sprintf( '<table %s>', $attr );
			$header .= '<thead>';
			if ( 'panels' === $this->ui ) {
				$header .= sprintf( '<tr class="navigation">', $parent, $parent_id );
				$header .= '<th colspan="' . esc_attr( count( $this->columns ) + 1 ) . '">';
				$header .= $breadcrumb;
				$header .= '</th>';
				$header .= '</tr>';
			}
			$header .= '<tr class="column-headings">';
			foreach ( $this->columns as $col_class => $col_label ) {
				$header .= sprintf( '<th class="%s">%s</th>', $col_class, $col_label );
			}
			$header .= '<th></th>'; // View File icon.
			$header .= '</tr></thead><tbody>';

			$footer = '</tbody></table>';

			$margin = 'panels' === $this->ui ? 0 : substr_count( $parent, '/' ) * 24;
			$empty  = sprintf( '<tr data-parent-id="%s"><td colspan="4"><span style="margin-left: %dpx;" class="empty-directory">%%s</span></td></tr>', esc_attr( $parent_id ), $margin );

			if ( 'panels' === $this->ui ) {
				$empty = $header . sprintf( $wrapper, $empty ) . $footer;
			}
		}

		// Build the Output.
		if ( $tree_html ) {
			if ( ! $is_root && 'tree' === $this->ui ) {
				$header = '';
				$footer = '';
			}

			$output = $header . sprintf( $wrapper, $tree_html ) . $footer;
		} else {
			$output = sprintf( $empty, __( 'This directory is empty.' ) );
		}

		if ( 'panels' === $this->ui ) {
			$class  = $is_root ? '' : ' incoming';
			$path   = $parent ? $parent : '/';
			$output = sprintf( '<div class="panel%s" data-path="%s">' . $output . '</div>', esc_attr( $class ), esc_attr( $path ) );
		}

		if ( false === $echo ) {
			return $output;
		}

		echo $output;
	}

	/**
	 * Convert array of files with full paths into nested array.
	 *
	 * @return array  Nested file array for tree structure.
	 */
	private function get_array() {
		$tree = array();

		foreach ( $this->files as $file_array ) {
			// Skip empty files.
			if ( ! $file_array ) {
				continue;
			}

			$file = $file_array['path'];
			$key  = $file;

			if ( is_string( $this->skip_empty ) ) {
				$file = str_replace( $this->skip_empty, '', $file );
			}

			if ( ! $file ) {
				continue;
			}

			// Root folder files.
			if ( false === strpos( $file, '/' ) ) {
				$tree[ $key ] = $file_array;
				continue;
			}

			// Empty folder.
			if ( '/' === substr( $file, -1 ) ) {
				if ( true !== $this->skip_empty ) {
					$tree[ $key ] = array(
						'dir' => $file, // Not really used.
					);
				}
				// Skip empty folder.
				continue;
			}

			$array = &$tree; // Always start at root.

			// Split path into array.
			$segments = explode( '/', $file );
			$last     = count( $segments ) - 1; // Get filename index.

			foreach ( $segments as $index => $segment ) {
				if ( empty( $segment ) ) {
					continue;
				}

				if ( $last === $index ) { // File.
					$array[ $key ] = $file_array;
				} else { // Folder.
					if ( ! isset( $array[ $segment ] ) ) {
						$array[ $segment ] = array();
					}
					$array = &$array[ $segment ];
				}
			}
		}

		return $tree;
	}

	/**
	 * Creates a valid HTML ID from string.
	 *
	 * @param string $string  The string.
	 *
	 * @return string  The HTML ID.
	 */
	public function generate_html_id( $string ) {
		return str_replace( array( '/', '.', '-' ), '_', ltrim( trim( $string, '/' ), '/' ) );
	}

	/**
	 * Github style folder browsing.
	 * Consolidates folders containing single folders into 1 item.
	 *
	 * @param array $array  Nested array of files.
	 *
	 * @return array  Modified array.
	 */
	private function consolidate_folders( $array ) {
		$return = array();

		foreach ( $array as $dir => $file ) {
			// We're only concerned with folders, not files.
			if ( isset( $file['path'] ) ) {
				$return[ $dir ] = $file;
				continue;
			}

			if ( 1 === count( $file ) ) {
				$key                         = array_key_first( $file );
				$return[ $dir . '/' . $key ] = $file[ $key ];
				continue;
			}

			$return[ $dir ] = $this->consolidate_folders( $file );
		}

		return $return;
	}

	/**
	 * Sort multidimensional array, placing folders first, then files.
	 *
	 * @param array $array  Array to sort.
	 *
	 * @return array  Sorted array.
	 */
	private function sort_array( $array ) {
		$folders = array();
		$files   = array();
		$other   = array();

		foreach ( $array as $key => $data ) {
			if ( ! is_array( $data ) ) { // just in case.
				$other[ (string) $key ] = $data;
			} else {
				if ( isset( $data['path'] ) ) {
					$files[ (string) $key ] = $data;
				} else {
					if ( ! isset( $data['dir'] ) && count( $data ) ) {
						$sorted = $this->sort_array( $data );
					} else {
						$sorted = $data;
					}
					$folders[ (string) $key ] = $sorted;
				}
			}
		}

		uksort( $folders, 'strnatcasecmp' );
		uksort( $files, 'strnatcasecmp' );
		uksort( $other, 'strnatcasecmp' );

		return array_merge( $folders, $files, $other );
	}

	/**
	 * Gets array of file viewer modes by extension.
	 *
	 * @return array  Array of file viewer modes.
	 */
	private function get_viewer_modes() {
		return array(
			// Editor types.
			'htaccess' => 'code',
			'js'       => 'code',
			'htm'      => 'code',
			'html'     => 'code',
			'css'      => 'code',
			'sass'     => 'code',
			'scss'     => 'code',
			'php'      => 'code',
			'txt'      => 'code',
			'log'      => 'code',
			'sql'      => 'code',
			'md'       => 'code',
			// Image types.
			'png'      => 'image',
			'svg'      => 'image',
			'gif'      => 'image',
			'bmp'      => 'image',
			'jpg'      => 'image',
			'jpeg'     => 'image',
		);
	}

	/**
	 * Recursive loop for building nested list html.
	 *
	 * @param array  $folders      Folders to loop through.
	 * @param string $parent_path  Parent folder path.
	 *
	 * @return string  HTML for tree.
	 */
	private function recursive_tree_loop( $folders, $parent_path = '' ) {
		$return = '';
		$modes  = $this->get_viewer_modes();

		$parent_id = $this->generate_html_id( $parent_path );
		$margin    = 'panels' === $this->ui ? 0 : substr_count( $parent_path, '/' ) * 24;

		foreach ( $folders as $path => $file_array ) {
			if ( $this->recursive && empty( $file_array ) ) {
				continue;
			}

			$v_backup    = '';
			$v_installed = '';
			// Plugins can be folders or files.
			if ( $this->is_plugin_directory( $parent_path ) ) {
				$v_backup    = $this->get_plugin_version_info( $path, $parent_path );
				$v_installed = $this->get_plugin_version_info( $path, $parent_path, 'installed' );
				$this->insert_version_columns();
			}

			if ( ! isset( $file_array['path'] ) ) { // Folder: Array of files.
				$full_path = $parent_path . rtrim( $path, '/' ) . '/';

				if ( isset( $file_array['dir'] ) ) {
					$file_array = array();
				}

				// Themes can only be folders.
				if ( $this->is_theme_directory( $parent_path ) ) {
					$v_backup    = $this->get_theme_version_info( $path );
					$v_installed = $this->get_theme_version_info( $path, 'installed' );
					$this->insert_version_columns();
				}

				$id       = $this->generate_html_id( $full_path );
				$class    = 'dir';
				$attr     = sprintf( ' data-dir="%s" id="dir_%s"', esc_attr( $full_path ), esc_attr( $id ) );
				$checkbox = sprintf( '<input type="checkbox" value="%s" id="%s" name="backupbuddy_restore[]">', esc_attr( $full_path ), esc_attr( $id ) );
				$modified = '';

				$total = count( $file_array );
				$count = true === $this->recursive ? $total . ' ' . _n( 'Item', 'Items', $total, 'it-l10n-backupbuddy' ) : false;

				$file_span  = sprintf( '<span class="file-name">%s</span>', esc_html( rtrim( $path, '/' ) ) );

				$icon   = 'tree' === $this->ui ? '<i class="toggle">‣</i>' : '';
				$toggle = sprintf( '<a href="#%%s" class="folder-toggle">%s' . $file_span . '%%s%%s%%s</a>', $icon );

				$args = array(
					'attr'        => $attr,
					'checkbox'    => '<span class="cbx"%s>' . $checkbox . '</span>',
					'file_array'  => $file_array,
					'parent_path' => $parent_path,
					'full_path'   => $full_path,
					'class'       => $class,
					'id'          => $id,
					'toggle'      => $toggle,
					'count'       => $count,
					'parent_id'   => $parent_id,
					'margin'      => $margin,
					'modified'    => $modified,
					'v_backup'    => $v_backup,
					'v_installed' => $v_installed,
				);

				if ( 'list' === $this->format ) {
					$return .= $this->get_folder_li( $args );
				} elseif ( 'table' === $this->format ) {
					if ( $parent_id && 'tree' === $this->ui ) {
						$args['class'] .= ' hidden';
					}
					$return .= $this->get_folder_row( $args );
				}
			} else { // File: Array of file properties.
				$full_path = $parent_path . rtrim( $path, '/' );
				$id        = str_replace( array( '/', '.', '-' ), '_', ltrim( $full_path, '/' ) );
				$class     = 'file';
				$ext       = backupbuddy_data_file()->get_extension( $path );
				$modified  = '';
				$view      = '';
				$attr      = '';

				if ( $ext ) {
					$class .= sprintf( ' ext-%s', esc_attr( $ext ) );
				}

				if ( $this->backup_file_exists() && isset( $modes[ $ext ] ) ) {
					$view_attr = sprintf( ' data-mode="%s" data-path="%s" data-type="%s"', esc_attr( $modes[ $ext ] ), esc_attr( $full_path ), esc_attr( $ext ) );
					$view      = sprintf( '<a href="#view" class="view-file"%s>View</a>', $view_attr );
				}

				if ( ! empty( $file_array['modified'] ) ) {
					$local_time = pb_backupbuddy::$format->localize_time( $file_array['modified'] );
					$modified   = rtrim( pb_backupbuddy::$format->date( $local_time, 'M j, Y g:ia' ), 'm' );
					$modified   = sprintf( '<span class="modified">%s</span>', $modified );
				}
				$size      = ! empty( $file_array['size'] ) ? $file_array['size'] : @filesize( $full_path );
				$size      = false !== $size ? pb_backupbuddy::$format->file_size( $size ) : 0;
				$file_name = basename( $file_array['path'] );
				$checkbox  = sprintf( '<input type="checkbox" value="%s" id="%s" name="backupbuddy_restore[]">', esc_attr( $full_path ), esc_attr( $id ) );

				$args = array(
					'full_path'   => $full_path,
					'parent_path' => $parent_path,
					'attr'        => $attr,
					'class'       => $class,
					'checkbox'    => '<span class="cbx"%s>' . $checkbox . '</span>',
					'id'          => $id,
					'file_name'   => $file_name,
					'v_backup'    => $v_backup,
					'v_installed' => $v_installed,
					'size'        => $size,
					'modified'    => $modified,
					'view'        => $view,
					'parent_id'   => $parent_id,
					'margin'      => $margin,
				);

				if ( 'list' === $this->format ) {
					$return .= $this->get_file_li( $args );
				} elseif ( 'table' === $this->format ) {
					if ( $parent_id && 'tree' === $this->ui ) {
						$args['class'] .= ' hidden';
					}
					$return .= $this->get_file_row( $args );
				}
			}
		}
		return $return;
	}

	/**
	 * Gets the list HTML for Folders.
	 *
	 * Needs args:
	 *     file_array
	 *     attr
	 *     checkbox
	 *     toggle
	 *     full_path
	 *     parent_path
	 *     class
	 *     count
	 *     modified
	 *     v_backup
	 *     v_installed
	 *
	 * @param array $args  Array of arguments.
	 *
	 * @return string  Folder List HTML.
	 */
	private function get_folder_li( $args ) {
		$href     = '';
		$size     = '';
		$modified = '';
		$version  = '';

		if ( false !== $args['count'] ) {
			$size = sprintf( '<span class="size">%s</span>', $args['count'] );
		}
		if ( $args['modified'] ) {
			$modified = sprintf( '<span class="modified">%s</span>', $args['modified'] );
		}
		if ( 'panels' === $this->ui ) {
			$href = $args['full_path'];
		}
		if ( $this->is_plugin_directory( $args['parent_path'] ) || $this->is_theme_directory( $args['parent_path'] ) ) {
			$version  = sprintf( '<span class="v_backup">%s</span>', $args['v_backup'] );
			$version .= sprintf( '<span class="v_installed">%s</span>', $args['v_installed'] );
		}

		$li  = sprintf( '<li%s class="%s">', $args['attr'], $args['class'] );
		$li .= '<div>';
		$li .= sprintf( $args['checkbox'], '' );
		$li .= sprintf( $args['toggle'], $href, $version, $size, $modified );
		$li .= '</div>';
		if ( $this->recursive ) {
			$li .= '<ul class="child">';
			$li .= $this->recursive_tree_loop( $args['file_array'], $args['full_path'] );
			$li .= '</ul>';
		}
		$li .= '</li>';

		return $li;
	}

	/**
	 * Gets the list HTML for Files
	 *
	 * Needs args:
	 *     full_path
	 *     parent_path
	 *     checkbox
	 *     attr
	 *     class
	 *     id
	 *     file_name
	 *     v_backup
	 *     v_installed
	 *     size
	 *     modified
	 *     view
	 *
	 * @param array $args  Array of arguments.
	 *
	 * @return string  File list HTML.
	 */
	private function get_file_li( $args ) {
		$checkbox = sprintf( $args['checkbox'], '' );

		$li  = sprintf( '<li%s class="%s">', $args['attr'], esc_attr( $args['class'] ) );
		$li .= '<div>';
		$li .= sprintf( '%s<label for="%s">', $checkbox, esc_attr( $args['id'] ) );
		$li .= sprintf( '<span class="file">%s</span>', esc_html( $args['file_name'] ) );
		if ( $this->is_plugin_directory( $args['parent_path'] ) || $this->is_theme_directory( $args['parent_path'] ) ) {
			$li .= sprintf( '<span class="v_backup">%s</span>', $args['v_backup'] );
			$li .= sprintf( '<span class="v_installed">%s</span>', $args['v_installed'] );
		}
		$li .= sprintf( '<span class="size">%s</span>%s', esc_html( $args['size'] ), $args['modified'] );
		$li .= sprintf( '%s</label>', $args['view'] );
		$li .= '</div></li>';

		return $li;
	}

	/**
	 * Gets the Table Row HTML for Folders.
	 *
	 * Needs args:
	 *     file_array
	 *     attr
	 *     checkbox
	 *     toggle
	 *     full_path
	 *     parent_path
	 *     class
	 *     count
	 *     modified
	 *     v_backup
	 *     v_installed
	 *
	 * Also needs:
	 *     margin
	 *     parent_id
	 *
	 * @param array $args  Array of arguments.
	 *
	 * @return string  Folder List HTML.
	 */
	private function get_folder_row( $args ) {
		$margin = sprintf( ' style="margin-left: %dpx;"', $args['margin'] );

		$href = $args['id'];
		if ( 'panels' === $this->ui ) {
			$href = $args['full_path'];
		}

		$row  = sprintf( '<tr%s class="%s" data-parent-id="%s">', $args['attr'], esc_attr( $args['class'] ), esc_attr( $args['parent_id'] ) );
		$row .= '<td class="file"><span class="label">';
		$row .= sprintf( $args['checkbox'], $margin );
		$row .= sprintf( $args['toggle'], esc_attr( $href ), '', '', '', '' ); // version, version, size, modified empty values.
		$row .= '</span></td>';
		if ( $this->is_plugin_directory( $args['parent_path'] ) || $this->is_theme_directory( $args['parent_path'] ) ) {
			$row .= sprintf( '<td class="v_backup">%s</td>', $args['v_backup'] );
			$row .= sprintf( '<td class="v_installed">%s</td>', $args['v_installed'] );
		}
		$row .= sprintf( '<td class="size">%s</td>', $args['count'] );
		$row .= sprintf( '<td class="modified">%s</td>', $args['modified'] ); // Modified Column.
		$row .= '<td>&nbsp;</td>'; // View Column.
		$row .= '</tr>';

		if ( $this->recursive ) {
			$row .= $this->recursive_tree_loop( $args['file_array'], $args['full_path'] );
		}

		return $row;
	}

	/**
	 * Gets the Table Row HTML for Files
	 *
	 * Needs args:
	 *     full_path
	 *     parent_path
	 *     checkbox
	 *     attr
	 *     class
	 *     id
	 *     file_name
	 *     size
	 *     modified
	 *     view
	 *     v_backup
	 *     v_installed
	 *
	 * Also:
	 *     margin
	 *     parent_id
	 *
	 * @param array $args  Array of arguments.
	 *
	 * @return string  File Table Row HTML.
	 */
	private function get_file_row( $args ) {
		$margin   = sprintf( ' style="margin-left: %dpx;"', $args['margin'] );
		$checkbox = sprintf( $args['checkbox'], $margin );

		$row  = sprintf( '<tr%s class="%s" data-parent-id="%s">', $args['attr'], esc_attr( $args['class'] ), esc_attr( $args['parent_id'] ) );
		$row .= '<td class="file"><span class="label">';
		$row .= sprintf( '%s<label for="%s"><span class="file-name">%s</span>', $checkbox, esc_attr( $args['id'] ), esc_html( $args['file_name'] ) );
		$row .= '</span></td>';
		if ( $this->is_plugin_directory( $args['parent_path'] ) || $this->is_theme_directory( $args['parent_path'] ) ) {
			$row .= sprintf( '<td class="v_backup">%s</td>', $args['v_backup'] );
			$row .= sprintf( '<td class="v_installed">%s</td>', $args['v_installed'] );
		}
		$row .= sprintf( '<td class="size"><label for="%s">%s</label</td>', esc_attr( $args['id'] ), esc_html( $args['size'] ) );
		$row .= sprintf( '<td class="modified"><label for="%s">%s</label></td>', esc_attr( $args['id'] ), $args['modified'] );
		$row .= sprintf( '<td class="view">%s</td>', $args['view'] );
		$row .= '</tr>';

		return $row;
	}

	/**
	 * Get the Plugin Detail from the backup data file.
	 *
	 * @param string $plugin_file  File or folder of plugin.
	 *
	 * @return array|false  Array of plugin info or false when not found.
	 */
	private function get_plugin_detail( $plugin_file ) {
		if ( empty( $this->plugin_info ) ) {
			return false;
		}

		foreach ( $this->plugin_info as $plugin_key => $plugin_array ) {
			$folder_name = false;
			if ( false !== strpos( $plugin_key, '/' ) ) {
				list( $folder_name, $file_name ) = explode( '/', $plugin_key );
			} else {
				$file_name = $plugin_key;
			}

			if ( $folder_name === $plugin_file ) {
				$plugin_array['plugin_file'] = $plugin_key;
				return $plugin_array;
			} elseif ( $file_name === $plugin_file ) {
				$plugin_array['plugin_file'] = $plugin_key;
				return $plugin_array;
			}
		}

		return false;
	}

	/**
	 * Get the Theme Detail from the backup data file.
	 *
	 * @param string $theme_folder  Folder of theme.
	 *
	 * @return array|false  Array of theme info or false when not found.
	 */
	private function get_theme_detail( $theme_folder ) {
		if ( empty( $this->theme_info ) ) {
			return false;
		}

		foreach ( $this->theme_info as $theme_file => $theme_array ) {
			list( $folder_name, $stylesheet ) = explode( '/', $theme_file );

			if ( $folder_name === $theme_folder ) {
				return $theme_array;
			}
		}

		return false;
	}

	/**
	 * Gets current WordPress Plugin information.
	 *
	 * @param string $plugin_file  Plugin file.
	 *
	 * @return array  Plugin info array.
	 */
	private function get_plugin_data( $plugin_file ) {
		if ( ! file_exists( ABSPATH . $plugin_file ) ) {
			return false;
		}
		return get_plugin_data( ABSPATH . $plugin_file );
	}

	/**
	 * Gets current WordPress Theme information.
	 *
	 * @param string $theme_folder  Theme Folder.
	 *
	 * @return array  Theme name and version array.
	 */
	private function get_theme_data( $theme_folder ) {
		$theme_folder = rtrim( $theme_folder, '/' );
		$theme        = wp_get_theme( $theme_folder );

		return array(
			'Name'    => $theme->get( 'Name' ),
			'Version' => $theme->get( 'Version' ),
		);
	}

	/**
	 * Checks if path is equal to plugins directory
	 *
	 * @param string $path  Path to check.
	 *
	 * @return bool  If is plugin directory
	 */
	private function is_plugin_directory( $path ) {
		if ( 'plugins' === $this->get_backup_type() ) {
			if ( empty( $path ) ) {
				return true;
			}
		}
		return 'wp-content/plugins/' === $path || 'wp-content/mu-plugins/' === $path;
	}

	/**
	 * Returns Plugin Version comparison info.
	 *
	 * @param string $path         Path to file.
	 * @param string $parent_path  Parent directory path.
	 * @param string $return       Either backup or installed version.
	 *
	 * @return string  Version info HTML or false.
	 */
	private function get_plugin_version_info( $path, $parent_path, $return = 'backup' ) {
		if ( 'plugins' === $this->get_backup_type() ) {
			$parent_path = 'wp-content/plugins/' . $parent_path;
		}
		$plugin = $this->get_plugin_detail( rtrim( $path, '/' ) );
		if ( $plugin ) {
			$current = $this->get_plugin_data( $parent_path . $plugin['plugin_file'] );
			if ( 'installed' === $return ) {
				$output_version = ! empty( $current['Version'] ) ? $current['Version'] : 'n/a';
				$diff           = ! empty( $current['Version'] ) ? version_compare( $current['Version'], $plugin['Version'] ) : -1;
			} else {
				$output_version = ! empty( $plugin['Version'] ) ? $plugin['Version'] : 'n/a';
				$diff           = ! empty( $current['Version'] ) ? version_compare( $plugin['Version'], $current['Version'] ) : 1;
			}
			$diff_class = 'same';
			if ( $diff < 0 ) {
				$diff_class = 'older';
			} elseif ( $diff > 0 ) {
				$diff_class = 'newer';
			}
			return sprintf( '<strong class="version %s">%s</strong>', esc_attr( $diff_class ), esc_html( $output_version ) );
		}
		return false;
	}

	/**
	 * Checks if path is equal to themes directory
	 *
	 * @param string $path  Path to check.
	 *
	 * @return bool  If is themes directory
	 */
	private function is_theme_directory( $path ) {
		if ( 'themes' === $this->get_backup_type() ) {
			if ( empty( $path ) ) {
				return true;
			}
		}
		return 'wp-content/themes/' === $path;
	}

	/**
	 * Returns Theme Version comparison info.
	 *
	 * @param string $path   Path.
	 * @param string $return Either backup or installed.
	 *
	 * @return string  Version info HTML or false.
	 */
	private function get_theme_version_info( $path, $return = 'backup' ) {
		$theme = $this->get_theme_detail( rtrim( $path, '/' ) );
		if ( $theme ) {
			$current = $this->get_theme_data( rtrim( $path, '/' ) );
			if ( 'installed' === $return ) {
				$output_version = ! empty( $current['Version'] ) ? $current['Version'] : 'n/a';
				$diff           = ! empty( $current['Version'] ) ? version_compare( $current['Version'], $theme['Version'] ) : -1;
			} else {
				$output_version = ! empty( $theme['Version'] ) ? $theme['Version'] : 'n/a';
				$diff           = ! empty( $current['Version'] ) ? version_compare( $theme['Version'], $current['Version'] ) : 1;
			}
			$diff_class = 'same';
			if ( $diff < 0 ) {
				$diff_class = 'older';
			} elseif ( $diff > 0 ) {
				$diff_class = 'newer';
			}
			return sprintf( '<strong class="version %s">%s</strong>', esc_attr( $diff_class ), esc_html( $output_version ) );
		}

		return false;
	}

	/**
	 * Adds Version Columns when displaying Theme & Plugin Directories.
	 */
	private function insert_version_columns() {
		if ( isset( $this->columns['v_backup'] ) ) {
			return;
		}

		$v_columns = array();
		foreach ( $this->columns as $key => $column ) {
			$v_columns[ $key ] = $column;
			if ( 'file' !== $key ) {
				continue;
			}
			$v_columns['v_backup']    = __( 'Backup Version', 'it-l10n-backupbuddy' );
			$v_columns['v_installed'] = __( 'Installed Version', 'it-l10n-backupbuddy' );
		}

		$this->columns = $v_columns;
	}
}
