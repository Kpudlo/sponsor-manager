<?php
/**
 * Deprecated Sponsor Sidebar — renders as a vertical sponsors block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = is_array( $attributes ) ? $attributes : array();
$attributes['orientation'] = 'vertical';
if ( empty( $attributes['maxItems'] ) ) {
	$attributes['maxItems'] = 24;
}

echo sponsor_manager_render_ads_block( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
