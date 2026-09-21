<?php
/**
 * Register Sponsor Manager Gutenberg blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'sponsor_manager_register_blocks' );
function sponsor_manager_register_blocks() {
	$blocks = array( 'sponsor-grid', 'sponsor-sidebar' );

	foreach ( $blocks as $block ) {
		$block_dir = SPONSOR_MANAGER_PATH . 'build/' . $block;
		if ( file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir );
		}
	}
}
