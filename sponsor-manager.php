<?php
/**
 * Plugin Name: Sponsor Manager
 * Description: Booking, scheduling, and sellable-inventory system for sponsor placements (sidebar, mega menu, landing pages, single posts/pages, targetable by sponsor placement). 
 * Author: Bemis Tech
 * Author URI: https://bemistech.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.3.2
 * Update URI: https://github.com/Kpudlo/sponsor-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPONSOR_MANAGER_LEGACY_PAUSE_META', 'expired' ); // Repurposed legacy field: manual pause override.
define( 'SPONSOR_MANAGER_EVERYWHERE_SLUG', 'everywhere' );
define( 'SPONSOR_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPONSOR_MANAGER_URL', plugin_dir_url( __FILE__ ) );

require_once SPONSOR_MANAGER_PATH . 'includes/block-helpers.php';
require_once SPONSOR_MANAGER_PATH . 'includes/blocks.php';
require_once SPONSOR_MANAGER_PATH . 'includes/styles.php';
require_once SPONSOR_MANAGER_PATH . 'includes/updater.php';
require_once SPONSOR_MANAGER_PATH . 'includes/dashboard.php';

add_action( 'after_setup_theme', 'sponsor_manager_register_image_sizes' );
function sponsor_manager_register_image_sizes() {
	// 3840x2160 source ads → 16:9 display crop at a usable web size.
	add_image_size( 'sponsor-ad', 1280, 720, true );
}

/* -------------------------------------------------------------------------
 * Dependency check: requires Advanced Custom Fields PRO or Secure Custom Fields.
 *
 * The plugin's fields, post type, and taxonomy are all ACF-JSON registered
 * using PRO-only features (Post Type / Taxonomy registration UI), which the
 * free version of ACF does not include. Both ACF PRO and Secure Custom Fields
 * (its free, WordPress.org-hosted counterpart) define the ACF_PRO constant;
 * plain "Advanced Custom Fields" does not, so checking it is enough to tell
 * them apart.
 * ---------------------------------------------------------------------- */

function sponsor_manager_has_required_acf() {
	return defined( 'ACF_PRO' ) && ACF_PRO;
}

add_action( 'admin_init', 'sponsor_manager_check_dependencies' );
function sponsor_manager_check_dependencies() {
	if ( sponsor_manager_has_required_acf() ) {
		return;
	}

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );
	add_action( 'admin_notices', 'sponsor_manager_missing_acf_notice' );

	if ( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}
}

function sponsor_manager_missing_acf_notice() {
	printf(
		'<div class="notice notice-error"><p>%1$s</p><p>%2$s</p></div>',
		esc_html__( 'Sponsor Manager requires either Advanced Custom Fields PRO or Secure Custom Fields to be installed and active. The plugin has been deactivated until one of them is available.', 'sponsor-manager' ),
		wp_kses_post( sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> &nbsp;|&nbsp; <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a>',
			esc_url( 'https://www.advancedcustomfields.com/pro/' ),
			esc_html__( 'Get Advanced Custom Fields PRO', 'sponsor-manager' ),
			esc_url( self_admin_url( 'plugin-install.php?s=secure-custom-fields&tab=search&type=term' ) ),
			esc_html__( 'Install Secure Custom Fields (free)', 'sponsor-manager' )
		) )
	);
}

// Bail out entirely — on the front end too — if the requirement isn't met.
if ( ! sponsor_manager_has_required_acf() ) {
	return;
}

add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = __DIR__ . '/acf-json';
	return $paths;
} );

add_filter( 'acf/settings/save_json', function ( $path ) {
	return __DIR__ . '/acf-json';
} );

add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
	if ( 'sponsor_ad' === $post_type ) {
		return false;
	}
	return $use;
}, 10, 2 );

add_filter( 'hidden_meta_boxes', function ( $hidden, $screen ) {
	if ( $screen && isset( $screen->id ) && 'sponsor_ad' === $screen->id ) {
		$hidden = array_values( array_diff( (array) $hidden, array( 'acf-group_sponsor_manager_booking' ) ) );
	}
	return $hidden;
}, 10, 2 );


add_action( 'init', 'sponsor_manager_ensure_placement_terms', 21 );
function sponsor_manager_ensure_placement_terms() {
	if ( get_option( 'sponsor_manager_terms_seeded_v1' ) ) {
		return;
	}
	if ( ! taxonomy_exists( 'sponsor_placement' ) ) {
		return;
	}

	$properties = array(
		'WDW'   => 'wdw',
		'DLNT'  => 'dlnt',
		'UPNT'  => 'upnt',
		'DCLNT' => 'dclnt',
	);
	$zones = array(
		'Single Post' => 'single-post',
		'Single Page' => 'single-page',
	);

	foreach ( $zones as $zone_label => $zone_slug ) {
		foreach ( $properties as $prop_label => $prop_slug ) {
			$slug = $zone_slug . '-' . $prop_slug;
			if ( ! term_exists( $slug, 'sponsor_placement' ) ) {
				wp_insert_term( $zone_label . ' - ' . $prop_label, 'sponsor_placement', array( 'slug' => $slug ) );
			}
		}
	}

	update_option( 'sponsor_manager_terms_seeded_v1', 1 );
}

add_action( 'init', 'sponsor_manager_ensure_everywhere_term', 22 );
function sponsor_manager_ensure_everywhere_term() {
	if ( ! taxonomy_exists( 'sponsor_placement' ) ) {
		return;
	}

	$everywhere = get_term_by( 'slug', SPONSOR_MANAGER_EVERYWHERE_SLUG, 'sponsor_placement' );
	if ( ! $everywhere ) {
		$everywhere = get_term_by( 'name', 'Everywhere', 'sponsor_placement' );
	}
	if ( $everywhere && ! is_wp_error( $everywhere ) ) {
		$update = array();
		if ( 'Everywhere' !== $everywhere->name ) {
			$update['name'] = 'Everywhere';
		}
		if ( SPONSOR_MANAGER_EVERYWHERE_SLUG !== $everywhere->slug ) {
			$update['slug'] = SPONSOR_MANAGER_EVERYWHERE_SLUG;
		}
		if ( $update ) {
			wp_update_term( $everywhere->term_id, 'sponsor_placement', $update );
		}
		return;
	}

	foreach ( array( 'all', 'All' ) as $legacy ) {
		$legacy_term = get_term_by( 'slug', sanitize_title( $legacy ), 'sponsor_placement' );
		if ( ! $legacy_term ) {
			$legacy_term = get_term_by( 'name', $legacy, 'sponsor_placement' );
		}
		if ( $legacy_term && ! is_wp_error( $legacy_term ) ) {
			wp_update_term(
				$legacy_term->term_id,
				'sponsor_placement',
				array(
					'name' => 'Everywhere',
					'slug' => SPONSOR_MANAGER_EVERYWHERE_SLUG,
				)
			);
			return;
		}
	}

	wp_insert_term(
		'Everywhere',
		'sponsor_placement',
		array( 'slug' => SPONSOR_MANAGER_EVERYWHERE_SLUG )
	);
}

/**
 * Slugs that mean "show this ad in every placement".
 *
 * `all` is kept so ads assigned before the rename still resolve.
 *
 * @return string[]
 */
function sponsor_manager_get_everywhere_slugs() {
	return array( SPONSOR_MANAGER_EVERYWHERE_SLUG, 'all' );
}

/**
 * Placement slugs to query for a block.
 *
 * A specific placement also includes Everywhere ads. Selecting Everywhere
 * itself only returns the catch-all ads.
 *
 * @param string $placement_slug
 * @return string[]
 */
function sponsor_manager_get_placement_query_slugs( $placement_slug ) {
	$slug       = sanitize_title( $placement_slug );
	$everywhere = sponsor_manager_get_everywhere_slugs();

	if ( ! $slug || in_array( $slug, $everywhere, true ) ) {
		return $everywhere;
	}

	return array_values( array_unique( array_merge( array( $slug ), $everywhere ) ) );
}

// Cosmetic relabel only — the legacy "expired" checkbox becomes the manual pause switch.
// We never touch the stored field-group option itself, just how it's displayed.
add_filter( 'acf/load_field/name=expired', function ( $field ) {
	$field['label']        = 'Paused (manual override)';
	$field['instructions'] = 'Check to pull this ad from rotation immediately, regardless of its scheduled dates.';
	return $field;
} );

/* -------------------------------------------------------------------------
 * Status engine
 * ---------------------------------------------------------------------- */

function sponsor_manager_compute_status( $post_id ) {
	if ( get_post_status( $post_id ) !== 'publish' ) {
		return 'draft';
	}
	if ( (bool) get_post_meta( $post_id, SPONSOR_MANAGER_LEGACY_PAUSE_META, true ) ) {
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

function sponsor_manager_refresh_status( $post_id ) {
	$status = sponsor_manager_compute_status( $post_id );
	update_post_meta( $post_id, '_sponsor_ad_status', $status );
	return $status;
}
add_action( 'save_post_sponsor_ad', 'sponsor_manager_refresh_status' );

add_action( 'sponsor_manager_daily_status_check', 'sponsor_manager_run_daily_status_check' );
function sponsor_manager_run_daily_status_check() {
	$ads = get_posts( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach ( $ads as $ad_id ) {
		sponsor_manager_refresh_status( $ad_id );
	}
}

register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'sponsor_manager_daily_status_check' ) ) {
		wp_schedule_event( time(), 'daily', 'sponsor_manager_daily_status_check' );
	}
	update_option( 'sponsor_manager_flush_rewrite_rules', 1 );
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'sponsor_manager_daily_status_check' );
	flush_rewrite_rules();
} );
// Safety net in case the plugin was placed on disk without going through activation (e.g. Studio site copy).
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'sponsor_manager_daily_status_check' ) ) {
		wp_schedule_event( time(), 'daily', 'sponsor_manager_daily_status_check' );
	}
} );

add_action( 'init', function () {
	if ( get_option( 'sponsor_manager_flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
		delete_option( 'sponsor_manager_flush_rewrite_rules' );
	}
}, 999 );


/**
 * Click-through URL for an ad. Prefers the ACF Sponsor URL field, then legacy meta.
 *
 * @param int $post_id
 * @return string
 */
function sponsor_manager_get_ad_url( $post_id ) {
	$url = get_post_meta( $post_id, 'sponsor_url', true );
	if ( empty( $url ) ) {
		$url = get_post_meta( $post_id, 'ad_link', true );
	}
	if ( empty( $url ) ) {
		$url = get_post_meta( $post_id, 'link', true );
	}

	return is_string( $url ) ? $url : '';
}

function sponsor_manager_get_ads( $placement_slug, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'category_id' => 0, // 0 = auto-detect from the current queried post; array|int to override.
		'limit'       => 0, // 0 = return every eligible sponsor (stacked list); N = pick top N by priority.
	) );

	if ( ! $args['category_id'] ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post && ! empty( $queried->ID ) ) {
			// Single post/page: target by the categories that post itself belongs to.
			$cats = get_the_category( $queried->ID );
			if ( $cats ) {
				$args['category_id'] = wp_list_pluck( $cats, 'term_id' );
			}
		} elseif ( $queried instanceof WP_Term && 'category' === $queried->taxonomy ) {

			$args['category_id'] = array( $queried->term_id );
		}
	}
	$context_categories = array_map( 'intval', (array) $args['category_id'] );

	$query = new WP_Query( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'sponsor_placement',
				'field'    => 'slug',
				'terms'    => sponsor_manager_get_placement_query_slugs( $placement_slug ),
			),
		),
	) );

	$today    = current_time( 'Ymd' );
	$eligible = array();

	foreach ( $query->posts as $post ) {
		if ( (bool) get_post_meta( $post->ID, SPONSOR_MANAGER_LEGACY_PAUSE_META, true ) ) {
			continue; // paused
		}

		$start = get_post_meta( $post->ID, 'sponsor_start_date', true );
		$end   = get_post_meta( $post->ID, 'sponsor_end_date', true );
		if ( $start && $today < $start ) {
			continue; // scheduled, not live yet
		}
		if ( $end && $today > $end ) {
			continue; // past its end date
		}

		$ad_categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
		if ( ! empty( $ad_categories ) && ! array_intersect( $ad_categories, $context_categories ) ) {
			continue; // targeted at other categories only
		}

		$priority = (int) get_post_meta( $post->ID, 'sponsor_priority', true );
		$priority = $priority > 0 ? $priority : 10;

		$link = sponsor_manager_get_ad_url( $post->ID );

		$img_url = get_the_post_thumbnail_url( $post->ID, 'sponsor-ad' );
		if ( empty( $img_url ) ) {
			$img_url = get_the_post_thumbnail_url( $post->ID, 'large' );
		}
		// Known-broken local attachment reference for the COP ad; carried over from the legacy renderer.
		if ( 1345437 === $post->ID || empty( $img_url ) || false !== strpos( (string) $img_url, 'dinosaur_pins_website_ad' ) ) {
			if ( 1345437 === $post->ID ) {
				$img_url = site_url( '/wp-content/uploads/2026/06/cop_ad_2026-standard.jpg' );
			}
		}

		$eligible[] = array(
			'id'       => $post->ID,
			'title'    => get_the_title( $post->ID ),
			'image'    => $img_url,
			'link'     => $link ? $link : '#',
			'priority' => $priority,
		);
	}

	if ( empty( $eligible ) ) {
		return array();
	}

	// Order by priority (desc), randomized within same-priority ties for fair rotation.
	$tiers = array();
	foreach ( $eligible as $ad ) {
		$tiers[ $ad['priority'] ][] = $ad;
	}
	krsort( $tiers );
	$ordered = array();
	foreach ( $tiers as $tier ) {
		shuffle( $tier );
		foreach ( $tier as $ad ) {
			$ordered[] = $ad;
		}
	}

	if ( $args['limit'] > 0 ) {
		$ordered = array_slice( $ordered, 0, $args['limit'] );
	}

	return $ordered;
}

/* -------------------------------------------------------------------------
 * Admin: list table columns
 * ---------------------------------------------------------------------- */

add_filter( 'manage_sponsor_ad_posts_columns', function ( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['sponsor_name']  = 'Sponsor';
			$new['placements']    = 'Placement(s)';
			$new['categories']    = 'Category Target';
			$new['status']        = 'Status';
			$new['sponsor_dates'] = 'Runs';
		}
	}
	return $new;
} );

add_action( 'manage_sponsor_ad_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'sponsor_name':
			echo esc_html( get_post_meta( $post_id, 'sponsor_name', true ) ?: '—' );
			break;

		case 'placements':
			$terms = get_the_terms( $post_id, 'sponsor_placement' );
			echo ( $terms && ! is_wp_error( $terms ) )
				? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) )
				: '<em>none assigned</em>';
			break;

		case 'categories':
			$terms = get_the_terms( $post_id, 'category' );
			echo ( $terms && ! is_wp_error( $terms ) )
				? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) )
				: 'All categories';
			break;

		case 'status':
			$status = sponsor_manager_refresh_status( $post_id );
			$colors = array(
				'active'    => '#1a7f37',
				'scheduled' => '#9a6700',
				'expired'   => '#cf222e',
				'paused'    => '#57606a',
				'draft'     => '#57606a',
			);
			$color = $colors[ $status ] ?? '#57606a';
			printf(
				'<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:%1$s;color:#fff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;">%2$s</span>',
				esc_attr( $color ),
				esc_html( $status )
			);
			break;

		case 'sponsor_dates':
			$start = get_post_meta( $post_id, 'sponsor_start_date', true );
			$end   = get_post_meta( $post_id, 'sponsor_end_date', true );
			$fmt   = function ( $d ) {
				return $d ? date_i18n( 'M j, Y', strtotime( $d ) ) : '—';
			};
			echo esc_html( $fmt( $start ) . ' – ' . $fmt( $end ) );
			break;
	}
}, 10, 2 );

add_filter( 'manage_edit-sponsor_ad_sortable_columns', function ( $columns ) {
	$columns['sponsor_dates'] = 'sponsor_end_date';
	return $columns;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'sponsor_ad' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( 'sponsor_end_date' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', 'sponsor_end_date' );
		$query->set( 'orderby', 'meta_value' );
	}
} );

/* -------------------------------------------------------------------------
 * Admin: dashboard widget — what needs attention
 * ---------------------------------------------------------------------- */

add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'sponsor_manager_alerts', 'Sponsor Manager — Needs Attention', 'sponsor_manager_render_dashboard_widget' );
} );

function sponsor_manager_render_dashboard_widget() {
	$ads = get_posts( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	) );

	$today    = current_time( 'Ymd' );
	$soon     = gmdate( 'Ymd', strtotime( '+7 days' ) );
	$expiring = array();
	$expired  = array();

	foreach ( $ads as $ad ) {
		$end = get_post_meta( $ad->ID, 'sponsor_end_date', true );
		if ( ! $end ) {
			continue;
		}
		if ( $end < $today ) {
			$expired[] = $ad;
		} elseif ( $end <= $soon ) {
			$expiring[] = $ad;
		}
	}

	if ( ! $expiring && ! $expired ) {
		echo '<p>All sponsor placements are current — nothing expiring in the next 7 days.</p>';
		return;
	}

	if ( $expired ) {
		echo '<p><strong style="color:#cf222e;">Expired but still published:</strong></p><ul style="margin-left:1em;list-style:disc;">';
		foreach ( $expired as $ad ) {
			printf(
				'<li><a href="%s">%s</a> — ended %s</li>',
				esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'ad' => $ad->ID ) ) ),
				esc_html( sponsor_manager_format_booking_label( $ad ) ),
				esc_html( date_i18n( 'M j, Y', strtotime( get_post_meta( $ad->ID, 'sponsor_end_date', true ) ) ) )
			);
		}
		echo '</ul>';
	}

	if ( $expiring ) {
		echo '<p><strong style="color:#9a6700;">Expiring within 7 days:</strong></p><ul style="margin-left:1em;list-style:disc;">';
		foreach ( $expiring as $ad ) {
			printf(
				'<li><a href="%s">%s</a> — ends %s</li>',
				esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'ad' => $ad->ID ) ) ),
				esc_html( sponsor_manager_format_booking_label( $ad ) ),
				esc_html( date_i18n( 'M j, Y', strtotime( get_post_meta( $ad->ID, 'sponsor_end_date', true ) ) ) )
			);
		}
		echo '</ul>';
	}
}

/* -------------------------------------------------------------------------
 * Admin: "Sponsor Manager" top-level menu — dashboard, ad inventory, help.
 *
 * The CPT and its taxonomy are nested under this page (see admin_menu_parent
 * in acf-json/post_type_sponsor_ad.json), which is why the parent must be
 * registered early (priority 5) — core attaches the CPT's own "All Sponsor
 * Ads" submenu on the default 'admin_menu' priority (10), and needs the
 * parent slug to already exist. Nesting a post type like this means core no
 * longer auto-adds "Add New" or the taxonomy submenu for it, so those are
 * added explicitly below, after that default-priority core hook has run.
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_menu_page(
		'Sponsor Manager',
		'Sponsor Manager',
		'edit_posts',
		'sponsor_manager',
		'sponsor_manager_render_dashboard_page',
		'dashicons-megaphone',
		25
	);

	add_submenu_page(
		'sponsor_manager',
		'Sponsor Manager',
		'Dashboard',
		'edit_posts',
		'sponsor_manager',
		'sponsor_manager_render_dashboard_page'
	);
}, 5 );

add_action( 'admin_menu', function () {
	add_submenu_page(
		'sponsor_manager',
		'Add New Sponsor Ad',
		'Add New Ad',
		'edit_posts',
		'post-new.php?post_type=sponsor_ad'
	);
	add_submenu_page(
		'sponsor_manager',
		'Sponsor Placements',
		'Placements',
		'manage_categories',
		'edit-tags.php?taxonomy=sponsor_placement&post_type=sponsor_ad'
	);
}, 20 );

function sponsor_manager_get_dashboard_stats() {
	$ad_ids = get_posts( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$active = 0;
	foreach ( $ad_ids as $ad_id ) {
		if ( 'active' === sponsor_manager_refresh_status( $ad_id ) ) {
			$active++;
		}
	}

	$placement_count = wp_count_terms( array( 'taxonomy' => 'sponsor_placement', 'hide_empty' => false ) );

	return array(
		'active_ads'       => $active,
		'total_ads'        => count( $ad_ids ),
		'total_placements' => is_wp_error( $placement_count ) ? 0 : (int) $placement_count,
	);
}

function sponsor_manager_render_dashboard_page() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	if ( ! in_array( $tab, array( 'overview', 'ads', 'placements', 'styles', 'how-to-use' ), true ) ) {
		$tab = 'overview';
	}
	$base_url = admin_url( 'admin.php?page=sponsor_manager' );
	$tabs     = array(
		'overview'    => __( 'Overview', 'sponsor-manager' ),
		'ads'         => __( 'Ads', 'sponsor-manager' ),
		'placements'  => __( 'Placements', 'sponsor-manager' ),
		'styles'      => __( 'Styles', 'sponsor-manager' ),
		'how-to-use'  => __( 'How to Use', 'sponsor-manager' ),
	);

	echo '<div class="wrap">';
	echo '<h1>Sponsor Manager</h1>';
	sponsor_manager_render_dashboard_notices();

	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $slug => $label ) {
		printf(
			'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
			esc_url( 'overview' === $slug ? $base_url : add_query_arg( 'tab', $slug, $base_url ) ),
			$tab === $slug ? 'nav-tab-active' : '',
			esc_html( $label )
		);
	}
	echo '</h2>';

	if ( 'how-to-use' === $tab ) {
		sponsor_manager_render_how_to_use_tab();
	} elseif ( 'styles' === $tab ) {
		sponsor_manager_render_styles_tab();
	} elseif ( 'ads' === $tab ) {
		sponsor_manager_render_ads_tab();
	} elseif ( 'placements' === $tab ) {
		sponsor_manager_render_placements_tab();
	} else {
		sponsor_manager_render_overview_tab();
	}

	echo '</div>';
}

function sponsor_manager_render_overview_tab() {
	$stats = sponsor_manager_get_dashboard_stats();
	$cards = array(
		array( 'label' => __( 'Active Ads', 'sponsor-manager' ), 'value' => $stats['active_ads'] ),
		array( 'label' => __( 'Total Ads', 'sponsor-manager' ), 'value' => $stats['total_ads'] ),
		array( 'label' => __( 'Total Placements', 'sponsor-manager' ), 'value' => $stats['total_placements'] ),
	);

	echo '<div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">';
	foreach ( $cards as $card ) {
		printf(
			'<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 24px;min-width:160px;">
				<div style="font-size:28px;font-weight:600;line-height:1.2;">%1$s</div>
				<div style="color:#646970;">%2$s</div>
			</div>',
			esc_html( $card['value'] ),
			esc_html( $card['label'] )
		);
	}
	echo '</div>';

	sponsor_manager_render_overview_actions();

	echo '<h2>' . esc_html__( 'Ad Inventory', 'sponsor-manager' ) . '</h2>';
	echo '<p>' . esc_html__( "What's booked and what's open to sell, by placement.", 'sponsor-manager' ) . '</p>';
	sponsor_manager_render_inventory_table();
}

function sponsor_manager_render_how_to_use_tab() {
	echo '<div style="max-width:760px;margin-top:20px;">';
	echo '<h2>' . esc_html__( 'How Sponsor Manager Works', 'sponsor-manager' ) . '</h2>';
	echo '<ol style="list-style:decimal;margin-left:1.2em;">
		<li><strong>Create a Sponsor Ad</strong> from the Ads tab (or Overview → Add New Ad). Set the ad name, company, image, Sponsor URL, and placements.</li>
		<li><strong>Create a Placement</strong> from the Placements tab. A specific placement also includes ads assigned to <em>Everywhere</em>. Use Everywhere for ads that should appear in every block.</li>
		<li><strong>Schedule it.</strong> Leave the start date blank to go live immediately, and the end date blank to run indefinitely. The ad moves through Scheduled → Active → Expired automatically based on today\'s date — no manual cleanup required.</li>
		<li><strong>Set a priority</strong> to control ordering when a placement has more than one active sponsor; higher numbers are shown first (and more often), with random rotation among ties.</li>
		<li><strong>Target by category</strong> (optional) by assigning post categories to the ad, the same as you would a normal post — it will then only serve on single posts/pages within those categories. Leave it uncategorized to serve everywhere.</li>
		<li><strong>Pause anytime</strong> with the "Paused (manual override)" checkbox to instantly pull an ad from rotation, regardless of its scheduled dates.</li>
		<li><strong>Place ads on the site</strong> using the <em>Sponsor Grid</em> block (four-up horizontal grid with priority-weighted rotation) or the <em>Sponsor Sidebar</em> block (vertical stack of all active ads). Both blocks are in the block inserter under Widgets — pick a placement in the block settings.</li>
		<li><strong>Set global styles</strong> on the Styles tab (border radius, border, shadow). Individual blocks use those defaults unless you turn on a per-block override.</li>
	</ol>';
	echo '<h2>' . esc_html__( 'Where to Check Status', 'sponsor-manager' ) . '</h2>';
	echo '<ul style="list-style:disc;margin-left:1.2em;">
		<li>The <strong>Overview</strong> tab shows current totals and what\'s open to sell, by placement. Use Ads and Placements to create or edit without leaving the dashboard.</li>
		<li>The <strong>Ads</strong> tab lists each ad name next to its company, status, and run dates.</li>
		<li>The dashboard widget ("Sponsor Manager — Needs Attention") flags anything expiring within 7 days, or still published past its end date.</li>
		<li>Plugin updates come from GitHub Releases. When a new version is published, WordPress will offer it on the Plugins screen like any other plugin.</li>
	</ul>';
	echo '</div>';
}

function sponsor_manager_render_inventory_table() {
	$terms = get_terms( array( 'taxonomy' => 'sponsor_placement', 'hide_empty' => false ) );
	$today = current_time( 'Ymd' );

	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Placement</th><th>Current / Upcoming Bookings</th><th>Availability</th>';
	echo '</tr></thead><tbody>';

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		echo '<tr><td colspan="3">No sponsor_placement terms found.</td></tr>';
	} else {
		foreach ( $terms as $term ) {
			$query = new WP_Query( array(
				'post_type'      => 'sponsor_ad',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => array(
					array( 'taxonomy' => 'sponsor_placement', 'field' => 'term_id', 'terms' => $term->term_id ),
				),
			) );

			$rows          = array();
			$latest_end    = null;
			$has_open_ended = false;

			foreach ( $query->posts as $post ) {
				if ( (bool) get_post_meta( $post->ID, SPONSOR_MANAGER_LEGACY_PAUSE_META, true ) ) {
					continue; // paused, not occupying the slot
				}
				$end = get_post_meta( $post->ID, 'sponsor_end_date', true );
				if ( $end && $end < $today ) {
					continue; // already expired, not occupying the slot
				}
				$rows[] = sprintf(
					'<a href="%s">%s</a>%s',
					esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'ads', 'ad' => $post->ID ) ) ),
					esc_html( sponsor_manager_format_booking_label( $post ) ),
					$end ? ' (through ' . esc_html( date_i18n( 'M j, Y', strtotime( $end ) ) ) . ')' : ' (no end date)'
				);
				if ( ! $end ) {
					$has_open_ended = true;
				} elseif ( ! $latest_end || $end > $latest_end ) {
					$latest_end = $end;
				}
			}

			if ( $has_open_ended ) {
				$availability = '<strong style="color:#cf222e;">Booked (indefinite)</strong>';
			} elseif ( $latest_end ) {
				$availability = '<strong style="color:#9a6700;">Booked through ' . esc_html( date_i18n( 'M j, Y', strtotime( $latest_end ) ) ) . '</strong>';
			} else {
				$availability = '<strong style="color:#1a7f37;">OPEN — available to sell</strong>';
			}

			printf(
				'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>',
				esc_url( sponsor_manager_dashboard_url( array( 'tab' => 'placements', 'placement' => $term->term_id ) ) ),
				esc_html( $term->name ),
				$rows ? implode( '<br>', $rows ) : '<em>none</em>',
				$availability
			);
		}
	}

	echo '</tbody></table>';
}
