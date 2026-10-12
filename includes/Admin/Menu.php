<?php
/**
 * Top-level admin menu. Parent must exist before the nested CPT submenu attaches.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Sponsor Manager menu and CPT submenus.
 */
class Menu {

	/**
	 * Parent at priority 5; extra submenus after core attaches the CPT.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_parent' ), 5 );
		add_action( 'admin_menu', array( __CLASS__, 'register_submenus' ), 20 );
	}

	/**
	 * Register the parent page the CPT uses as show_in_menu.
	 */
	public static function register_parent() {
		add_menu_page(
			__( 'Sponsor Manager', 'sponsor-manager' ),
			__( 'Sponsor Manager', 'sponsor-manager' ),
			'edit_posts',
			Constants::MENU_SLUG,
			array( Dashboard::class, 'render_page' ),
			'dashicons-megaphone',
			29
		);

		add_submenu_page(
			Constants::MENU_SLUG,
			__( 'Sponsor Manager', 'sponsor-manager' ),
			__( 'Dashboard', 'sponsor-manager' ),
			'edit_posts',
			Constants::MENU_SLUG,
			array( Dashboard::class, 'render_page' )
		);
	}

	/**
	 * Add New and Placements links core does not add when the CPT is nested.
	 */
	public static function register_submenus() {
		add_submenu_page(
			Constants::MENU_SLUG,
			__( 'Add New Sponsor Ad', 'sponsor-manager' ),
			__( 'Add New Ad', 'sponsor-manager' ),
			'edit_posts',
			'post-new.php?post_type=' . Constants::POST_TYPE
		);
		add_submenu_page(
			Constants::MENU_SLUG,
			__( 'Sponsor Placements', 'sponsor-manager' ),
			__( 'Placements', 'sponsor-manager' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . Constants::TAXONOMY . '&post_type=' . Constants::POST_TYPE
		);
	}
}
