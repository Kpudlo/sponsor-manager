<?php
/**
 * Native CPT and taxonomy. Same slugs as the old ACF JSON definitions.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers sponsor_ad and sponsor_placement without ACF.
 */
class PostTypes {

	/**
	 * Hook registration early so menus and term seeding can use the types.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_types' ), 5 );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Register the post type and placement taxonomy.
	 */
	public static function register_types() {
		register_post_type(
			Constants::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Sponsor Ads', 'sponsor-manager' ),
					'singular_name'         => __( 'Ad', 'sponsor-manager' ),
					'menu_name'             => __( 'Sponsor Manager', 'sponsor-manager' ),
					'all_items'             => __( 'All Sponsor Ads', 'sponsor-manager' ),
					'add_new'               => __( 'Add New Sponsor Ad', 'sponsor-manager' ),
					'add_new_item'          => __( 'Add New Sponsor Ad', 'sponsor-manager' ),
					'edit_item'             => __( 'Edit Sponsor Ad', 'sponsor-manager' ),
					'new_item'              => __( 'New Sponsor Ad', 'sponsor-manager' ),
					'view_item'             => __( 'View Sponsor Ad', 'sponsor-manager' ),
					'search_items'          => __( 'Search Sponsor Ads', 'sponsor-manager' ),
					'not_found'             => __( 'No sponsor ads found', 'sponsor-manager' ),
					'not_found_in_trash'    => __( 'No sponsor ads found in Trash', 'sponsor-manager' ),
					'featured_image'        => __( 'Ad image', 'sponsor-manager' ),
					'set_featured_image'    => __( 'Set ad image', 'sponsor-manager' ),
					'remove_featured_image' => __( 'Remove ad image', 'sponsor-manager' ),
					'use_featured_image'    => __( 'Use as ad image', 'sponsor-manager' ),
				),
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => Constants::MENU_SLUG,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => Constants::POST_TYPE,
					'with_front' => true,
				),
				'supports'            => array( 'title', 'thumbnail' ),
				'taxonomies'          => array( 'category' ),
				'menu_icon'           => 'dashicons-megaphone',
				'delete_with_user'    => false,
			)
		);

		register_taxonomy(
			Constants::TAXONOMY,
			array( Constants::POST_TYPE ),
			array(
				'labels'            => array(
					'name'          => __( 'Sponsor Placements', 'sponsor-manager' ),
					'singular_name' => __( 'Ad Placement', 'sponsor-manager' ),
					'search_items'  => __( 'Search Sponsor Placements', 'sponsor-manager' ),
					'all_items'     => __( 'All Sponsor Placements', 'sponsor-manager' ),
					'edit_item'     => __( 'Edit Sponsor Placement', 'sponsor-manager' ),
					'update_item'   => __( 'Update Sponsor Placement', 'sponsor-manager' ),
					'add_new_item'  => __( 'Add New Sponsor Placement', 'sponsor-manager' ),
					'new_item_name' => __( 'New Sponsor Placement Name', 'sponsor-manager' ),
					'not_found'     => __( 'No sponsor placements found', 'sponsor-manager' ),
				),
				'public'            => true,
				'publicly_queryable' => true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'show_tagcloud'     => false,
				'rewrite'           => array(
					'slug'         => Constants::TAXONOMY,
					'with_front'   => true,
					'hierarchical' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		register_taxonomy_for_object_type( 'category', Constants::POST_TYPE );
	}

	/**
	 * Classic editor so the booking metabox stays easy to use.
	 *
	 * @param bool   $use       Whether to use the block editor.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function disable_block_editor( $use, $post_type ) {
		if ( Constants::POST_TYPE === $post_type ) {
			return false;
		}
		return $use;
	}

	/**
	 * @param string   $title Placeholder.
	 * @param \WP_Post $post  Post.
	 * @return string
	 */
	public static function title_placeholder( $title, $post ) {
		if ( $post && Constants::POST_TYPE === $post->post_type ) {
			return __( 'Ad name', 'sponsor-manager' );
		}
		return $title;
	}
}
