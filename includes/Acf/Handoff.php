<?php
/**
 * Keep ACF / SCF from re-registering Sponsor Manager types while ACF stays on for other content.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Acf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Strips our old ACF JSON / UI objects from ACF's registry.
 */
class Handoff {

	public const NOTICE_OPTION = 'sponsor_manager_hide_acf_handoff_notice';

	/**
	 * ACF internal post keys this plugin used to ship.
	 *
	 * @return string[]
	 */
	public static function acf_keys() {
		return array(
			'post_type_sponsor_ad',
			'taxonomy_sponsor_placement',
			'group_sponsor_manager_booking',
		);
	}

	/**
	 * Register ACF filters and a one-time cleanup notice.
	 */
	public static function register() {
		add_filter( 'acf/load_post_types', array( __CLASS__, 'exclude_post_types' ), 99 );
		add_filter( 'acf/load_taxonomies', array( __CLASS__, 'exclude_taxonomies' ), 99 );
		add_filter( 'acf/load_field_groups', array( __CLASS__, 'exclude_field_groups' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss_notice' ) );
	}

	/**
	 * @param mixed $posts ACF post type definitions.
	 * @return array
	 */
	public static function exclude_post_types( $posts ) {
		return self::reject(
			$posts,
			function ( $post ) {
				$key  = isset( $post['key'] ) ? (string) $post['key'] : '';
				$slug = isset( $post['post_type'] ) ? (string) $post['post_type'] : '';
				return 'post_type_sponsor_ad' === $key || Constants::POST_TYPE === $slug;
			}
		);
	}

	/**
	 * @param mixed $posts ACF taxonomy definitions.
	 * @return array
	 */
	public static function exclude_taxonomies( $posts ) {
		return self::reject(
			$posts,
			function ( $post ) {
				$key  = isset( $post['key'] ) ? (string) $post['key'] : '';
				$slug = isset( $post['taxonomy'] ) ? (string) $post['taxonomy'] : '';
				return 'taxonomy_sponsor_placement' === $key || Constants::TAXONOMY === $slug;
			}
		);
	}

	/**
	 * @param mixed $posts ACF field groups.
	 * @return array
	 */
	public static function exclude_field_groups( $posts ) {
		return self::reject(
			$posts,
			function ( $post ) {
				$key   = isset( $post['key'] ) ? (string) $post['key'] : '';
				$title = isset( $post['title'] ) ? (string) $post['title'] : '';
				return 'group_sponsor_manager_booking' === $key || 'Sponsor Booking' === $title;
			}
		);
	}

	/**
	 * Optional reminder if leftover ACF definition posts still exist in the database.
	 */
	public static function maybe_notice() {
		if ( get_option( self::NOTICE_OPTION ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! self::leftover_acf_posts() ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg( 'sponsor_manager_dismiss_acf_notice', '1' ),
			'sponsor_manager_dismiss_acf_notice'
		);

		printf(
			'<div class="notice notice-info is-dismissible"><p>%1$s</p><p>%2$s</p></div>',
			esc_html__( 'Sponsor Manager now registers its own post type, placements, and booking fields. ACF can stay active for everything else. In ACF, delete the leftover “Sponsor Ads” post type, “Sponsor Placements” taxonomy, and “Sponsor Booking” field group if they still appear — ads and placements in the database are not deleted.', 'sponsor-manager' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dismiss this notice', 'sponsor-manager' ) . '</a>'
		);
	}

	/**
	 * Persist notice dismissal.
	 */
	public static function maybe_dismiss_notice() {
		if ( ! isset( $_GET['sponsor_manager_dismiss_acf_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'sponsor_manager_dismiss_acf_notice' );
		update_option( self::NOTICE_OPTION, 1 );
		wp_safe_redirect( remove_query_arg( array( 'sponsor_manager_dismiss_acf_notice', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * @return \WP_Post[]
	 */
	private static function leftover_acf_posts() {
		if ( ! post_type_exists( 'acf-post-type' ) && ! post_type_exists( 'acf-field-group' ) ) {
			return array();
		}

		$types = array_filter(
			array( 'acf-post-type', 'acf-taxonomy', 'acf-field-group' ),
			'post_type_exists'
		);
		if ( empty( $types ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'post_name__in'  => self::acf_keys(),
				'no_found_rows'  => true,
			)
		);

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * @param mixed    $posts    Definitions.
	 * @param callable $matches  Whether an item is ours.
	 * @return array
	 */
	private static function reject( $posts, $matches ) {
		if ( ! is_array( $posts ) ) {
			return array();
		}

		$kept = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || ! $matches( $post ) ) {
				$kept[] = $post;
			}
		}

		return $kept;
	}
}
