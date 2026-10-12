<?php
/**
 * Placement taxonomy helpers and default terms.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Ads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Everywhere catch-all plus seeded property/zone placements.
 */
class Placements {

	/**
	 * Seed default terms after the taxonomy exists.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'ensure_property_terms' ), 21 );
		add_action( 'init', array( __CLASS__, 'ensure_everywhere_term' ), 22 );
	}

	/**
	 * Slugs that mean "show this ad in every placement".
	 *
	 * @return string[]
	 */
	public static function everywhere_slugs() {
		return array( Constants::EVERYWHERE_SLUG, 'all' );
	}

	/**
	 * Terms to query for a block: the selected placement plus Everywhere.
	 *
	 * @param string $placement_slug Selected slug.
	 * @return string[]
	 */
	public static function query_slugs( $placement_slug ) {
		$slug       = sanitize_title( $placement_slug );
		$everywhere = self::everywhere_slugs();

		if ( ! $slug || in_array( $slug, $everywhere, true ) ) {
			return $everywhere;
		}

		return array_values( array_unique( array_merge( array( $slug ), $everywhere ) ) );
	}

	/**
	 * Whether a term is the protected Everywhere placement.
	 *
	 * @param \WP_Term|null $term Term object.
	 * @return bool
	 */
	public static function is_protected( $term ) {
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		return in_array( $term->slug, self::everywhere_slugs(), true );
	}

	/**
	 * One-time seed of single-post/page × property placements.
	 */
	public static function ensure_property_terms() {
		if ( get_option( 'sponsor_manager_terms_seeded_v1' ) ) {
			return;
		}
		if ( ! taxonomy_exists( Constants::TAXONOMY ) ) {
			return;
		}

		$properties = array(
			'WDW'   => 'wdw',
			'DLNT'  => 'dlnt',
			'UPNT'  => 'upnt',
			'DCLNT' => 'dclnt',
		);
		$zones      = array(
			'Single Post' => 'single-post',
			'Single Page' => 'single-page',
		);

		foreach ( $zones as $zone_label => $zone_slug ) {
			foreach ( $properties as $prop_label => $prop_slug ) {
				$slug = $zone_slug . '-' . $prop_slug;
				if ( ! term_exists( $slug, Constants::TAXONOMY ) ) {
					wp_insert_term( $zone_label . ' - ' . $prop_label, Constants::TAXONOMY, array( 'slug' => $slug ) );
				}
			}
		}

		update_option( 'sponsor_manager_terms_seeded_v1', 1 );
	}

	/**
	 * Guarantee an Everywhere term exists (migrating legacy "all" if needed).
	 */
	public static function ensure_everywhere_term() {
		if ( ! taxonomy_exists( Constants::TAXONOMY ) ) {
			return;
		}

		$everywhere = get_term_by( 'slug', Constants::EVERYWHERE_SLUG, Constants::TAXONOMY );
		if ( ! $everywhere ) {
			$everywhere = get_term_by( 'name', 'Everywhere', Constants::TAXONOMY );
		}
		if ( $everywhere && ! is_wp_error( $everywhere ) ) {
			$update = array();
			if ( 'Everywhere' !== $everywhere->name ) {
				$update['name'] = 'Everywhere';
			}
			if ( Constants::EVERYWHERE_SLUG !== $everywhere->slug ) {
				$update['slug'] = Constants::EVERYWHERE_SLUG;
			}
			if ( $update ) {
				wp_update_term( $everywhere->term_id, Constants::TAXONOMY, $update );
			}
			return;
		}

		foreach ( array( 'all', 'All' ) as $legacy ) {
			$legacy_term = get_term_by( 'slug', sanitize_title( $legacy ), Constants::TAXONOMY );
			if ( ! $legacy_term ) {
				$legacy_term = get_term_by( 'name', $legacy, Constants::TAXONOMY );
			}
			if ( $legacy_term && ! is_wp_error( $legacy_term ) ) {
				wp_update_term(
					$legacy_term->term_id,
					Constants::TAXONOMY,
					array(
						'name' => 'Everywhere',
						'slug' => Constants::EVERYWHERE_SLUG,
					)
				);
				return;
			}
		}

		wp_insert_term(
			'Everywhere',
			Constants::TAXONOMY,
			array( 'slug' => Constants::EVERYWHERE_SLUG )
		);
	}
}
