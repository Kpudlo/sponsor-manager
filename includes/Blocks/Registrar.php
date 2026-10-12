<?php
/**
 * Gutenberg block registration.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Sponsors block and the deprecated sidebar alias.
 */
class Registrar {

	/**
	 * Hook block registration on init.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Register each block from its build directory.
	 */
	public static function register_blocks() {
		foreach ( array( 'sponsor-grid', 'sponsor-sidebar' ) as $block ) {
			$block_dir = SPONSOR_MANAGER_PATH . 'build/' . $block;
			if ( file_exists( $block_dir . '/block.json' ) ) {
				register_block_type( $block_dir );
			}
		}
	}
}
