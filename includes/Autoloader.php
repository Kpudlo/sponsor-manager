<?php
/**
 * PSR-4 autoloader for the SponsorManager namespace.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps SponsorManager\* class names to files under includes/.
 */
class Autoloader {

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a namespaced class file if it belongs to this plugin.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( $class ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = SPONSOR_MANAGER_PATH . 'includes/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
