<?php
/**
 * Computed ad status (active, scheduled, expired, paused).
 *
 * @package SponsorManager
 */

namespace SponsorManager\Ads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Stores `_sponsor_ad_status` so list tables and the dashboard stay in sync.
 */
class Status {

	/**
	 * Hook save and the daily cron event.
	 */
	public static function register() {
		add_action( 'save_post_' . Constants::POST_TYPE, array( __CLASS__, 'refresh' ) );
		add_action( Constants::CRON_HOOK, array( __CLASS__, 'run_daily' ) );
	}

	/**
	 * @param int $post_id Ad ID.
	 * @return string
	 */
	public static function compute( $post_id ) {
		if ( get_post_status( $post_id ) !== 'publish' ) {
			return 'draft';
		}
		if ( (bool) get_post_meta( $post_id, Constants::PAUSE_META, true ) ) {
			return 'paused';
		}

		$today = current_time( 'Ymd' );
		$start = get_post_meta( $post_id, 'sponsor_start_date', true );
		$end   = get_post_meta( $post_id, 'sponsor_end_date', true );

		if ( $start && $today < $start ) {
			return 'scheduled';
		}
		if ( $end && $today > $end ) {
			return 'expired';
		}

		return 'active';
	}

	/**
	 * Recompute and persist status meta.
	 *
	 * @param int $post_id Ad ID.
	 * @return string
	 */
	public static function refresh( $post_id ) {
		$status = self::compute( $post_id );
		update_post_meta( $post_id, '_sponsor_ad_status', $status );
		return $status;
	}

	/**
	 * Refresh every published ad (daily cron).
	 */
	public static function run_daily() {
		$ads = get_posts(
			array(
				'post_type'      => Constants::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ads as $ad_id ) {
			self::refresh( $ad_id );
		}
	}
}
