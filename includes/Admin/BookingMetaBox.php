<?php
/**
 * Booking fields on the Sponsor Ad edit screen.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Native metabox that writes the same post meta ACF used to own.
 */
class BookingMetaBox {

	/**
	 * Register metabox and save handler.
	 */
	public static function register() {
		add_action( 'add_meta_boxes_' . Constants::POST_TYPE, array( __CLASS__, 'add_box' ) );
		add_action( 'save_post_' . Constants::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Add the booking box above the rest of the editor.
	 */
	public static function add_box() {
		add_meta_box(
			'sponsor_manager_booking',
			__( 'Sponsor Booking', 'sponsor-manager' ),
			array( __CLASS__, 'render' ),
			Constants::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * @param \WP_Post $post Ad.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'sponsor_manager_save_booking', 'sponsor_manager_booking_nonce' );

		$company = (string) get_post_meta( $post->ID, 'sponsor_name', true );
		$link    = (string) get_post_meta( $post->ID, 'sponsor_url', true );
		if ( '' === $link ) {
			$link = (string) get_post_meta( $post->ID, 'ad_link', true );
		}
		$start    = function_exists( 'sponsor_manager_ymd_to_input' ) ? sponsor_manager_ymd_to_input( get_post_meta( $post->ID, 'sponsor_start_date', true ) ) : '';
		$end      = function_exists( 'sponsor_manager_ymd_to_input' ) ? sponsor_manager_ymd_to_input( get_post_meta( $post->ID, 'sponsor_end_date', true ) ) : '';
		$priority = (int) get_post_meta( $post->ID, 'sponsor_priority', true );
		$priority = $priority > 0 ? $priority : 10;
		$paused   = (bool) get_post_meta( $post->ID, Constants::PAUSE_META, true );
		$notes    = (string) get_post_meta( $post->ID, 'sponsor_internal_notes', true );

		$view = SPONSOR_MANAGER_PATH . 'includes/Admin/views/booking-metabox.php';
		if ( is_readable( $view ) ) {
			require $view;
		}
	}

	/**
	 * Persist booking meta. Keys match the previous ACF field names.
	 *
	 * @param int      $post_id Ad ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['sponsor_manager_booking_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sponsor_manager_booking_nonce'] ) ), 'sponsor_manager_save_booking' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = array();
		if ( isset( $_POST['sponsor_booking'] ) && is_array( $_POST['sponsor_booking'] ) ) {
			$input = map_deep( wp_unslash( $_POST['sponsor_booking'] ), 'sanitize_text_field' );
		}

		$company  = sanitize_text_field( $input['company'] ?? '' );
		$link     = esc_url_raw( $input['link'] ?? '' );
		$start    = function_exists( 'sponsor_manager_input_to_ymd' ) ? sponsor_manager_input_to_ymd( $input['start'] ?? '' ) : '';
		$end      = function_exists( 'sponsor_manager_input_to_ymd' ) ? sponsor_manager_input_to_ymd( $input['end'] ?? '' ) : '';
		$priority = isset( $input['priority'] ) ? (int) $input['priority'] : 10;
		$priority = $priority > 0 ? $priority : 10;
		$paused   = ! empty( $input['paused'] );
		$notes    = isset( $_POST['sponsor_booking']['notes'] )
			? sanitize_textarea_field( wp_unslash( $_POST['sponsor_booking']['notes'] ) )
			: '';

		update_post_meta( $post_id, 'sponsor_name', $company );
		update_post_meta( $post_id, 'sponsor_url', $link );
		update_post_meta( $post_id, 'sponsor_start_date', $start );
		update_post_meta( $post_id, 'sponsor_end_date', $end );
		update_post_meta( $post_id, 'sponsor_priority', $priority );
		update_post_meta( $post_id, 'sponsor_internal_notes', $notes );

		if ( $paused ) {
			update_post_meta( $post_id, Constants::PAUSE_META, 1 );
		} else {
			delete_post_meta( $post_id, Constants::PAUSE_META );
		}
	}
}
