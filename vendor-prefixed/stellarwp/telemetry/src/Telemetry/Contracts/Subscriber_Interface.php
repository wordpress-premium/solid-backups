<?php
/**
 * The API implemented by all subscribers.
 *
 * @package Solid_Backups\Strauss\StellarWP\Telemetry\Contracts
 */

namespace Solid_Backups\Strauss\StellarWP\Telemetry\Contracts;

/**
 * Interface Subscriber_Interface
 *
 * @package Solid_Backups\Strauss\StellarWP\Telemetry\Contracts
 */
interface Subscriber_Interface {

	/**
	 * Register action/filter listeners to hook into WordPress
	 *
	 * @return void
	 */
	public function register();
}
