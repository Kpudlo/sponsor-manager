<?php
/**
 * Prefixed helpers for blocks, templates, and remaining dashboard screens.
 *
 * @package SponsorManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Admin\Dashboard;
use SponsorManager\Ads\Placements;
use SponsorManager\Ads\Query;
use SponsorManager\Ads\Status;
use SponsorManager\Blocks\Renderer;
use SponsorManager\Styles\Repository;

/**
 * @return string[]
 */
function sponsor_manager_get_everywhere_slugs() {
	return Placements::everywhere_slugs();
}

/**
 * @param string $placement_slug Placement slug.
 * @return string[]
 */
function sponsor_manager_get_placement_query_slugs( $placement_slug ) {
	return Placements::query_slugs( $placement_slug );
}

/**
 * @param int $post_id Ad ID.
 * @return string
 */
function sponsor_manager_compute_status( $post_id ) {
	return Status::compute( $post_id );
}

/**
 * @param int $post_id Ad ID.
 * @return string
 */
function sponsor_manager_refresh_status( $post_id ) {
	return Status::refresh( $post_id );
}

/**
 * @param int $post_id Ad ID.
 * @return string
 */
function sponsor_manager_get_ad_url( $post_id ) {
	return Query::get_ad_url( $post_id );
}

/**
 * @param string $placement_slug Placement slug.
 * @param array  $args           Query args.
 * @return array
 */
function sponsor_manager_get_ads( $placement_slug, $args = array() ) {
	return Query::get_ads( $placement_slug, $args );
}

/**
 * @param array $ads         Ad pool.
 * @param int[] $exclude_ids IDs to skip.
 * @return array|null
 */
function sponsor_manager_weighted_pick( array $ads, array $exclude_ids = array() ) {
	return Renderer::weighted_pick( $ads, $exclude_ids );
}

/**
 * @param array $ads         Ad pool.
 * @param int   $count       Count.
 * @param int[] $exclude_ids IDs to skip.
 * @return array
 */
function sponsor_manager_weighted_pick_multiple( array $ads, $count, array $exclude_ids = array() ) {
	return Renderer::weighted_pick_multiple( $ads, $count, $exclude_ids );
}

/**
 * @param array $attributes Block attributes.
 * @return string
 */
function sponsor_manager_render_ads_block( array $attributes ) {
	return Renderer::render_block( $attributes );
}

/**
 * @param array  $ad     Ad data.
 * @param string $layout Unused; kept for existing callers.
 * @return string
 */
function sponsor_manager_render_ad_markup( array $ad, $layout = 'grid' ) {
	return Renderer::ad_markup( $ad, $layout );
}

/**
 * @param array $attributes Block attributes.
 * @return string
 */
function sponsor_manager_get_block_style_attribute( array $attributes ) {
	return Renderer::style_attribute( $attributes );
}

/**
 * @param array $ads Ads.
 * @return array
 */
function sponsor_manager_prepare_ads_for_client( array $ads ) {
	return Renderer::ads_for_client( $ads );
}

/**
 * @return array
 */
function sponsor_manager_get_default_styles() {
	return Repository::defaults();
}

/**
 * @return array
 */
function sponsor_manager_get_theme_palette() {
	return Repository::palette();
}

/**
 * @param mixed $color Color value.
 * @return string
 */
function sponsor_manager_sanitize_color_value( $color ) {
	return Repository::sanitize_color( $color );
}

/**
 * @param string $color    Color.
 * @param string $fallback Fallback.
 * @return string
 */
function sponsor_manager_color_to_css( $color, $fallback = 'currentColor' ) {
	return Repository::color_to_css( $color, $fallback );
}

/**
 * @return array
 */
function sponsor_manager_get_global_styles() {
	return Repository::get();
}

/**
 * @param array $input Raw styles.
 * @return array
 */
function sponsor_manager_sanitize_styles( $input ) {
	return Repository::sanitize( $input );
}

/**
 * @param array $styles      Styles.
 * @param bool  $for_preview Preview mode.
 * @return string
 */
function sponsor_manager_styles_to_css( array $styles, $for_preview = false ) {
	return Repository::to_css( $styles, $for_preview );
}

/**
 * @return string
 */
function sponsor_manager_theme_palette_css() {
	return Repository::palette_css();
}

/**
 * @param \WP_Post|int $post Ad.
 * @return string
 */
function sponsor_manager_format_booking_label( $post ) {
	return Dashboard::booking_label( $post );
}

/**
 * @param array $args Query args.
 * @return string
 */
function sponsor_manager_dashboard_url( $args = array() ) {
	return Dashboard::url( $args );
}

/**
 * @param string $id    Input id.
 * @param string $name  Input name.
 * @param string $value Value.
 */
function sponsor_manager_render_color_control( $id, $name, $value ) {
	\SponsorManager\Admin\StylesPage::color_control( $id, $name, $value );
}
