<?php
/**
 * Used to create tabbed interface UI.
 *
 * @package BackupBuddy
 */

/**
 * Tabs UI class.
 */
class BackupBuddy_Tabs {

	/**
	 * Tabs array.
	 *
	 * @var array
	 */
	private $tabs = array();

	/**
	 * Settings array.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Current active tab.
	 *
	 * @var string
	 */
	private $active_tab = '';

	/**
	 * Initialize tabs.
	 *
	 * @param array $tabs      Tabs array.
	 * @param array $settings  Array of settings.
	 */
	public function __construct( $tabs, $settings = array() ) {
		$this->tabs     = $tabs;
		$defaults       = array(
			'class'   => '',
			'between' => '',
		);
		$this->settings = array_merge( $defaults, $settings );

		$this->set_active_tab( pb_backupbuddy::_GET( 'tab' ) );

		return $this;
	}

	/**
	 * Set the current active tab.
	 *
	 * @param string $active_tab  Active Tab ID.
	 */
	public function set_active_tab( $active_tab ) {
		foreach ( $this->tabs as $tab ) {
			if ( $tab['id'] === $active_tab ) {
				$this->active_tab = $active_tab;
				break;
			}
		}
	}

	/**
	 * Get the current active tab.
	 *
	 * @return string  Current active tab.
	 */
	public function get_active_tab() {
		return $this->active_tab;
	}

	/**
	 * Output the entire tabs interface.
	 */
	public function render() {
		$this->wrap_start();
		$this->controls();
		$this->between();
		$this->tabs();
		$this->wrap_end();
	}

	/**
	 * Start the tabs wrapper element.
	 */
	private function wrap_start() {
		printf( '<div class="backupbuddy-tabs %s">', esc_attr( $this->settings['class'] ) );
	}

	/**
	 * End the tabs wrapper element.
	 */
	private function wrap_end() {
		echo '</div><!-- .backupbuddy-tabs -->';
	}

	/**
	 * Output the tab controls (the links);
	 */
	private function controls() {
		echo '<ul class="tab-controls">';
		$first = true;
		foreach ( $this->tabs as $index => $tab ) :
			$label = $tab['label'];
			$href  = ! empty( $tab['href'] ) ? $tab['href'] : '#' . $tab['id'];
			$slug  = sanitize_title( $label );
			$class = $slug;
			if ( ! empty( $tab['class'] ) ) {
				// Remove Slug from class to prevent duplicates.
				$tab['class'] = str_replace( array( $slug . ' ', $slug ), array( ' ', '' ), $tab['class'] );
				$class       .= ' ' . trim( $tab['class'] );
			}

			if ( $this->get_active_tab() ) {
				$class .= ( false !== strpos( $href, '#' . $this->get_active_tab() ) ) ? ' active' : '';
			} else {
				$class .= $first ? ' active' : '';
				if ( $first ) {
					$this->set_active_tab( $tab['id'] );
				}
			}

			printf( '<li class="tab %s"><a href="%s">%s</a>', esc_attr( $class ), esc_attr( $href ), esc_html( $label ) );
			$first = false;
		endforeach;
		echo '</ul>';
	}

	/**
	 * Execute anything that goes between the controls and the tab contents.
	 */
	private function between() {
		if ( empty( $this->settings['between'] ) ) {
			return;
		}
		if ( is_callable( $this->settings['between'] ) ) {
			call_user_func( $this->settings['between'] );
			pb_backupbuddy::flush();
		} else {
			echo $this->settings['between'];
		}
	}

	/**
	 * Output the tab contents.
	 */
	private function tabs() {
		echo '<div class="tabs-container">';

		foreach ( $this->tabs as $tab ) {
			if ( ! empty( $tab['before'] ) ) {
				if ( is_callable( $tab['before'] ) ) {
					call_user_func( $tab['before'] );
					pb_backupbuddy::flush();
				} else {
					echo $tab['before'];
				}
			}

			if ( ! empty( $tab['callback'] ) || ! empty( $tab['content'] ) ) {

				printf( '<div id="%s" class="tab-contents%s">', esc_attr( $tab['id'] ), ( $tab['id'] === $this->get_active_tab() ? ' active' : '' ) );

				if ( is_callable( $tab['callback'] ) ) {
					call_user_func( $tab['callback'] );
					pb_backupbuddy::flush();
				} elseif ( ! empty( $tab['content'] ) ) {
					echo $tab['content'];
				}

				printf( '</div><!-- #%s -->', esc_html( $tab['id'] ) );
			}

			if ( ! empty( $tab['after'] ) ) {
				if ( is_callable( $tab['after'] ) ) {
					call_user_func( $tab['after'] );
					pb_backupbuddy::flush();
				} else {
					echo $tab['after'];
				}
			}
		}

		echo '</div><!-- .tabs-container -->';
	}
}
